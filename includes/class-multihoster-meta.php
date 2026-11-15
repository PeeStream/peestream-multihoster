<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Meta {
    const KEY = '_psmh_sources';
    const POST_TYPES_KEY = '_psmh_enabled_post_types';
    const HOSTERS = array('peestream', 'doodstream', 'mixdrop', 'voe', 'filemoon', 'streamtape', 'other');
    const STATUSES = array('alive', 'dead', 'checking');
    public static function init() {
        add_action('init', array(__CLASS__, 'register_meta'));
        add_action('init', array(__CLASS__, 'register_shortcode'));
    }
    public static function activate() {
        if (get_option(self::POST_TYPES_KEY, false) === false) { add_option(self::POST_TYPES_KEY, array('post')); }
    }
    public static function register_meta() {
        register_post_meta('', self::KEY, array(
            'type' => 'array', 'single' => true, 'show_in_rest' => false,
            'sanitize_callback' => array(__CLASS__, 'sanitize_sources'),
            'auth_callback' => static function () { return current_user_can('edit_posts'); },
        ));
    }
    public static function register_shortcode() { add_shortcode('peestream_sources', array('PSMH_Player', 'shortcode')); }
    public static function get_sources($post_id) {
        $sources = get_post_meta((int) $post_id, self::KEY, true);
        return self::sanitize_sources(is_array($sources) ? $sources : array());
    }
    public static function sanitize_sources($sources) {
        if (!is_array($sources)) { return array(); }
        $clean = array(); $seen = array();
        foreach (array_slice($sources, 0, 20) as $source) {
            if (!is_array($source)) { continue; }
            $hoster = sanitize_key(isset($source['hoster']) ? $source['hoster'] : '');
            if (!in_array($hoster, self::HOSTERS, true)) { continue; }
            $url = esc_url_raw(trim(isset($source['url']) ? $source['url'] : ''), array('http', 'https'));
            if (!$url || !preg_match('#^https?://#i', $url)) { continue; }
            $identity = strtolower($hoster . '|' . $url);
            if (isset($seen[$identity])) { continue; }
            $seen[$identity] = true;
            $status = sanitize_key(isset($source['status']) ? $source['status'] : 'alive');
            if (!in_array($status, self::STATUSES, true)) { $status = 'alive'; }
            $clean[] = array(
                'hoster' => $hoster,
                'url' => $url,
                'label' => sanitize_text_field(isset($source['label']) ? $source['label'] : ''),
                'order' => isset($source['order']) && is_numeric($source['order']) ? max(0, min(9999, (int) $source['order'])) : count($clean),
                'status' => $status,
                'last_checked' => isset($source['last_checked']) ? max(0, (int) $source['last_checked']) : 0,
                'retry_count' => isset($source['retry_count']) ? max(0, min(3, (int) $source['retry_count'])) : 0,
                'next_check' => isset($source['next_check']) ? max(0, (int) $source['next_check']) : 0,
                'last_http_code' => isset($source['last_http_code']) ? max(0, (int) $source['last_http_code']) : 0,
            );
        }
        usort($clean, static function ($a, $b) { return $a['order'] <=> $b['order']; });
        foreach ($clean as $i => $source) { $clean[$i]['order'] = $i; }
        return $clean;
    }
}
