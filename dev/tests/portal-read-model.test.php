<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Pure regression tests with in-memory WooCommerce/WordPress boundary fakes.
define('ABSPATH', __DIR__);
$checks=0;
function check($condition,$message) { global $checks; $checks++; if (!$condition) { fwrite(STDERR,"FAIL: $message\n"); exit(1); } }
function add_action(...$args) {}
function current_user_can($cap) { return $GLOBALS['staff'] ?? false; }
function absint($n) { return abs((int)$n); }
function wp_json_encode($v) { return json_encode($v); }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['rows'][$id][substr($key,5)] ?? ''; }
class WP_Error { function __construct(public $code,public $message,public $data=[]) {} }
class WP_REST_Response { function __construct(public $data,public $status=200,public $headers=[]) {} }
class WTO_Operations {
    static function stage($o) { return $o->get_status()==='processing'?'preparing':'new'; }
    static function order_data($o) { return ['id'=>$o->id,'stage'=>self::stage($o),'version'=>'unchanged-plugin-version']; }
    static function reservation_data($id) { return ['id'=>$id]+$GLOBALS['rows'][$id]; }
}
class FakeOrder {
    function __construct(public $id, public $status, public $created,public $total=10.00,public $refund=0) {}
    function get_status() { return $this->status; }
    function get_total() { return $this->total; }
    function get_total_refunded() { return $this->refund; }
    function get_currency() { return 'EUR'; }
    function get_date_created() { return new DateTimeImmutable($this->created); }
    function get_id() { return $this->id; }
    function get_order_number() { return 'TEST-'.$this->id; }
    function get_payment_method() { return 'cod'; }
    function get_payment_method_title() { return 'Fixture cash'; }
}
function wc_get_orders($args) {
    $GLOBALS['queries'][]=$args;
    $list=array_values(array_filter($GLOBALS['orders'],function($o) use($args) {
        if (!in_array($o->status,$args['status'],true)) return false;
        if (isset($args['date_created'])) {
            $ts=$o->get_date_created()->getTimestamp(); $range=explode('...',$args['date_created']);
            if (count($range)===2 && ($ts<(int)$range[0] || $ts>(int)$range[1])) return false;
        }
        return true;
    }));
    return array_slice($list,(($args['page']??1)-1)*$args['limit'],$args['limit']);
}
class WP_Query {
    public $posts;
    function __construct($args) {
        $this->posts=array_map(fn($id)=>(object)['ID'=>$id],array_keys($GLOBALS['rows']));
    }
}
$module=dirname(__DIR__,2).'/inc/portal-read-model.php';
check(is_file($module),'portal needs a shared, permission-checked read model');
require $module;
check(!wowthai_portal_can_read(),'anonymous/customer cannot read staff data');
$GLOBALS['staff']=true;
check(wowthai_portal_can_read(),'existing staff capabilities can read');
$summer=wowthai_portal_day('2026-03-29');
$winter=wowthai_portal_day('2026-10-25');
check($summer['end']-$summer['start']+1===23*3600,'DST spring day is 23 hours');
check($winter['end']-$winter['start']+1===25*3600,'DST autumn day is 25 hours');
check(wowthai_portal_day('2026-02-30') instanceof WP_Error,'reject impossible calendar day');
$GLOBALS['orders']=[]; $GLOBALS['queries']=[];
for ($i=1;$i<=205;$i++) $GLOBALS['orders'][]=new FakeOrder($i,'completed','2026-10-19T12:00:00+02:00');
$GLOBALS['orders'][]=new FakeOrder(300,'processing','2026-10-19T13:00:00+02:00',20,5);
$GLOBALS['orders'][]=new FakeOrder(301,'cancelled','2026-10-19T14:00:00+02:00',999);
$GLOBALS['orders'][]=new FakeOrder(302,'failed','2026-10-19T14:00:00+02:00',999);
$GLOBALS['orders'][]=new FakeOrder(303,'refunded','2026-10-19T14:00:00+02:00',30,30);
$GLOBALS['orders'][]=new FakeOrder(304,'processing','2026-10-18T23:59:59+02:00',40);
$GLOBALS['orders'][]=new FakeOrder(305,'completed','2026-10-20T00:00:00+02:00',500);
$GLOBALS['rows']=[1=>['date'=>'2026-10-19','time'=>'12:00','party'=>2,'status'=>''],2=>['date'=>'2026-10-24','time'=>'18:00','party'=>6,'status'=>'pending'],3=>['date'=>'2026-10-19','time'=>'18:00','party'=>4,'status'=>'cancelled'],4=>['date'=>'2026-10-19','time'=>'17:00','party'=>3,'status'=>'confirmed']];
$d=wowthai_portal_daily(['date'=>'2026-10-19','page'=>5]);
check($d['orders']['total']===209,'daily count includes every status, excludes adjacent days, exceeds old 200 limit');
check($d['orders']['gross_minor']===210000,'failed/cancelled values excluded');
check($d['orders']['refund_minor']===3500,'associated refunds counted separately');
check($d['orders']['net_minor']===206500,'net order value reconciles after refunds');
check($d['pages']===5 && count($d['items'])===9,'details paginate full daily population');
check($d['reservations']['total']===2 && $d['reservations']['guests']===5,'daily reservations/guests both exclude cancelled');
$pending=wowthai_portal_reservations(['view'=>'pending','date'=>'2026-10-19','shift'=>'all']);
check($pending['total']===2 && $pending['guests']===8,'global pending includes legacy missing status, ignores selected day explicitly');
$dinner=wowthai_portal_reservations(['view'=>'daily','date'=>'2026-10-19','shift'=>'dinner']);
check($dinner['total']===1 && $dinner['guests']===3,'shift applies to totals before pagination and excludes cancelled from KPI');
check(count($dinner['items'])===2,'cancelled booking remains visible with explicit status');
$saved_orders=$GLOBALS['orders'];
$today=new DateTimeImmutable('today',new DateTimeZone('Europe/Vienna'));
$GLOBALS['orders']=[new FakeOrder(800,'processing',$today->modify('-1 day')->format(DATE_ATOM)),new FakeOrder(801,'pending',$today->format(DATE_ATOM))];
$sum=wowthai_portal_summary([]);
check($sum['new_order_ids']===[801],'sound identities cover all active pages');
check($sum['pending_reservations']===2,'global badge independent of selected view/date');
check($sum['backlog_orders']===1,'prior day active order separated from current Vienna day');
check($sum['is_open']===null,'missing hours provider never defaults to open');
if (true) { function wowthai_is_open() { return false; } }
check(wowthai_portal_summary(['date'=>'2026-10-19'])['is_open']===false,'summary honors canonical closed state');
$GLOBALS['orders']=$saved_orders;
for ($i=10;$i<=70;$i++) $GLOBALS['rows'][$i]=['date'=>'2026-10-19','time'=>'12:00','party'=>2,'status'=>'confirmed'];
$r=wowthai_portal_reservations(['view'=>'daily','date'=>'2026-10-19','page'=>2]);
check($r['total']===63 && $r['guests']===127,'booking KPIs count all pages');
check(count($r['items'])===14 && $r['pages']===2,'stable reservation pagination includes cancelled rows');
check($r['counts']['confirmed']['count']===62,'column count covers full filter');
$archive=wowthai_portal_orders(['view'=>'archive','page'=>5]);
check($archive['pages']===5 && count($archive['items'])>0,'archive beyond first page is accessible');
check($archive['items'][0]['version']==='unchanged-plugin-version','use live plugin serializer to preserve mutation concurrency token');
echo "PASS: $checks portal read-model assertions\n";
