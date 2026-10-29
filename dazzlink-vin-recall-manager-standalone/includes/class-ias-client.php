<?php
if ( ! defined('ABSPATH') ) exit;

final class DVRMS_IAS_Client {
    const TOKEN = 'dvrms_api_token_v3';
    const CALL_TIMEOUT = 8;   // seconds per HTTP call
    const TIME_BUDGET = 25;   // seconds for a whole lookup, below the usual PHP max_execution_time of 30
    private $s;
    private $deadline;
    public function __construct($settings) { $this->s = $settings; $this->deadline = microtime(true) + self::TIME_BUDGET; }
    const LOOKUP_PATH = '/v1/eu/dms/getUnfinishedTechnologyActivityByVin';
    /**
     * Accepts the base URL in any of the forms the factory hands out:
     *   https://HOST                                   → https://HOST/api/externalIntegration/v1/eu/dms/...
     *   https://HOST/api/externalIntegration           → .../v1/eu/dms/...
     *   https://HOST/api/externalIntegration/v1/eu/dms/getUnfinishedTechnologyActivityByVin (as is)
     */
    public static function lookup_url($base) {
        $base = rtrim((string)$base, '/');
        if ($base === '') return '';
        $path = (string)wp_parse_url($base, PHP_URL_PATH);
        if (substr($path, -strlen(self::LOOKUP_PATH)) === self::LOOKUP_PATH) return $base;
        if ($path === '') $base .= '/api/externalIntegration';
        return $base.self::LOOKUP_PATH;
    }
    public static function normalize_vin($vin) { return strtoupper(preg_replace('/[\s\-\.]+/u', '', (string)$vin)); }
    public static function clear_token() { delete_transient(self::TOKEN); }
    private function error($code, $message) { return new WP_Error($code, $message); }
    public static function valid_url($url) {
        $p = wp_parse_url($url);
        return is_array($p) && isset($p['scheme'], $p['host']) && $p['scheme'] === 'https'
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['query']) && !isset($p['fragment']);
    }
    public function encrypted_secret() {
        $secret = $this->s['app_secret'];
        if ($this->s['secret_mode'] === 'supplied') {
            if (preg_match('/[\r\n\x00]/', $secret)) return $this->error('secret', 'Το App Secret περιέχει μη επιτρεπτούς χαρακτήρες header.');
            return $secret;
        }
        if ($this->s['secret_mode'] === 'encrypted') {
            if (!preg_match('/^(?:[a-fA-F0-9]{32})+$/D', $secret)) return $this->error('secret', 'Το encrypted App Secret πρέπει να είναι AES ciphertext σε HEX.');
            return strtolower($secret);
        }
        $key = $this->s['aes_key'] !== '' ? $this->s['aes_key'] : 'CheryOdin!202503';
        $iv = $this->s['aes_iv'] !== '' ? $this->s['aes_iv'] : 'CheryOdin!202503';
        if (!in_array(strlen($key), array(16,24,32), true) || strlen($iv) !== 16) {
            return $this->error('aes_config', 'Απαιτείται AES key 16/24/32 bytes και IV 16 bytes. Ελέγξτε τις προσαρμοσμένες τιμές σας.');
        }
        if (!function_exists('openssl_encrypt')) return $this->error('openssl', 'Απαιτείται η επέκταση PHP OpenSSL.');
        $raw = openssl_encrypt($secret, 'aes-'.(strlen($key)*8).'-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $raw === false ? $this->error('encryption', 'Αποτυχία κρυπτογράφησης App Secret.') : bin2hex($raw);
    }
    private function request($url, $args) {
        // Never follow redirects carrying credentials. Never expose response bodies or URLs in errors.
        $args = array_merge(array('redirection'=>0,'sslverify'=>true,'limit_response_size'=>65536,'headers'=>array('Accept'=>'application/json')), $args);
        for ($attempt=0; $attempt<2; $attempt++) {
            // Every call shares one time budget, so token + refresh + retries never outlive the PHP request.
            $left = $this->deadline - microtime(true);
            if ($left < 2) return $this->error('timeout', 'Ο συνολικός χρόνος αναμονής για το εργοστάσιο εξαντλήθηκε.');
            $args['timeout'] = (int)min(self::CALL_TIMEOUT, floor($left));
            $r = wp_safe_remote_request($url, $args);
            $status = is_wp_error($r) ? 0 : wp_remote_retrieve_response_code($r);
            if (!(is_wp_error($r) || in_array($status,array(502,503,504),true)) || $attempt === 1) break;
            usleep(250000);
        }
        if (is_wp_error($r)) return $this->error('network', 'Αδυναμία επικοινωνίας με το εργοστάσιο. Ελέγξτε σύνδεση και IP allowlisting.');
        if ($status === 401 || $status === 403) return $this->error('unauthorized', 'Το εργοστάσιο απέρριψε την πρόσβαση (HTTP '.$status.').');
        if ($status < 200 || $status >= 300) return $this->error('http', 'Το API επέστρεψε HTTP '.$status.'.');
        $json = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($json)) return $this->error('json', 'Το API δεν επέστρεψε έγκυρο JSON.');
        return $json;
    }
    /** HTTP 200 with a non-zero gateway code and no business data object: the gateway rejected the token. */
    private static function gateway_rejected($r) {
        return is_array($r) && isset($r['code']) && !in_array($r['code'],array(0,'0'),true) && !(isset($r['data']) && is_array($r['data']));
    }
    private static function as_count($v) {
        if (is_int($v)) return $v;
        if (is_float($v) && floor($v) === $v) return (int)$v;
        if (is_string($v) && preg_match('/^[0-9]{1,6}$/D', $v)) return (int)$v;
        return null;
    }
    public function token($force=false) {
        if (!self::valid_url($this->s['auth_url']) || !self::valid_url($this->s['base_url']) || $this->s['app_id'] === '' || $this->s['app_secret'] === '') return $this->error('config', 'Συμπληρώστε HTTPS endpoints, App ID και App Secret στις ρυθμίσεις.');
        if (!$force) {
            $cached=get_transient(self::TOKEN);
            if (is_array($cached) && isset($cached['value'],$cached['expires']) && $cached['expires']>time()) return $cached['value'];
        }
        $secret=$this->encrypted_secret(); if(is_wp_error($secret)) return $secret;
        // Factory UAT curl example requires credentials in headers, not in the URL.
        $r=$this->request($this->s['auth_url'],array('method'=>'GET','headers'=>array('Accept'=>'application/json','app-id'=>$this->s['app_id'],'app-secret'=>$secret)));
        if(is_wp_error($r)) return $r;
        if (!isset($r['code']) || !in_array($r['code'],array(0,'0'),true) || empty($r['data']) || !is_string($r['data']) || preg_match('/[\r\n]/',$r['data'])) {
            // Only fixed field names, numeric status codes and types are exposed. Never echo messages or values.
            $code = isset($r['code']) && (is_int($r['code']) || (is_string($r['code']) && preg_match('/^-?[0-9]{1,6}$/D',$r['code']))) ? (string)$r['code'] : 'missing/non-numeric';
            $shape = array('code='.$code, 'data='.(array_key_exists('data',$r)?gettype($r['data']):'missing'));
            foreach (array('msg','message','success','token','access_token','accessToken') as $field) {
                if (array_key_exists($field,$r)) $shape[]=$field.':'.gettype($r[$field]);
                if (isset($r['data']) && is_array($r['data']) && array_key_exists($field,$r['data'])) $shape[]='data.'.$field.':'.gettype($r['data'][$field]);
            }
            return $this->error('auth', 'Δεν αναγνωρίστηκε έγκυρο token. Διάγνωση: '.implode(' | ',$shape).'. Δεν εμφανίζονται τιμές credentials ή token.');
        }
        $ttl=17700; // Documented 18000 seconds, with a five-minute safety margin.
        set_transient(self::TOKEN,array('value'=>$r['data'],'expires'=>time()+$ttl),$ttl);
        return $r['data'];
    }
    public static function parse_lookup($r,$vin) {
        if (!isset($r['code']) || !in_array($r['code'],array(0,'0'),true) || (isset($r['ok']) && $r['ok'] !== true)) return new WP_Error('business','Το API ανέφερε αποτυχία ελέγχου.');
        if (!empty($r['data']['message']) || (isset($r['data']['errorCode']) && !in_array($r['data']['errorCode'],array(0,'0',null,''),true))) return new WP_Error('business','Το iDMS ανέφερε σφάλμα.');
        $p=isset($r['data']['payload']) ? $r['data']['payload'] : null;
        if (!is_array($p) || !isset($p['vin']) || !is_string($p['vin']) || self::normalize_vin($p['vin']) !== $vin) return new WP_Error('payload','Ελλιπής απάντηση ή διαφορετικό VIN στην απάντηση.');
        // null/missing means unknown, never a negative recall result.
        if (!array_key_exists('vinExist',$p) || !is_bool($p['vinExist'])) return new WP_Error('unknown','Το iDMS δεν επιβεβαίωσε αν υπάρχει το VIN.');
        if ($p['vinExist'] === false) return array('type'=>'not_found','vin'=>$vin,'count'=>null,'minutes'=>null);
        $count = isset($p['unfinishedCount']) ? self::as_count($p['unfinishedCount']) : null;
        if ($count === null) return new WP_Error('payload','Μη έγκυρος αριθμός τεχνικών ενεργειών.');
        // Labour minutes are informative only: missing/null must not hide the campaign count.
        $m=isset($p['totalLaborMinutes']) ? $p['totalLaborMinutes'] : null;
        if ($m !== null && (!is_numeric($m) || (float)$m<0 || !is_finite((float)$m))) return new WP_Error('payload','Μη έγκυρος χρόνος εργασίας.');
        return array('type'=>$count>0?'match':'no_match','vin'=>$vin,'count'=>$count,'minutes'=>$m===null?null:(float)$m);
    }
    public function lookup($vin) {
        $vin=self::normalize_vin($vin);
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D',$vin)) return $this->error('vin','Μη έγκυρο VIN.');
        $token=$this->token(); if(is_wp_error($token)) return $token;
        for($i=0;$i<2;$i++) {
            $r=$this->request(self::lookup_url($this->s['base_url']),array('method'=>'POST','headers'=>array('Accept'=>'application/json','Content-Type'=>'application/json','APP-ID'=>$this->s['app_id'],'APP-TOKEN'=>$token),'body'=>wp_json_encode(array('vin'=>$vin))));
            // Expired or replaced tokens come back as HTTP 401/403 or as HTTP 200 with a gateway error code.
            if ($i===0 && ((is_wp_error($r) && $r->get_error_code()==='unauthorized') || self::gateway_rejected($r))) {
                self::clear_token(); $token=$this->token(true); if(is_wp_error($token)) return $token; continue;
            }
            return is_wp_error($r) ? $r : self::parse_lookup($r,$vin);
        }
        return $this->error('auth','Αποτυχία ανανέωσης πρόσβασης.');
    }
}
