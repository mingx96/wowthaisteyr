"""Actual public/checkout templates with synthetic availability; no external data."""
import json,subprocess,threading,http.server,functools
from pathlib import Path
from datetime import date,timedelta
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[2]
fixtures={name:subprocess.check_output(['php',str(ROOT/'dev/tests/hours-public-fixture.php'),name],text=True) for name in ['booking','home','checkout']}
class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self,*a): pass
    def do_GET(self):
        name=self.path.strip('/')
        if name in fixtures:
            self.send_response(200);self.send_header('Content-Type','text/html; charset=utf-8');self.end_headers();self.wfile.write(fixtures[name].encode())
        else:super().do_GET()
server=http.server.ThreadingHTTPServer(('127.0.0.1',0),functools.partial(Handler,directory=str(ROOT)))
threading.Thread(target=server.serve_forever,daemon=True).start()
def payload(day,slots=['12:30'],revision='one'):
    return {'date':day,'timezone':'Europe/Vienna','ranges':[['12:00','15:00']],'reservation_slots':slots,'pickup_slots':['12:15'], 'display_rows':[{'days':'Mo – So','hours':'12:00 – 15:00 Uhr','weekdays':list(range(1,8))}], 'opening_status':{'is_open':False,'status_type':'preorder','status_label':'Heute geschlossen','status_detail':'Fixture fresh closure'},'revision':revision,'schema_opening_hours':[{'@type':'OpeningHoursSpecification','dayOfWeek':['Tuesday'],'opens':'12:00','closes':'15:00'}]}
try:
  with sync_playwright() as p:
    browser=p.chromium.launch(headless=True)
    for width,height in [(320,667),(390,667),(768,1024),(1440,900)]:
      page=browser.new_page(viewport={'width':width,'height':height});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
      state={'failure':False,'revision':'one','slots':['12:30'],'calls':0,'ajax':0,'closed':True,'pickup':'closed'}
      def route(r):
        state['calls']+=1
        day=parse_qs(urlparse(r.request.url).query).get('date',[''])[0]
        assert r.request.method=='GET'
        assert 'no-cache' in r.request.headers.get('cache-control','') or 'no-store' in r.request.headers.get('cache-control','')
        if state['failure']:r.fulfill(status=503,content_type='application/json',body='{}');return
        data=payload(day,state['slots'],state['revision'])
        if not state['closed']:data['opening_status']={'is_open':True,'status_type':'open','status_label':'Jetzt geöffnet','status_detail':'Fixture reopened'}
        r.fulfill(content_type='application/json',body=json.dumps(data))
      page.route('**/api/availability?**',route)
      def ajax(r):
        state['ajax']+=1
        if state['pickup']=='error':r.fulfill(status=503,content_type='application/json',body='{}');return
        data={'prep_time':'fresh prep','asap':None,'dates':[]}
        if state['pickup']=='open':data={'prep_time':'fresh prep','asap':'13:15','dates':[{'value':'2026-10-21','label':'fresh reopening','slots':['12:00','12:30']}]}
        r.fulfill(content_type='application/json',body=json.dumps({'success':True,'data':data}))
      page.route('**/ajax?**',ajax)
      page.goto(f'http://127.0.0.1:{server.server_port}/booking')
      expect(page.locator('#res_time')).to_have_value('12:30')
      expect(page.locator('[data-wowthai-hours-rows]').first).to_contain_text('12:00 – 15:00')
      expect(page.locator('.menu-status-banner')).to_contain_text('Fixture fresh closure')
      expect(page.locator('.special-closed-banner')).to_be_visible()
      expect(page.locator('.special-closed-banner')).to_contain_text('Fixture fresh closure')
      schema=page.locator('script[type="application/ld+json"]').evaluate('(el)=>JSON.parse(el.textContent)')
      assert schema['name']=='Synthetic fixture' and schema['openingHoursSpecification'][0]['opens']=='12:00'
      original=page.locator('#res_date').input_value();a=(date.fromisoformat(original)+timedelta(days=4)).isoformat();b=(date.fromisoformat(original)+timedelta(days=5)).isoformat()
      page.evaluate("""day => {
        const nativeFetch = window.fetch;
        window.fetch = async (...args) => {
          const response = await nativeFetch(...args);
          if (new URL(args[0]).searchParams.get('date') === day) {
            const read = response.json.bind(response);
            response.json = async () => {
              window.hoursHeld = true;
              await new Promise(resolve => { window.releaseHours = resolve; });
              return {...await read(), reservation_slots:['19:00']};
            };
          }
          return response;
        };
      }""",a)
      page.locator('#res_date').fill(a);page.locator('#res_date').dispatch_event('change')
      expect(page.locator('#res_time')).to_be_disabled();expect(page.locator('.booking-submit button')).to_be_disabled()
      page.wait_for_function('window.hoursHeld === true')
      page.locator('#res_date').fill(b);page.locator('#res_date').dispatch_event('change');expect(page.locator('#res_time')).to_have_value('12:30')
      page.evaluate('window.releaseHours()')
      page.wait_for_timeout(50);expect(page.locator('#res_time')).to_have_value('12:30')
      page.locator('#res_name').fill('Fixture kept input')
      state['failure']=True;page.evaluate('window.dispatchEvent(new Event("focus"))')
      expect(page.locator('#res_time')).to_be_disabled();expect(page.locator('#res_time')).to_have_value('');expect(page.locator('#res_availability_status')).to_contain_text('nicht geladen')
      state['failure']=False;state['revision']='two';state['slots']=[];page.evaluate('window.dispatchEvent(new Event("focus"))')
      expect(page.locator('#res_availability_status')).to_contain_text('Keine');expect(page.locator('.booking-submit button')).to_be_disabled()
      expect(page.locator('#res_name')).to_have_value('Fixture kept input')
      state['slots']=['13:00'];state['closed']=False;page.evaluate('window.dispatchEvent(new Event("focus"))');expect(page.locator('#res_time')).to_have_value('13:00');expect(page.locator('.booking-submit button')).to_be_enabled();expect(page.locator('.special-closed-banner')).not_to_be_visible()
      # Same-day reload and focus retain valid customer choice and refresh cart callback.
      page.evaluate('document.getElementById("cart-root").innerHTML="<div id=cart-overlay></div>"')
      state['revision']='three';page.evaluate('window.dispatchEvent(new Event("focus"))');page.wait_for_function('document.querySelector("#res_time").value==="13:00"')
      page.wait_for_timeout(100);assert state['ajax']>=1
      assert not errors,errors
      page.goto(f'http://127.0.0.1:{server.server_port}/home')
      expect(page.locator('[data-wowthai-hours-rows]').first).to_contain_text('12:00 – 15:00')
      expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state','open')
      page.locator('.home-weekly summary').click()
      expect(page.locator('.home-sushi-note')).to_be_visible()
      expect(page.locator('.home-sushi-note')).to_contain_text('Montags Sushi-Ruhetag')
      assert not errors,errors
      # The actual checkout template/controls and real closure-local refresh must reject old responses.
      page.add_init_script('''(() => {
        const nativeFetch=window.fetch;let pickupCalls=0;
        window.fetch=async (...args)=>{
          const response=await nativeFetch(...args);
          if(String(args[0]).includes('action=wowthai_pickup_availability')){
            const call=++pickupCalls;
            if(call===1){response.json=async()=>{window.pickupHeld=true;await new Promise(resolve=>window.releaseOldPickup=resolve);window.firstPickupDelivered=true;return {success:true,data:{prep_time:'stale prep',asap:'12:05',dates:[{value:'2026-10-21',label:'stale reopening',slots:['12:00']}]}};};}
            else if(window.holdNextPickup){window.holdNextPickup=false;const read=response.json.bind(response);response.json=async()=>{window.nextPickupHeld=true;await new Promise(resolve=>window.releaseSupersededPickup=resolve);window.nextPickupDelivered=true;return read();};}
          }
          return response;
        };
      })();''')
      state['ajax']=0;state['pickup']='closed'
      page.goto(f'http://127.0.0.1:{server.server_port}/checkout')
      page.wait_for_function('window.pickupHeld===true')
      expect(page.locator('#pickup_date_select')).to_be_disabled();expect(page.locator('#pickup_time_select')).to_be_disabled()
      assert page.locator('#pickup_mode').input_value()==''
      assert page.evaluate('new FormData(document.querySelector("form.checkout")).get("pickup_date")') is None
      assert page.evaluate('new FormData(document.querySelector("form.checkout")).get("pickup_time")') is None
      page.evaluate('document.getElementById("cart-root").innerHTML="<span class=badge-asap>cached prep</span>"')
      page.locator('#billing_first_name').fill('Fixture kept name');page.locator('#order_comments').fill('Fixture kept notes')
      page.evaluate('document.dispatchEvent(new CustomEvent("wowthai:hours-changed",{detail:{revision:"closed latest"}}))')
      expect(page.locator('#pickup_date_select option')).to_have_count(0)
      expect(page.locator('#pickup_time_select option')).to_have_count(0)
      page.wait_for_function('document.getElementById("pickup_mode").dataset.availabilityState==="ready"')
      page.evaluate('window.releaseOldPickup()')
      page.wait_for_function('window.firstPickupDelivered===true')
      expect(page.locator('#pickup_date_select option')).to_have_count(0)
      expect(page.locator('#pickup_time_select option')).to_have_count(0)
      expect(page.locator('#btn-asap')).to_be_disabled()
      assert page.evaluate('window.wowthaiConfig.prep_time')=='fresh prep'
      expect(page.locator('.badge-asap')).to_have_text('Vorbestellung')
      state['pickup']='open';page.evaluate('window.dispatchEvent(new Event("focus"))')
      expect(page.locator('#pickup_time_select')).to_be_enabled()
      page.locator('#btn-later').click();page.locator('#pickup_time_select').select_option('12:30')
      page.evaluate('window.holdNextPickup=true;window.dispatchEvent(new Event("focus"))')
      page.wait_for_function('window.nextPickupHeld===true')
      expect(page.locator('#pickup_time_select')).to_be_disabled();expect(page.locator('#btn-asap')).to_be_disabled()
      state['pickup']='error';page.evaluate('window.dispatchEvent(new Event("focus"))')
      expect(page.locator('#pickup_time_select')).to_be_disabled();expect(page.locator('#pickup_time_select option')).to_have_count(0)
      expect(page.locator('#pickup_availability_status')).to_contain_text('nicht geladen')
      page.evaluate('window.releaseSupersededPickup()');page.wait_for_function('window.nextPickupDelivered===true')
      expect(page.locator('#pickup_time_select')).to_be_disabled();expect(page.locator('#pickup_time_select option')).to_have_count(0)
      assert page.locator('#pickup_mode').input_value()==''
      assert page.evaluate('new FormData(document.querySelector("form.checkout")).get("pickup_time")') is None
      expect(page.locator('.badge-asap')).to_have_text('Zeiten derzeit nicht verfügbar')
      page.locator('#place_order').evaluate('el=>el.disabled=true')
      state['pickup']='open';page.evaluate('window.dispatchEvent(new Event("focus"))')
      expect(page.locator('#pickup_time_select')).to_have_value('12:30')
      expect(page.locator('#place_order')).to_be_disabled()
      expect(page.locator('#billing_first_name')).to_have_value('Fixture kept name');expect(page.locator('#order_comments')).to_have_value('Fixture kept notes')
      assert not errors,errors
      page.wait_for_load_state('networkidle')
      page.unroute_all(behavior='wait')
      page.close()
    browser.close()
  print('PASS public hours actual templates: 320/390-short/768/1440; date races, errors, closure, focus, rows/weekly note/schema, cart/checkout response races and focus/error recovery, preserved input')
finally:server.shutdown()
