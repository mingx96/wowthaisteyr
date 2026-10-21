"""Visual contract for the public Homepage and reservation template."""
import functools
import http.server
import json
import subprocess
import threading
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
fixtures = {
    name: subprocess.check_output(
        ['php', str(ROOT / 'dev/tests/hours-public-fixture.php'), name], text=True
    )
    for name in ['booking', 'home']
}


class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        name = self.path.strip('/')
        if name in fixtures:
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(fixtures[name].encode())
            return
        super().do_GET()


def availability(day):
    return {
        'date': day,
        'timezone': 'Europe/Vienna',
        'ranges': [['12:00', '15:00']],
        'reservation_slots': ['12:30'],
        'pickup_slots': ['12:15'],
        'display_rows': [{'days': 'Mo – So', 'hours': '12:00 – 15:00 Uhr', 'weekdays': list(range(1, 8))}],
        'opening_status': {'is_open': True, 'status_type': 'open', 'status_label': 'Jetzt geöffnet', 'status_detail': 'Fixture'},
        'revision': 'design-test',
        'schema_opening_hours': [],
    }


server = http.server.ThreadingHTTPServer(
    ('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT))
)
threading.Thread(target=server.serve_forever, daemon=True).start()

try:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        for width, height in [(320, 667), (390, 844), (768, 1024), (1440, 900)]:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))

            def route(request_route):
                day = parse_qs(urlparse(request_route.request.url).query).get('date', [''])[0]
                request_route.fulfill(content_type='application/json', body=json.dumps(availability(day)))

            page.route('**/api/availability?**', route)
            page.goto(f'http://127.0.0.1:{server.server_port}/home')
            page.wait_for_load_state('networkidle')

            expect(page.locator('.hero-actions .action-icon')).to_have_count(2)
            expect(page.locator('.home-reservation .action-icon')).to_have_count(1)
            assert page.locator('.action-icon:not([aria-hidden="true"])').count() == 0
            assert page.locator('body').inner_text().find('↗') == -1
            assert page.locator('body').inner_text().find('→') == -1
            assert page.evaluate("document.fonts.check('16px Playfair Display')")
            assert 'Playfair Display' in page.locator('#home-title').evaluate('el => getComputedStyle(el).fontFamily')
            if width > 768:
                heading_metrics = page.locator('#home-title').evaluate(
                    'el => ({height:el.getBoundingClientRect().height,lineHeight:parseFloat(getComputedStyle(el).lineHeight)})'
                )
                # Two display lines plus the small SEO badge that lives inside the h1.
                assert heading_metrics['height'] <= heading_metrics['lineHeight'] * 2.6, heading_metrics
            assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')

            hero_buttons = page.locator('.hero-actions .button')
            expect(hero_buttons).to_have_count(2)
            first_style = hero_buttons.nth(0).evaluate(
                'el => ({bg:getComputedStyle(el).backgroundImage,bgc:getComputedStyle(el).backgroundColor,border:getComputedStyle(el).borderColor,height:el.getBoundingClientRect().height})'
            )
            second_style = hero_buttons.nth(1).evaluate(
                'el => ({bg:getComputedStyle(el).backgroundImage,bgc:getComputedStyle(el).backgroundColor,border:getComputedStyle(el).borderColor,height:el.getBoundingClientRect().height})'
            )
            assert first_style['bg'] == second_style['bg']
            assert first_style['bgc'] == second_style['bgc']
            assert first_style['border'] == second_style['border']
            assert abs(first_style['height'] - second_style['height']) < .1
            assert first_style['height'] >= 44
            icon_style = page.locator('.hero-actions .action-icon').first.evaluate(
                'el => ({w:el.getBoundingClientRect().width,h:el.getBoundingClientRect().height,r:getComputedStyle(el).borderRadius,b:getComputedStyle(el).borderTopWidth})'
            )
            assert icon_style['w'] >= 24 and icon_style['w'] == icon_style['h']
            assert icon_style['r'] == '50%' and icon_style['b'] != '0px'

            page.goto(f'http://127.0.0.1:{server.server_port}/booking')
            page.wait_for_load_state('networkidle')
            expect(page.locator('#res_time')).to_have_value('12:30')
            assert 'Playfair Display' in page.locator('.reservation-intro h1').evaluate('el => getComputedStyle(el).fontFamily')
            assert 'Playfair Display' in page.locator('.booking-title').evaluate('el => getComputedStyle(el).fontFamily')
            panel = page.locator('.reservation-panel').bounding_box()
            aside = page.locator('.reservation-aside').bounding_box()
            if width <= 768:
                mobile_menu = page.locator('.reservation-menu-link--mobile')
                expect(mobile_menu).to_be_visible()
                menu_box = mobile_menu.bounding_box()
                assert panel['y'] < 300, f'{width}px: booking panel starts too low at {panel["y"]}'
                assert panel['y'] < menu_box['y'] < aside['y']
                expect(page.locator('.reservation-menu-link--desktop')).to_be_hidden()
            else:
                expect(page.locator('.reservation-menu-link--desktop')).to_be_visible()
                expect(page.locator('.reservation-menu-link--mobile')).to_be_hidden()
                assert aside['x'] < panel['x'] and abs(aside['y'] - panel['y']) < 2
            assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
            assert not errors, errors
            page.close()
        browser.close()
    print('PASS public design browser contract: SVG CTA, restored local Playfair, equal glass actions, reservation form priority at 320/390/768 and desktop grid')
finally:
    server.shutdown()
