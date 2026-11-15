<?php
require __DIR__ . '/bootstrap.php';
define('ABSPATH',__DIR__.'/'); define('LOGGED_IN_KEY','unit-test-only-logged-key'); define('NONCE_KEY','unit-test-only-nonce-key');
require dirname(__DIR__).'/includes/class-multihoster-meta.php';
require dirname(__DIR__).'/includes/interface-hoster-adapter.php';
require dirname(__DIR__).'/includes/class-credentials.php';
require dirname(__DIR__).'/includes/class-adapter-registry.php';
require dirname(__DIR__).'/includes/adapters/class-peestream-adapter.php';
function credential_check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: $msg\n");exit(1);}echo "PASS: $msg\n";}
$GLOBALS['psmh_options']=array();
$encrypted=PSMH_Credentials::encrypt('secret-token-test');
credential_check(strpos($encrypted,'secret-token-test')===false,'ciphertext does not contain plaintext secret');
credential_check(PSMH_Credentials::decrypt($encrypted)==='secret-token-test','encrypted secret decrypts with configured site keys');
try { PSMH_Credentials::decrypt(substr($encrypted,0,-4).'AAAA'); credential_check(false,'tampered ciphertext rejected'); } catch (RuntimeException $e) { credential_check(true,'tampered ciphertext rejected'); }
PSMH_Credentials::save('peestream','unit-secret');
credential_check(PSMH_Credentials::get('peestream')==='unit-secret','credential store round-trips secret');
credential_check(strpos(serialize($GLOBALS['psmh_options']), 'unit-secret')===false,'database option contains ciphertext only');
PSMH_Credentials::delete('peestream'); credential_check(PSMH_Credentials::get('peestream')===null,'credential can be removed');
$adapter=new PSMH_PeeStream_Adapter(); PSMH_Adapter_Registry::register($adapter);
credential_check(PSMH_Adapter_Registry::get('peestream')===$adapter,'adapter registry resolves PeeStream adapter');
credential_check($adapter->submit_remote_url('https://source.example/v','secret')['code']==='instance_not_configured','adapter refuses network operation without configured instance');
echo "Credential and adapter tests passed.\n";
