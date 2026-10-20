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
anonymous_fixture=subprocess.check_output(['php', str(ROOT/'dev/tests/portal-fixture.php'),'anonymous'], text=True)
class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self,*args): pass
    def do_GET(self):
        if self.path.startswith('/fixture'):
            self.send_response(200); self.send_header('Content-Type','text/html; charset=utf-8'); self.end_headers(); self.wfile.write((anonymous_fixture if self.path.startswith('/fixture-anonymous') else fixture).encode())
        else: super().do_GET()
server=http.server.ThreadingHTTPServer(('127.0.0.1',0),functools.partial(Handler,directory=str(ROOT)))
threading.Thread(target=server.serve_forever,daemon=True).start()
ORDER={'id':1,'number':'TEST-001','stage':'completed','status':'completed','created':'2026-10-19T12:00:00+02:00','created_ts':1792404000,'name':'Fixture guest','items':[{'name':'Test meal','quantity':1,'choices':['Test choice']}],'total':'20,00 €','payment':'Bezahlt','payment_display':'Barzahlung vor Ort','version':'fixture','currency':'EUR','gross_minor':2000,'refund_minor':500,'net_minor':1500,'included':True}
RES={'id':1,'date':'2026-10-24','time':'12:00','party':2,'status':'pending','name':'Fixture reservation','version':'fixture','period':'lunch'}
try:
  with sync_playwright() as p:
    browser=p.chromium.launch(headless=True)
    for width,height in [(1440,1000),(390,844),(768,1024),(667,390),(320,667)]:
      page=browser.new_page(viewport={'width':width,'height':height})
      page.clock.install(time=datetime(2026,10,19,19,30,tzinfo=timezone.utc))
      flags={'open':False,'fail':False,'archive_empty':False,'note':'','posts':0,'new_ids':[],'daily_note':'','daily_fail':False,'res_status':'pending','mixed_res':False,'hours_posts':[],'hours_revision':'r1','can_edit':True,'hours_fail':False,'save_status':200,'save_hold':False,'held_save':None,'summary_reads':0,'conflicts':True,'preview_hold':False,'held_preview':None,'slots':['12:00','12:30'],'zero_counts':False}
      page.add_init_script('''window.chimes=0; window.AudioContext=class { constructor(){this.state='running';this.currentTime=0;this.destination={};} createOscillator(){return {type:'',frequency:{setValueAtTime(){}},connect(){},start(){window.chimes++},stop(){}}} createGain(){return {gain:{setValueAtTime(){},exponentialRampToValueAtTime(){}},connect(){}}} };''')
      errors=[]
      page.on('pageerror',lambda error: errors.append(str(error)))
      def route(r):
        from urllib.parse import urlparse,parse_qs
        path=r.request.url; query=parse_qs(urlparse(path).query)
        current=int(query.get('page',[1])[0])
        if r.request.method!='GET' and '/wowthai-hours/v1/' not in path: flags['posts']+=1
        if '/wowthai-hours/v1/schedule' in path:
          assert r.request.headers.get('x-wp-nonce')=='fixture-only', 'schedule requests require the actual REST nonce header'
          schedule={'weekly':{str(i):([['11:00','14:00']] if i!=1 else []) for i in range(1,8)},'exceptions':{'2026-10-24':{'closed':True,'reason':'Fixture closure'},'2026-10-25':{'closed':False,'ranges':[['12:00','15:00']],'reason':'Fixture custom'}},'revision':flags['hours_revision'],'can_edit':flags['can_edit'],'timezone':'Europe/Vienna'}
          if flags['hours_fail']:
            r.fulfill(status=503,content_type='application/json',body=json.dumps({'message':'Fixture schedule unavailable'})); return
          if r.request.method=='GET': data=schedule
          else:
            body=r.request.post_data_json; flags['hours_posts'].append({'method':r.request.method,'path':path,'body':body})
            if '/preview' in path:
              data={'revision':flags['hours_revision'],'confirmation_token':'fixture-token-'+str(len(flags['hours_posts'])),'conflicts':([{'kind':'order','id':17,'date':'','time':'','status':'processing','unknown':True},{'kind':'reservation','id':18,'date':'2026-10-24','time':'12:00','status':'confirmed','unknown':False}] if flags['conflicts'] else []),'preview':{'date':body['preview_date'],'ranges':[['12:00','15:00']],'reservation_slots':['12:30','13:00'],'pickup_slots':['12:15']}}
              if flags['preview_hold']:
                flags['held_preview']=(r,data); return
            else:
              assert body['confirmation_token'].startswith('fixture-token-')
              if flags['conflicts']: assert body.get('acknowledge_conflicts') is True, 'conflicts require explicit boolean acknowledgement'
              if flags['save_hold']:
                flags['held_save']=r; return
              if flags['save_status']!=200:
                r.fulfill(status=flags['save_status'],content_type='application/json',body=json.dumps({'message':'Fixture save failed'})); return
              flags['hours_revision']='r'+str(len(flags['hours_posts'])); data={**schedule,'revision':flags['hours_revision']}
          r.fulfill(status=200,content_type='application/json',body=json.dumps(data)); return
        if '/wowthai-hours/v1/availability' in path:
          data={'date':query.get('date',['2026-10-19'])[0],'ranges':[['11:00','14:00']],'reservation_slots':flags['slots'],'pickup_slots':['12:15']}
        elif '/summary' in path or '/stats' in path:
          flags['summary_reads']+=1
          if flags['fail']:
            r.fulfill(status=503,content_type='application/json',body='{"message":"fixture disconnected"}'); return
          data={'date':'2026-10-19','is_open':flags['open'],'pending_reservations':0 if flags['zero_counts'] else 60,'new_orders':0 if flags['zero_counts'] else 2,'active_orders':3,'backlog_orders':1,'new_order_ids':flags['new_ids']}
        elif '/daily?' in path:
          if flags['daily_fail']:
            r.fulfill(status=503,content_type='application/json',body='{}'); return
          second={**ORDER,'id':2,'number':'TEST-002','gross_minor':3000,'refund_minor':0,'net_minor':3000}
          data={'date':'2026-10-19','orders':{'total':2,'excluded':0},'currencies':{'EUR':{'gross_minor':5000,'refund_minor':500,'net_minor':4500}},'reservations':{'total':4,'guests':10},'items':[{**ORDER,'note':flags['daily_note']},second],'page':1,'pages':1,'records':2}
        elif '/reservations?' in path:
          pending=query.get('view',['daily'])[0]=='pending'
          dinner=query.get('shift',['all'])[0]=='dinner'
          cards=[{**RES,'id':i,'status':flags['res_status'],'notes':flags['note']} for i in range((current-1)*50+1,min(current*50,60)+1)] if pending and not dinner else []
          data={'items':cards,'total':60 if cards else 0,'guests':120 if cards else 0,'counts':{flags['res_status']:{'count':60 if cards else 0,'guests':120 if cards else 0}},'page':current,'pages':2 if cards else 1,'records':60 if cards else 0}
          if flags['mixed_res']:
            data={'items':[{**RES,'id':900,'status':'arrived'}]+[{**RES,'id':901+i,'status':'cancelled','name':f'Cancelled fixture {i}'} for i in range(5)],'total':1,'guests':2,'counts':{'arrived':{'count':1,'guests':2},'cancelled':{'count':5,'guests':10}},'page':1,'pages':1,'records':6}
        elif '/orders?' in path:
          archive='archive' in path
          active=[{**ORDER,'id':i,'stage':'new','status':'processing'} for i in [1,2]]
          data={'items':([] if flags['archive_empty'] else [{**ORDER,'number':f'TEST-00{current}'}]) if archive else active,'total':51 if archive else 2,'records':51 if archive else 2,'pages':2 if archive else 1,'page':current,'counts':{'new':2,'preparing':0,'ready':0,'dispatched':0}}
        else: data={}
        r.fulfill(status=200,content_type='application/json',body=json.dumps(data))
      page.route('**/api/**',route)
      page.goto(f'http://127.0.0.1:{server.server_port}/fixture')
      expect(page.locator('.wto-header-brand img')).to_be_visible()
      expect(page.locator('.wto-primary-nav [data-tab=orders]')).to_be_visible()
      expect(page.locator('.wto-primary-nav [data-tab=reservations]')).to_be_visible()
      expect(page.locator('#wto-view-hours')).to_have_count(1)
      menu=page.locator('#wto-command-menu')
      expect(menu).to_have_count(1)
      expect(menu.locator('summary')).to_have_text('Menü▾')
      trigger=menu.locator('summary')
      expect(menu.locator('.wto-portal-nav')).not_to_be_visible()
      trigger.focus(); trigger.press('Enter')
      expect(menu.locator('.wto-portal-nav')).to_be_visible()
      expect(menu.get_by_text('Bereiche',exact=True)).to_be_visible()
      expect(menu.locator('#wto-live-clock')).to_have_count(0)
      assert len(menu.locator('.wto-portal-nav').evaluate('(el)=>getComputedStyle(el).gridTemplateColumns.split(" ")'))==1, 'menu areas must use one compact scan column'
      assert menu.locator('.wto-command-panel').bounding_box()['width'] <= 380, 'desktop command menu must stay compact'
      expect(menu.get_by_role('link',name='Abmelden ⎋')).to_be_visible()
      assert menu.get_by_role('link',name='Abmelden ⎋').bounding_box()['height'] >= 44, 'logout must remain a 44px touch target'
      expect(page.locator('#wto-network-dot')).to_be_visible()
      page.keyboard.press('Escape')
      expect(menu).not_to_have_attribute('open','')
      expect(trigger).to_be_focused()
      trigger.press('Enter')
      for _ in range(25):
        page.keyboard.press('Tab')
        if not menu.evaluate('(el)=>el.open'): break
      expect(menu).not_to_have_attribute('open','')
      def menu_click(selector):
        if page.locator(selector).is_visible():
          page.locator(selector).click()
          return
        if not menu.evaluate('(el)=>el.open'): trigger.click()
        page.locator(selector).click()
        if menu.evaluate('(el)=>el.open'): trigger.click()
      trigger.click()
      page.locator('#wto-kanban-board').click(position={'x':5,'y':5})
      expect(menu).not_to_have_attribute('open','')
      assert hashlib.sha256((ROOT/'assets/js/portal.js').read_bytes()).hexdigest()[:12] in page.locator('script[src*="portal.js"]').get_attribute('src'), 'portal URL must change when its bytes change; version alone can retain stale CDN assets'
      assert hashlib.sha256((ROOT/'assets/css/portal.css').read_bytes()).hexdigest()[:12] in page.locator('link[href*="portal.css"]').get_attribute('href'), 'portal CSS must carry its content fingerprint'
      expect(page.locator('#wto-opening-status')).to_contain_text('geschlossen')
      expect(page.locator('#wto-badge-res')).to_have_text('60')
      # Every active card, including the second card, keeps its action buttons.
      expect(page.locator('.btn-order-accept')).to_have_count(2)
      expect(page.locator('.wto-card-payment').first).to_have_text('Barzahlung vor Ort')
      expect(page.locator('#wto-orders-pagination')).not_to_be_visible()
      expect(page.locator('#wto-orders-feedback')).to_be_empty()
      if width >= 1024:
        assert page.locator('#wto-kanban-board').bounding_box()['height'] >= height * .82, 'daily controls must leave enough space for orders'
      menu_click('#wto-help-toggle')
      expect(page.locator('#wto-dialog-help')).to_be_visible()
      expect(page.locator('#wto-dialog-help')).to_contain_text('Zeiträume')
      page.locator('#wto-dialog-help').get_by_role('button',name='Hilfe schließen').click()

      menu_click('[data-view="archive"]')
      expect(page.locator('#wto-archive-list')).to_be_visible()
      expect(page.locator('#wto-kanban-board')).not_to_be_visible()
      expect(page.locator('#wto-orders-col-switcher')).not_to_be_visible()
      expect(page.locator('#wto-archive-list')).to_contain_text('TEST-001')
      assert page.locator('#wto-orders-pagination button').first.bounding_box()['width'] < 160, 'pagination buttons must fit their labels'
      expect(page.locator('#wto-archive-list')).to_contain_text('19.10.2026')
      expect(page.locator('#wto-archive-list')).to_contain_text('Abgeschlossen')
      assert page.locator('#wto-archive-list [class*="btn-order-"]').count()==0
      page.locator('#wto-orders-pagination').get_by_role('button',name='Weiter').click()
      expect(page.locator('#wto-archive-list')).to_contain_text('TEST-002')
      expect(page.locator('#wto-badge-res')).to_have_text('60')
      flags['archive_empty']=True; page.clock.run_for(12000)
      expect(page.locator('#wto-archive-list')).to_contain_text('Keine archivierten')
      menu_click('[data-tab="reservations"]')
      expect(page.locator('#wto-res-pagination')).not_to_be_visible()
      page.locator('#wto-btn-new-reservation').click()
      expect(page.locator('#wto-dialog-new-res')).to_be_visible()
      phone=page.locator('#wto-dialog-new-res')
      phone.locator('[name="name"]').fill('Synthetic phone guest')
      phone.locator('[name="time"]').select_option('12:30')
      page.evaluate("document.dispatchEvent(new CustomEvent('wowthai:hours-saved'))")
      expect(phone.locator('[name="time"]')).to_have_value('12:30')
      expect(phone.locator('[name="name"]')).to_have_value('Synthetic phone guest')
      flags['slots']=[]
      page.evaluate("document.dispatchEvent(new CustomEvent('wowthai:hours-saved'))")
      expect(phone.locator('[name="time"]')).to_be_disabled()
      expect(phone.locator('[type="submit"]')).to_be_disabled()
      flags['slots']=['12:00','12:30']
      page.evaluate("document.dispatchEvent(new CustomEvent('wowthai:hours-saved'))")
      expect(phone.locator('[name="time"]')).to_be_enabled()
      page.locator('#wto-dialog-new-res .wto-btn-close').click()
      if width <= 768:
        menu_click('#wto-res-col-switcher [data-col="prep"]')
        expect(page.locator('.wto-res-kanban-board .col-prep')).to_be_visible()
        expect(page.locator('.wto-res-kanban-board .col-new')).not_to_be_visible()
        menu_click('#wto-res-col-switcher [data-col="new"]')
      menu_click('.wto-res-filters [data-view="pending"]')
      expect(page.locator('#wto-res-date-input')).to_be_disabled()
      expect(page.locator('#wto-res-scope')).to_contain_text('alle Tage')
      expect(page.locator('.wto-res-date').first).to_contain_text('24.10.2026')
      expect(page.locator('#wto-kpi-res-guests')).to_have_text('120')
      expect(page.locator('#wto-kpi-res-total')).to_have_text('60')
      # Poll preserves an operator's keyboard focus and scrolled column.
      action=page.locator('.btn-res-action[data-action="confirmed"]').first
      action.focus()
      page.locator('#wto-res-list .wto-cards-scroll').first.evaluate('(el)=>el.scrollTop=90')
      flags['note']='Fixture updated note'
      page.clock.run_for(12000)
      expect(page.locator('.btn-res-action[data-action="confirmed"]').first).to_be_focused()
      assert page.locator('#wto-res-list .wto-cards-scroll').first.evaluate('(el)=>el.scrollTop')>=80
      menu_click('[data-shift="dinner"]')
      expect(page.locator('#wto-kpi-res-total')).to_have_text('0')
      expect(page.locator('#wto-m-res-new')).to_have_text('0')
      expect(page.locator('#wto-badge-res')).to_have_text('60')
      menu_click('[data-tab="customers"]')
      trigger.click()
      expect(menu.locator('#wto-cust-consent')).to_be_visible()
      menu.locator('#wto-cust-consent').select_option('eligible')
      trigger.click()
      expect(page.locator('#wto-cust-search')).to_be_visible()
      flags['daily_fail']=True
      menu_click('[data-tab="stats"]')
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
      expect(page.locator('#wto-badge-res')).to_have_text('60')
      expect(page.locator('#wto-badge-orders')).to_have_text('2')
      expect(page.locator('#wto-sync-status')).to_contain_text('veraltet')
      if width <= 768:
        expect(page.locator('#wto-network-dot')).to_have_class('wto-network-dot stale')
      flags['fail']=False; page.clock.run_for(12000)
      expect(page.locator('#wto-opening-status')).to_contain_text('geöffnet')
      assert flags['posts']==0,'read-only navigation/polling must not mutate anything'
      assert not errors,errors
      assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'), 'horizontal overflow'
      menu_click('[data-tab="reservations"]')
      menu_click('[data-shift="all"]')
      expect(page.locator('#wto-kpi-res-total')).to_have_text('60')
      expect(page.locator('.wto-res-card').first).to_contain_text('Fixture reservation')
      folder=Path(os.environ.get('PORTAL_TEST_ARTIFACTS',str(Path(tempfile.gettempdir())/'wowthai-portal-artifacts')));folder.mkdir(parents=True,exist_ok=True)
      page.screenshot(path=str(folder/f'reservations-before-{width}.png'))
      if height >= 600:
        assert page.locator('#wto-res-list').bounding_box()['height'] >= page.viewport_size['height'] * (.66 if width==320 else .70), 'booking controls must leave most of the portrait viewport for reservations'
      page.screenshot(path=str(folder/f'reservations-{width}.png'))
      trigger.click()
      panel=menu.locator('.wto-command-panel')
      if width == 390: page.set_viewport_size({'width':390,'height':667})
      page.wait_for_function("document.querySelector('.wto-command-panel').scrollTop===0")
      assert panel.evaluate('(el)=>el.scrollTop')==0, 'reopened menu must start at the first area'
      box=panel.bounding_box()
      header=page.locator('.wto-command-header').bounding_box()
      assert box['y']>=header['y']+header['height'], 'open menu must sit below the whole header, preserving primary queue controls'
      assert page.locator('.wto-primary-nav .wto-nav-tab').evaluate_all('buttons=>buttons.every(button=>{const r=button.getBoundingClientRect();return button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})'), 'queue button centers must remain unobscured while menu is open'
      assert abs(box['x']+box['width']-(width-(8 if width<=768 else 12)))<=1, 'dropdown must align right'
      assert box['x'] >= 0 and box['x']+box['width'] <= width+1, 'dropdown must fit the viewport'
      assert box['y']+box['height'] <= page.viewport_size['height']+1, 'dropdown must scroll within short screens'
      page.screenshot(path=str(folder/f'menu-{width}.png'))
      trigger.click()
      menu_click('[data-tab="orders"]')
      menu_click('[data-view="kanban"]')
      expect(page.locator('.btn-order-accept')).to_have_count(2)
      expect(page.locator('#wto-orders-pagination')).not_to_be_visible()
      page.screenshot(path=str(folder/f'orders-{width}.png'))
      # Cancelled rows are separate from guests in-house and remain reachable on short screens.
      flags['mixed_res']=True
      menu_click('[data-tab="reservations"]')
      menu_click('.wto-res-filters [data-view="all"]')
      expect(page.locator('.wto-cancelled')).to_be_visible()
      expect(page.locator('.col-ready .wto-res-card')).to_have_count(1)
      expect(page.locator('.col-ready .btn-res-action')).to_have_count(0)
      if width == 390: page.set_viewport_size({'width':390,'height':667})
      disclosure=page.locator('.wto-cancelled')
      disclosure.locator('summary').click()
      expect(disclosure.locator('.wto-res-card')).to_have_count(5)
      assert page.locator('#wto-res-list').evaluate('(el)=>el.scrollHeight<=el.clientHeight+1 || ["auto","scroll"].includes(getComputedStyle(el).overflowY)'), 'overflowing history requires a user-scrollable outer list'
      # Both the outer list and inner history scrollport must let the final card enter view.
      page.locator('#wto-res-list').evaluate('(el)=>el.scrollTop=el.scrollHeight')
      disclosure.locator('.wto-cancelled-cards').evaluate('(el)=>el.scrollTop=el.scrollHeight')
      last=disclosure.locator('.wto-res-card').last.bounding_box()
      outer=page.locator('#wto-res-list').bounding_box()
      assert last['y']+last['height'] <= outer['y']+outer['height']+1, 'cancelled history bottom must remain reachable on short screens'
      disclosure.locator('summary').focus(); page.clock.run_for(12000)
      expect(disclosure).to_have_attribute('open','')
      expect(disclosure.locator('summary')).to_be_focused()
      # Hours editor uses only intercepted synthetic schedule writes.
      assert flags['hours_posts']==[], 'hours editor loads lazily and never saves on navigation'
      menu_click('[data-tab="hours"]')
      expect(page.locator('#wto-hours-message')).to_contain_text('Veröffentlichter Stand geladen')
      expect(page.locator('#wto-hours-weekly-fields')).not_to_have_attribute('disabled','')
      expect(page.locator('.wto-orders-toolbar')).not_to_be_visible()
      expect(page.locator('.wto-primary-nav [data-tab="orders"]')).to_be_visible()
      expect(page.locator('.wto-primary-nav [data-tab="reservations"]')).to_be_visible()
      expect(trigger).to_have_text('Menü▾')
      expect(menu.locator('[data-tab="hours"]')).to_have_class('wto-nav-tab active')
      expect(menu.locator('[data-tab="hours"]')).to_have_attribute('aria-current','page')
      assert hashlib.sha256((ROOT/'assets/js/portal-hours.js').read_bytes()).hexdigest()[:12] in page.locator('script[src*="portal-hours.js"]').get_attribute('src')
      assert page.locator('#wto-hours-start').evaluate('(input)=>getComputedStyle(input).colorScheme')=='dark', 'native calendar controls must use the dark scheme'
      assert page.locator('[data-time="start"]').first.evaluate('(input)=>getComputedStyle(input).colorScheme')=='dark', 'native time controls must use the dark scheme'
      # Reproduce the live report: changing Von beyond the old Bis value must
      # keep the date range valid and make the two-step save flow obvious.
      expect(page.locator('#wto-hours-save')).not_to_be_visible()
      expect(page.locator('#wto-hours-check')).to_have_class('wto-btn btn-primary')
      page.locator('#wto-hours-tab-exceptions').click()
      start=page.locator('#wto-hours-start'); end=page.locator('#wto-hours-end')
      start.fill('2026-12-24'); start.press('Tab')
      expect(end).to_have_attribute('min','2026-12-24')
      expect(end).to_have_value('2026-12-24')
      expect(page.locator('#wto-hours-preview-date')).to_have_value('2026-12-24')
      end.fill('2026-10-24'); end.press('Tab')
      expect(end).to_have_attribute('aria-invalid','true')
      expect(page.locator('#wto-hours-date-error')).to_be_visible()
      expect(page.locator('#wto-hours-date-error')).to_contain_text('Enddatum darf nicht vor dem Startdatum liegen')
      page.locator('#wto-hours-tab-preview').click()
      before=len(flags['hours_posts']); page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-tab-exceptions')).to_have_attribute('aria-selected','true')
      expect(end).to_be_focused()
      assert len(flags['hours_posts'])==before, 'invalid date range must not reach the preview API'
      end.fill('2026-12-25'); end.press('Tab')
      expect(end).not_to_have_attribute('aria-invalid','true')
      expect(page.locator('#wto-hours-date-error')).not_to_be_visible()
      flags['conflicts']=False; page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-save')).to_be_visible()
      expect(page.locator('#wto-hours-save')).to_be_enabled()
      body=flags['hours_posts'][-1]['body']
      assert body['kind']=='exceptions' and body['exception']['mode']=='closed' and body['exception']['start_date']=='2026-12-24' and body['exception']['end_date']=='2026-12-25' and body['preview_date']=='2026-12-24'
      flags['conflicts']=True
      page.locator('#wto-hours-tab-weekly').click()
      monday=page.locator('[data-day="1"]')
      expect(monday.locator('[data-closed]')).to_be_checked()
      tuesday=page.locator('[data-day="2"]')
      tuesday.locator('[data-add]').click()
      tuesday.locator('[data-time="start"]').last.fill('17:00')
      tuesday.locator('[data-time="end"]').last.fill('20:00')
      flags['preview_hold']=True
      page.locator('#wto-hours-check').click()
      page.wait_for_function("document.getElementById('wto-hours-message').textContent.includes('geprüft') || document.getElementById('wto-hours-message').textContent.includes('werden geprüft')")
      tuesday.locator('[data-time="end"]').last.fill('20:15')
      assert flags['held_preview'] is not None
      held,preview=flags['held_preview']; held.fulfill(status=200,content_type='application/json',body=json.dumps(preview))
      expect(page.locator('#wto-hours-save')).to_be_disabled()
      expect(page.locator('#wto-hours-result')).to_be_empty()
      flags['preview_hold']=False
      tuesday.locator('[data-time="end"]').last.fill('20:00')
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-result')).to_contain_text('12:30, 13:00')
      expect(page.locator('#wto-hours-conflicts')).to_contain_text('Bestellung #17')
      expect(page.locator('#wto-hours-conflicts')).to_contain_text('Datum unbekannt')
      expect(page.locator('#wto-hours-save')).to_be_visible()
      expect(page.locator('#wto-hours-save')).to_be_disabled()
      expect(page.locator('#wto-hours-ack')).not_to_be_checked()
      assert flags['hours_posts'][-1]['body']['weekly']['2']==[['11:00','14:00'],['17:00','20:00']]
      page.locator('#wto-hours-ack').check()
      expect(page.locator('#wto-hours-save')).to_be_enabled()
      # Any edit cancels the signed preview, including edits after tab changes.
      page.locator('#wto-hours-tab-weekly').click()
      tuesday.locator('[data-time="end"]').last.fill('20:30')
      expect(page.locator('#wto-hours-save')).to_be_disabled()
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-ack-label')).to_be_visible()
      page.locator('#wto-hours-ack').check()
      flags['save_status']=409
      page.locator('#wto-hours-save').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('veraltet')
      expect(page.locator('#wto-hours-check')).to_be_disabled()
      page.locator('#wto-hours-reload').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('Entwurf bleibt erhalten')
      page.locator('#wto-hours-tab-weekly').click()
      expect(tuesday.locator('[data-time="end"]').last).to_have_value('20:30')
      flags['save_status']=500
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-ack-label')).to_be_visible()
      page.locator('#wto-hours-ack').check(); page.locator('#wto-hours-save').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('Ihr Entwurf bleibt erhalten')
      expect(page.locator('#wto-hours-save')).to_be_disabled()
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-ack-label')).to_be_visible()
      page.locator('#wto-hours-ack').check()
      flags['save_status']=200; flags['save_hold']=True
      page.evaluate("window.savedEvents=0; document.addEventListener('wowthai:hours-saved',()=>window.savedEvents++)")
      page.locator('#wto-hours-save').click()
      expect(page.locator('#wto-hours-save')).to_be_disabled()
      expect(page.locator('#wto-hours-weekly-fields')).to_have_attribute('disabled','')
      sent=len(flags['hours_posts'])
      page.locator('#wto-hours-save').dispatch_event('click')
      assert len(flags['hours_posts'])==sent, 'in-flight save must not be sent twice'
      assert flags['held_save'] is not None
      reads=flags['summary_reads']
      flags['held_save'].fulfill(status=200,content_type='application/json',body=json.dumps({'weekly':{str(i):[] for i in range(1,8)},'exceptions':{},'revision':'saved-weekly','can_edit':True,'timezone':'Europe/Vienna'}))
      expect(page.locator('#wto-hours-message')).to_contain_text('gespeichert')
      page.wait_for_function('window.savedEvents===1')
      assert flags['summary_reads']>reads, 'successful save immediately refreshes staff kitchen status'
      assert flags['hours_posts'][-1]['body']['revision']=='r1'
      flags['save_hold']=False; flags['hours_revision']='saved-weekly'
      # Existing exceptions remain visible; selecting one only prefills a draft.
      page.locator('#wto-hours-reload').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('Entwurf bleibt erhalten')
      page.locator('#wto-hours-tab-exceptions').click()
      page.locator('.wto-hours-existing summary').click()
      before=len(flags['hours_posts'])
      page.locator('#wto-hours-existing button').filter(has_text='Fixture custom').click()
      expect(page.locator('#wto-hours-start')).to_have_value('2026-10-25')
      expect(page.locator('#wto-hours-mode')).to_have_value('hours')
      expect(page.locator('#wto-hours-reason')).to_have_value('Fixture custom')
      expect(page.locator('#wto-hours-custom-ranges input').first).to_have_value('12:00')
      assert len(flags['hours_posts'])==before
      page.locator('#wto-hours-end').fill('2028-10-25')
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('maximal 367 Tage')
      assert len(flags['hours_posts'])==before
      page.locator('#wto-hours-end').fill('2026-10-26')
      page.locator('#wto-hours-mode').select_option('remove')
      flags['conflicts']=False
      page.locator('#wto-hours-check').click()
      expect(page.locator('#wto-hours-conflicts')).to_contain_text('Keine betroffenen')
      expect(page.locator('#wto-hours-save')).to_be_enabled()
      page.locator('#wto-hours-save').click()
      expect(page.locator('#wto-hours-message')).to_contain_text('gespeichert')
      body=flags['hours_posts'][-1]['body']
      assert body['kind']=='exceptions' and body['exception']['mode']=='remove' and body['exception']['start_date']=='2026-10-25' and body['exception']['end_date']=='2026-10-26' and body['revision']=='saved-weekly'
      flags['zero_counts']=True; page.clock.run_for(12000)
      expect(page.locator('#wto-badge-orders')).to_have_text('0')
      expect(page.locator('#wto-badge-res')).to_have_text('0')
      expect(page.locator('#wto-badge-orders')).to_be_visible()
      expect(page.locator('#wto-badge-res')).to_be_visible()
      flags['zero_counts']=False
      # Published date preview remains accessible without a write capability.
      flags['can_edit']=False
      page.reload()
      menu_click('[data-tab="hours"]')
      expect(page.locator('#wto-hours-permission')).to_contain_text('Nur Lesezugriff')
      expect(page.locator('#wto-hours-weekly-fields')).to_have_attribute('disabled','')
      expect(page.locator('#wto-hours-save')).not_to_be_visible()
      expect(page.locator('#wto-hours-check')).to_be_disabled()
      page.locator('#wto-hours-tab-preview').click()
      page.locator('#wto-hours-published').click()
      expect(page.locator('#wto-hours-preview-source')).to_contain_text('Veröffentlichter Stand')
      expect(page.locator('#wto-hours-result')).to_contain_text('12:00, 12:30')
      # Deep links prefill only and remain non-writing.
      before=len(flags['hours_posts'])
      flags['can_edit']=True
      page.goto(f'http://127.0.0.1:{server.server_port}/fixture?view=hours&hours_date=2026-10-27&hours_mode=closed&hours_reason=Fixture+legacy')
      expect(page.locator('#wto-hours-start')).to_have_value('2026-10-27')
      expect(page.locator('#wto-hours-reason')).to_have_value('Fixture legacy')
      expect(page.locator('#wto-hours-tab-exceptions')).to_have_attribute('aria-selected','true')
      assert len(flags['hours_posts'])==before, 'legacy links must never auto-preview or save'
      page.screenshot(path=str(folder/f'hours-{width}.png'))
      assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'), 'hours horizontal overflow'
      flags['hours_fail']=True
      page.reload()
      expect(page.locator('#wto-hours-message')).to_contain_text('unavailable')
      expect(page.locator('#wto-hours-weekly-fields')).to_have_attribute('disabled','')
      expect(page.locator('#wto-hours-save')).not_to_be_visible()
      assert flags['posts']==0, 'only synthetic hours writes are allowed; no order/booking mutation'
      before=len(flags['hours_posts']); reads=flags['summary_reads']
      page.goto(f'http://127.0.0.1:{server.server_port}/fixture-anonymous?view=hours')
      expect(page.locator('#wto-portal-login-form')).to_be_visible()
      expect(page.locator('#wto-portal-app')).to_have_count(0)
      assert len(flags['hours_posts'])==before and flags['summary_reads']==reads, 'login must not initialize staff API reads/writes'
      assert not errors, errors
      print(f'PASS: {width}px archive/pagination/readonly, booking dates/filter/counts/focus/scroll, daily details/refunds, polling/open/failure/recovery; hours permissions/preview/conflicts/409/500/inflight/legacy')
      page.close()
    browser.close()
finally: server.shutdown()
