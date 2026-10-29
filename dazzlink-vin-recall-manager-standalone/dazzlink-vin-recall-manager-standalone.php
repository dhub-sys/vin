<?php
/**
 * Plugin Name: Dazzlink VIN Recall Manager Standalone
 * Description: Live IAS/iDMS VIN lookup with an independent form, API settings and diagnostics.
 * Version: 3.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Dazzlink
 * Author URI: https://www.dazzlink.gr/
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/includes/class-ias-client.php';

final class Dazzlink_VIN_Recall_Manager_Standalone {
    const VERSION='3.1.0';
    const OPTION='dvrms_api_settings_v3';
    private $notice='';
    private $bad=false;
    /** Secret fields whose stored value exists but no longer decrypts (WordPress salts changed). */
    private $undecryptable=array();
    public function __construct() {
        add_action('admin_menu',array($this,'menu'));
        add_action('admin_init',array($this,'admin_actions'));
        add_shortcode('vin_recall_search',array($this,'shortcode'));
        add_action('wp_ajax_dvrms_lookup_vin',array($this,'ajax'));
        add_action('wp_ajax_nopriv_dvrms_lookup_vin',array($this,'ajax'));
        add_action('wp_ajax_dvrms_form_nonce',array($this,'nonce'));
        add_action('wp_ajax_nopriv_dvrms_form_nonce',array($this,'nonce'));
    }
    public static function defaults() {
        return array('auth_url'=>'','base_url'=>'','app_id'=>'','app_secret'=>'','aes_key'=>'','aes_iv'=>'','secret_mode'=>'aes','environment'=>'uat',
        'heading'=>'Έλεγχος τεχνικών ενεργειών οχήματος',
        'intro'=>'Συμπληρώστε τον αριθμό πλαισίου (VIN) για έλεγχο εκκρεμών τεχνικών ενεργειών στο σύστημα του εργοστασίου.',
        'vin_label'=>'Αριθμός Πλαισίου (VIN)','vin_placeholder'=>'Εισάγετε VIN 17 χαρακτήρων','button_text'=>'Έλεγχος','contact_text'=>'',
        'match_message'=>'Για το VIN {vin} υπάρχουν {count} εκκρεμείς τεχνικές ενέργειες. Παρακαλούμε επικοινωνήστε με εξουσιοδοτημένο συνεργάτη service. {contact}',
        'match_one_message'=>'Για το VIN {vin} υπάρχει 1 εκκρεμής τεχνική ενέργεια. Παρακαλούμε επικοινωνήστε με εξουσιοδοτημένο συνεργάτη service. {contact}',
        'no_match_message'=>'Για το VIN {vin} δεν υπάρχουν εκκρεμείς τεχνικές ενέργειες στο σύστημα του εργοστασίου.',
        'not_found_message'=>'Το VIN {vin} δεν βρέθηκε στο σύστημα του εργοστασίου. Παρακαλούμε επικοινωνήστε με εξουσιοδοτημένο συνεργάτη. {contact}',
        'invalid_message'=>'Εισάγετε έγκυρο VIN 17 χαρακτήρων, χωρίς I, O ή Q.',
        'error_message'=>'Ο έλεγχος δεν είναι διαθέσιμος αυτή τη στιγμή. Παρακαλούμε δοκιμάστε ξανά αργότερα.',
        'rate_message'=>'Έγιναν πολλές προσπάθειες. Παρακαλούμε περιμένετε ένα λεπτό.',
        'loading_message'=>'Γίνεται έλεγχος...',
        'contact_url'=>'','contact_url_label'=>'Κλείστε ραντεβού στο service','contact_phone'=>'');
    }
    /** Admin labels and help, in the order the fields are shown. */
    private static function fields() {
        return array(
            'api'=>array(
                'environment'=>array('Περιβάλλον',''),
                'auth_url'=>array('Auth / Token endpoint','Πλήρες URL, π.χ. https://ipaas-admin-om-sit.omodaglobal.com/api/auth/getToken (UAT). Δεν προστίθεται path.'),
                'base_url'=>array('API base URL','Όπως το έδωσε το εργοστάσιο, π.χ. https://ipaas-om-sit.omodaglobal.com. Αν είναι σκέτο host, προστίθεται αυτόματα το /api/externalIntegration.'),
                'app_id'=>array('App ID',''),
                'secret_mode'=>array('Αποστολή App Secret',''),
                'app_secret'=>array('App Secret',''),
                'aes_key'=>array('AES key','Ακριβές UTF-8 κείμενο 16/24/32 bytes. Κενό: CheryOdin!202503.'),
                'aes_iv'=>array('AES IV','Ακριβές UTF-8 κείμενο 16 bytes. Κενό: CheryOdin!202503.'),
            ),
            'texts'=>array(
                'heading'=>array('Τίτλος φόρμας',''),
                'intro'=>array('Εισαγωγικό κείμενο',''),
                'vin_label'=>array('Ετικέτα πεδίου VIN',''),
                'vin_placeholder'=>array('Placeholder πεδίου VIN',''),
                'button_text'=>array('Κείμενο κουμπιού',''),
                'match_message'=>array('Υπάρχουν εκκρεμείς ενέργειες (2 ή περισσότερες)',''),
                'match_one_message'=>array('Υπάρχει 1 εκκρεμής ενέργεια','Κενό: χρησιμοποιείται το προηγούμενο μήνυμα.'),
                'no_match_message'=>array('Δεν υπάρχουν εκκρεμείς ενέργειες',''),
                'not_found_message'=>array('Το VIN δεν βρέθηκε',''),
                'invalid_message'=>array('Μη έγκυρο VIN',''),
                'error_message'=>array('Αδυναμία ελέγχου / σφάλμα API',''),
                'rate_message'=>array('Πολλές προσπάθειες',''),
                'loading_message'=>array('Κατά τον έλεγχο',''),
                'contact_text'=>array('Κείμενο επικοινωνίας {contact}','Απλό κείμενο που μπαίνει στη θέση του {contact}.'),
                'contact_url'=>array('Σύνδεσμος ραντεβού','Προαιρετικό HTTPS/HTTP URL. Εμφανίζεται ως κουμπί όταν υπάρχουν ενέργειες ή το VIN δεν βρέθηκε.'),
                'contact_url_label'=>array('Κείμενο κουμπιού ραντεβού',''),
                'contact_phone'=>array('Τηλέφωνο service','Προαιρετικό. Εμφανίζεται ως σύνδεσμος κλήσης μαζί με το κουμπί.'),
            ),
        );
    }
    /** 180 → "3 ώρες", 95 → "1 ώρα και 35 λεπτά". Empty when the API gave no minutes. */
    public static function duration($minutes) {
        if ($minutes === null) return '';
        $m=(int)round((float)$minutes); $h=intdiv($m,60); $m=$m%60; $parts=array();
        if($h) $parts[]=$h.($h===1?' ώρα':' ώρες');
        if($m) $parts[]=$m.($m===1?' λεπτό':' λεπτά');
        return $parts ? implode(' και ',$parts) : '';
    }
    private static function secrets() { return array('app_secret','aes_key','aes_iv'); }
    private function seal($v) {
        if ($v==='') return '';
        if (!function_exists('openssl_encrypt')) return new WP_Error('openssl','Απαιτείται PHP OpenSSL για ασφαλή αποθήκευση.');
        $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($v,'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false) return new WP_Error('storage','Αποτυχία ασφαλούς αποθήκευσης.');
        return 'enc:'.base64_encode($iv.$tag.$cipher);
    }
    public function settings() {
        $s=wp_parse_args(get_option(self::OPTION,array()),self::defaults());
        foreach(self::secrets() as $k) {
            if($s[$k]==='') continue;
            $b=base64_decode(substr($s[$k],4),true);
            $v=(function_exists('openssl_decrypt') && $b!==false && strlen($b)>=28) ? openssl_decrypt(substr($b,28),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16)) : false;
            if($v===false) $this->undecryptable[$k]=true;
            $s[$k]=$v===false?'':$v;
        }
        return $s;
    }
    public function menu() {
        add_menu_page('VIN Recall','VIN Recall','manage_options','dazzlink-vin-recall',array($this,'page'),'dashicons-search');
        add_submenu_page('dazzlink-vin-recall','API & Messages','API & Messages','manage_options','dazzlink-vin-recall',array($this,'page'));
        add_submenu_page('dazzlink-vin-recall','Diagnostics','Diagnostics','manage_options','dazzlink-vin-diagnostics',array($this,'diagnostics'));
    }
    private function record($operation,$result) {
        $logs=get_option('dvrms_api_logs_v3',array());
        array_unshift($logs,array('time'=>gmdate('Y-m-d H:i:s').' UTC','operation'=>$operation,'status'=>is_wp_error($result)?$result->get_error_code():'success'));
        update_option('dvrms_api_logs_v3',array_slice($logs,0,30),false);
    }
    public function admin_actions() {
        if(!current_user_can('manage_options') || !isset($_POST['dvrms_task']) || !is_string($_POST['dvrms_task'])) return;
        check_admin_referer('dvrms_admin');
        $task=sanitize_key($_POST['dvrms_task']);
        if($task==='save') {
            $s=$this->settings(); $input=isset($_POST['s']) && is_array($_POST['s']) ? wp_unslash($_POST['s']) : array();
            foreach(self::defaults() as $k=>$default) {
                // Pasted credentials often carry stray spaces; secrets, IDs and URLs never contain leading/trailing whitespace.
                if(in_array($k,self::secrets(),true)) { if(isset($input[$k]) && is_string($input[$k]) && trim($input[$k])!=='') $s[$k]=trim($input[$k]); }
                else $s[$k]=isset($input[$k]) && is_string($input[$k]) ? sanitize_textarea_field($input[$k]) : $default;
            }
            foreach(array('auth_url','base_url','app_id','contact_url','contact_phone') as $k) $s[$k]=trim($s[$k]);
            if(!empty($_POST['reset_aes'])) { $s['aes_key']=''; $s['aes_iv']=''; }
            if(!in_array($s['secret_mode'],array('aes','encrypted','supplied'),true) || !in_array($s['environment'],array('uat','production'),true) || preg_match('/[\r\n]/',$s['app_id'])) { $this->bad=true; $this->notice='Μη έγκυρες ρυθμίσεις.'; return; }
            foreach(array('auth_url','base_url') as $k) if($s[$k]!=='' && !DVRMS_IAS_Client::valid_url($s[$k])) { $this->bad=true; $this->notice='Χρησιμοποιήστε HTTPS URL χωρίς query, fragment ή credentials.'; return; }
            if($s['contact_url']!=='' && !preg_match('#^https?://#i',$s['contact_url'])) { $this->bad=true; $this->notice='Ο σύνδεσμος ραντεβού πρέπει να ξεκινά με https:// ή http://.'; return; }
            $s['contact_url']=esc_url_raw($s['contact_url']);
            $s['base_url']=rtrim($s['base_url'],'/');
            foreach(self::secrets() as $k) { $s[$k]=$this->seal($s[$k]); if(is_wp_error($s[$k])) { $this->notice=$s[$k]->get_error_message(); $this->bad=true; return; } }
            update_option(self::OPTION,$s,false); DVRMS_IAS_Client::clear_token(); $this->notice='Οι ρυθμίσεις αποθηκεύτηκαν.';
        } elseif($task==='test' || $task==='lookup') {
            $client=new DVRMS_IAS_Client($this->settings());
            $vin=isset($_POST['test_vin']) && is_string($_POST['test_vin']) ? DVRMS_IAS_Client::normalize_vin(wp_unslash($_POST['test_vin'])) : '';
            $r=$task==='test' ? $client->token(true) : $client->lookup($vin);
            $this->record($task,$r); $this->bad=is_wp_error($r);
            if($this->bad) $this->notice=$r->get_error_message();
            elseif($task==='test') $this->notice='Επιτυχής έκδοση token. Η σύνδεση με το iDMS ελέγχεται ξεχωριστά με Test VIN.';
            else { $this->notice='Αποτέλεσμα: '.$r['type'].' | unfinishedCount: '.($r['count']===null?'—':$r['count']).' | totalLaborMinutes: '.($r['minutes']===null?'—':$r['minutes']).' | Ο επισκέπτης βλέπει: '.$this->message($this->settings(),$r); }
        } elseif($task==='clear_token') { DVRMS_IAS_Client::clear_token(); $this->notice='Το token διαγράφηκε.'; }
        elseif($task==='clear_logs') { delete_option('dvrms_api_logs_v3'); $this->notice='Τα logs διαγράφηκαν.'; }
    }
    private function header($title) {
        echo '<div class="wrap"><h1>'.esc_html($title).'</h1>';
        if($this->notice!=='') echo '<div class="notice '.($this->bad?'notice-error':'notice-success').'"><p>'.esc_html($this->notice).'</p></div>';
        $s=$this->settings();
        if($this->undecryptable) echo '<div class="notice notice-error"><p>'.esc_html('Τα αποθηκευμένα '.implode(', ',array_keys($this->undecryptable)).' δεν αποκρυπτογραφούνται (πιθανότατα άλλαξαν τα WordPress salts). Εισάγετέ τα ξανά και αποθηκεύστε.').'</p></div>';
        if($s['environment']==='uat') echo '<div class="notice notice-warning"><p>Περιβάλλον: <strong>UAT / Test</strong>. Για τη δημόσια λειτουργία συμπληρώστε τα production URLs και credentials.</p></div>';
    }
    /** Visitor-facing text for a lookup result. */
    private function message($s,$r) {
        $template=$s[$r['type']==='match'?'match_message':($r['type']==='no_match'?'no_match_message':'not_found_message')];
        if($r['type']==='match' && $r['count']===1 && trim($s['match_one_message'])!=='') $template=$s['match_one_message'];
        $text=strtr($template,array('{vin}'=>$r['vin'],'{count}'=>(string)$r['count'],'{minutes}'=>$r['minutes']===null?'':(string)(0+$r['minutes']),'{duration}'=>self::duration($r['minutes']),'{contact}'=>$s['contact_text']));
        return trim(preg_replace('/[ \t]{2,}/',' ',$text));
    }
    public function page() {
        if(!current_user_can('manage_options')) return;
        $s=$this->settings(); $this->header('Dazzlink VIN Recall · v'.self::VERSION); $f=self::fields();
        $choices=array('environment'=>array('uat'=>'UAT / Test','production'=>'Production'),'secret_mode'=>array('aes'=>'Raw App Secret → AES-CBC → HEX','encrypted'=>'Ήδη κρυπτογραφημένο App Secret (HEX)','supplied'=>'Όπως δόθηκε — χωρίς επιπλέον κρυπτογράφηση'));
        echo '<p>Online έλεγχος αποκλειστικά μέσω IAS/iDMS. Shortcode: <code>[vin_recall_search]</code></p><form method="post">';
        wp_nonce_field('dvrms_admin'); echo '<input type="hidden" name="dvrms_task" value="save"><h2>Σύνδεση API</h2><table class="form-table" role="presentation">';
        foreach($f['api'] as $k=>$meta) {
            list($label,$help)=$meta; $secret=in_array($k,self::secrets(),true);
            echo '<tr><th><label for="s-'.esc_attr($k).'">'.esc_html($label).'</label></th><td>';
            if(isset($choices[$k])) {
                echo '<select id="s-'.esc_attr($k).'" name="s['.esc_attr($k).']">';
                foreach($choices[$k] as $value=>$text) echo '<option value="'.esc_attr($value).'" '.selected($s[$k],$value,false).'>'.esc_html($text).'</option>';
                echo '</select>';
            } else {
                echo '<input class="large-text" id="s-'.esc_attr($k).'" name="s['.esc_attr($k).']" type="'.($secret?'password':'text').'" value="'.esc_attr($secret?'':$s[$k]).'" autocomplete="off" spellcheck="false">';
                if($secret) $help=trim(($s[$k]!==''?'Έχει αποθηκευτεί. Κενό πεδίο: διατήρηση υπάρχουσας τιμής. ':($k==='app_secret'?'Δεν έχει οριστεί. ':'')).$help);
            }
            if($k==='base_url') $help.=' Τελικό URL ερωτήματος: '.($s['base_url']!==''?DVRMS_IAS_Client::lookup_url($s['base_url']):'—');
            if($k==='secret_mode') $help='Σε «Ήδη κρυπτογραφημένο» και «Όπως δόθηκε» τα AES key/IV αγνοούνται. Το «Όπως δόθηκε» στέλνει το App Secret αυτούσιο στο header, όπως το curl του εργοστασίου.';
            if($help!=='') echo '<p class="description">'.esc_html($help).'</p>';
            if($k==='aes_iv') echo '<p><label><input type="checkbox" name="reset_aes" value="1"> Επαναφορά AES key και IV στις προεπιλογές</label></p>';
            echo '</td></tr>';
        }
        echo '</table><h2>Κείμενα φόρμας & αποτελεσμάτων</h2><p>Μεταβλητές: <code>{vin}</code> <code>{count}</code> <code>{minutes}</code> (λεπτά, αριθμός) <code>{duration}</code> (π.χ. «3 ώρες») <code>{contact}</code>. Το endpoint δεν επιστρέφει κωδικούς ή περιγραφές καμπανιών.</p><table class="form-table" role="presentation">';
        foreach($f['texts'] as $k=>$meta) {
            list($label,$help)=$meta; $long=substr($k,-8)==='_message' || $k==='intro';
            echo '<tr><th><label for="s-'.esc_attr($k).'">'.esc_html($label).'</label></th><td>';
            if($long) echo '<textarea class="large-text" rows="2" id="s-'.esc_attr($k).'" name="s['.esc_attr($k).']">'.esc_textarea($s[$k]).'</textarea>';
            else echo '<input class="large-text" type="text" id="s-'.esc_attr($k).'" name="s['.esc_attr($k).']" value="'.esc_attr($s[$k]).'">';
            if($help!=='') echo '<p class="description">'.esc_html($help).'</p>';
            echo '</td></tr>';
        }
        echo '</table>'; submit_button('Αποθήκευση'); echo '</form></div>';
    }
    public function diagnostics() {
        if(!current_user_can('manage_options')) return;
        $this->header('VIN Recall · Diagnostics'); $s=$this->settings(); $t=get_transient(DVRMS_IAS_Client::TOKEN);
        echo '<p>Environment: <strong>'.esc_html($s['environment']).'</strong> · Token: '.esc_html(is_array($t)&&isset($t['expires'])&&$t['expires']>time()?'cached έως '.gmdate('Y-m-d H:i:s',$t['expires']).' UTC':'δεν υπάρχει ενεργό token').'</p><p>Τα tests χρησιμοποιούν τις αποθηκευμένες ρυθμίσεις. Δεν εμφανίζονται credentials, token ή raw API responses.</p>';
        foreach(array('test'=>'Test Connection (authentication)','lookup'=>'Test VIN lookup','clear_token'=>'Clear token','clear_logs'=>'Clear logs') as $task=>$label) {
            echo '<form method="post" style="margin:16px 0">'; wp_nonce_field('dvrms_admin'); echo '<input type="hidden" name="dvrms_task" value="'.esc_attr($task).'">';
            if($task==='lookup') echo '<label>Test VIN <input name="test_vin" required minlength="17" maxlength="17" pattern="[A-HJ-NPR-Za-hj-npr-z0-9]{17}" autocomplete="off"></label> ';
            echo '<button class="button">'.esc_html($label).'</button></form>';
        }
        echo '<h2>Τελευταίες λειτουργίες</h2><p>Καταγράφονται μόνο χρόνος, λειτουργία και κωδικός κατάστασης. Δεν αποθηκεύονται VIN ή IP στα logs. Διατηρούνται έως 30 εγγραφές.</p><table class="widefat"><thead><tr><th>Χρόνος</th><th>Λειτουργία</th><th>Κατάσταση</th></tr></thead><tbody>';
        foreach(get_option('dvrms_api_logs_v3',array()) as $r) echo '<tr><td>'.esc_html($r['time']).'</td><td>'.esc_html($r['operation']).'</td><td>'.esc_html($r['status']).'</td></tr>';
        echo '</tbody></table></div>';
    }
    public function nonce() { nocache_headers(); wp_send_json_success(array('nonce'=>wp_create_nonce('dvrms_lookup_vin'))); }
    public function ajax() {
        nocache_headers(); $s=$this->settings();
        if(!check_ajax_referer('dvrms_lookup_vin','nonce',false)) wp_send_json_error(array('message'=>$s['error_message']),403);
        $vin=isset($_POST['vin']) && is_string($_POST['vin']) ? DVRMS_IAS_Client::normalize_vin(wp_unslash($_POST['vin'])) : '';
        if(!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D',$vin)) wp_send_json_error(array('message'=>$s['invalid_message']),400);
        // Best-effort abuse protection using direct peer IP only; no untrusted forwarding headers.
        $peer=isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $keys=array('dvrms_rl_'.hash_hmac('sha256',$peer,wp_salt('nonce'))=>10,'dvrms_rl_global'=>100);
        foreach($keys as $key=>$limit) { $n=(int)get_transient($key); if($n>=$limit) wp_send_json_error(array('message'=>$s['rate_message']),429); }
        foreach($keys as $key=>$limit) { $window=get_transient($key.'_end'); if(!$window || $window<=time()) $window=time()+60; set_transient($key.'_end',$window,60); set_transient($key,(int)get_transient($key)+1,max(1,$window-time())); }
        $r=(new DVRMS_IAS_Client($s))->lookup($vin); $this->record('public_lookup',$r);
        if(is_wp_error($r)) wp_send_json_error(array('message'=>$s['error_message']),503);
        $out=array('type'=>$r['type'],'message'=>$this->message($s,$r));
        if($r['type']!=='no_match' && ($s['contact_url']!=='' || $s['contact_phone']!=='')) {
            $out['contact']=array('url'=>$s['contact_url'],'label'=>$s['contact_url_label'],'phone'=>$s['contact_phone'],'tel'=>preg_replace('/[^0-9+]/','',$s['contact_phone']));
        }
        wp_send_json_success($out);
    }
    public function shortcode() {
        $s=$this->settings();
        wp_enqueue_style('dvrms-frontend',plugins_url('assets/frontend.css',__FILE__),array(),self::VERSION);
        wp_enqueue_script('dvrms-frontend',plugins_url('assets/frontend.js',__FILE__),array(),self::VERSION,true);
        wp_localize_script('dvrms-frontend','DVRMS',array('ajaxUrl'=>admin_url('admin-ajax.php'),'loading'=>$s['loading_message'],'error'=>$s['error_message'],'invalid'=>$s['invalid_message']));
        $id=wp_unique_id('dvrms-vin-'); ob_start(); ?>
        <div class="dvrms-search" data-dvrms>
            <h2 class="dvrms-title"><?php echo esc_html($s['heading']); ?></h2>
            <p class="dvrms-intro"><?php echo nl2br(esc_html($s['intro'])); ?></p>
            <form class="dvrms-form" novalidate>
                <label class="dvrms-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($s['vin_label']); ?></label>
                <div class="dvrms-row"><input id="<?php echo esc_attr($id); ?>" type="text" name="vin" class="dvrms-input" maxlength="24" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?php echo esc_attr($s['vin_placeholder']); ?>"><button class="dvrms-button" type="submit"><?php echo esc_html($s['button_text']); ?></button></div>
                <div class="dvrms-result" aria-live="polite" aria-atomic="true" role="status"></div>
            </form>
        </div>
        <?php return ob_get_clean();
    }
}
// Retain safe presentation fields only; CSV-era result messages imply unsupported campaign details.
register_activation_hook(__FILE__,function() {
    if(get_option(Dazzlink_VIN_Recall_Manager_Standalone::OPTION,false)===false) {
        $s=Dazzlink_VIN_Recall_Manager_Standalone::defaults(); $old=get_option('dvrms_settings',array());
        foreach(array('heading','vin_label','vin_placeholder','button_text','contact_text','invalid_message','error_message','loading_message') as $k) if(isset($old[$k]) && is_string($old[$k])) $s[$k]=$old[$k];
        add_option(Dazzlink_VIN_Recall_Manager_Standalone::OPTION,$s,'',false);
    }
});
new Dazzlink_VIN_Recall_Manager_Standalone();
