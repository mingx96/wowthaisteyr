/* Rehydrate cached public HTML from date-specific, no-store canonical availability. */
(() => {
  'use strict';
  const init = () => {
    const endpoint = window.wowthaiHoursConfig?.availability_url;
    if (!endpoint) return;
    const dateInput = document.getElementById('res_date');
    const times = document.getElementById('res_time');
    const status = document.getElementById('res_availability_status');
    const submit = document.querySelector('.booking-submit button');
    let sequence = 0;
    let revision = '';
    let selectedTime = times?.value || '';
    const homeStatus = document.querySelector('[data-wowthai-home-status]');
    const homeLabel = document.querySelector('[data-wowthai-home-label]');
    const homeHours = document.querySelector('[data-wowthai-today-hours]');
    const homeFeedback = document.querySelector('[data-wowthai-home-feedback]');
    let homeFreshDate = '';
    if (homeStatus) homeStatus.hidden = false;
    const homeText = (node, value) => {
      // Avoid repeating unchanged announcements during periodic refreshes.
      if (node && node.textContent !== value) node.textContent = value;
    };
    const today = () => {
      const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
      const part = type => parts.find(item => item.type === type).value;
      return `${part('year')}-${part('month')}-${part('day')}`;
    };
    const bookingState = (message, disabled) => {
      if (times) times.disabled = disabled;
      if (submit) submit.disabled = disabled;
      if (status) status.textContent = message;
    };
    const hydrateRows = data => {
      if (homeStatus) {
        if (!Array.isArray(data.ranges) || !data.ranges.every(range => Array.isArray(range) && range.length === 2 && range.every(value => typeof value === 'string' && /^\d{2}:\d{2}$/.test(value))) || typeof data.opening_status.is_open !== 'boolean') throw new Error('availability');
        homeStatus.dataset.state = data.opening_status.is_open ? 'open' : 'closed';
        homeText(homeLabel, data.opening_status.status_label);
        homeText(homeHours, data.ranges.length ? `${data.ranges.map(range => range.join(' – ')).join(' · ')} Uhr` : 'Heute geschlossen');
        homeText(homeFeedback, data.opening_status.status_detail);
        homeFreshDate = data.date;
      }
      document.querySelectorAll('[data-wowthai-hours-rows]').forEach(container => {
        const oldRows = [...container.querySelectorAll('[data-wowthai-hours-row]')];
        const template = oldRows[0];
        const marker = document.createComment('hours');
        if (template) template.before(marker);
        else container.insertBefore(marker, container.querySelector('[data-wowthai-hours-static]:last-child'));
        for (const row of data.display_rows) {
          const node = template ? template.cloneNode(false) : document.createElement('span');
          node.setAttribute('data-wowthai-hours-row', '');
          node.textContent = `${row.days}: ${row.hours}`;
          if (node.tagName === 'SPAN' && !container.classList.contains('hours-row')) node.appendChild(document.createElement('br'));
          marker.before(node);
        }
        oldRows.forEach(node => node.remove());
        marker.remove();
        container.dataset.hoursFresh = 'true';
      });
      document.querySelectorAll('.menu-status-banner').forEach(banner => {
        banner.classList.toggle('status-open', data.opening_status.is_open);
        banner.classList.toggle('status-preorder', !data.opening_status.is_open);
        const label = banner.querySelector('.status-content strong');
        const detail = banner.querySelector('.status-content span');
        if (label) label.textContent = `${data.opening_status.status_label}: `;
        if (detail) detail.textContent = data.opening_status.status_detail;
      });
      // An old full-page closure notice must not survive an updated schedule.
      let closure = document.querySelector('.special-closed-banner');
      if (!closure && data.opening_status.status_label === 'Heute geschlossen') {
        closure = document.createElement('div');
        closure.className = 'special-closed-banner';
        closure.setAttribute('role', 'status');
        closure.style.cssText = 'background:#3a1d16;border-bottom:1px solid #d4a359;color:#fff9f5;padding:10px 16px;text-align:center;font-size:13px;font-weight:600';
        const header = document.querySelector('.site-header');
        if (header) header.before(closure); else document.body.prepend(closure);
      }
      if (closure) {
        closure.hidden = data.opening_status.status_label !== 'Heute geschlossen';
        closure.textContent = data.opening_status.status_detail;
      }
      const updateSchema = value => {
        if (!value || typeof value !== 'object') return;
        if (Array.isArray(value)) { value.forEach(updateSchema); return; }
        const types = Array.isArray(value['@type']) ? value['@type'] : [value['@type']];
        if (types.includes('Restaurant')) value.openingHoursSpecification = data.schema_opening_hours;
        if (value['@graph']) updateSchema(value['@graph']);
      };
      document.querySelectorAll('script[type="application/ld+json"]').forEach(script => {
        try { const value = JSON.parse(script.textContent); updateSchema(value); script.textContent = JSON.stringify(value); } catch (_) { /* Leave unrelated invalid schema untouched. */ }
      });
    };
    const refresh = async () => {
      if (document.hidden) return;
      const requestId = ++sequence;
      const currentDay = today();
      if (dateInput) {
        dateInput.min = currentDay;
        // UTC calendar arithmetic here is only for a date input's +90-day bound.
        const last = new Date(`${currentDay}T12:00:00Z`);
        last.setUTCDate(last.getUTCDate() + 90);
        dateInput.max = last.toISOString().slice(0, 10);
        if (dateInput.value < dateInput.min || dateInput.value > dateInput.max) dateInput.value = currentDay;
      }
      const date = dateInput?.value || currentDay;
      if (times) { selectedTime = times.value || selectedTime; times.replaceChildren(); }
      bookingState('Verfügbare Zeiten werden geladen …', true);
      // Only the homepage has a periodic status read. Bound its pending state
      // without changing reservation requests or clearing guests' selections.
      const controller = homeStatus ? new AbortController() : null;
      const timeout = controller ? window.setTimeout(() => controller.abort(), 10000) : null;
      try {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('date', date);
        const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { 'Cache-Control': 'no-cache' }, ...(controller ? { signal: controller.signal } : {}) });
        if (!response.ok) throw new Error('availability');
        const data = await response.json();
        if (requestId !== sequence || (dateInput && dateInput.value !== date)) return;
        if (data.date !== date || !Array.isArray(data.reservation_slots) || !Array.isArray(data.display_rows) || !data.opening_status || !Array.isArray(data.schema_opening_hours) || typeof data.revision !== 'string') throw new Error('availability');
        hydrateRows(data);
        if (times) {
          times.replaceChildren(...data.reservation_slots.map(hm => {
            const option = document.createElement('option'); option.value = hm; option.textContent = `${hm} Uhr`; return option;
          }));
          if (data.reservation_slots.includes(selectedTime)) times.value = selectedTime;
          selectedTime = times.value;
          const sushiHint = document.getElementById('res_sushi_monday_hint');
          if (sushiHint) sushiHint.style.display = new Date(`${date}T12:00:00Z`).getUTCDay() === 1 ? 'block' : 'none';
          bookingState(data.reservation_slots.length ? '' : 'Keine Reservierungszeiten an diesem Tag verfügbar.', !data.reservation_slots.length);
        }
        if (revision !== data.revision) {
          revision = data.revision;
          document.dispatchEvent(new CustomEvent('wowthai:hours-changed', { detail: { revision } }));
        }
      } catch (_) {
        if (requestId !== sequence) return;
        document.querySelectorAll('[data-wowthai-hours-rows]').forEach(node => { node.dataset.hoursFresh = 'false'; });
        if (homeStatus) {
          const sameDay = homeFreshDate === currentDay;
          homeStatus.dataset.state = sameDay ? 'stale' : 'error';
          homeText(homeLabel, 'Zeiten nicht bestätigt');
          if (!sameDay) homeText(homeHours, 'Heute bitte telefonisch erfragen.');
          homeText(homeFeedback, sameDay
            ? 'Zuletzt geladene Zeiten. Bitte Änderungen telefonisch prüfen.'
            : 'Zeiten konnten nicht geladen werden. Bitte telefonisch prüfen.');
        }
        bookingState('Zeiten konnten nicht geladen werden. Bitte erneut versuchen oder anrufen.', true);
      } finally {
        if (timeout !== null) window.clearTimeout(timeout);
      }
    };
    dateInput?.addEventListener('change', refresh);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    document.addEventListener('wowthai:hours-saved', refresh);
    // Visible pages must also update across kitchen boundaries and midnight.
    // refresh() skips hidden tabs; focus/visibility handles the return to them.
    if (homeStatus) window.setInterval(refresh, 60000);
    refresh();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
