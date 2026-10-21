"""Actual public templates: manual media, safe dish discovery, fresh hours and layout.

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
    for name in ['home', 'home-empty', 'home-images', 'menu']
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
            errors, writes, outgoing, film_requests = [], [], [], []
            state = {'mode': 'open'}
            page.on('pageerror', lambda error: errors.append(str(error)))

            def route(request_route):
                request = request_route.request
                if urlparse(request.url).path.endswith('.mp4'):
                    film_requests.append(request.url)
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
                        request_route.fulfill(content_type='application/json', body=json.dumps(availability(day, state['mode'] == 'closed')))
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

            # The gallery must reach its last item without assuming equal slide widths.
            gallery = page.locator('[data-home-gallery]')
            track = gallery.locator('[data-home-gallery-track]')
            slides = track.locator('[data-home-gallery-slide]')
            controls = gallery.locator('[data-home-gallery-controls]')
            previous = gallery.locator('[data-home-gallery-prev]')
            following = gallery.locator('[data-home-gallery-next]')
            status = gallery.locator('[data-home-gallery-status]')
            expect(slides).to_have_count(3)
            expect(controls).to_be_visible()
            expect(previous).to_be_disabled()
            expect(status).to_have_text('Moment 1 von 3')
            film = gallery.locator('[data-home-film]')
            expect(film).to_have_count(1)
            assert film.get_attribute('controls') is not None
            assert film.get_attribute('playsinline') is not None
            assert film.get_attribute('preload') == 'none'
            assert film.get_attribute('autoplay') is None and film.get_attribute('loop') is None
            assert film.get_attribute('poster').startswith('/assets/img/homepage/')
            assert film.evaluate('video => video.paused && video.readyState === 0')
            assert not film_requests, film_requests
            if width in (390, 1440):
                page.evaluate('document.activeElement?.blur()')
                gallery.screenshot(path=str(ARTIFACTS / f'homepage-gallery-initial-{width}.png'))
            # Manual playback remains available even with reduced motion selected.
            film.scroll_into_view_if_needed()
            with page.expect_request(lambda request: urlparse(request.url).path.endswith('.mp4')):
                film.evaluate('video => video.play()')
            page.wait_for_function('video => !video.paused && video.currentTime > 0', arg=film.element_handle())
            for _ in range(3):
                if following.is_disabled():
                    break
                following.click()
            expect(following).to_be_disabled()
            expect(status).to_have_text('Moment 3 von 3')
            assert film.evaluate('video => video.paused')
            expect(track).to_be_focused()
            assert slides.last.bounding_box()['x'] + slides.last.bounding_box()['width'] <= track.bounding_box()['x'] + track.bounding_box()['width'] + 1
            previous.click()
            expect(following).to_be_enabled()

            # Real responsive assets load locally; decorative motion stays absent.
            expect(slides.locator('img')).to_have_count(2)
            for photo in slides.locator('img').all():
                photo.scroll_into_view_if_needed()
                page.wait_for_function('image => image.complete && image.naturalWidth > 0', arg=photo.element_handle())
                assert photo.evaluate('image => image.currentSrc').startswith(origin + '/assets/img/homepage/')
                assert photo.get_attribute('srcset') and photo.get_attribute('sizes')
                assert photo.get_attribute('width') and photo.get_attribute('height')
                assert photo.get_attribute('loading') == 'lazy'
                assert photo.get_attribute('alt')
            for reveal in page.locator('[data-home-reveal]').all():
                reveal.scroll_into_view_if_needed()
                assert reveal.evaluate('el => getComputedStyle(el).opacity') == '1'
                assert reveal.evaluate('el => el.getAnimations().length') == 0

            if width == 1440:
                # A narrower container exposes the unequal desktop photo widths.
                track.evaluate("el => { el.style.width = '520px'; el.scrollTo({left: 0, behavior: 'instant'}); }")
                expect(previous).to_be_disabled()
                following.click()
                page.wait_for_function("""track => Math.abs(
                    track.querySelectorAll('[data-home-gallery-slide]')[1].getBoundingClientRect().left
                    - track.getBoundingClientRect().left) < 2""", arg=track.element_handle())
                expect(status).to_have_text('Moment 2 von 3')
                following.click()
                expect(following).to_be_disabled()
                previous.click()
                page.wait_for_function("""track => Math.abs(
                    track.querySelectorAll('[data-home-gallery-slide]')[1].getBoundingClientRect().left
                    - track.getBoundingClientRect().left) < 2""", arg=track.element_handle())
                # Controls follow container resizing, including when every photo fits.
                track.evaluate("el => { el.style.width = '1400px'; }")
                expect(controls).to_be_hidden()
                track.evaluate("el => { el.style.width = ''; el.scrollTo({left: 0, behavior: 'instant'}); }")
                expect(controls).to_be_visible()

            # Native keyboard scrolling remains available alongside the buttons.
            track.evaluate("el => el.scrollTo({left: 0, behavior: 'instant'})")
            expect(previous).to_be_disabled()
            track.focus()
            track.press('ArrowRight')
            page.wait_for_function('track => track.scrollLeft > 0', arg=track.element_handle())
            expect(previous).to_be_enabled()
            expect(track).to_be_focused()
            if width == 390:
                # Leaving a manually playing film stops playback without a restart.
                film.scroll_into_view_if_needed()
                film.evaluate('video => video.play()')
                page.wait_for_function('video => !video.paused', arg=film.element_handle())
                page.locator('#besuch').scroll_into_view_if_needed()
                page.wait_for_function('video => video.paused', arg=film.element_handle())
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
        # A changed preference takes effect on this page, including active motion.
        track = page.locator('[data-home-gallery-track]')
        following = page.locator('[data-home-gallery-next]')
        page.locator('[data-home-reveal]').last.scroll_into_view_if_needed()
        following.click()
        page.wait_for_function('track => track.scrollLeft > 0', arg=track.element_handle())
        page.emulate_media(reduced_motion='reduce')
        for reveal in page.locator('[data-home-reveal]').all():
            page.wait_for_function('el => getComputedStyle(el).opacity === "1" && el.getAnimations().length === 0', arg=reveal.element_handle())
        track.evaluate("el => el.scrollTo({left: 0, behavior: 'instant'})")
        expect(page.locator('[data-home-gallery-prev]')).to_be_disabled()
        following.click()
        second_slide = page.locator('[data-home-gallery-slide]').nth(1)
        assert abs(second_slide.bounding_box()['x'] - track.bounding_box()['x']) < 2
        following.click()
        expect(following).to_be_disabled()
        expect(track).to_be_focused()
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

        # The weekly plan and native photo scrolling work without JavaScript.
        page = browser.new_page(viewport={'width': 390, 'height': 844}, java_script_enabled=False)
        page.goto(origin + '/home')
        expect(page.locator('[data-wowthai-home-status]')).to_be_hidden()
        expect(page.locator('[data-home-gallery-controls]')).to_be_hidden()
        track = page.locator('[data-home-gallery-track]')
        track.scroll_into_view_if_needed()
        track.focus()
        track.press('ArrowRight')
        # JS-disabled documents do not run wait_for_function's rAF callbacks.
        expect(track).not_to_have_js_property('scrollLeft', 0)
        expect(track).to_be_focused()
        for reveal in page.locator('[data-home-reveal]').all():
            assert reveal.evaluate('el => getComputedStyle(el).opacity') == '1'
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
        assert page.locator('.hero-scroll').get_attribute('href') == '#einblicke'
        expect(page.locator('#einblicke [data-home-gallery-slide]')).to_have_count(3)
        page.goto(origin + '/home-images')
        expect(page.locator('.home-dish img')).to_have_count(3)
        for photo in page.locator('.home-dish img').all():
            assert photo.get_attribute('loading') == 'lazy'
            assert photo.get_attribute('width') and photo.get_attribute('height')
        assert page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
        browser.close()
    print('PASS homepage browser: manual gallery and native film, responsive local media, unequal widths/endpoints/focus/resize, no-JS scrolling, changed reduced-motion preference, managed cards, safe read-only dish anchors, live/error/closed hours, foreground refresh/timeout/midnight recovery at 320/390/768/1440')
finally:
    server.shutdown()
