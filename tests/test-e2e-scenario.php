<?php
/**
 * Offline integration scenario for the healing worker. No WordPress install,
 * outbound network, or real credentials are used.
 */
require __DIR__ . '/bootstrap.php';
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('PSMH_VERSION', '0.3.3');
define('LOGGED_IN_KEY', 'offline-e2e-key');
define('NONCE_KEY', 'offline-e2e-nonce');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

foreach (array(
    'includes/class-multihoster-meta.php',
    'includes/interface-hoster-adapter.php',
    'includes/class-credentials.php',
    'includes/class-adapter-registry.php',
    'includes/adapters/class-peestream-adapter.php',
    'includes/class-healing-queue.php',
    'includes/class-healing-worker.php',
) as $file) {
    require dirname(__DIR__) . '/' . $file;
}

final class E2EQueueDB {
    public $prefix = 'wp_';
    public $update_data = array();
    public $job;
    public function prepare($sql, ...$args) { return $sql; }
    public function query($sql) { return 1; }
    public function get_results($sql, $mode) {
        return strpos($sql, 'GROUP BY state') !== false ? array() : array($this->job);
    }
    public function update($table, $data, $where) { $this->update_data = $data; return 1; }
}

final class E2EMockAdapter implements PSMH_Hoster_Adapter {
    private $mode;
    public $calls = array();
    public function __construct($mode) { $this->mode = $mode; }
    public function get_id() { return 'peestream'; }
    public function validate_credentials($credentials) { return array('ok' => true); }
    public function check($url) { return array('kind' => 'alive'); }
    public function fetch($url, $destination, $max_bytes) { return array('ok' => false); }
    public function upload($file_path, $credentials, $title = '') { return array('ok' => false); }
    public function submit_remote_url($url, $credentials, $folder_id = 0, $title = '') {
        $this->calls[] = array('submit', $url);
        if ($this->mode === 'rate_limit') { return array('ok' => false, 'code' => 'http_429'); }
        if ($this->mode === 'invalid_url') { return array('ok' => false, 'code' => 'invalid_input'); }
        if ($this->mode === 'timeout') { return array('ok' => false, 'code' => 'network_timeout'); }
        return array('ok' => true, 'file_code' => 'mock-file-1');
    }
    public function file_info($filecode, $credentials) {
        $this->calls[] = array('poll', $filecode);
        return array('ok' => true, 'state' => 'ready', 'embed' => 'https://peestream.example/embed/mock-file-1');
    }
}

function e2e_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
    echo "PASS: " . $message . "\n";
}
function e2e_reset($mode) {
    $db = new E2EQueueDB();
    $db->job = array(
        'id' => 91, 'post_id' => 9,
        'source_key' => hash('sha256', 'peestream|https://dead.example/old'),
        'source_url' => 'https://backup.example/good', 'target_hoster' => 'peestream',
        'state' => 'queued', 'attempts' => 0, 'file_code' => null,
    );
    $GLOBALS['wpdb'] = $db;
    $GLOBALS['psmh_meta'][9] = array(PSMH_Meta::KEY => array(
        array('hoster' => 'peestream', 'url' => 'https://dead.example/old', 'status' => 'dead', 'order' => 0),
        array('hoster' => 'voe', 'url' => 'https://backup.example/good', 'status' => 'alive', 'order' => 1),
    ));
    $GLOBALS['psmh_options']['psmh_healing_enabled'] = 'yes';
    $GLOBALS['psmh_options']['psmh_peestream_instance_url'] = 'https://peestream.example';
    PSMH_Credentials::save('peestream', 'offline-only-secret');
    $adapter = new E2EMockAdapter($mode);
    PSMH_Adapter_Registry::register($adapter);
    return array($db, $adapter);
}

list($db, $adapter) = e2e_reset('success');
e2e_assert(PSMH_Healing_Worker::run_batch() === 1, 'worker consumed the queued job');
$sources = PSMH_Meta::get_sources(9);
e2e_assert($sources[0]['url'] === 'https://peestream.example/embed/mock-file-1' && $sources[0]['status'] === 'alive', 'ready mock ingest replaces dead primary source');
e2e_assert($db->update_data['state'] === 'complete' && count($adapter->calls) === 2, 'remote ingest and ready polling complete the job');

foreach (array('rate_limit', 'invalid_url', 'timeout') as $mode) {
    list($db, $adapter) = e2e_reset($mode);
    PSMH_Healing_Worker::run_batch();
    e2e_assert($db->update_data['state'] === 'retry', $mode . ' is retryable and does not loop synchronously');
    e2e_assert($db->update_data['attempts'] === 1 && $db->update_data['lease_until'] === null, $mode . ' increments retry count and releases lease');
    e2e_assert(isset($db->update_data['next_attempt']), $mode . ' schedules bounded backoff for a later run');
    e2e_assert(PSMH_Meta::get_sources(9)[0]['url'] === 'https://dead.example/old', $mode . ' does not mutate the post source');
}
echo "Offline E2E scenario passed.\n";
