<?php
/** Test the security boundary and exact-ID deletion flow WITHOUT WordPress or touching files. */
define('ABSPATH', '/test/');
define('MINUTE_IN_SECONDS',60);
define('EMPTY_TRASH_DAYS',30);
define('MEDIA_TRASH', in_array('--trash', $argv, true));
$GLOBALS['db'] = array(
  11 => (object) array('ID'=>11,'post_type'=>'attachment','post_status'=>'inherit'),
  12 => (object) array('ID'=>12,'post_type'=>'attachment','post_status'=>'inherit'),
  13 => (object) array('ID'=>13,'post_type'=>'attachment','post_status'=>'inherit'),
  44 => (object) array('ID'=>44,'post_type'=>'post','post_status'=>'publish')
);
$GLOBALS['cap'] = true;
$GLOBALS['transients'] = array('mediarefinspector_scan_status_11'=>array('status'=>'referenced','count'=>4));
$GLOBALS['calls']=array();
class WP_Post { public $ID; public $post_type; public $post_status; }
class JSON_Reply extends Exception { public $success; public $data; public $code; public function __construct($success,$data,$code=200){ parent::__construct('', $code);$this->success=$success;$this->data=$data;$this->code=$code;} }
function __( $v, $d = '' ) { return $v; }
function _n($one,$many,$count,$d=''){return $count===1?$one:$many;}
function absint($i){return abs((int)$i);}
function sanitize_text_field($v){return is_scalar($v)?strip_tags((string)$v):'';}
function map_deep($v,$callback){ if(is_array($v)){return array_map(function($x)use($callback){return map_deep($x,$callback);},$v);}return call_user_func($callback,$v); }
function wp_unslash($v){return $v;}
function wp_strip_all_tags($v){return strip_tags($v);}
function get_current_user_id(){return 1;}
function get_post($id){if(!isset($GLOBALS['db'][$id])) return null;$src=$GLOBALS['db'][$id];$post=new WP_Post();$post->ID=$src->ID;$post->post_type=$src->post_type;$post->post_status=$src->post_status;return $post;}
function get_the_title($id){return 'Sample '.$id;}
function get_post_mime_type($id){return 'image/jpeg';}
function current_user_can($cap,$id=0){return $GLOBALS['cap'] && $id!==13;}
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$value,$exp){$GLOBALS['transients'][$key]=$value;return true;}
function delete_transient($key){unset($GLOBALS['transients'][$key]);return true;}
function wp_generate_password($length,$special=false,$extra=false){return str_repeat('A',$length);}
function check_ajax_referer($action,$field){if(($_POST[$field]??'')!=='valid')throw new JSON_Reply(false,array('message'=>'nonce reject'),403);return true;}
function wp_send_json_success($data=null){throw new JSON_Reply(true,$data);}
function wp_send_json_error($data=null,$code=200){throw new JSON_Reply(false,$data,$code);}
function wp_delete_attachment($id,$force){$GLOBALS['calls'][]=array($id,$force);if(MEDIA_TRASH){$GLOBALS['db'][$id]->post_status='trash';} else {unset($GLOBALS['db'][$id]);}return (object)array('ID'=>$id);}
require dirname(__DIR__).'/includes/class-mediarefinspector-plugin.php';
$p=(new ReflectionClass('MediaRefInspector_Plugin'))->newInstanceWithoutConstructor();
function call_action($method,$params){global $p; $_POST=array_merge(array('nonce'=>'valid'),$params);try{$p->$method();throw new Exception('Unexpected method return');}catch(JSON_Reply $e){return $e;}}
function okay($yes,$label){if(!$yes)throw new RuntimeException('FAILED: '.$label);echo 'PASS: '.$label."\n";}
$result=call_action('ajax_delete_preview',array('nonce'=>'invalid','ids'=>array(11)));
okay(!$result->success && $result->code===403,'CSRF token required');
foreach (array(array('11junk'), array('-11'), array('0'), array(array(11)), array('9999999999999999999999999999999999')) as $invalid_ids) {
    $result=call_action('ajax_delete_preview',array('ids'=>$invalid_ids));
    okay(!$result->success && $result->code===400, 'malformed input IDs rejected');
}
$result=call_action('ajax_delete_preview',array('ids'=>array(11,44)));
okay(!$result->success,'non-attachment refused');
$result=call_action('ajax_delete_preview',array('ids'=>array(13)));
okay(!$result->success,'attachment-specific deletion permission required');
$result=call_action('ajax_delete_preview',array('ids'=>array(11,12,11)));
okay($result->success && count($result->data['items'])===2, 'IDs validated and de-duplicated');
okay($result->data['items'][0]['status']==='referenced' && $result->data['items'][0]['knownReferences']===4,'reference warning from cached scan');
okay($result->data['items'][1]['status']==='not_scanned','unknown status is not auto labeled unused');
$token=$result->data['token'];
$result=call_action('ajax_delete_confirm',array('token'=>$token,'confirmation'=>'DELETE','acknowledge'=>'0'));
okay(!$result->success && !count($GLOBALS['calls']),'acknowledgement required');
$result=call_action('ajax_delete_confirm',array('token'=>$token,'confirmation'=>'delete','acknowledge'=>'1'));
okay(!$result->success && !count($GLOBALS['calls']),'uppercase typed authorization required');
$result=call_action('ajax_delete_confirm',array('token'=>$token,'confirmation'=>'DELETE','acknowledge'=>'1','ids'=>array(13)));
okay($result->success && $result->data['removed']===2,'only reviewed IDs removed');
okay(count($GLOBALS['calls'])===2 && $GLOBALS['calls'][0][0]===11 && $GLOBALS['calls'][1][0]===12,'server-side original selection enforced');
okay(isset($GLOBALS['db'][13]) && (MEDIA_TRASH ? $GLOBALS['db'][11]->post_status==='trash' : !isset($GLOBALS['db'][11])),'correct Trash/permanent behavior');
$result=call_action('ajax_delete_confirm',array('token'=>$token,'confirmation'=>'DELETE','acknowledge'=>'1'));
okay(!$result->success && count($GLOBALS['calls'])===2,'one-time token prevents replay');
okay(!isset($GLOBALS['transients']['mediarefinspector_scan_status_11']),'deleted media scan cache invalidated');
echo 'ALL DELETION TESTS PASSED (mode: '.(MEDIA_TRASH?'trash':'permanent').")\n";
