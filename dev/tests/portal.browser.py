"""Run: python dev/tests/portal.browser.py (PHP + Python Playwright/Chromium).
Runs the actual PHP template, CSS and JS against synthetic, staff-only API fixtures.
No Internet, production data or real order/reservation mutations are involved.
"""
import json, os, subprocess, threading, http.server, functools, tempfile, hashlib
from datetime import datetime, timezone
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
ROOT=Path(__file__).resolve().parents[2]
fixture=subprocess.check_output(['php', str(ROOT/'dev/tests/portal-fixture.php')], text=True)
class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self,*args): pass
    def do_GET(self):
        if self.path=='/fixture':
            self.send_response(200); self.send_header('Content-Type','text/html; charset=utf-8'); self.end_headers(); self.wfile.write(fixture.encode())
        else: super().do_GET()
server=http.server.ThreadingHTTPServer(('127.0.0.1',0),functools.partial(Handler,directory=str(ROOT)))
threading.Thread(target=server.serve_forever,daemon=True).start()
ORDER={'id':1,'number':'TEST-001','stage':'completed','status':'completed','created':'2026-10-19T12:00:00+02:00','created_ts':1792404000,'name':'Fixture guest','items':[{'name':'Test meal','quantity':1,'choices':['Test choice']}],'total':'20,00 €','payment':'Barzahlung bei Abholung','version':'fixture','currency':'EUR','gross_minor':2000,'refund_minor':500,'net_minor':1500,'included':True}
RES={'id':1,'date':'2026-10-24','time':'12:00','party':2,'status':'pending','name':'Fixture reservation','version':'fixture','period':'lunch'}
try:
  with sync_playwright() as p:
    browser=p.chromium.launch(headless=True)
    for width,height in [(1440,1000),(390,844),(768,1024)]:
      page=browser.new_page(viewport={'width':width,'height':height})
      page.clock.install(time=datetime(2026,10,19,19,30,tzinfo=timezone.utc))
      flags={'open':False,'fail':False,'archive_empty':False,'note':'','posts':0,'new_ids':[],'daily_note':'','daily_fail':False}
      page.add_init_script('''window.chimes=0; window.AudioContext=class { constructor(){this.state='running';this.currentTime=0;this.destination={};} createOscillator(){return {type:'',frequency:{setValueAtTime(){}},connect(){},start(){window.chimes++},stop(){}}} createGain(){return {gain:{setValueAtTime(){},exponentialRampToValueAtTime(){}},connect(){}}} };''')
      errors=[]
      page.on('pageerror',lambda error: errors.append(str(error)))
      def route(r):
        from urllib.parse import urlparse,parse_qs
        path=r.request.url; query=parse_qs(urlparse(path).query)
        current=int(query.get('page',[1])[0])
        if r.request.method!='GET': flags['posts']+=1
        if '/summary' in path or '/stats' in path:
          if flags['fail']:
            r.fulfill(status=503,content_type='application/json',body='{"message":"fixture disconnected"}'); return
          data={'date':'2026-10-19','is_open':flags['open'],'pending_reservations':6,'new_orders':2,'active_orders':3,'backlog_orders':1,'new_order_ids':flags['new_ids']}
        elif '/daily?' in path:
          if flags['daily_fail']:
            r.fulfill(status=503,content_type='application/json',body='{}'); return
          second={**ORDER,'id':2,'number':'TEST-002','gross_minor':3000,'refund_minor':0,'net_minor':3000}
          data={'date':'2026-10-19','orders':{'total':2,'excluded':0},'currencies':{'EUR':{'gross_minor':5000,'refund_minor':500,'net_minor':4500}},'reservations':{'total':4,'guests':10},'items':[{**ORDER,'note':flags['daily_note']},second],'page':1,'pages':1,'records':2}
        elif '/reservations?' in path:
          pending=query.get('view',['daily'])[0]=='pending'
          dinner=query.get('shift',['all'])[0]=='dinner'
          cards=[{**RES,'id':i,'notes':flags['note']} for i in range(1,16)] if pending and not dinner else []
          data={'items':cards,'total':6 if cards else 0,'guests':29 if cards else 0,'counts':{'pending':{'count':6 if cards else 0,'guests':29 if cards else 0}},'page':1,'pages':1,'records':len(cards)}
        elif '/orders?' in path:
          archive='archive' in path
          active=[{**ORDER,'id':i,'stage':'new','status':'processing'} for i in [1,2]]
          data={'items':([] if flags['archive_empty'] else [{**ORDER,'number':f'TEST-00{current}'}]) if archive else active,'total':51 if archive else 2,'records':51 if archive else 2,'pages':2 if archive else 1,'page':current,'counts':{'new':2,'preparing':0,'ready':0,'dispatched':0}}
        else: data={}
        r.fulfill(status=200,content_type='application/json',body=json.dumps(data))
      page.route('**/api/**',route)
      page.goto(f'http://127.0.0.1:{server.server_port}/fixture')
      assert hashlib.sha256((ROOT/'assets/js/portal.js').read_bytes()).hexdigest()[:12] in page.locator('script[src*="portal.js"]').get_attribute('src'), 'portal URL must change when its bytes change; version alone can retain stale CDN assets'
      assert hashlib.sha256((ROOT/'assets/css/portal.css').read_bytes()).hexdigest()[:12] in page.locator('link[href*="portal.css"]').get_attribute('href'), 'portal CSS must carry its content fingerprint'
      expect(page.locator('#wto-opening-status')).to_contain_text('geschlossen')
      expect(page.locator('#wto-badge-res')).to_have_text('6')
      # Every active card, including the second card, keeps its action buttons.
      expect(page.locator('.btn-order-accept')).to_have_count(2)
      page.locator('[data-view="archive"]').click()
      expect(page.locator('#wto-archive-list')).to_be_visible()
      expect(page.locator('#wto-kanban-board')).not_to_be_visible()
      expect(page.locator('#wto-orders-col-switcher')).not_to_be_visible()
      expect(page.locator('#wto-archive-list')).to_contain_text('TEST-001')
      expect(page.locator('#wto-archive-list')).to_contain_text('19.10.2026')
      expect(page.locator('#wto-archive-list')).to_contain_text('Abgeschlossen')
      assert page.locator('#wto-archive-list [class*="btn-order-"]').count()==0
      page.locator('#wto-orders-pagination').get_by_role('button',name='Weiter').click()
      expect(page.locator('#wto-archive-list')).to_contain_text('TEST-002')
      expect(page.locator('#wto-badge-res')).to_have_text('6')
      flags['archive_empty']=True; page.clock.run_for(12000)
      expect(page.locator('#wto-archive-list')).to_contain_text('Keine archivierten')
      page.locator('[data-tab="reservations"]').click()
      page.locator('.wto-res-filters [data-view="pending"]').click()
      expect(page.locator('#wto-res-date-input')).to_be_disabled()
      expect(page.locator('#wto-res-scope')).to_contain_text('alle Tage')
      expect(page.locator('.wto-res-date').first).to_contain_text('24.10.2026')
      expect(page.locator('#wto-kpi-res-guests')).to_have_text('29')
      expect(page.locator('#wto-kpi-res-total')).to_have_text('6')
      # Poll preserves an operator's keyboard focus and scrolled column.
      action=page.locator('.btn-res-action[data-action="confirmed"]').first
      action.focus()
      page.locator('#wto-res-list .wto-cards-scroll').first.evaluate('(el)=>el.scrollTop=90')
      flags['note']='Fixture updated note'
      page.clock.run_for(12000)
      expect(page.locator('.btn-res-action[data-action="confirmed"]').first).to_be_focused()
      assert page.locator('#wto-res-list .wto-cards-scroll').first.evaluate('(el)=>el.scrollTop')>=80
      page.locator('[data-shift="dinner"]').click()
      expect(page.locator('#wto-kpi-res-total')).to_have_text('0')
      expect(page.locator('#wto-m-res-new')).to_have_text('0')
      expect(page.locator('#wto-badge-res')).to_have_text('6')
      flags['daily_fail']=True
      page.locator('[data-tab="stats"]').click()
      expect(page.locator('#wto-daily-feedback')).to_contain_text('nicht geladen')
      expect(page.locator('#wto-stat-revenue-today')).to_have_text('—')
      flags['daily_fail']=False; page.clock.run_for(12000)
      expect(page.locator('#wto-stat-revenue-today')).to_contain_text('45,00')
      expect(page.locator('#wto-daily-totals')).to_contain_text('50,00')
      expect(page.locator('#wto-daily-totals')).to_contain_text('5,00')
      expect(page.locator('#wto-daily-backlog')).to_contain_text('1 aktive Bestellungen aus früheren Tagen')
      first=page.locator('.wto-daily-order').first
      first.locator('summary').click()
      expect(first.locator('.wto-card-items')).to_contain_text('Test choice')
      assert page.locator('#wto-daily-orders [class*="btn-order-"]').count()==0
      first.locator('summary').focus(); flags['daily_note']='Updated fixture daily note'
      flags['open']=True; flags['new_ids']=[999]; page.clock.run_for(12000)
      expect(page.locator('#wto-opening-status')).to_contain_text('geöffnet')
      expect(first).to_have_attribute('open','')
      expect(first.locator('summary')).to_be_focused()
      assert page.evaluate('window.chimes')==2,'new order outside displayed page must chime exactly once'
      page.clock.run_for(12000)
      assert page.evaluate('window.chimes')==2,'unchanged new order must not chime twice'
      flags['fail']=True; page.clock.run_for(12000)
      expect(page.locator('#wto-opening-status')).to_contain_text('unbekannt')
      expect(page.locator('#wto-sync-status')).to_contain_text('veraltet')
      flags['fail']=False; page.clock.run_for(12000)
      expect(page.locator('#wto-opening-status')).to_contain_text('geöffnet')
      assert flags['posts']==0,'read-only navigation/polling must not mutate anything'
      assert not errors,errors
      assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'), 'horizontal overflow'
      folder=Path(os.environ.get('PORTAL_TEST_ARTIFACTS',str(Path(tempfile.gettempdir())/'wowthai-portal-artifacts')));folder.mkdir(parents=True,exist_ok=True)
      page.screenshot(path=str(folder/f'daily-{width}.png'))
      print(f'PASS: {width}px archive/pagination/readonly, booking dates/filter/counts/focus/scroll, daily details/refunds, polling/open/failure/recovery')
      page.close()
    browser.close()
finally: server.shutdown()
