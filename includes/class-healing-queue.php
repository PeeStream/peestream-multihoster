<?php
if (!defined('ABSPATH')) { exit; }
final class PSMH_Healing_Queue {
    const TABLE = 'psmh_healing_jobs';
    public static function table() { global $wpdb; return $wpdb->prefix . self::TABLE; }
    public static function install() {
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table=self::table();$charset=$wpdb->get_charset_collate();
        $sql="CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, job_key char(64) NOT NULL, post_id bigint(20) unsigned NOT NULL, source_key char(64) NOT NULL, source_url text NOT NULL, target_hoster varchar(32) NOT NULL, state varchar(20) NOT NULL DEFAULT 'queued', attempts smallint unsigned NOT NULL DEFAULT 0, next_attempt datetime NOT NULL, lease_until datetime NULL, file_code varchar(191) NULL, result_url text NULL, error_code varchar(64) NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY job_key (job_key), KEY due (state,next_attempt), KEY post_id (post_id)) $charset;";
        dbDelta($sql);
    }
    public static function enqueue($post_id,$target_source_key,$alternate_url,$target_hoster) {
        global $wpdb; $now=current_time('mysql',true);
        $job_key=hash('sha256',absint($post_id).'|'.$target_source_key.'|'.$target_hoster);
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO ".self::table()." (job_key,post_id,source_key,source_url,target_hoster,state,attempts,next_attempt,created_at,updated_at) VALUES (%s,%d,%s,%s,%s,'queued',0,%s,%s,%s)",$job_key,absint($post_id),$target_source_key,$alternate_url,sanitize_key($target_hoster),$now,$now,$now));
        return $job_key;
    }
    public static function claim($limit=5) {
        global $wpdb; $now=current_time('mysql',true);$lease=gmdate('Y-m-d H:i:s',time()+300);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::table()." WHERE (state IN ('queued','retry','polling') OR state='processing') AND next_attempt <= %s AND (lease_until IS NULL OR lease_until < %s) ORDER BY id ASC LIMIT %d",$now,$now,max(1,min(5,(int)$limit))),ARRAY_A);
        $claimed=array();
        foreach ((array)$rows as $row) {
            $updated=$wpdb->query($wpdb->prepare("UPDATE ".self::table()." SET state='processing',lease_until=%s,updated_at=%s WHERE id=%d AND (lease_until IS NULL OR lease_until < %s)",$lease,$now,$row['id'],$now));
            if ($updated) { $row['lease_until']=$lease;$claimed[]=$row; }
        }
        return $claimed;
    }
    public static function status_counts() {
        global $wpdb;
        $counts=array('pending'=>0,'processing'=>0,'polling'=>0,'failed'=>0,'healed'=>0);
        $rows=$wpdb->get_results("SELECT state, COUNT(*) AS total FROM ".self::table()." GROUP BY state",ARRAY_A);
        foreach((array)$rows as $row){$state=isset($row['state'])?$row['state']:'';$n=isset($row['total'])?(int)$row['total']:0;
            if(in_array($state,array('queued','retry'),true)){$counts['pending']+=$n;}
            elseif($state==='processing'){$counts['processing']+=$n;}
            elseif($state==='polling'){$counts['polling']+=$n;}
            elseif($state==='failed'){$counts['failed']+=$n;}
            elseif($state==='complete'){$counts['healed']+=$n;}
        }
        return $counts;
    }
    public static function update($id,$fields) {
        global $wpdb; $allowed=array('state','attempts','next_attempt','lease_until','file_code','result_url','error_code');$data=array();
        foreach($allowed as $key){if(array_key_exists($key,$fields)){$data[$key]=$fields[$key];}}
        $data['updated_at']=current_time('mysql',true);
        if($data){$wpdb->update(self::table(),$data,array('id'=>absint($id)));}
    }
}
