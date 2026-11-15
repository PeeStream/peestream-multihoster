#!/usr/bin/env php
<?php
/**
 * Build a clean, deterministic-layout WordPress install ZIP.
 * Usage: php scripts/build-zip.php [output.zip]
 * Falls back to Python's standard-library zipfile when PHP ZipArchive is absent.
 */
$root = dirname(__DIR__);
$output = isset($argv[1]) ? $argv[1] : $root . '/dist/peestream-multihoster.zip';
$files = array(
    'peestream-multihoster.php',
    'README.md',
    'CHANGELOG.md',
    'admin/admin.js',
    'admin/class-credentials-admin.php',
    'admin/class-multihoster-admin.php',
    'includes/adapters/class-peestream-adapter.php',
    'includes/class-adapter-registry.php',
    'includes/class-credentials.php',
    'includes/class-healing-queue.php',
    'includes/class-healing-worker.php',
    'includes/class-multihoster-health.php',
    'includes/class-multihoster-meta.php',
    'includes/class-multihoster-player.php',
    'includes/interface-hoster-adapter.php',
);
$parent = dirname($output);
if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
    fwrite(STDERR, "ERROR: Cannot create output directory.\n");
    exit(2);
}
$prefix = 'peestream-multihoster/';
$added = array();
foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path) || strpos(realpath($path), realpath($root) . DIRECTORY_SEPARATOR) !== 0) {
        fwrite(STDERR, "ERROR: Missing or out-of-root package candidate: " . $relative . "\n");
        exit(2);
    }
    $added[] = $relative;
}

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $opened = $zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        fwrite(STDERR, "ERROR: Cannot open output ZIP (code " . $opened . ").\n");
        exit(2);
    }
    foreach ($added as $relative) {
        if (!$zip->addFile($root . '/' . $relative, $prefix . $relative)) {
            $zip->close();
            @unlink($output);
            fwrite(STDERR, "ERROR: Could not add package candidate: " . $relative . "\n");
            exit(2);
        }
    }
    $ok = $zip->close();
} else {
    $manifest = json_encode(array('root' => $root, 'output' => $output, 'prefix' => $prefix, 'files' => $added));
    $python = <<<'PY'
import json, sys, zipfile
data = json.loads(sys.argv[1])
with zipfile.ZipFile(data["output"], "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for rel in data["files"]:
        z.write(data["root"] + "/" + rel, data["prefix"] + rel)
PY;
    $command = 'python3 -c ' . escapeshellarg($python) . ' ' . escapeshellarg($manifest);
    exec($command, $ignored, $status);
    $ok = ($status === 0);
}
if (!$ok || !is_file($output) || filesize($output) === 0) {
    @unlink($output);
    fwrite(STDERR, "ERROR: ZIP finalization or verification failed.\n");
    exit(2);
}
echo "Created " . $output . " (" . filesize($output) . " bytes; " . count($added) . " allowlisted files).\n";
