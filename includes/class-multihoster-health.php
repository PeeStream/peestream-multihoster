<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Health {
    const HOOK = 'psmh_health_batch';
    const INTERVAL = 'psmh_health_interval';
    const BATCH_SIZE = 20;
    const TIMEOUT = 8;
    const MAX_FAILURES = 3;
    const MIN_RECHECK = 3600;
    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'schedules'));
        add_action('init', array(__CLASS__, 'schedule'));
        add_action(self::HOOK, array(__CLASS__, 'run_batch'));
        add_action('admin_post_psmh_check_now', array(__CLASS__, 'check_now'));
    }
    public static function activate() {
        if (!wp_next_scheduled(self::HOOK)) { wp_schedule_event(time() + 300, self::INTERVAL, self::HOOK); }
    }
    public static function deactivate() { wp_clear_scheduled_hook(self::HOOK); }
    public static function schedules($schedules) {
        if (!isset($schedules[self::INTERVAL])) { $schedules[self::INTERVAL] = array('interval' => 6 * HOUR_IN_SECONDS, 'display' => __('Every six hours', 'peestream-multihoster')); }
        return $schedules;
    }
    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) { wp_schedule_event(time() + 300, self::INTERVAL, self::HOOK); }
    }
    public static function run_batch() {
        if (get_transient('psmh_health_lock')) { return 0; }
        set_transient('psmh_health_lock', 1, 10 * MINUTE_IN_SECONDS);
        $processed = 0;
        try {
            $ids = get_posts(array('post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => PSMH_Meta::KEY, 'no_found_rows' => true));
            foreach ($ids as $post_id) {
                $sources = PSMH_Meta::get_sources($post_id); $dirty = false;
                foreach ($sources as $i => $source) {
                    if ($processed >= self::BATCH_SIZE) { break 2; }
                    $now = time(); $next = isset($source['next_check']) ? (int) $source['next_check'] : 0;
                    if ($source['status'] === 'dead' || ($next > $now)) { continue; }
                    $sources[$i]['status'] = 'checking'; $sources[$i]['last_checked'] = $now; $dirty = true;
                    $result = self::probe($source['url']);
                    $sources[$i] = self::apply_result($sources[$i], $result, $now);
                    $processed++;
                }
                if ($dirty) { update_post_meta($post_id, PSMH_Meta::KEY, $sources); }
            }
        } finally { delete_transient('psmh_health_lock'); }
        return $processed;
    }
    public static function probe($url) {
        $args = array('method' => 'HEAD', 'timeout' => self::TIMEOUT, 'redirection' => 3, 'limit_response_size' => 1024, 'headers' => array('User-Agent' => 'PeeStream-MultiHoster/' . PSMH_VERSION));
        $response = wp_safe_remote_request($url, $args);
        if (is_wp_error($response)) { return array('kind'=>'transient','code'=>0); }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 405 || $code === 501) {
            $args['method'] = 'GET'; $args['headers']['Range'] = 'bytes=0-1024';
            $response = wp_safe_remote_request($url, $args);
            if (is_wp_error($response)) { return array('kind'=>'transient','code'=>0); }
            $code = (int) wp_remote_retrieve_response_code($response);
        }
        if ($code === 404 || $code === 410) { return array('kind'=>'dead','code'=>$code); }
        if (($code >= 200 && $code < 400) || $code === 206) { return array('kind'=>'alive','code'=>$code); }
        return array('kind'=>'transient','code'=>$code);
    }
    public static function apply_result($source, $result, $now) {
        $fails = isset($source['retry_count']) ? (int) $source['retry_count'] : 0;
        $source['last_checked'] = (int) $now;
        $source['last_http_code'] = (int) $result['code'];
        if ($result['kind'] === 'alive') {
            $source['status']='alive'; $source['retry_count']=0; $source['next_check']=$now + self::MIN_RECHECK;
        } elseif ($result['kind'] === 'dead') {
            $source['status']='dead'; $source['retry_count']=$fails + 1; $source['next_check']=0;
        } else {
            $fails++; $source['retry_count']=$fails;
            if ($fails >= self::MAX_FAILURES) { $source['status']='dead'; $source['next_check']=0; }
            else { $source['status']='checking'; $source['next_check']=$now + (15 * MINUTE_IN_SECONDS * (2 ** ($fails - 1))); }
        }
        return $source;
    }
    public static function check_now() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        check_admin_referer('psmh_check_now_' . $post_id);
        if (!$post_id || !current_user_can('edit_post', $post_id)) { wp_die(esc_html__('Permission denied.', 'peestream-multihoster'), 403); }
        $sources = PSMH_Meta::get_sources($post_id);
        foreach ($sources as $i => $source) {
            $prior = isset($source['status']) ? $source['status'] : 'alive';
            $sources[$i]['status']='checking'; $sources[$i]['last_checked']=time();
            $checked = self::apply_result($sources[$i], self::probe($source['url']), time());
            if ($checked['status'] === 'checking' && $prior === 'alive' && !empty($source['retry_count'])) { $checked['status'] = 'alive'; }
            $sources[$i] = $checked;
        }
        update_post_meta($post_id, PSMH_Meta::KEY, $sources);
        wp_safe_redirect(add_query_arg(array('post'=>$post_id,'action'=>'edit','psmh_checked'=>1), admin_url('post.php')));
        exit;
    }
    public static function cli_run() { return self::run_batch(); }
}
