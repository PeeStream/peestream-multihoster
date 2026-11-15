<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Credentials {
    const OPTION = 'psmh_encrypted_credentials';
    const PREFIX = 'psmh:v1:';
    public static function crypto_available() { return function_exists('sodium_crypto_secretbox') || (function_exists('openssl_encrypt') && function_exists('openssl_decrypt')); }
    private static function master_key() {
        $parts = array();
        foreach (array('LOGGED_IN_KEY','NONCE_KEY') as $name) { if (defined($name) && constant($name) !== '' && constant($name) !== 'put your unique phrase here') { $parts[] = constant($name); } }
        if (count($parts) !== 2) { throw new RuntimeException('Configure unique LOGGED_IN_KEY and NONCE_KEY values in wp-config.php.'); }
        return hash_hmac('sha256', implode('|', $parts), 'PSMH credential encryption v1', true);
    }
    public static function encrypt($plaintext) {
        if (!self::crypto_available()) { throw new RuntimeException('No supported authenticated encryption is available.'); }
        $key = self::master_key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return self::PREFIX . 's:' . base64_encode($nonce . sodium_crypto_secretbox((string)$plaintext, $nonce, $key));
        }
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt((string)$plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'psmh:v1', 16);
        if ($cipher === false || strlen($tag) !== 16) { throw new RuntimeException('Credential encryption failed.'); }
        return self::PREFIX . 'o:' . base64_encode($iv . $tag . $cipher);
    }
    public static function decrypt($payload) {
        if (!is_string($payload) || strpos($payload, self::PREFIX) !== 0) { throw new RuntimeException('Encrypted credential format is invalid.'); }
        $parts = explode(':', substr($payload, strlen(self::PREFIX)), 2);
        if (count($parts) !== 2) { throw new RuntimeException('Encrypted credential format is invalid.'); }
        $raw = base64_decode($parts[1], true); if ($raw === false) { throw new RuntimeException('Encrypted credential encoding is invalid.'); }
        $key = self::master_key();
        if ($parts[0] === 's' && function_exists('sodium_crypto_secretbox_open')) {
            if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { throw new RuntimeException('Encrypted credential is truncated.'); }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        } elseif ($parts[0] === 'o' && function_exists('openssl_decrypt')) {
            if (strlen($raw) < 28) { throw new RuntimeException('Encrypted credential is truncated.'); }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw,0,12), substr($raw,12,16), 'psmh:v1');
        } else { throw new RuntimeException('Credential encryption backend is unavailable.'); }
        if ($plain === false) { throw new RuntimeException('Credential decryption failed; key may have changed or data is corrupt.'); }
        return $plain;
    }
    public static function save($hoster, $secret) {
        $hoster = sanitize_key($hoster); if (!in_array($hoster, PSMH_Meta::HOSTERS, true)) { throw new InvalidArgumentException('Unsupported hoster.'); }
        if (!is_string($secret) || trim($secret) === '') { throw new InvalidArgumentException('Credential must not be empty.'); }
        $all = get_option(self::OPTION, array()); if (!is_array($all)) { $all=array(); }
        $all[$hoster] = self::encrypt($secret);
        if (!update_option(self::OPTION, $all, false)) { if (get_option(self::OPTION) !== $all) { throw new RuntimeException('Credential storage failed.'); } }
        return true;
    }
    public static function get($hoster) {
        $hoster = sanitize_key($hoster); $all = get_option(self::OPTION, array());
        if (!is_array($all) || empty($all[$hoster])) { return null; }
        return self::decrypt($all[$hoster]);
    }
    public static function delete($hoster) {
        $all = get_option(self::OPTION, array()); if (!is_array($all)) { return; }
        unset($all[sanitize_key($hoster)]); update_option(self::OPTION, $all, false);
    }
    public static function configured_hosters() {
        $all = get_option(self::OPTION, array()); return is_array($all) ? array_keys($all) : array();
    }
}
