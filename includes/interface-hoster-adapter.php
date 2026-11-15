<?php
if (!defined('ABSPATH')) { exit; }
interface PSMH_Hoster_Adapter {
    public function get_id();
    public function validate_credentials($credentials);
    public function check($url);
    public function fetch($url, $destination, $max_bytes);
    public function upload($file_path, $credentials, $title = '');
}
