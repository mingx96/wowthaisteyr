<?php
/** Opt-in: wp eval-file wp-content/themes/wowthai/dev/tests/hours-api.integration.php
 * Real REST + MySQL, disposable fixture only. Always restores options/deletes synthetic records.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('WOWTHAI_PORTAL_TEST') || WOWTHAI_PORTAL_TEST !== true || home_url() !== 'http://fixture.invalid') exit('Disposable fixture environment required.');
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || !has_filter('pre_wp_mail')) WP_CLI::error('Block outbound HTTP and mail first.');
$GLOBALS['hours_fixture_checks']=0;
function hours_expect($condition,$message){$GLOBALS['hours_fixture_checks']++;if(!$condition)throw new RuntimeException($message);}
function hours_api($path,$method='GET',$body=[],$nonce=true){
    $request=new WP_REST_Request($method,'/wowthai-hours/v1/'.$path);
    if($nonce)$request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
    if($method==='GET')foreach($body as $k=>$v)$request->set_param($k,$v);
    else{$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($body));}
    return rest_do_request($request);
}
function hours_status($response,$status,$message){hours_expect($response->get_status()===$status,$message.' ('.$response->get_status().')');return $response->get_data();}
$keys=['wowthai_weekly_hours','wowthai_special_days'];$before=[];foreach($keys as $k)$before[$k]=get_option($k,null);
$order_ids=[];$res_ids=[];$users=[];$connection=null;$failure=null;$fault=null;$thrower=null;
try {
    wp_set_current_user(1);
    hours_expect(wowthai_hours_api_can_edit(),'fixture manager');
    $date=wowthai_now()->modify('+7 days')->setTime(0,0);$ymd=$date->format('Y-m-d');
    $week=array_fill(1,7,[['11:00','14:00'],['17:00','21:00']]);
    delete_option('wowthai_weekly_hours');update_option('wowthai_special_days',['2020-01-01'=>['closed'=>true,'reason'=>'Historical fixture']]);
    $read=hours_api('schedule');$initial=hours_status($read,200,'manager config read');
    hours_expect(get_option('wowthai_weekly_hours',null)===null,'GET never seeds defaults');
    hours_expect(str_contains($read->get_headers()['Cache-Control']??'','no-store'),'staff response no-store');
    $staff=wp_insert_user(['user_login'=>'hours-fixture-'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber']);hours_expect(!is_wp_error($staff),'staff fixture created');$users[]=$staff;(new WP_User($staff))->add_cap('edit_shop_orders');
    wp_set_current_user(0);hours_status(hours_api('schedule'),401,'anonymous read denied');hours_status(hours_api('schedule/weekly','PUT'),401,'anonymous write denied');
    $public=hours_api('availability','GET',['date'=>$ymd]);$a=hours_status($public,200,'public availability');
    hours_expect(str_contains($public->get_headers()['Cache-Control']??'','no-store'),'public no-store');
    foreach(['exceptions','conflicts','weekly','can_edit','audit'] as $key)hours_expect(!array_key_exists($key,$a),'public excludes '.$key);
    hours_expect(isset($a['schema_opening_hours'])&&is_array($a['pickup_slots']),'public generic slots/schema');
    hours_status(hours_api('availability','GET',['date'=>'2026-02-30']),400,'impossible date');
    hours_status(hours_api('availability','GET',['date'=>wowthai_now()->modify('+91 days')->format('Y-m-d')]),400,'90-day public bound');
    wp_set_current_user($staff);$r=hours_status(hours_api('schedule'),200,'staff reads schedule');hours_expect($r['can_edit']===false,'staff read only');
    $draft=['kind'=>'weekly','weekly'=>$week,'revision'=>$r['revision'],'preview_date'=>$ymd];
    hours_status(hours_api('schedule/preview','POST',$draft,false),403,'preview requires actual REST nonce');
    $preview=hours_status(hours_api('schedule/preview','POST',$draft),200,'staff can preview');
    hours_status(hours_api('schedule/weekly','PUT',$draft+['confirmation_token'=>$preview['confirmation_token'],'acknowledge_conflicts'=>true]),403,'edit_shop_orders staff cannot save');
    wp_set_current_user(1);hours_status(hours_api('schedule/weekly','PUT',$draft+['confirmation_token'=>$preview['confirmation_token']],false),403,'manager missing nonce denied');
    $a=$draft;$b=$draft;$b['weekly'][1]=[];
    $pa=hours_status(hours_api('schedule/preview','POST',$a),200,'first writer preview');$pb=hours_status(hours_api('schedule/preview','POST',$b),200,'second writer preview');
    $saved=hours_status(hours_api('schedule/weekly','PUT',$a+['confirmation_token'=>$pa['confirmation_token'],'acknowledge_conflicts'=>true]),200,'first writer saves');
    hours_status(hours_api('schedule/weekly','PUT',$b+['confirmation_token'=>$pb['confirmation_token'],'acknowledge_conflicts'=>true]),409,'second stale writer rejected');
    hours_expect(get_option('wowthai_weekly_hours')===$week,'weekly DB persisted');hours_expect(get_option('wowthai_special_days')['2020-01-01']['closed']===true,'historical exceptions preserved');
    $after=hours_status(hours_api('schedule'),200,'read after save');hours_expect($after['revision']===$saved['revision'],'fresh revision read after save');
    // Connection-scoped lock held by a genuinely separate MySQL connection.
    $connection=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
    hours_expect((string)$connection->get_var($connection->prepare('SELECT GET_LOCK(%s, 0)',wowthai_hours_api_lock_name()))==='1','second DB connection acquired lock');
    $a['revision']=$saved['revision'];$pa=hours_status(hours_api('schedule/preview','POST',$a),200,'preview while writer lock held is pure');
    hours_status(hours_api('schedule/weekly','PUT',$a+['confirmation_token'=>$pa['confirmation_token'],'acknowledge_conflicts'=>true]),503,'competing DB lock blocks save');
    $connection->get_var($connection->prepare('SELECT RELEASE_LOCK(%s)',wowthai_hours_api_lock_name()));
    // No-op does not ask WP to update, while an actual failed write is a 503.
    $attempts=0;$fault=static function($new,$old)use(&$attempts){$attempts++;return $old;};add_filter('pre_update_option_wowthai_weekly_hours',$fault,10,2);
    hours_status(hours_api('schedule/weekly','PUT',$a+['confirmation_token'=>$pa['confirmation_token'],'acknowledge_conflicts'=>true]),200,'no-op save successful');hours_expect($attempts===0,'no-op never issues update_option');
    $a['weekly'][1]=[];$pa=hours_status(hours_api('schedule/preview','POST',$a),200,'preview changed weekly');
    hours_status(hours_api('schedule/weekly','PUT',$a+['confirmation_token'=>$pa['confirmation_token'],'acknowledge_conflicts'=>true]),503,'WP options failure is reported');hours_expect(get_option('wowthai_weekly_hours')===$week,'failed save leaves DB unchanged');remove_filter('pre_update_option_wowthai_weekly_hours',$fault,10);$fault=null;
    hours_expect((string)$connection->get_var($connection->prepare('SELECT IS_FREE_LOCK(%s)',wowthai_hours_api_lock_name()))==='1','lock released after write failure');
    // >100 orders and bookings prove pagination. Only synthetic fixture data is created.
    for($i=0;$i<101;$i++){
        $o=new WC_Order();$o->set_status('pending');$o->set_date_created('2020-01-01T12:00:00+01:00');$o->set_billing_first_name('Hours synthetic fixture');$o->set_payment_method('cod');$o->update_meta_data('_wowthai_pickup_date',$ymd);$o->update_meta_data('_wowthai_pickup_time','12:00');$o->save();$order_ids[]=$o->get_id();
        $id=wp_insert_post(['post_type'=>'tischanfrage','post_status'=>'publish','post_title'=>'Hours synthetic fixture']);$res_ids[]=$id;
        foreach(['date'=>$ymd,'time'=>'12:00','status'=>$i===0?'confirmed':'pending'] as $k=>$v)update_post_meta($id,'_res_'.$k,$v);
    }
    $o=new WC_Order();$o->set_status('processing');$o->update_meta_data('Abholzeit','Schnellstmöglich');$o->save();$order_ids[]=$o->get_id();$unknown_id=$o->get_id();
    $o=new WC_Order();$o->set_status('processing');$o->update_meta_data('Abholzeit',wp_date('l, j. F Y',$date->getTimestamp(),wowthai_timezone()).' um 12:00 Uhr');$o->save();$order_ids[]=$o->get_id();$label_id=$o->get_id();
    $label_service=wowthai_hours_api_parse_service($o->get_meta('Abholzeit'));hours_expect($label_service===[$ymd,'12:00'],'real localized checkout label parses');
    $ex=['kind'=>'exceptions','revision'=>$saved['revision'],'exception'=>['start_date'=>$ymd,'end_date'=>$ymd,'mode'=>'closed','reason'=>'Fixture closure'],'preview_date'=>$ymd];
    $snapshots=[get_option('wowthai_weekly_hours'),get_option('wowthai_special_days')];$p=hours_status(hours_api('schedule/preview','POST',$ex),200,'closure preview');
    hours_expect($snapshots===[get_option('wowthai_weekly_hours'),get_option('wowthai_special_days')],'preview does not swap DB options');
    $oc=array_filter($p['conflicts'],fn($r)=>$r['kind']==='order'&&in_array($r['id'],$order_ids,true));$bc=array_filter($p['conflicts'],fn($r)=>$r['kind']==='reservation'&&in_array($r['id'],$res_ids,true));
    hours_expect(count($oc)===103&&count($bc)===101,'all paginated commitments by service date, not creation');
    $unknown=array_values(array_filter($p['conflicts'],fn($r)=>$r['kind']==='order'&&$r['id']===$unknown_id));hours_expect($unknown[0]['unknown']===true&&$unknown[0]['date']==='','ASAP explicitly manual check');
    hours_expect($p['preview']['ranges']===[]&&$p['preview']['pickup_slots']===[]&&$p['preview']['reservation_slots']===[],'closed preview has no public slots');
    $payload=$ex+['confirmation_token'=>$p['confirmation_token']];hours_status(hours_api('schedule/exceptions','PUT',$payload),400,'conflicts need explicit acknowledgement');$payload['acknowledge_conflicts']=true;
    update_post_meta($res_ids[0],'_res_time','13:00');hours_status(hours_api('schedule/exceptions','PUT',$payload),409,'changed commitment invalidates preview');
    $p=hours_status(hours_api('schedule/preview','POST',$ex),200,'new preview');$payload['confirmation_token']=$p['confirmation_token'];
    $edited=$payload;$edited['exception']['reason']='Altered draft';hours_status(hours_api('schedule/exceptions','PUT',$edited),409,'draft alteration invalidates signature');
    $expired=explode('.',$payload['confirmation_token']);$expired[0]=(string)(wowthai_now()->getTimestamp()-1);$edited=$payload;$edited['confirmation_token']=implode('.',$expired);hours_status(hours_api('schedule/exceptions','PUT',$edited),409,'expired token denied');
    hours_status(hours_api('schedule/exceptions','PUT',$payload),200,'acknowledged current conflict set saves');
    $fresh=hours_status(hours_api('availability','GET',['date'=>$ymd]),200,'public read after save');hours_expect($fresh['ranges']===[]&&$fresh['reservation_slots']===[],'public exception immediate read-after-save');hours_expect(get_option('wowthai_weekly_hours')===$week,'exception save never changes weekly resource');
    $res=get_post_meta($res_ids[0]);hours_expect(get_post_meta($res_ids[0],'_res_status',true)==='confirmed'&&get_post_meta($res_ids[0],'_res_time',true)==='13:00','schedule never edits booking state/time');hours_expect(wc_get_order($order_ids[0])->get_status()==='pending','schedule never changes order status');
    // Standard WP post cache hook is a bounded cache invalidation path, not global CDN purge.
    $events=0;$hook=static function()use(&$events){$events++;};add_action('wowthai_hours_saved',$hook);wowthai_hours_api_invalidate_public();remove_action('wowthai_hours_saved',$hook);hours_expect($events===1,'public invalidation action available');
    $thrower=static function(){throw new RuntimeException('fixture unavailable');};add_filter('wowthai_hours_schedule',$thrower);
    hours_status(hours_api('availability','GET',['date'=>$ymd]),503,'public resolver unavailable returns 503');remove_filter('wowthai_hours_schedule',$thrower);$thrower=null;
} catch(Throwable $e){$failure=$e;}
finally {
    if($fault)remove_filter('pre_update_option_wowthai_weekly_hours',$fault,10);
    if($thrower)remove_filter('wowthai_hours_schedule',$thrower);
    if($connection){$connection->get_var($connection->prepare('SELECT RELEASE_LOCK(%s)',wowthai_hours_api_lock_name()));$connection->close();}
    wp_set_current_user(1);
    foreach($order_ids as $id){$o=wc_get_order($id);if($o)$o->delete(true);}foreach($res_ids as $id)wp_delete_post($id,true);
    require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id)wp_delete_user($id);
    foreach($before as $k=>$v){if($v===null)delete_option($k);else update_option($k,$v);}
    wowthai_hours_api_refresh_options();
}
if($failure)WP_CLI::error($failure->getMessage());
$storage=\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()?'HPOS':'legacy';
WP_CLI::success($GLOBALS['hours_fixture_checks'].' hours REST/database assertions ('.$storage.'); options restored; synthetic records deleted.');
