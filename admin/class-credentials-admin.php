<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Credentials_Admin {
    const NONCE = 'psmh_credentials_save';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_psmh_save_credential', array(__CLASS__, 'save'));
        add_action('admin_post_psmh_save_instance', array(__CLASS__, 'save_instance'));
        add_action('admin_post_psmh_delete_credential', array(__CLASS__, 'delete'));
        add_action('admin_post_psmh_test_credential', array(__CLASS__, 'test_credential'));
        add_action('admin_post_psmh_save_healing', array(__CLASS__, 'save_healing'));
        add_action('admin_post_psmh_run_worker', array(__CLASS__, 'run_worker'));
    }
    public static function menu() { add_options_page(__('PeeStream Host Accounts','peestream-multihoster'), __('PeeStream Host Accounts','peestream-multihoster'), 'manage_options', 'psmh-accounts', array(__CLASS__,'render')); }
    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>' . esc_html__('Host API credentials','peestream-multihoster') . '</h1>';
        echo '<div class="notice notice-info" style="margin:15px 0;padding:12px 15px;border-left:4px solid #10b981;">';
        echo '<h3 style="margin-top:0;margin-bottom:6px;color:#065f46;">' . esc_html__('Early Adopter Promo & Transparency','peestream-multihoster') . '</h3>';
        echo '<p style="margin:0 0 6px 0;"><strong>' . esc_html__('Multi-Hoster Player Tabs:','peestream-multihoster') . '</strong> ' . esc_html__('100% Free Forever. No PeeStream account or API credentials required to display and switch multiple hoster sources.','peestream-multihoster') . '</p>';
        echo '<p style="margin:0;"><strong>' . esc_html__('Automated Dead-Link Healing:','peestream-multihoster') . '</strong> ' . esc_html__('Currently 100% unlocked for free under our Early Adopter Promo. Uses your PeeStream API key to automatically re-ingest alternate sources when primary links die. High-volume background healing may later be supported via an optional VIP webmaster addon (from $1/mo).','peestream-multihoster') . '</p>';
        echo '</div>';
        echo '<p>' . esc_html__('Secrets are encrypted in the database. Saved values are never displayed. Configure unique LOGGED_IN_KEY and NONCE_KEY constants first.','peestream-multihoster') . '</p>';
        $counts=PSMH_Healing_Queue::status_counts();
        echo '<h2>' . esc_html__('Healing queue','peestream-multihoster') . '</h2><table class="widefat striped"><thead><tr><th>Pending</th><th>Processing</th><th>Polling</th><th>Failed</th><th>Healed</th></tr></thead><tbody><tr>';
        foreach(array('pending','processing','polling','failed','healed') as $state){echo '<td>'.esc_html((string)$counts[$state]).'</td>';}
        echo '</tr></tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="psmh_run_worker">'; wp_nonce_field('psmh_run_worker'); echo '<button class="button button-primary">'.esc_html__('Run healing worker now','peestream-multihoster').'</button></form>';
        if (!PSMH_Credentials::crypto_available()) { echo '<div class="notice notice-error"><p>' . esc_html__('Authenticated encryption is unavailable; credential storage is disabled.','peestream-multihoster') . '</p></div>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="psmh_save_healing">'; wp_nonce_field('psmh_healing_save'); echo '<label><input type="checkbox" name="enabled" value="yes" ' . checked(get_option('psmh_healing_enabled','no'),'yes',false) . '> ' . esc_html__('Enable automatic PeeStream healing (remote ingest from an alternate source)','peestream-multihoster') . '</label> <button class="button">' . esc_html__('Save healing setting','peestream-multihoster') . '</button></form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="psmh_save_instance">'; wp_nonce_field('psmh_instance_save'); echo '<label>' . esc_html__('PeeStream HTTPS instance','peestream-multihoster') . ' <input type="url" name="instance_url" value="' . esc_attr(get_option('psmh_peestream_instance_url','')) . '" placeholder="https://peestream.example" required></label> <button class="button">' . esc_html__('Save instance','peestream-multihoster') . '</button></form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="psmh_save_credential">';
        wp_nonce_field(self::NONCE); echo '<label>' . esc_html__('Hoster','peestream-multihoster') . ' <select name="hoster">';
        foreach (PSMH_Meta::HOSTERS as $hoster) { echo '<option value="' . esc_attr($hoster) . '">' . esc_html(ucfirst($hoster)) . '</option>'; }
        echo '</select></label> <label>' . esc_html__('API key','peestream-multihoster') . ' <input type="password" name="secret" autocomplete="new-password" required></label> <button class="button button-primary">' . esc_html__('Save encrypted credential','peestream-multihoster') . '</button></form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="psmh_test_credential">'; wp_nonce_field('psmh_test_credential'); echo '<label>' . esc_html__('Test PeeStream key (not saved by this form)','peestream-multihoster') . ' <input type="password" name="secret" autocomplete="off" required></label> <button class="button">' . esc_html__('Validate key','peestream-multihoster') . '</button></form><h2>' . esc_html__('Configured','peestream-multihoster') . '</h2><ul>';
        foreach (PSMH_Credentials::configured_hosters() as $hoster) { echo '<li>' . esc_html(ucfirst($hoster)) . ' - ' . esc_html__('saved (value hidden)','peestream-multihoster') . ' <form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="psmh_delete_credential"><input type="hidden" name="hoster" value="' . esc_attr($hoster) . '">'; wp_nonce_field('psmh_delete_' . $hoster); echo '<button class="button-link-delete">' . esc_html__('Delete','peestream-multihoster') . '</button></form></li>'; }
        echo '</ul></div>';
    }
    public static function run_worker() {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Permission denied.','peestream-multihoster'),403);}
        check_admin_referer('psmh_run_worker');
        if(!wp_next_scheduled(PSMH_Healing_Worker::HOOK,array(true))){wp_schedule_single_event(time()+1,PSMH_Healing_Worker::HOOK,array(true));}
        wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>'worker_scheduled'),admin_url('options-general.php')));exit;
    }
    public static function save_healing() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.','peestream-multihoster'),403); }
        check_admin_referer('psmh_healing_save'); update_option('psmh_healing_enabled',isset($_POST['enabled'])&&$_POST['enabled']==='yes'?'yes':'no',false);
        wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>'healing_saved'),admin_url('options-general.php'))); exit;
    }
    public static function test_credential() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.','peestream-multihoster'),403); }
        check_admin_referer('psmh_test_credential'); $secret=isset($_POST['secret'])?(string)wp_unslash($_POST['secret']):'';
        $adapter=PSMH_Adapter_Registry::get('peestream'); $result=$adapter?$adapter->validate_credentials($secret):array('ok'=>false);
        set_transient('psmh_credential_test_'.get_current_user_id(),!empty($result['ok'])?'valid':'invalid',60);
        wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>!empty($result['ok'])?'credential_valid':'credential_invalid'),admin_url('options-general.php'))); exit;
    }
    public static function save_instance() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.','peestream-multihoster'),403); }
        check_admin_referer('psmh_instance_save'); $url=isset($_POST['instance_url'])?esc_url_raw(trim(wp_unslash($_POST['instance_url'])),array('https')):'';
        if (!$url || strpos($url,'https://')!==0 || !wp_parse_url($url,PHP_URL_HOST)) { wp_die(esc_html__('A valid HTTPS instance URL is required.','peestream-multihoster'),400); }
        update_option('psmh_peestream_instance_url',untrailingslashit($url),false); wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>'instance_saved'),admin_url('options-general.php'))); exit;
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.','peestream-multihoster'),403); }
        check_admin_referer(self::NONCE);
        $hoster = isset($_POST['hoster']) ? sanitize_key(wp_unslash($_POST['hoster'])) : '';
        $secret = isset($_POST['secret']) ? trim((string)wp_unslash($_POST['secret'])) : '';
        try { PSMH_Credentials::save($hoster,$secret); $notice='credential_saved'; }
        catch (Exception $e) { $notice='credential_error'; }
        wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>$notice),admin_url('options-general.php'))); exit;
    }
    public static function delete() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.','peestream-multihoster'),403); }
        $hoster=isset($_POST['hoster'])?sanitize_key(wp_unslash($_POST['hoster'])):''; check_admin_referer('psmh_delete_'.$hoster);
        PSMH_Credentials::delete($hoster); wp_safe_redirect(add_query_arg(array('page'=>'psmh-accounts','psmh_notice'=>'credential_deleted'),admin_url('options-general.php'))); exit;
    }
}
