<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Adapter_Registry {
    private static $adapters = array();
    public static function register(PSMH_Hoster_Adapter $adapter) { self::$adapters[$adapter->get_id()] = $adapter; }
    public static function get($hoster) { return isset(self::$adapters[$hoster]) ? self::$adapters[$hoster] : null; }
    public static function all() { return self::$adapters; }
}
