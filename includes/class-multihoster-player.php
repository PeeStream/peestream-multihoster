<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Player {
    public static function init() { add_filter('the_content', array(__CLASS__, 'append_player'), 20); }
    public static function append_player($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) { return $content; }
        $post_id = get_the_ID();
        if (!$post_id || !PSMH_Meta::get_sources($post_id)) { return $content; }
        return $content . self::render($post_id);
    }
    public static function shortcode($atts) {
        $atts = shortcode_atts(array('post_id' => get_the_ID()), $atts, 'peestream_sources');
        return self::render(absint($atts['post_id']));
    }
    public static function render($post_id) {
        $sources = array_values(array_filter(PSMH_Meta::get_sources($post_id), static function ($s) { return $s['status'] === 'alive'; }));
        if (!$sources) { return ''; }
        $id = 'psmh-player-' . absint($post_id);
        $first = $sources[0];
        $html = '<section class="psmh-player" id="' . esc_attr($id) . '" data-psmh-player>';
        if (count($sources) > 1) {
            $html .= '<div class="psmh-sources" role="tablist" aria-label="' . esc_attr__('Video sources', 'peestream-multihoster') . '">';
            foreach ($sources as $i => $source) {
                $label = $source['label'] !== '' ? $source['label'] : ucfirst($source['hoster']);
                $html .= '<button type="button" role="tab" aria-selected="' . ($i === 0 ? 'true' : 'false') . '" data-source="' . esc_attr($source['url']) . '">' . esc_html($label) . '</button>';
            }
            $html .= '</div>';
        }
        $html .= self::embed($first['url'], $id . '-frame');
        $html .= '<noscript><a href="' . esc_url($first['url']) . '" rel="nofollow noopener" target="_blank">' . esc_html__('Open video source', 'peestream-multihoster') . '</a></noscript>';
        $html .= '</section>';
        if (count($sources) > 1) { $html .= self::script(); }
        return $html;
    }
    private static function embed($url, $id) {
        $provider = wp_oembed_get($url, array('width' => 1280, 'height' => 720));
        if ($provider) { return '<div class="psmh-frame" data-frame>' . $provider . '</div>'; }
        return '<div class="psmh-frame" data-frame><iframe id="' . esc_attr($id) . '" src="' . esc_url($url) . '" title="' . esc_attr__('Video player', 'peestream-multihoster') . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    }
    private static function script() {
        return '<script>(function(){document.querySelectorAll("[data-psmh-player]").forEach(function(root){if(root.dataset.bound)return;root.dataset.bound="1";var frame=root.querySelector("[data-frame]");root.querySelectorAll("[data-source]").forEach(function(button){button.addEventListener("click",function(){root.querySelectorAll("[role=tab]").forEach(function(tab){tab.setAttribute("aria-selected",tab===button?"true":"false")});var url=button.getAttribute("data-source");var iframe=frame.querySelector("iframe");if(iframe){iframe.src=url}else{frame.innerHTML="";var next=document.createElement("iframe");next.src=url;next.title="Video player";next.loading="lazy";next.referrerPolicy="strict-origin-when-cross-origin";next.allow="autoplay; fullscreen; picture-in-picture";next.allowFullscreen=true;frame.appendChild(next)}})})})})();</script>';
    }
}
