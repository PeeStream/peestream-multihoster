<?php
require __DIR__ . '/bootstrap.php';
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/class-multihoster-meta.php';
require dirname(__DIR__) . '/includes/class-multihoster-player.php';
function check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n";}
$input=array(
 array('hoster'=>'peestream','url'=>'https://peestream.example/embed/abc','label'=>'Primary','order'=>1,'status'=>'alive'),
 array('hoster'=>'voe','url'=>'https://voe.example/e/xyz','label'=>'Backup','order'=>0,'status'=>'alive'),
 array('hoster'=>'doodstream','url'=>'javascript:alert(1)','status'=>'alive'),
 array('hoster'=>'unknown','url'=>'https://unknown.example/x'),
 array('hoster'=>'mixdrop','url'=>'https://mixdrop.example/e/1','status'=>'unknown'),
);
$sources=PSMH_Meta::sanitize_sources($input);
check(count($sources)===3,'valid source rows retained; invalid protocol/hoster rejected');
check($sources[0]['order']===0 && $sources[1]['order']===1,'source list receives contiguous stable order');
check($sources[2]['status']==='alive','invalid status defaults to alive');
check($sources[0]['label']==='Backup','submitted source ordering retained');
$GLOBALS['psmh_meta'][5][PSMH_Meta::KEY]=$sources;
check(count(PSMH_Meta::get_sources(5))===3,'source metadata can be read back');
$html=PSMH_Player::render(5);
check(strpos($html,'data-psmh-player')!==false,'player wrapper renders');
check(strpos($html,'Backup')!==false && strpos($html,'Primary')!==false,'multi-source selector renders labels');
check(strpos($html,'javascript:')===false,'unsafe URL is absent from rendered output');
$GLOBALS['psmh_meta'][6][PSMH_Meta::KEY]=array(array('hoster'=>'voe','url'=>'https://voe.example/embed/1','status'=>'dead'));
check(PSMH_Player::render(6)==='','dead-only sources do not render');
echo "All tests passed.\n";
