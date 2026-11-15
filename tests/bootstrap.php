<?php
// Lightweight WordPress-function stubs for deterministic unit tests; no production dependencies.
$GLOBALS['psmh_meta'] = array(); $GLOBALS['psmh_actions'] = array(); $GLOBALS['psmh_filters'] = array(); $GLOBALS['psmh_shortcodes'] = array();
function add_action($hook,$cb){$GLOBALS['psmh_actions'][$hook][]=$cb;} function add_filter($hook,$cb,$p=10){$GLOBALS['psmh_filters'][$hook][]=$cb;}
function add_shortcode($tag,$cb){$GLOBALS['psmh_shortcodes'][$tag]=$cb;} function plugin_dir_path($f){return dirname($f).'/';}
function register_activation_hook($f,$cb){} function register_post_meta($a,$b,$c){} function is_admin(){return false;}
function get_post_meta($id,$key,$single=true){return isset($GLOBALS['psmh_meta'][$id][$key])?$GLOBALS['psmh_meta'][$id][$key]:'';}
function update_post_meta($id,$key,$value){$GLOBALS['psmh_meta'][$id][$key]=$value;} function delete_post_meta($id,$key){unset($GLOBALS['psmh_meta'][$id][$key]);}
function add_meta_box(){} function wp_nonce_field(){} function selected($a,$b,$echo=true){return (string)$a===(string)$b?'selected="selected"':'';}
function esc_attr__($s,$domain=null){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} function esc_url_raw($s,$p=array()){return filter_var($s,FILTER_VALIDATE_URL)?$s:'';}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function __($s){return $s;} function esc_html__($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');} function absint($n){return abs((int)$n);}
function shortcode_atts($pairs,$atts){return array_merge($pairs,(array)$atts);} function wp_oembed_get($u,$args=array()){return false;}
function is_singular(){return false;} function in_the_loop(){return false;} function is_main_query(){return false;} function get_the_ID(){return 1;}
function current_user_can(){return true;} function get_post_types($args=array(),$output='names'){return array('post','page');} function wp_verify_nonce(){return true;}
function wp_unslash($v){return $v;} function wp_is_post_revision(){return false;} function wp_is_post_autosave(){return false;}
function is_string_safe_for_test($v){return is_string($v);}

function plugin_dir_url($f){return 'https://example.test/wp-content/plugins/peestream-multihoster/';} function wp_enqueue_script(){} function wp_add_inline_style(){}

$GLOBALS['psmh_transients']=array(); $GLOBALS['psmh_http_queue']=array(); $GLOBALS['psmh_posts']=array();
function wp_next_scheduled(){return false;} function wp_schedule_event(){} function wp_clear_scheduled_hook(){}
function get_transient($k){return isset($GLOBALS['psmh_transients'][$k])?$GLOBALS['psmh_transients'][$k]:false;} function set_transient($k,$v,$t){$GLOBALS['psmh_transients'][$k]=$v;} function delete_transient($k){unset($GLOBALS['psmh_transients'][$k]);}
function get_posts($args){return array_keys($GLOBALS['psmh_posts']);} function wp_safe_remote_request($url,$args){$GLOBALS['psmh_last_http_args']=$args;return array('response'=>array('code'=>array_shift($GLOBALS['psmh_http_queue'])?:200));}
function is_wp_error($v){return $v instanceof WP_Error;} function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;} function wp_nonce_url($url,$action){return $url.'&_wpnonce=test';} function wp_safe_redirect($url){} function check_admin_referer(){} function wp_die($m,$c=null){} function date_i18n($f,$t){return date($f,$t);}
if (!class_exists('WP_Error')) { class WP_Error {} }


$GLOBALS['psmh_options']=array(); function get_option($k,$default=false){return array_key_exists($k,$GLOBALS['psmh_options'])?$GLOBALS['psmh_options'][$k]:$default;} function update_option($k,$v,$autoload=null){$GLOBALS['psmh_options'][$k]=$v;return true;}

$GLOBALS['psmh_api_queue']=array(); function untrailingslashit($v){return rtrim($v,'/');} function add_query_arg($args,$url){$q=array();foreach($args as $k=>$v)$q[$k]=$v;return $url.'?'.http_build_query($q);}
function wp_safe_remote_get($url,$args){$GLOBALS['psmh_last_api_url']=$url;$x=array_shift($GLOBALS['psmh_api_queue']);return array('response'=>array('code'=>$x[0]),'body'=>json_encode($x[1]));} function wp_remote_retrieve_body($r){return $r['body'];} function wp_parse_url($u,$c=-1){return parse_url($u,$c);}

function wp_http_validate_url($url){$h=parse_url($url,PHP_URL_HOST);return filter_var($h,FILTER_VALIDATE_IP)?(filter_var($h,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false):($h && !in_array(strtolower($h),array("localhost","localhost.localdomain"),true));} function checked($a,$b,$echo=true){return (string)$a===(string)$b?"checked=\"checked\"":"";} function get_current_user_id(){return 1;}

function get_the_title($id){return "Test title";}

function current_time($type,$gmt=false){return $type==='timestamp'?time():'2026-11-14 12:00:00';}
