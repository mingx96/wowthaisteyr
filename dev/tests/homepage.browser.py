"""Actual public templates: safe dish discovery, fresh hours, responsive layout.

All server/API/catalog data is synthetic. No production transactions or requests.
"""
import functools
import http.server
import json
import os
import subprocess
import threading
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
ARTIFACTS = Path(os.environ.get('HOMEPAGE_TEST_ARTIFACTS', '/tmp/wowthai-homepage-artifacts'))
ARTIFACTS.mkdir(parents=True, exist_ok=True)
fixtures = {
    name: subprocess.check_output(['php', str(ROOT / 'dev/tests/homepage-fixture.php'), name], text=True)
    for name in ['home', 'home-empty', 'home-images', 'menu', 'menu-lunch']
}
for name, html in fixtures.items():
    assert 'Fatal error' not in html and 'Warning:' not in html, (name, html[:1000])


class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        name = urlparse(self.path).path.strip('/') or 'home'
        if name in fixtures:
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(fixtures[name].encode())
            return
        super().do_GET()


def availability(day, closed=False):
    return {
        'date': day, 'timezone': 'Europe/Vienna',
        'ranges': [] if closed else [['12:00', '15:00'], ['18:00', '21:00']],
        'reservation_slots': [] if closed else ['12:30'], 'pickup_slots': [],
        'display_rows': [{'days': 'Mo – So', 'hours': '12:00 – 15:00 · 18:00 – 21:00 Uhr', 'weekdays': list(range(1, 8))}],
        'opening_status': {'is_open': not closed, 'status_type': 'preorder' if closed else 'open',
                           'status_label': 'Heute geschlossen' if closed else 'Jetzt geöffnet',
                           'status_detail': 'Geschlossen laut Tagesplan' if closed else 'Küche bis 15:00 Uhr'},
        'revision': 'home-closed' if closed else 'home-open', 'schema_opening_hours': [],
    }


server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT)))
threading.Thread(target=server.serve_forever, daemon=True).start()
origin = f'http://127.0.0.1:{server.server_port}'

try:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        for width, height in [(320, 667), (390, 844), (768, 1024), (1440, 900)]:
            page = browser.new_page(viewport={'width': width, 'height': height}, reduced_motion='reduce')
            errors, writes, outgoing = [], [], []
            state = {'mode': 'open'}
            page.on('pageerror', lambda error: errors.append(str(error)))

            def route(request_route):
                request = request_route.request
                if not request.url.startswith(origin):
                    outgoing.append(request.url)
                    request_route.abort()
                elif request.method != 'GET':
                    writes.append(request.url)
                    request_route.fulfill(status=500, body='No writes allowed')
                elif '/api/availability' in request.url:
                    if state['mode'] == 'error':
                        request_route.fulfill(status=503, body='Unavailable')
                    else:
                        day = parse_qs(urlparse(request.url).query)['date'][0]
                        request_route.fulfill(content_type='application/json', body=json.dumps({**availability(day, state['mode'] == 'closed'),'lunch_available_on_date':state.get('lunch',False),'m4_available_on_date':state.get('m4',False)}))
                elif '/ajax' in request.url:
                    request_route.fulfill(content_type='application/json', body=json.dumps({'success': True, 'data': {'items': [], 'count': 0, 'total': '€0,00'}}))
                else:
                    request_route.continue_()

            page.route('**/*', route)
            page.goto(origin + '/home')
            expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'open')
            expect(page.locator('[data-wowthai-today-hours]')).to_contain_text('12:00 – 15:00')
            expect(page.locator('.home-dish')).to_have_count(3)
            expect(page.locator('.home-dish img')).to_have_count(0)
            assert page.locator('.home-dish').first.get_attribute('href') == '/menu/#gericht-56'
            assert page.locator('.home-dish').first.inner_text().find('ab €13,50') >= 0
            if page.locator('#mobile-nav-toggle').is_visible():
                page.locator('#mobile-nav-toggle').click()
                expect(page.locator('.mobile-nav-footer')).to_contain_text('12:00 – 15:00')
                assert 'Montags Ruhetag' not in page.locator('.mobile-nav-footer').inner_text()
                page.locator('#mobile-nav-close').click()
            else:
                assert '12:00 – 15:00' in page.locator('.mobile-nav-footer').text_content()
            assert page.locator('.hero-photo').evaluate('el => getComputedStyle(el).animationName') == 'none'
            assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
            assert page.locator('h1').count() == 1
            buttons = page.locator('.hero-actions .button').all()
            assert len(buttons) == 2
            for button in buttons:
                assert abs(button.bounding_box()['height'] - 48) < .5
            for link in page.locator('.home-dish, .home-visit a, .home-weekly summary, .home-footer a').all():
                if link.is_visible():
                    assert link.bounding_box()['height'] >= 44, link.inner_text()
            # Switch to keyboard navigation before checking :focus-visible.
            # The preceding mobile-menu clicks intentionally use pointer input.
            page.keyboard.press('Tab')
            page.locator('.home-dish').first.focus()
            assert page.locator('.home-dish').first.evaluate('el => getComputedStyle(el).outlineStyle') != 'none'
            page.screenshot(path=str(ARTIFACTS / f'homepage-{width}.png'), full_page=True)

            # A failed refresh must not keep advertising a fresh open status.
            state['mode'] = 'error'
            page.evaluate("document.dispatchEvent(new Event('wowthai:hours-saved'))")
            expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'stale')
            expect(page.locator('[data-wowthai-home-feedback]')).to_contain_text('telefonisch')
            expect(page.locator('[data-wowthai-today-hours]')).to_contain_text('12:00 – 15:00')
            state['mode'] = 'closed'
            page.evaluate("document.dispatchEvent(new Event('wowthai:hours-saved'))")
            expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'closed')
            expect(page.locator('[data-wowthai-today-hours]')).to_contain_text('geschlossen')

            # Direct discovery links reveal sushi without clicking any ordering control.
            page.goto(origin + '/menu/#gericht-119')
            expect(page.locator('.menu-tab[data-menu="sushi"]')).to_have_attribute('aria-selected', 'true')
            expect(page.locator('#gericht-119')).to_be_visible()
            expect(page.locator('#gericht-119')).to_be_focused()
            assert page.locator('#cart-count').inner_text() == '0'
            page.goto(origin + '/menu/#gericht-43')
            expect(page.locator('#gericht-43')).to_be_visible()
            assert page.locator('#cart-count').inner_text() == '0'
            page.goto(origin + '/menu-lunch')
            expect(page.locator('#gericht-1 .dish-control')).to_be_hidden()
            expect(page.locator('#gericht-4 .dish-control')).to_be_hidden()
            expect(page.locator('#gericht-43 .dish-control')).to_be_visible()
            state['lunch']=True
            page.evaluate("window.dispatchEvent(new Event('focus'))")
            expect(page.locator('#gericht-1 .dish-control')).to_be_visible()
            expect(page.locator('#gericht-4 .dish-control')).to_be_hidden()
            state['m4']=True
            page.evaluate("window.dispatchEvent(new Event('focus'))")
            expect(page.locator('#gericht-4 .dish-control')).to_be_visible()
            state['lunch']=False; state['m4']=False
            page.evaluate("window.dispatchEvent(new Event('focus'))")
            expect(page.locator('#gericht-1 .dish-control')).to_be_hidden()
            expect(page.locator('#gericht-4 .dish-control')).to_be_hidden()
            page.locator('#gericht-1 .dish-control').dispatch_event('click')
            assert not writes, 'hidden lunch controls must never create a transaction'
            assert not writes, writes
            assert not outgoing, outgoing
            assert not errors, errors
            page.close()

        # A continuously visible homepage must cross closing and date boundaries.
        page = browser.new_page(viewport={'width': 390, 'height': 844})
        page.clock.install(time=datetime(2026, 10, 21, 12, 59, 30, tzinfo=timezone.utc))
        clock_state = {'closed': False, 'hang': False}
        requested_dates = []
        held_routes = []

        def clock_route(request_route):
            request = request_route.request
            if not request.url.startswith(origin) or request.method != 'GET':
                raise AssertionError(f'Unexpected request: {request.method} {request.url}')
            if '/api/availability' in request.url:
                day = parse_qs(urlparse(request.url).query)['date'][0]
                requested_dates.append(day)
                if clock_state['hang']:
                    held_routes.append(request_route)
                else:
                    request_route.fulfill(content_type='application/json', body=json.dumps(availability(day, clock_state['closed'])))
            elif '/ajax' in request.url:
                request_route.fulfill(content_type='application/json', body=json.dumps({'success': True, 'data': {'items': [], 'count': 0, 'total': '€0,00'}}))
            else:
                request_route.continue_()

        page.route('**/*', clock_route)
        page.goto(origin + '/home')
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'open')
        assert requested_dates == ['2026-10-21']
        clock_state['closed'] = True
        page.clock.run_for(60_001)
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'closed')
        assert len(requested_dates) == 2
        clock_state['hang'] = True
        with page.expect_request('**/api/availability?**'):
            page.clock.fast_forward(60_001)
        page.clock.run_for(10_001)
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'stale')
        expect(page.locator('[data-wowthai-home-label]')).to_have_text('Zeiten nicht bestätigt')
        # Resolve Playwright's intercepted route after the browser aborts it.
        # Otherwise its pending route handler can hold later interceptions.
        for held in held_routes:
            held.abort()
        held_routes.clear()
        # Yesterday's cached ranges cannot remain labeled as today's on a failure.
        page.clock.set_system_time(datetime(2026, 10, 21, 22, 0, 0, tzinfo=timezone.utc))
        with page.expect_request('**/api/availability?**'):
            page.clock.fast_forward(60_001)
        page.clock.run_for(10_001)
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'error')
        assert requested_dates[-1] == '2026-10-22', (requested_dates, page.evaluate('new Date().toISOString()'))
        expect(page.locator('[data-wowthai-today-hours]')).to_have_text('Heute bitte telefonisch erfragen.')
        for held in held_routes:
            held.abort()
        held_routes.clear()
        clock_state.update({'hang': False, 'closed': False})
        with page.expect_request('**/api/availability?**'):
            page.clock.fast_forward(60_001)
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'open')
        page.close()

        # The weekly plan remains usable without JavaScript or a loading promise.
        page = browser.new_page(viewport={'width': 390, 'height': 844}, java_script_enabled=False)
        page.goto(origin + '/home')
        expect(page.locator('[data-wowthai-home-status]')).to_be_hidden()
        page.locator('.home-weekly summary').click()
        expect(page.locator('.home-weekly [data-wowthai-hours-row]').first).to_be_visible()
        page.close()

        # Empty and photo-bearing catalogs must both remain usable.
        page = browser.new_page(viewport={'width': 390, 'height': 844})
        page.route('**/api/availability?**', lambda route: route.fulfill(status=503, body='Unavailable'))
        page.goto(origin + '/home-empty')
        expect(page.locator('[data-wowthai-home-status]')).to_have_attribute('data-state', 'error')
        expect(page.locator('.home-dish')).to_have_count(0)
        expect(page.locator('#besuch')).to_be_visible()
        assert page.locator('.hero-scroll').get_attribute('href') == '#besuch'
        page.goto(origin + '/home-images')
        expect(page.locator('.home-dish img')).to_have_count(3)
        for photo in page.locator('.home-dish img').all():
            assert photo.get_attribute('loading') == 'lazy'
            assert photo.get_attribute('width') and photo.get_attribute('height')
        assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
        browser.close()
    print('PASS homepage browser: managed cards, safe read-only dish anchors, live/error/closed hours, foreground refresh/timeout/midnight recovery, no-JS fallback, responsive and reduced motion at 320/390/768/1440')
finally:
    server.shutdown()
