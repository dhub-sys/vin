<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// Removes the 3.x settings (encrypted credentials included), logs, cached token and rate-limit counters.
global $wpdb;
delete_option('dvrms_api_settings_v3');
delete_option('dvrms_api_logs_v3');
foreach (array('dvrms_api_token_v3', 'dvrms_rl_') as $prefix) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_'.$prefix).'%',
        $wpdb->esc_like('_transient_timeout_'.$prefix).'%'
    ));
}
