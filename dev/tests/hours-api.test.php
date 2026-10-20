<?php
/** Standalone synthetic API contract/validation/security regressions. */
define('ABSPATH', dirname(__DIR__, 2) . '/');
$GLOBALS['options'] = []; $GLOBALS['caps'] = ['manage_options']; $GLOBALS['orders'] = []; $GLOBALS['bookings'] = [];
$GLOBALS['write_failure'] = false; $GLOBALS['locked'] = false; $GLOBALS['releases'] = 0;
class WP_Error { public function __construct(public $code, public $message, public $data = []) {} public function get_error_code(){return $this->code;} }
class WP_REST_Response { public $headers=[]; public function __construct(public $data, public $status=200){} public function header($k,$v){$this->headers[$k]=$v;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,...$a){if($GLOBALS['write_failure'])return false;$GLOBALS['options'][$k]=$v;return true;}
function apply_filters($t,$v){return $v;}
function add_action(...$a){} function add_filter(...$a){} function current_user_can($c){return in_array($c,$GLOBALS['caps'],true);}
function is_user_logged_in(){return count($GLOBALS['caps'])>0;} function wp_verify_nonce($v,$a){return $v==='valid'&&$a==='wp_rest';}
function wp_json_encode($v){return json_encode($v);} function wp_salt($s){return 'synthetic-salt';}
function wp_unslash($s){return $s;} function home_url($p=''){return 'http://fixture.invalid'.$p;}
function wp_die(...$a){throw new RuntimeException('denied');} function wp_safe_redirect($s){$GLOBALS['redirect']=$s;throw new RuntimeException('redirected');}
function add_query_arg($p,$url){return $url.'?'.http_build_query($p);}
function sanitize_text_field($s){return strip_tags($s);} function wp_cache_delete(...$a){} function clean_post_cache($id){}
function get_page_by_path($p){return null;} function get_current_blog_id(){return 1;} function do_action(...$a){}
function wc_get_orders($args){return $args['page']===1?$GLOBALS['orders']:[];}
function get_post_meta($id,$k,$single=true){return $GLOBALS['bookings'][$id][$k]??'';}
class WP_Query {public $posts; public function __construct($a){$this->posts=array_map(fn($id)=>(object)['ID'=>$id,'post_modified_gmt'=>'2026-10-20 10:00:00'],array_keys($GLOBALS['bookings']));}}
class TestDB {public $prefix='wp_';public function prepare($q,...$a){return $q;}public function get_var($q){if(str_contains($q,'RELEASE_LOCK')){$GLOBALS['releases']++;$GLOBALS['locked']=false;return 1;} if($GLOBALS['locked'])return 0; $GLOBALS['locked']=true;return 1;}}
$GLOBALS['wpdb']=new TestDB();
class Request implements ArrayAccess {public function __construct(public $body=[],public $nonce='valid'){}public function get_json_params(){return $this->body;}public function get_header($h){return $this->nonce;}public function offsetExists($o):bool{return isset($this->body[$o]);}public function offsetGet($o):mixed{return $this->body[$o]??null;}public function offsetSet($o,$v):void{$this->body[$o]=$v;}public function offsetUnset($o):void{unset($this->body[$o]);}}
class Order {public function __construct(public $id,public $meta,public $status='processing'){}public function get_id(){return $this->id;} public function get_status(){return $this->status;} public function get_meta($k,...$a){return $this->meta[$k]??'';}public function get_date_modified(){return new DateTimeImmutable('2026-10-20 12:00');}}
require ABSPATH.'inc/hours.php'; require ABSPATH.'inc/pickup.php';
$passed=0;$failed=0;
function check($label,$ok){global $passed,$failed;if($ok){$passed++;}else{$failed++;echo "FAIL $label\n";}}
if(!file_exists(ABSPATH.'inc/hours-api.php')){check('hours REST API exists',false);echo "$passed passed, $failed failed\n";exit(1);}
require ABSPATH.'inc/hours-api.php';
$today=wowthai_now()->setTime(0,0);$date=$today->modify('+2 days')->format('Y-m-d');$week=array_fill(1,7,[['11:00','14:00']]);
check('strict invalid date',is_wp_error(wowthai_hours_api_date('2026-02-30')));
check('strict whitespace date',is_wp_error(wowthai_hours_api_date(' 2026-10-20')));
check('future bounds',is_wp_error(wowthai_hours_api_availability(new Request(['date'=>$today->modify('+91 days')->format('Y-m-d')]))));
$before=$GLOBALS['options'];$schedule=wowthai_hours_api_schedule();check('read never seeds',$GLOBALS['options']===$before);check('manager editable',$schedule['can_edit']===true);
check('nonce enforced',is_wp_error(wowthai_hours_api_write_permission(new Request([],''))));
$GLOBALS['caps']=['edit_shop_orders'];check('staff cannot save',is_wp_error(wowthai_hours_api_write_permission(new Request())));check('staff can read',wowthai_hours_api_read_permission()===true);$GLOBALS['caps']=['manage_options'];
$draft=['kind'=>'weekly','revision'=>$schedule['revision'],'weekly'=>$week,'preview_date'=>$date];
$p=wowthai_hours_api_preview(new Request($draft));check('preview returns token',!is_wp_error($p)&&isset($p['confirmation_token']));check('preview pure',$GLOBALS['options']===$before);
check('preview uses draft ranges',$p['preview']['ranges']===[['11:00','14:00']]);check('generic pickup slots',$p['preview']['pickup_slots'][0]==='11:00');
$body=$draft+['confirmation_token'=>$p['confirmation_token']];$saved=wowthai_hours_api_save(new Request($body),'weekly');check('save returns revision',!is_wp_error($saved)&&$saved['revision']!==$schedule['revision']);check('save only resource',array_keys($GLOBALS['options'])===['wowthai_weekly_hours']);check('lock released',!$GLOBALS['locked']);
check('two writer stale rejection',wowthai_hours_api_save(new Request($body),'weekly')->code==='stale_schedule');
$draft['revision']=$saved['revision'];$p=wowthai_hours_api_preview(new Request($draft));$body=$draft+['confirmation_token'=>$p['confirmation_token']];$GLOBALS['write_failure']=true;check('identical save succeeds',!is_wp_error(wowthai_hours_api_save(new Request($body),'weekly')));
$draft['weekly'][1]=[];$p=wowthai_hours_api_preview(new Request($draft));$body=$draft+['confirmation_token'=>$p['confirmation_token']];check('failed options write fails closed',wowthai_hours_api_save(new Request($body),'weekly')->code==='hours_storage_failed');check('failure releases lock',!$GLOBALS['locked']);$GLOBALS['write_failure']=false;
$ex=['kind'=>'exceptions','revision'=>$saved['revision'],'exception'=>['start_date'=>$date,'end_date'=>$date,'mode'=>'closed','reason'=>'Fixture']];
$GLOBALS['orders']=[new Order(1,['Abholzeit'=>'Schnellstmöglich']),new Order(2,['_wowthai_pickup_date'=>$date,'_wowthai_pickup_time'=>'12:00']),new Order(3,['_wowthai_pickup_date'=>'2020-01-01','_wowthai_pickup_time'=>'12:00'])];
$GLOBALS['bookings']=[4=>['_res_date'=>$date,'_res_time'=>'12:00','_res_status'=>'confirmed'],5=>['_res_date'=>'bad','_res_time'=>'12:00']];
$p=wowthai_hours_api_preview(new Request($ex));check('all active service conflicts including unknown',count($p['conflicts'])===4);check('unknown ASAP explicit',$p['conflicts'][0]['unknown']===true);
$body=$ex+['confirmation_token'=>$p['confirmation_token']];check('requires explicit acknowledgement',wowthai_hours_api_save(new Request($body),'exceptions')->code==='hours_acknowledgement_required');
$GLOBALS['bookings'][4]['_res_time']='13:00';$body['acknowledge_conflicts']=true;check('changed conflict rejects token',wowthai_hours_api_save(new Request($body),'exceptions')->code==='stale_preview');
$p=wowthai_hours_api_preview(new Request($ex));$body['confirmation_token']=$p['confirmation_token'];check('acknowledged fresh save succeeds',!is_wp_error(wowthai_hours_api_save(new Request($body),'exceptions')));
check('exceptions persisted',!empty($GLOBALS['options']['wowthai_special_days'][$date]['closed']));
check('canonical German service label',wowthai_hours_api_parse_service('Dienstag, 20. Oktober 2026 um 12:15 Uhr')===['2026-10-20','12:15']);
check('invalid service label unknown',wowthai_hours_api_parse_service('Dienstag, 30. Februar 2026 um 12:15 Uhr')===null);
check('DST gap unknown',wowthai_hours_api_parse_service('', '2027-03-28','02:15')===null);
$impact_before=wowthai_hours_api_impact(['weekly'=>$week,'exceptions'=>[]]);
$GLOBALS['orders'][0]->meta['_wto_revision']='new fixture revision';
$impact_after=wowthai_hours_api_impact(['weekly'=>$week,'exceptions'=>[]]);
check('real Operations order revision changes signature',$impact_before['signature']!==$impact_after['signature']);
$impact_before=$impact_after;$GLOBALS['bookings'][4]['_res_party']=8;
check('booking party change changes signature',$impact_before['signature']!==wowthai_hours_api_impact(['weekly'=>$week,'exceptions'=>[]])['signature']);
$ex['revision']=wowthai_hours_api_schedule()['revision'];$ex['exception']['mode']='hours';$ex['exception']['ranges']=[['14:00','13:00']];check('invalid exception rejected',is_wp_error(wowthai_hours_api_preview(new Request($ex))));
$GLOBALS['locked']=true;check('lock contention unavailable',wowthai_hours_api_save(new Request($body),'exceptions')->code==='hours_busy');$GLOBALS['locked']=false;
$GLOBALS['options']['wowthai_weekly_hours']=array_fill(1,7,[]);
check('draft reopening Tuesday retains sushi',wowthai_compute_opening_status(new DateTimeImmutable('2026-10-20 12:00',wowthai_timezone()),$week,[])['is_sushi_day']===true);
check('draft Monday still no sushi',wowthai_compute_opening_status(new DateTimeImmutable('2026-10-19 12:00',wowthai_timezone()),$week,[])['is_sushi_day']===false);
check('canonical schema accepts pure draft',wowthai_schema_opening_hours($week)!==[]);
$before_legacy=$GLOBALS['options'];
require ABSPATH.'inc/restaurant-experience.php';
$GLOBALS['caps']=['edit_shop_orders'];
try { wowthai_handle_save_special_day(); check('legacy staff denied',false); } catch(RuntimeException $e) { check('legacy staff denied',$e->getMessage()==='denied'); }
$GLOBALS['caps']=['manage_options'];$_REQUEST=['sd_date'=>$date,'sd_mode'=>'remove','sd_reason'=>'Fixture prefill'];
try { wowthai_handle_save_special_day(); } catch(RuntimeException $e) { check('legacy manager redirected',$e->getMessage()==='redirected'&&str_contains($GLOBALS['redirect'],'view=hours')&&str_contains($GLOBALS['redirect'],'hours_mode=remove')); }
check('legacy handler never mutates',$GLOBALS['options']===$before_legacy);
$_REQUEST=['sd_date'=>'2026-02-30'];try { wowthai_handle_save_special_day(); } catch(RuntimeException $e) { check('legacy invalid date never prefilled',!str_contains($GLOBALS['redirect'],'hours_date')); }
check('staff preview missing nonce denied',is_wp_error(wowthai_hours_api_preview_permission(new Request([],''))));
check('staff preview valid nonce allowed',wowthai_hours_api_preview_permission(new Request())===true);
check('English checkout service label',wowthai_hours_api_parse_service('Tuesday, 20. October 2026 um 12:15 Uhr')===['2026-10-20','12:15']);
$near=['weekly'=>$week,'exceptions'=>[]];check('existing service ignores ordinary lead time',wowthai_hours_api_service_fits('order',[$date,'11:00'],$near));
check('booking respects closing offset',!wowthai_hours_api_service_fits('reservation',[$date,'13:45'],$near));
check('first split pickup uses 15-minute cutoff',wowthai_hours_api_service_fits('order',[$date,'13:45'],['weekly'=>array_fill(1,7,[['11:00','14:00'],['17:00','21:00']]),'exceptions'=>[]]));
check('single pickup window uses 30-minute cutoff',!wowthai_hours_api_service_fits('order',[$date,'13:45'],$near));
$GLOBALS['options']['wowthai_weekly_hours']='corrupt';check('staff corruption exposed',is_wp_error(wowthai_hours_api_schedule()));
echo "$passed passed, $failed failed\n";exit($failed?1:0);
