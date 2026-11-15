<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Admin {
    const NONCE = 'psmh_save_sources';
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_box'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }
    public static function add_box() {
        $types = get_post_types(array('public' => true), 'names');
        foreach ($types as $type) { add_meta_box('psmh-sources', __('Video sources', 'peestream-multihoster'), array(__CLASS__, 'render'), $type, 'normal', 'default'); }
    }
    public static function render($post) {
        wp_nonce_field(self::NONCE, '_psmh_nonce');
        $sources = PSMH_Meta::get_sources($post->ID);
        if (!$sources) { $sources = array(array('hoster'=>'peestream','url'=>'','label'=>'','status'=>'alive')); }
        echo '<div id="psmh-source-list">';
        foreach ($sources as $i => $source) { self::row($i, $source); }
        echo '</div><button type="button" class="button" id="psmh-add-source">' . esc_html__('Add source', 'peestream-multihoster') . '</button>';
        echo '<p class="description">' . esc_html__('Sources marked alive are shown in the front-end player. Reorder rows to choose the default.', 'peestream-multihoster') . '</p>';
        if (current_user_can('edit_post', $post->ID)) { echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=psmh_check_now&post_id=' . (int) $post->ID), 'psmh_check_now_' . (int) $post->ID)) . '">' . esc_html__('Check sources now', 'peestream-multihoster') . '</a></p>'; }
        foreach ($sources as $source) { $when = !empty($source['last_checked']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $source['last_checked']) : __('Never checked', 'peestream-multihoster'); echo '<p><strong>' . esc_html(ucfirst($source['hoster'])) . ':</strong> ' . esc_html(ucfirst($source['status'])) . ' - ' . esc_html($when) . ' (' . esc_html(isset($source['retry_count']) ? (string) $source['retry_count'] : '0') . '/3 retries)</p>'; }
        self::row('__INDEX__', array('hoster'=>'peestream','url'=>'','label'=>'','status'=>'alive'), true);
    }
    private static function row($i, $source, $template = false) {
        $style = $template ? ' style="display:none" data-template="1"' : '';
        echo '<div class="psmh-row"' . $style . '><label>' . esc_html__('Hoster', 'peestream-multihoster') . ' <select name="psmh_sources[' . esc_attr($i) . '][hoster]">';
        foreach (PSMH_Meta::HOSTERS as $hoster) { echo '<option value="' . esc_attr($hoster) . '" ' . selected($source['hoster'], $hoster, false) . '>' . esc_html(ucfirst($hoster)) . '</option>'; }
        echo '</select></label> <button type="button" class="button psmh-up">↑</button> <button type="button" class="button psmh-down">↓</button> <label>' . esc_html__('URL or embed URL', 'peestream-multihoster') . ' <input class="large-text" type="url" name="psmh_sources[' . esc_attr($i) . '][url]" value="' . esc_attr($source['url']) . '" placeholder="https://"></label> <label>' . esc_html__('Label', 'peestream-multihoster') . ' <input type="text" name="psmh_sources[' . esc_attr($i) . '][label]" value="' . esc_attr($source['label']) . '"></label> <label>' . esc_html__('Status', 'peestream-multihoster') . ' <select name="psmh_sources[' . esc_attr($i) . '][status]">';
        foreach (PSMH_Meta::STATUSES as $status) { echo '<option value="' . esc_attr($status) . '" ' . selected($source['status'], $status, false) . '>' . esc_html(ucfirst($status)) . '</option>'; }
        echo '</select></label> <input type="hidden" data-order name="psmh_sources[' . esc_attr($i) . '][order]" value="' . esc_attr((string) (isset($source['order']) ? $source['order'] : $i)) . '"> <button type="button" class="button-link-delete psmh-remove">' . esc_html__('Remove', 'peestream-multihoster') . '</button><span class="psmh-order">' . esc_html((string) $i) . '</span></div>';
    }
    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
        if (!isset($_POST['_psmh_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_psmh_nonce'])), self::NONCE)) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }
        if (!in_array($post->post_type, get_post_types(array('public'=>true), 'names'), true)) { return; }
        $raw = isset($_POST['psmh_sources']) ? wp_unslash($_POST['psmh_sources']) : array();
        $sources = PSMH_Meta::sanitize_sources($raw);
        $previous = PSMH_Meta::get_sources($post_id);
        $internal = array();
        foreach ($previous as $old) { $internal[strtolower($old['hoster'] . '|' . $old['url'])] = $old; }
        foreach ($sources as $i => $source) { $key = strtolower($source['hoster'] . '|' . $source['url']); if (isset($internal[$key])) { foreach (array('last_checked','retry_count','next_check','last_http_code') as $field) { $sources[$i][$field] = isset($internal[$key][$field]) ? $internal[$key][$field] : 0; } } }
        if ($sources) { update_post_meta($post_id, PSMH_Meta::KEY, $sources); }
        else { delete_post_meta($post_id, PSMH_Meta::KEY); }
    }
    public static function assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) { return; }
        wp_add_inline_style('common', '.psmh-row{padding:12px;margin:8px 0;border:1px solid #ccd0d4;background:#fff}.psmh-row label{display:inline-block;vertical-align:top;margin:0 8px 8px 0}.psmh-row input[type=url]{min-width:320px}.psmh-frame{position:relative;aspect-ratio:16/9}.psmh-frame iframe,.psmh-frame video{position:absolute;width:100%;height:100%;inset:0;border:0}.psmh-sources{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap}.psmh-sources [aria-selected=true]{font-weight:bold}');
        wp_enqueue_script('psmh-admin', plugin_dir_url(PSMH_FILE) . 'admin/admin.js', array(), PSMH_VERSION, true);
    }
}
