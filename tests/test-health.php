<?php
require __DIR__ . '/bootstrap.php';
define('ABSPATH', __DIR__ . '/');
define('PSMH_VERSION','0.2.0');
define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
require dirname(__DIR__) . '/includes/class-multihoster-meta.php';
require dirname(__DIR__) . '/includes/class-multihoster-health.php';
function health_check($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL: $msg\n");exit(1);}echo "PASS: $msg\n";}
$now=100000; $s=array('status'=>'alive','retry_count'=>0,'url'=>'https://host.example/video');
$r=PSMH_Health::apply_result($s,array('kind'=>'dead','code'=>404),$now); health_check($r['status']==='dead','404 marks source dead immediately');
$r=PSMH_Health::apply_result($s,array('kind'=>'dead','code'=>410),$now); health_check($r['status']==='dead','410 marks source dead immediately');
$r=PSMH_Health::apply_result($s,array('kind'=>'transient','code'=>429),$now); health_check($r['status']==='checking' && $r['retry_count']===1 && $r['next_check']===$now+900,'429 remains checking and schedules first backoff');
$r=PSMH_Health::apply_result(array_merge($s,array('retry_count'=>1)),array('kind'=>'transient','code'=>503),$now); health_check($r['status']==='checking' && $r['next_check']===$now+1800,'second transient failure backs off');
$r=PSMH_Health::apply_result(array_merge($s,array('retry_count'=>2)),array('kind'=>'transient','code'=>0),$now); health_check($r['status']==='dead' && $r['retry_count']===3,'third consecutive transient failure marks dead');
$r=PSMH_Health::apply_result(array_merge($s,array('retry_count'=>2)),array('kind'=>'alive','code'=>200),$now); health_check($r['status']==='alive' && $r['retry_count']===0,'success restores alive and resets failures');
$GLOBALS['psmh_http_queue']=array(405,206); $probe=PSMH_Health::probe('https://host.example/video');
health_check($probe['kind']==='alive' && $probe['code']===206,'HEAD 405 falls back to ranged GET');
health_check($GLOBALS['psmh_last_http_args']['method']==='GET' && $GLOBALS['psmh_last_http_args']['headers']['Range']==='bytes=0-1024','fallback GET is range-limited');
$GLOBALS['psmh_http_queue']=array(429); $probe=PSMH_Health::probe('https://host.example/video'); health_check($probe['kind']==='transient','429 is not a dead-link verdict');
$GLOBALS['psmh_meta'][12]=array(PSMH_Meta::KEY=>array(array('hoster'=>'voe','url'=>'https://host.example/a','label'=>'A','order'=>0,'status'=>'alive','retry_count'=>0,'next_check'=>0))); $GLOBALS['psmh_posts'][12]=true;
$GLOBALS['psmh_http_queue']=array(404); $count=PSMH_Health::run_batch();
health_check($count===1,'cron worker processes due URL');
health_check(PSMH_Meta::get_sources(12)[0]['status']==='dead','cron worker persists health result');
$GLOBALS['psmh_transients']['psmh_health_lock']=1; health_check(PSMH_Health::run_batch()===0,'lock prevents overlapping batches');
echo "Health tests passed.\n";
