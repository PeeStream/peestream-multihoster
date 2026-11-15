<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Healing_Worker {
    const HOOK='psmh_healing_batch'; const BATCH=3; const MAX_BYTES=2147483648;
    public static function init(){add_action(self::HOOK,array(__CLASS__,'run_batch'));add_action('psmh_health_batch',array(__CLASS__,'enqueue_dead_targets'),20);}
    public static function enqueue_dead_targets(){
        if(get_option('psmh_healing_enabled','no')!=='yes'){return 0;}
        $offset=max(0,(int)get_option('psmh_healing_scan_offset',0));$ids=get_posts(array('post_type'=>'any','post_status'=>'publish','posts_per_page'=>50,'offset'=>$offset,'fields'=>'ids','meta_key'=>PSMH_Meta::KEY,'no_found_rows'=>true));update_option('psmh_healing_scan_offset',count($ids)<50?0:$offset+50,false);$n=0;
        foreach((array)$ids as $post_id){$sources=PSMH_Meta::get_sources($post_id);$alive=array_values(array_filter($sources,static function($s){return $s['status']==='alive';}));if(!$alive)continue;
            foreach($sources as $target){if($target['status']!=='dead'||$target['hoster']!=='peestream')continue;
                foreach($alive as $alt){if($alt['url']===$target['url'])continue;$key=hash('sha256',$target['hoster'].'|'.$target['url']);PSMH_Healing_Queue::enqueue($post_id,$key,$alt['url'],'peestream');$n++;break;}
            }
        }return $n;
    }
    public static function run_batch($force=false){
        if(!$force && get_option('psmh_healing_enabled','no')!=='yes'){return 0;}
        $jobs=PSMH_Healing_Queue::claim(self::BATCH);$done=0;
        foreach($jobs as $job){
            try{$r=self::process($job);
                if(!empty($r['ready'])){self::replace_post_source($job,$r);PSMH_Healing_Queue::update($job['id'],array('state'=>'complete','file_code'=>$r['file_code'],'result_url'=>$r['embed'],'lease_until'=>null,'error_code'=>null));}
                elseif(!empty($r['failed'])){self::retry_or_fail($job,$r['code']);}
                else{PSMH_Healing_Queue::update($job['id'],array('state'=>'polling','file_code'=>$r['file_code'],'next_attempt'=>gmdate('Y-m-d H:i:s',time()+120),'lease_until'=>null));}
            }catch(Exception $e){self::retry_or_fail($job,'worker_exception');}
            $done++;
        }
        return $done;
    }
    public static function process($job){
        if(!empty($job['file_code'])){return self::poll_job($job);}
        $sources=PSMH_Meta::get_sources((int)$job['post_id']);$alternative=null;
        foreach($sources as $s){if($s['status']==='alive' && $s['url']===$job['source_url']){$alternative=$s;break;}}
        if(!$alternative){return array('failed'=>true,'code'=>'alternate_source_missing');}
        $credentials=PSMH_Credentials::get('peestream');if(!$credentials){return array('failed'=>true,'code'=>'credentials_missing');}
        $instance=PSMH_PeeStream_Adapter::instance_url();if(!$instance){return array('failed'=>true,'code'=>'instance_not_configured');}
        $adapter=PSMH_Adapter_Registry::get('peestream');
        $submitted=$adapter->submit_remote_url($alternative['url'],$credentials,0,get_the_title((int)$job['post_id']));
        if(empty($submitted['ok'])){return array('failed'=>true,'code'=>isset($submitted['code'])?$submitted['code']:'remote_ingest_failed');}
        $job['file_code']=$submitted['file_code'];return self::poll_job($job);
    }
    private static function poll_job($job){
        $credentials=PSMH_Credentials::get('peestream');$adapter=PSMH_Adapter_Registry::get('peestream');
        $result=$adapter->file_info($job['file_code'],$credentials);
        if(empty($result['ok'])){return array('failed'=>!empty($result['terminal']),'code'=>isset($result['code'])?$result['code']:'poll_error','file_code'=>$job['file_code']);}
        if($result['state']==='ready' && !empty($result['embed'])){return array('ready'=>true,'file_code'=>$job['file_code'],'embed'=>$result['embed']);}
        if($result['state']==='failed'){return array('failed'=>true,'code'=>'processing_failed','file_code'=>$job['file_code']);}
        return array('pending'=>true,'file_code'=>$job['file_code']);
    }
    private static function retry_or_fail($job,$code){$attempts=(int)$job['attempts']+1;$terminal=$attempts>=5;PSMH_Healing_Queue::update($job['id'],array('state'=>$terminal?'failed':'retry','attempts'=>$attempts,'next_attempt'=>gmdate('Y-m-d H:i:s',time()+min(21600,60*(2**$attempts))),'lease_until'=>null,'error_code'=>sanitize_key($code),'file_code'=>$code==='processing_failed'?null:(isset($job['file_code'])?$job['file_code']:null)));}
    private static function replace_post_source($job,$result){
        $sources=PSMH_Meta::get_sources((int)$job['post_id']);$updated=false;
        foreach($sources as $i=>$s){$key=hash('sha256',$s['hoster'].'|'.$s['url']);if($s['hoster']===$job['target_hoster'] && $key===$job['source_key'] && $s['status']==='dead'){$sources[$i]['url']=esc_url_raw($result['embed'],array('https'));$sources[$i]['status']='alive';$sources[$i]['retry_count']=0;$sources[$i]['next_check']=time()+3600;$sources[$i]['last_checked']=time();$updated=true;break;}}
        if($updated){update_post_meta((int)$job['post_id'],PSMH_Meta::KEY,$sources);}
    }
}
