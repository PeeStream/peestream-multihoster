<?php
/**
 * Plugin Name: PeeStream Multi-Hoster
 * Description: Manage multiple manually supplied video sources per post and render a source-switching player.
 * Version: 0.3.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: peestream-multihoster
 */
if (!defined('ABSPATH')) { exit; }
define('PSMH_VERSION', '0.3.4');
define('PSMH_FILE', __FILE__);
define('PSMH_DIR', plugin_dir_path(__FILE__));
require_once PSMH_DIR . 'includes/class-multihoster-meta.php';
require_once PSMH_DIR . 'includes/class-multihoster-player.php';
require_once PSMH_DIR . 'includes/class-multihoster-health.php';
require_once PSMH_DIR . 'includes/interface-hoster-adapter.php';
require_once PSMH_DIR . 'includes/class-credentials.php';
require_once PSMH_DIR . 'includes/class-adapter-registry.php';
require_once PSMH_DIR . 'includes/class-healing-queue.php';
require_once PSMH_DIR . 'includes/class-healing-worker.php';
require_once PSMH_DIR . 'includes/adapters/class-peestream-adapter.php';
require_once PSMH_DIR . 'admin/class-multihoster-admin.php';
require_once PSMH_DIR . 'admin/class-credentials-admin.php';
register_activation_hook(__FILE__, array('PSMH_Meta', 'activate'));
register_activation_hook(__FILE__, array('PSMH_Health', 'activate'));
register_activation_hook(__FILE__, array('PSMH_Healing_Queue', 'install'));
register_activation_hook(__FILE__, static function(){ if (!wp_next_scheduled(PSMH_Healing_Worker::HOOK)) { wp_schedule_event(time()+600, 'hourly', PSMH_Healing_Worker::HOOK); } });
register_deactivation_hook(__FILE__, array('PSMH_Health', 'deactivate'));
register_deactivation_hook(__FILE__, static function(){ wp_clear_scheduled_hook(PSMH_Healing_Worker::HOOK); });
add_action('plugins_loaded', static function () {
    PSMH_Meta::init();
    PSMH_Player::init();
    PSMH_Health::init();
    PSMH_Adapter_Registry::register(new PSMH_PeeStream_Adapter());
    PSMH_Healing_Worker::init();
    if (is_admin()) { PSMH_Admin::init(); PSMH_Credentials_Admin::init(); }
});
