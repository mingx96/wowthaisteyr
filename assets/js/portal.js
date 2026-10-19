/**
 * WOW Thai Steyr - Staff Operations Portal Application (Wix-Style Operations)
 * Handles live orders Kanban, reservations, thermal printing, Web Audio chimes, and customer lookup.
 */

document.addEventListener('DOMContentLoaded', () => {
  // If we are on the login screen
  const loginForm = document.getElementById('wto-portal-login-form');
  if (loginForm) {
    initLogin(loginForm);
    return;
  }

  // If we are in the main portal app
  const app = document.getElementById('wto-portal-app');
  if (!app || !window.wtoPortalConfig) return;

  initPortal(window.wtoPortalConfig);
});

/* ==========================================================================
   LOGIN SCREEN CONTROLLER
   ========================================================================== */
function initLogin(form) {
  const errorBox = document.getElementById('wto-login-error');
  const btn = form.querySelector('.wto-login-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.style.display = 'none';
    btn.disabled = true;
    const origText = btn.innerHTML;
    btn.innerHTML = '<span>Anmelden …</span>';

    try {
      const formData = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        window.location.reload();
      } else {
        errorBox.textContent = data.data?.message || 'Anmeldung fehlgeschlagen.';
        errorBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = origText;
      }
    } catch (err) {
      errorBox.textContent = 'Verbindungsfehler. Bitte erneut versuchen.';
      errorBox.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = origText;
    }
  });
}

/* ==========================================================================
   PORTAL APP CONTROLLER
   ========================================================================== */
function initPortal(cfg) {
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function formatMoney(val) {
    if (typeof val === 'number') return val.toFixed(2).replace('.', ',') + ' €';
    if (!val) return '0,00 €';
    let s = String(val)
      .replace(/&euro;/gi, '€')
      .replace(/&nbsp;/gi, ' ')
      .replace(/&#8364;/g, '€')
      .trim();
    s = s.replace(/\s+/g, ' ');
    if (!s.includes('€')) s += ' €';
    return s;
  }

  function formatElapsed(a, b) {
    let createdMs = 0;
    if (a && !isNaN(a) && Number(a) > 1000000000) {
      createdMs = Number(a) * 1000;
    } else if (typeof a === 'string' && a.includes('-')) {
      let d = new Date(a);
      createdMs = d.getTime();

    } else if (b) {
      if (!isNaN(b) && Number(b) > 1000000000) {
        createdMs = Number(b) * 1000;
      } else if (typeof b === 'string' && b.includes('-')) {
        let d = new Date(b);
        createdMs = d.getTime();

      }
    }
    if (!createdMs || isNaN(createdMs)) return 'gerade eben';

    let diffSec = Math.floor((Date.now() - createdMs) / 1000);
    if (diffSec < 0) diffSec = 0;

    if (diffSec < 60) return diffSec === 0 ? 'gerade eben' : `vor ${diffSec}s`;
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) {
      return `vor ${diffMin} Min.`;
    }
    const diffHours = Math.floor(diffMin / 60);
    const remMin = diffMin % 60;
    return `vor ${diffHours} Std. ${remMin} Min.`;
  }

  const todayStr = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Vienna',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(new Date());

  const state = {
    activeTab: 'orders',
    orderPage: 1, resPage: 1, dailyPage: 1, dailyDate: todayStr,
    orderData: null, resData: null,
    orderView: 'kanban', // 'kanban' or 'archive'
    mobileOrdersCol: 'new', // 'new', 'prep', 'ready'
    mobileResCol: 'new',    // 'new', 'prep', 'ready'
    resFilter: 'daily',  // 'daily', 'pending', 'all'
    resShift: 'all',    // 'all', 'lunch', 'dinner'
    resDate: todayStr,
    searchCustomer: '',
    soundOn: localStorage.getItem('wto_sound') !== 'off',
    knownOrders: new Set(),
    isInitialLoad: true,
    orders: [],
    reservations: [],
    stats: null,
    pollTimer: null,
  };

  let audioCtx = null;

  // --- Web Audio Chime ---
  function playChime() {
    if (!state.soundOn) return;
    try {
      if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      const now = audioCtx.currentTime;
      // Tone 1: 587.33 Hz (D5)
      const osc1 = audioCtx.createOscillator();
      const gain1 = audioCtx.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(587.33, now);
      gain1.gain.setValueAtTime(0.2, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
      osc1.connect(gain1);
      gain1.connect(audioCtx.destination);
      osc1.start(now);
      osc1.stop(now + 0.35);

      // Tone 2: 880 Hz (A5)
      const osc2 = audioCtx.createOscillator();
      const gain2 = audioCtx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(880, now + 0.16);
      gain2.gain.setValueAtTime(0.25, now + 0.16);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.65);
      osc2.connect(gain2);
      gain2.connect(audioCtx.destination);
      osc2.start(now + 0.16);
      osc2.stop(now + 0.65);
    } catch (e) {
      console.warn('Audio chime notice:', e);
    }
  }

  // --- REST API Fetcher ---
  async function api(path, options = {}) {
    const mutating = options.method && options.method !== 'GET';
    if (mutating) mutations++;
    try {
    const res = await fetch((options.read ? cfg.read_api : cfg.api) + path, {
      signal: AbortSignal.timeout(20000),
      cache: 'no-store',
      method: options.method || 'GET',
      headers: {
        'X-WP-Nonce': cfg.nonce,
        'Content-Type': 'application/json',
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }
    return data;
    } finally { if (mutating) mutations--; }
  }

  const requestIds = {};
  const channelStatus = {};
  let mutations = 0;
  const originalApi = api;
  // Current Operations endpoints continue to own all writes and version checks.
  async function read(path) { return originalApi(path, {read: true}); }
  function formatDate(value, withTime = false) {
    if (!value) return 'Datum unbekannt';
    const date = new Date(value.length === 10 ? value + 'T12:00:00+00:00' : value);
    if (Number.isNaN(date.getTime())) return 'Datum unbekannt';
    return new Intl.DateTimeFormat('de-AT', {timeZone: 'Europe/Vienna', dateStyle: 'medium', ...(withTime ? {timeStyle: 'short'} : {})}).format(date);
  }
  function minorMoney(value, currency = 'EUR') {
    return new Intl.NumberFormat('de-AT', {style: 'currency', currency}).format(Number(value || 0) / 100);
  }
  const statusNames = {pending: 'Zahlung ausstehend', 'on-hold': 'Wartend', processing: 'In Bearbeitung', completed: 'Abgeschlossen', cancelled: 'Storniert', failed: 'Fehlgeschlagen', refunded: 'Erstattet'};
  function markChannel(channel, ok) {
    channelStatus[channel] = ok;
    const failed = Object.values(channelStatus).includes(false);
    setNetworkStatus(!failed);
    const status = document.getElementById('wto-sync-status');
    if (status) {
      status.classList.toggle('is-error', failed);
      status.textContent = failed ? 'Abgleich fehlgeschlagen. Angezeigte Daten können veraltet sein. Automatischer Neuversuch alle 12 Sekunden.' : 'Letzter Abgleich: ' + new Intl.DateTimeFormat('de-AT', {timeZone:'Europe/Vienna',timeStyle:'medium'}).format(new Date()) + ' · Wien';
    }
  }
  function feedback(id, message, error = false) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.classList.toggle('is-error', error);
    el.setAttribute('role', error ? 'alert' : 'status');
  }
  // Skip unchanged DOM; preserve scroll and keyboard focus when polling updates cards.
  function patchHTML(container, html) {
    if (!container || container.dataset.rendered === html) return;
    const scrolls = [...container.querySelectorAll('.wto-cards-scroll')].map(el => el.scrollTop);
    const ownScroll = container.scrollTop;
    const expanded = [...container.querySelectorAll('details[open][data-order]')].map(el => el.dataset.order);
    const focused = container.contains(document.activeElement) ? document.activeElement : null;
    const cardId = focused?.closest('article')?.dataset.id;
    const actionClass = focused ? [...focused.classList].find(c => c.startsWith('btn-')) : null;
    const action = focused?.dataset.action;
    const focusedPage = focused?.dataset.page;
    const focusedDetail = focused?.tagName === 'SUMMARY' ? focused.closest('details')?.dataset.order : null;
    container.innerHTML = html;
    container.dataset.rendered = html;
    container.scrollTop = ownScroll;
    expanded.forEach(id => { const detail = container.querySelector(`details[data-order="${CSS.escape(id)}"]`); if (detail) detail.open = true; });
    container.querySelectorAll('.wto-cards-scroll').forEach((el, i) => { el.scrollTop = scrolls[i] || 0; });
    if (focusedDetail) {
      container.querySelector(`details[data-order="${CSS.escape(focusedDetail)}"] > summary`)?.focus({preventScroll:true});
    }
    if (focusedPage) {
      const next = container.querySelector(`[data-page="${CSS.escape(focusedPage)}"]:not(:disabled)`);
      if (next) next.focus({preventScroll:true});
    }
    if (cardId && actionClass) {
      const next = container.querySelector(`article[data-id="${CSS.escape(cardId)}"] .${CSS.escape(actionClass)}${action ? `[data-action="${CSS.escape(action)}"]` : ''}`);
      if (next) next.focus({preventScroll: true});
      else { container.tabIndex = -1; container.focus({preventScroll: true}); }
    }
  }
  function paginate(id, data, navigate) {
    const el = document.getElementById(id);
    if (!el) return;
    const page = data.page || 1, pages = data.pages || 1;
    patchHTML(el, `<button type="button" class="wto-btn btn-secondary" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Zurück</button><span>Seite ${page} / ${pages} · ${data.records ?? data.total ?? 0} Einträge</span><button type="button" class="wto-btn btn-secondary" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>Weiter</button>`);
    el.querySelectorAll('[data-page]').forEach(btn => { btn.onclick = () => navigate(Number(btn.dataset.page)); });
  }

  // --- Live Vienna Clock & Order Timers ---
  function updateClock() {
    const clockEl = document.getElementById('wto-live-clock');
    if (clockEl) {
      const now = new Date();
      const timeStr = new Intl.DateTimeFormat('de-AT', {
        timeZone: 'Europe/Vienna',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).format(now);
      clockEl.textContent = timeStr;
    }

    // Live ticking: update all active card timers every second
    document.querySelectorAll('.wto-live-timer').forEach(el => {
      const ts = el.dataset.ts;
      const iso = el.dataset.created;
      el.textContent = `⏱️ ${formatElapsed(ts, iso)}`;
    });
  }
  setInterval(updateClock, 1000);
  updateClock();

  // --- Header Sound Toggle (Desktop & Mobile) ---
  const soundBtn = document.getElementById('wto-sound-toggle');
  const soundBtnM = document.getElementById('wto-sound-toggle-m');
  function toggleSound() {
    state.soundOn = !state.soundOn;
    localStorage.setItem('wto_sound', state.soundOn ? 'on' : 'off');
    updateSoundBtn();
    if (state.soundOn) playChime();
  }
  if (soundBtn) soundBtn.addEventListener('click', toggleSound);
  if (soundBtnM) soundBtnM.addEventListener('click', toggleSound);

  function updateSoundBtn() {
    const text = state.soundOn ? '🔔 Signal: AN' : '🔕 Signal: AUS';
    const cls = 'wto-btn-sound' + (state.soundOn ? '' : ' sound-off');
    if (soundBtn) {
      soundBtn.className = cls;
      soundBtn.innerHTML = text;
    }
    if (soundBtnM) {
      soundBtnM.className = cls;
      soundBtnM.innerHTML = text;
    }
  }
  updateSoundBtn();

  // --- Kitchen Prep Time Dropdown (Desktop & Mobile) ---
  const prepSelect = document.getElementById('wto-kitchen-prep-select');
  const prepSelectM = document.getElementById('wto-kitchen-prep-select-m');
  async function handlePrepChange(val) {
    if (prepSelect) prepSelect.value = val;
    if (prepSelectM) prepSelectM.value = val;
    try {
      await api('prep', { method: 'POST', body: { prep: val } });
      showToast(`Küchenzeit auf ${val} gesetzt.`);
    } catch (err) {
      showToast('Fehler beim Ändern der Küchenzeit.', true);
    }
  }
  if (prepSelect) prepSelect.addEventListener('change', () => handlePrepChange(prepSelect.value));
  if (prepSelectM) prepSelectM.addEventListener('change', () => handlePrepChange(prepSelectM.value));

  // --- Mobile Quick Settings Dialog ---
  const btnMobileSettings = document.getElementById('wto-btn-mobile-settings');
  const dialogMobileSettings = document.getElementById('wto-dialog-mobile-settings');
  if (btnMobileSettings && dialogMobileSettings) {
    btnMobileSettings.addEventListener('click', () => {
      dialogMobileSettings.showModal();
    });
  }

  // --- Navigation Tabs ---
  document.querySelectorAll('.wto-nav-tab').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      const target = tabBtn.dataset.tab;
      switchTab(target);
    });
  });

  function switchTab(tabId) {
    state.activeTab = tabId;
    document.querySelectorAll('.wto-nav-tab').forEach(b => {
      b.classList.toggle('active', b.dataset.tab === tabId);
    });
    document.querySelectorAll('.wto-view').forEach(v => {
      v.classList.toggle('active', v.id === `wto-view-${tabId}`);
    });
    loadTabData();
  }

  // --- Toast Notification ---
  function showToast(text, isError = false) {
    const existing = document.querySelector('.wto-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'wto-toast' + (isError ? ' error' : '');
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.textContent = text;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  }

  // --- Network Dot ---
  function setNetworkStatus(online) {
    const dot = document.getElementById('wto-network-dot');
    const label = document.getElementById('wto-network-text');
    if (dot && label) {
      dot.className = 'wto-network-dot' + (online ? '' : ' stale');
      label.textContent = online ? 'Verbunden' : 'Verbindung getrennt';
    }
  }

  // =========================================================================
  // ORDERS TAB CONTROLLER
  // =========================================================================
  const orderSwitchButtons = document.querySelectorAll('.wto-orders-switch button');
  orderSwitchButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      orderSwitchButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.orderView = btn.dataset.view;
      state.orderPage = 1; state.orders = []; state.orderData = null;
      renderOrdersUI();
      loadOrders();
    });
  });

  // Mobile Orders Column Switcher
  document.querySelectorAll('#wto-orders-col-switcher .wto-mcol-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const col = btn.dataset.col;
      state.mobileOrdersCol = col;
      document.querySelectorAll('#wto-orders-col-switcher .wto-mcol-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.col === col);
      });
      const board = document.getElementById('wto-kanban-board');
      if (board) {
        board.classList.remove('show-new', 'show-prep', 'show-ready');
        board.classList.add(`show-${col}`);
      }
    });
  });

  async function loadOrders() {
    const request = requestIds.orders = (requestIds.orders || 0) + 1;
    const view = state.orderView, page = state.orderPage;
    feedback('wto-orders-feedback', 'Bestellungen werden aktualisiert …');
    try {
      const data = await read(`orders?view=${view === 'archive' ? 'archive' : 'active'}&page=${page}`);
      if (request !== requestIds.orders || mutations) return;
      markChannel('orders', true);
      state.orders = data.items || [];
      state.orderData = data;
      state.orderPage = data.page;
      renderOrdersUI();
      paginate('wto-orders-pagination', data, p => { state.orderPage = p; loadOrders(); });
      feedback('wto-orders-feedback', view === 'archive' ? 'Archiv · alle Tage · nur Ansicht' : 'Aktive Bestellungen · alle Tage. Spaltenzahlen gelten für alle Seiten; Karten zeigen diese Seite.');
    } catch (err) {
      if (request !== requestIds.orders) return;
      markChannel('orders', false);
      feedback('wto-orders-feedback', 'Bestellungen konnten nicht geladen werden. Vorhandene Karten können veraltet sein.', true);
    }
  }

  function renderOrdersUI() {
    const archive = state.orderView === 'archive';
    document.getElementById('wto-kanban-board').hidden = archive;
    document.getElementById('wto-orders-col-switcher').hidden = archive;
    document.getElementById('wto-archive-list').hidden = !archive;
    if (archive) {
      const container = document.getElementById('wto-archive-list');
      if (!container) return;
      if (state.orders.length === 0) {
        patchHTML(container, '<p class="wto-empty-col">' + (state.orderData ? 'Keine archivierten Bestellungen vorhanden.' : 'Lade Archiv …') + '</p>');
        return;
      }
      patchHTML(container, state.orders.map(o => renderOrderCard(o, true)).join(''));
      return;
    }

    // 3 Kanban Columns
    const colNew = document.getElementById('wto-col-new-cards');
    const colPrep = document.getElementById('wto-col-prep-cards');
    const colReady = document.getElementById('wto-col-ready-cards');

    const newOrders = state.orders.filter(o => o.stage === 'new');
    const prepOrders = state.orders.filter(o => o.stage === 'preparing');
    const readyOrders = state.orders.filter(o => o.stage === 'ready' || o.stage === 'dispatched');

    document.getElementById('wto-count-new').textContent = state.orderData?.counts?.new ?? newOrders.length;
    document.getElementById('wto-count-prep').textContent = state.orderData?.counts?.preparing ?? prepOrders.length;
    document.getElementById('wto-count-ready').textContent = (state.orderData ? (state.orderData.counts?.ready || 0) + (state.orderData.counts?.dispatched || 0) : readyOrders.length);

    const mNew = document.getElementById('wto-m-count-new');
    if (mNew) mNew.textContent = state.orderData?.counts?.new ?? newOrders.length;
    const mPrep = document.getElementById('wto-m-count-prep');
    if (mPrep) mPrep.textContent = state.orderData?.counts?.preparing ?? prepOrders.length;
    const mReady = document.getElementById('wto-m-count-ready');
    if (mReady) mReady.textContent = (state.orderData ? (state.orderData.counts?.ready || 0) + (state.orderData.counts?.dispatched || 0) : readyOrders.length);

    document.querySelectorAll('#wto-orders-col-switcher .wto-mcol-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.col === (state.mobileOrdersCol || 'new'));
    });

    patchHTML(colNew, newOrders.length > 0
      ? newOrders.map(o => renderOrderCard(o)).join('')
      : '<p class="wto-empty-col">Keine neuen Bestellungen.</p>');

    patchHTML(colPrep, prepOrders.length > 0
      ? prepOrders.map(o => renderOrderCard(o)).join('')
      : '<p class="wto-empty-col">Küche ist aktuell frei.</p>');

    patchHTML(colReady, readyOrders.length > 0
      ? readyOrders.map(o => renderOrderCard(o)).join('')
      : '<p class="wto-empty-col">Keine abholbereiten Speisen.</p>');

    attachOrderCardEvents(document.getElementById('wto-kanban-board'));
  }

  function renderOrderCard(o, readOnly = false) {
    const isNew = !readOnly && o.stage === 'new';

    return `
      <article class="wto-order-card ${isNew ? 'urgent' : ''}" data-id="${esc(o.id)}" data-version="${esc(o.version)}">
        <div class="wto-card-top">
          <span class="wto-card-num">#${esc(o.number)}</span>
          ${readOnly ? `<span class="wto-card-age">${esc(formatDate(o.created, true))} · Wien</span>` : `<span class="wto-card-age wto-live-timer" data-created="${esc(o.created)}" data-ts="${esc(o.created_ts || '')}">⏱️ ${formatElapsed(o.created_ts, o.created)}</span>`}
        </div>

        <div class="wto-card-pickup-banner">
          <span>🕒 ${esc(o.pickup || 'Sofort (ASAP)')}</span>
          <span style="font-size:11px;opacity:0.85;">Abholung</span>
        </div>

        <div class="wto-card-guest">
          <strong>${esc(o.name || 'Gast')}</strong>
          ${o.phone ? `<a href="tel:${esc(o.phone)}">📞 ${esc(o.phone)}</a>` : ''}
        </div>

        <ul class="wto-card-items">
          ${(o.items || []).map(i => `
            <li>
              <strong>${esc(i.quantity)}×</strong> ${esc(i.name)}
              ${(i.choices || []).map(c => `<span class="wto-item-custom">+ ${esc(c)}</span>`).join('')}
            </li>
          `).join('')}
        </ul>

        ${o.note ? `<p class="wto-card-note"><strong>Hinweis:</strong> ${esc(o.note)}</p>` : ''}

        <div class="wto-card-bottom">
          <span class="wto-card-payment">${esc(o.payment)}</span>
          <span class="wto-card-total">${esc(formatMoney(o.total))}</span>
        </div>

        <p class="wto-order-status">${esc(statusNames[o.status] || o.status || o.stage)}${o.stage === 'dispatched' ? ' · Unterwegs' : ''}</p>
        ${readOnly ? '' : `<div class="wto-card-actions">
          ${o.stage === 'new' ? `
            <button type="button" class="wto-btn btn-primary btn-order-accept" data-id="${esc(o.id)}">✓ Annehmen</button>
            <button type="button" class="wto-btn btn-danger btn-order-reject" data-id="${esc(o.id)}">✕ Ablehnen</button>
          ` : ''}

          ${o.stage === 'preparing' ? `
            <button type="button" class="wto-btn btn-success btn-order-ready" data-id="${esc(o.id)}">🥡 Abholbereit</button>
            <button type="button" class="wto-btn btn-secondary btn-order-print" data-id="${esc(o.id)}">🖨️ Bon</button>
          ` : ''}

          ${o.stage === 'ready' ? `
            <button type="button" class="wto-btn btn-primary btn-order-complete" data-id="${esc(o.id)}">✓ Abgeholt</button>
            <button type="button" class="wto-btn btn-secondary btn-order-print" data-id="${esc(o.id)}">🖨️ Bon</button>
          ` : ''}
        </div>`}
      </article>
    `;
  }

  function attachOrderCardEvents(container) {
    // Accept
    container.querySelectorAll('.btn-order-accept').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card.dataset.version;
        btn.disabled = true;
        try {
          await api(`orders/${id}/action`, {
            method: 'POST',
            body: { action: 'accept', version: ver }
          });
          showToast(`Bestellung #${id} angenommen!`);
          await loadOrders(); loadStats();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      };
    });

    // Reject
    container.querySelectorAll('.btn-order-reject').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card.dataset.version;
        if (!confirm(`Möchten Sie die Bestellung #${id} wirklich ablehnen?`)) return;
        btn.disabled = true;
        try {
          await api(`orders/${id}/action`, {
            method: 'POST',
            body: { action: 'reject', version: ver }
          });
          showToast(`Bestellung #${id} abgelehnt.`);
          await loadOrders(); loadStats();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      };
    });

    // Ready
    container.querySelectorAll('.btn-order-ready').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card.dataset.version;
        btn.disabled = true;
        try {
          await api(`orders/${id}/action`, {
            method: 'POST',
            body: { action: 'ready', version: ver }
          });
          showToast(`Bestellung #${id} ist abholbereit!`);
          await loadOrders(); loadStats();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      };
    });

    // Complete (Picked up)
    container.querySelectorAll('.btn-order-complete').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card.dataset.version;
        btn.disabled = true;
        try {
          await api(`orders/${id}/action`, {
            method: 'POST',
            body: { action: 'complete', version: ver }
          });
          showToast(`Bestellung #${id} als abgeholt markiert.`);
          await loadOrders(); loadStats();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      };
    });

    // Print Thermal Bon (80 mm)
    container.querySelectorAll('.btn-order-print').forEach(btn => {
      btn.onclick = () => {
        const id = btn.dataset.id;
        const order = state.orders.find(o => String(o.id) === String(id));
        if (order) printBon(order);
      };
    });
  }

  // --- Thermal 80mm Bon Print ---
  function printBon(order) {
    const printArea = document.getElementById('wto-print-area');
    if (!printArea) return;

    printArea.innerHTML = `
      <div class="bon-center bon-bold" style="font-size:16px;">WOW THAI STEYR</div>
      <div class="bon-center" style="font-size:11px;">City Point · Leopold-Werndl-Str. 2 · Steyr<br>Tel: +43 7252 74973</div>
      <div class="bon-double"></div>

      <div style="background:#000;color:#fff;padding:6px;text-align:center;margin:6px 0;">
        <div style="font-size:10px;font-weight:bold;letter-spacing:1px;">ABHOLZEIT:</div>
        <div class="bon-huge">${esc(order.pickup || 'SOFORT (ASAP)')}</div>
      </div>

      <div class="bon-row bon-bold">
        <span>BESTELLUNG #${esc(order.number)}</span>
        <span>${new Intl.DateTimeFormat('de-AT',{timeStyle:'short'}).format(new Date())} Uhr</span>
      </div>
      <div>Gast: <strong>${esc(order.name)}</strong></div>
      ${order.phone ? `<div>Tel: ${esc(order.phone)}</div>` : ''}

      ${order.note ? `
        <div class="bon-divider"></div>
        <div style="border:1px solid #000;padding:4px;">
          <strong>HINWEIS:</strong> ${esc(order.note)}
        </div>
      ` : ''}

      <div class="bon-divider"></div>
      <div class="bon-bold" style="margin-bottom:4px;">SPEISEN:</div>
      ${(order.items || []).map(i => `
        <div style="margin-bottom:6px;">
          <div class="bon-row">
            <span><strong>${esc(i.quantity)}x</strong> ${esc(i.name)}</span>
          </div>
          ${(i.choices || []).map(c => `<div style="font-size:10px;padding-left:14px;">+ ${esc(c)}</div>`).join('')}
        </div>
      `).join('')}

      <div class="bon-double"></div>
      <div class="bon-row bon-bold" style="font-size:15px;">
        <span>GESAMT:</span>
        <span>${esc(formatMoney(order.total))}</span>
      </div>
      <div style="font-size:11px;margin-top:2px;">Zahlung: ${esc(order.payment)}</div>
      <div class="bon-divider"></div>
      <div class="bon-center" style="font-size:11px;margin-top:8px;">Guten Appetit! ขอบคุณมาก</div>
    `;

    window.print();
  }

  // =========================================================================
  // RESERVATIONS TAB CONTROLLER
  // =========================================================================
  const resDatePicker = document.getElementById('wto-res-date-input');
  if (resDatePicker) {
    resDatePicker.value = state.resDate;
    resDatePicker.addEventListener('change', () => {
      state.resDate = resDatePicker.value || todayStr;
      state.resPage = 1; state.reservations = []; state.resData = null;
      renderReservationsUI(); loadReservations();
    });
  }

  document.querySelectorAll('.wto-res-filters button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.wto-res-filters button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.resFilter = btn.dataset.view;
      state.resPage = 1; state.reservations = []; state.resData = null;
      resDatePicker.disabled = state.resFilter !== 'daily';
      renderReservationsUI(); loadReservations();
    });
  });

  document.querySelectorAll('.wto-shift-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.wto-shift-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.resShift = btn.dataset.shift;
      state.resPage = 1; state.reservations = []; state.resData = null;
      renderReservationsUI(); loadReservations();
    });
  });

  const btnNewRes = document.getElementById('wto-btn-new-reservation');
  if (btnNewRes) {
    btnNewRes.addEventListener('click', () => openNewReservationModal());
  }

  // Mobile Reservations Column Switcher
  document.querySelectorAll('#wto-res-col-switcher .wto-mcol-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const col = btn.dataset.col;
      state.mobileResCol = col;
      document.querySelectorAll('#wto-res-col-switcher .wto-mcol-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.col === col);
      });
      const resBoard = document.querySelector('.wto-res-kanban-board');
      if (resBoard) {
        resBoard.classList.remove('show-new', 'show-prep', 'show-ready');
        resBoard.classList.add(`show-${col}`);
      }
    });
  });

  async function loadReservations() {
    const request = requestIds.reservations = (requestIds.reservations || 0) + 1;
    feedback('wto-res-feedback', 'Reservierungen werden aktualisiert …');
    try {
      const data = await read(`reservations?view=${state.resFilter}&date=${encodeURIComponent(state.resDate)}&shift=${state.resShift}&page=${state.resPage}`);
      if (request !== requestIds.reservations || mutations) return;
      markChannel('reservations', true);
      state.reservations = data.items || [];
      state.resData = data;
      state.resPage = data.page;
      document.getElementById('wto-kpi-res-total').textContent = data.total ?? '—';
      document.getElementById('wto-kpi-res-guests').textContent = data.guests ?? '—';
      renderReservationsUI();
      paginate('wto-res-pagination', data, p => { state.resPage = p; loadReservations(); });
      feedback('wto-res-feedback', data.records ? 'Aktualisiert · stornierte Reservierungen bleiben im Detail sichtbar.' : 'Keine Reservierungen im gewählten Filter.');
    } catch (err) {
      if (request !== requestIds.reservations) return;
      markChannel('reservations', false);
      feedback('wto-res-feedback', 'Reservierungen konnten nicht geladen werden. Vorhandene Daten können veraltet sein.', true);
    }
  }

  function renderReservationsUI() {
    const container = document.getElementById('wto-res-list');
    if (!container) return;

    const scope = state.resFilter === 'daily' ? formatDate(state.resDate) + ' · Wien' : state.resFilter === 'pending' ? 'Offene Anfragen · alle Tage' : 'Alle Reservierungen · alle Tage';
    feedback('wto-res-scope', scope + ' · ' + ({all:'alle Zeiträume',lunch:'Mittag (vor 16 Uhr)',dinner:'Abend (ab 16 Uhr)'}[state.resShift]));
    if (!state.resData) {
      document.getElementById('wto-kpi-res-total').textContent = '—';
      document.getElementById('wto-kpi-res-guests').textContent = '—';
    }
    const list = state.reservations;
    const pending = list.filter(r => r.status === 'pending');
    const confirmed = list.filter(r => r.status === 'confirmed');
    const arrived = list.filter(r => r.status === 'arrived');
    const cancelled = list.filter(r => r.status === 'cancelled');

    const pendingGuests = pending.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);
    const confirmedGuests = confirmed.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);
    const arrivedGuests = arrived.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);

    // Update mobile switcher badges
    const mResNew = document.getElementById('wto-m-res-new');
    if (mResNew) mResNew.textContent = state.resData?.counts?.pending?.count || 0;
    const mResPrep = document.getElementById('wto-m-res-prep');
    if (mResPrep) mResPrep.textContent = state.resData?.counts?.confirmed?.count || 0;
    const mResReady = document.getElementById('wto-m-res-ready');
    if (mResReady) mResReady.textContent = state.resData?.counts?.arrived?.count || 0;

    document.querySelectorAll('#wto-res-col-switcher .wto-mcol-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.col === (state.mobileResCol || 'new'));
    });

    let html = `
      <div class="wto-res-kanban-board show-${esc(state.mobileResCol || 'new')}">
        <!-- Col 1: Neue Anfragen -->
        <section class="wto-kanban-col col-new">
          <div class="wto-col-head">
            <div class="wto-col-title">
              <span class="wto-col-dot" style="background:var(--wto-amber);"></span>
              <span>📩 Neue Anfragen</span>
            </div>
            <span class="wto-col-count">${state.resData?.counts?.pending?.count || 0} · ${state.resData?.counts?.pending?.guests || 0} Gäste</span>
          </div>
          <div class="wto-cards-scroll">
            ${pending.length > 0
              ? pending.map(renderReservationCard).join('')
              : '<p class="wto-empty-col">Keine offenen Anfragen.</p>'}
          </div>
        </section>

        <!-- Col 2: Bestätigt / Erwartet -->
        <section class="wto-kanban-col col-prep">
          <div class="wto-col-head">
            <div class="wto-col-title">
              <span class="wto-col-dot" style="background:var(--wto-gold);"></span>
              <span>📅 Bestätigt · Erwartet</span>
            </div>
            <span class="wto-col-count">${state.resData?.counts?.confirmed?.count || 0} · ${state.resData?.counts?.confirmed?.guests || 0} Gäste</span>
          </div>
          <div class="wto-cards-scroll">
            ${confirmed.length > 0
              ? confirmed.map(renderReservationCard).join('')
              : '<p class="wto-empty-col">Keine ausstehenden Gäste.</p>'}
          </div>
        </section>

        <!-- Col 3: Platziert (Im Haus) -->
        <section class="wto-kanban-col col-ready">
          <div class="wto-col-head">
            <div class="wto-col-title">
              <span class="wto-col-dot" style="background:var(--wto-green);"></span>
              <span>🍽️ Platziert (Im Haus)</span>
            </div>
            <span class="wto-col-count">${state.resData?.counts?.arrived?.count || 0} · ${state.resData?.counts?.arrived?.guests || 0} Gäste</span>
          </div>
          <div class="wto-cards-scroll">
            ${arrived.length > 0
              ? arrived.map(renderReservationCard).join('')
              : '<p class="wto-empty-col">Noch keine Gäste platziert.</p>'}
            ${cancelled.length > 0 ? `
              <div style="margin-top:20px;border-top:1px dashed var(--wto-line);padding-top:10px;">
                <div style="font-size:11px;color:var(--wto-ink-subtle);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;">Storniert (${cancelled.length})</div>
                ${cancelled.map(renderReservationCard).join('')}
              </div>
            ` : ''}
          </div>
        </section>
      </div>
    `;

    patchHTML(container, html);
    attachReservationEvents(container);
  }

  function renderReservationCard(r) {
    const statusLabels = {
      pending: 'Anfrage (Neu)',
      confirmed: 'Bestätigt',
      arrived: 'Gast da (Platziert)',
      cancelled: 'Storniert'
    };

    const isLunch = r.period === 'lunch';
    const shiftBadge = r.period === 'unknown' ? '<span>Zeitraum unbekannt</span>' : isLunch
      ? '<span class="wto-shift-pill lunch">☀️ Mittag</span>'
      : '<span class="wto-shift-pill dinner">🌙 Abend</span>';

    return `
      <article class="wto-res-card status-${esc(r.status)}" data-id="${esc(r.id)}" data-version="${esc(r.version)}">
        <p class="wto-res-date">${esc(formatDate(r.date))} · Wien</p>
        <div class="wto-res-top">
          <span class="wto-res-time" style="display:inline-flex;align-items:center;gap:6px;">
            🕒 <strong>${esc(r.time)} Uhr</strong> ${shiftBadge}
          </span>
          <span class="wto-res-party">👥 <strong>${esc(r.party)}</strong> Pers.</span>
        </div>

        <div class="wto-res-guest" style="margin:10px 0 6px;">
          <strong style="font-size:15px;color:var(--wto-ink);">${esc(r.name)}</strong>
          ${r.phone ? `<div style="margin-top:4px;"><a href="tel:${esc(r.phone)}" style="color:var(--wto-gold);text-decoration:none;font-weight:600;">📞 ${esc(r.phone)}</a></div>` : ''}
          ${r.email ? `<div style="font-size:11px;color:var(--wto-ink-subtle);margin-top:2px;">✉️ ${esc(r.email)}</div>` : ''}
        </div>

        ${r.notes ? `<p class="wto-card-note" style="background:rgba(217,172,54,0.1);border-left:3px solid var(--wto-gold);color:var(--wto-gold-bright);margin:8px 0;padding:6px 10px;border-radius:4px;font-size:12px;"><strong>Notiz:</strong> ${esc(r.notes)}</p>` : ''}

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;border-top:1px solid var(--wto-line);padding-top:10px;gap:8px;">
          <span class="wto-res-badge badge-${esc(r.status)}">${esc(statusLabels[r.status] || r.status)}</span>
          <div style="display:flex;gap:6px;">
            ${r.status === 'pending' ? `
              <button type="button" class="wto-btn btn-success btn-res-action" data-action="confirmed" data-id="${esc(r.id)}" style="height:32px;padding:0 12px;font-size:11px;font-weight:700;">✓ Bestätigen</button>
              <button type="button" class="wto-btn btn-danger btn-res-action" data-action="cancelled" data-id="${esc(r.id)}" aria-label="Reservierung stornieren" style="height:32px;padding:0 8px;font-size:11px;">✕</button>
            ` : ''}
            ${r.status === 'confirmed' ? `
              <button type="button" class="wto-btn btn-primary btn-res-action" data-action="arrived" data-id="${esc(r.id)}" style="height:32px;padding:0 14px;font-size:11px;font-weight:700;background:linear-gradient(135deg, #d4af37, #b8860b);color:#141210;">🍽️ Gast da</button>
              <button type="button" class="wto-btn btn-danger btn-res-action" data-action="cancelled" data-id="${esc(r.id)}" aria-label="Reservierung stornieren" style="height:32px;padding:0 8px;font-size:11px;">Storno</button>
            ` : ''}
            ${r.status === 'arrived' ? `
              <button type="button" class="wto-btn btn-secondary btn-res-action" data-action="confirmed" data-id="${esc(r.id)}" style="height:32px;padding:0 8px;font-size:11px;" title="Zurück zu Erwartet" aria-label="Zurück zu Erwartet">↩</button>
            ` : ''}
          </div>
        </div>
      </article>
    `;
  }

  function attachReservationEvents(container) {
    container.querySelectorAll('.btn-res-action').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const action = btn.dataset.action;
        const card = btn.closest('.wto-res-card');
        const ver = card.dataset.version;
        btn.disabled = true;
        try {
          await api(`reservations/${id}`, {
            method: 'POST',
            body: { action: action, version: ver }
          });
          showToast(`Reservierung aktualisiert.`);
          await loadReservations(); loadStats();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      };
    });
  }

  // --- Modal for New Phone/Walk-in Reservation ---
  async function openNewReservationModal() {
    const dialog = document.getElementById('wto-dialog-new-res');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    form.reset();

    const dateInput = form.elements['date'];
    const timeSelect = form.elements['time'];
    dateInput.value = state.resDate;

    // Load available slots
    async function updateSlots() {
      timeSelect.innerHTML = '<option value="">Lade verfügbare Zeiten …</option>';
      try {
        const slotsData = await api(`reservation-slots?date=${encodeURIComponent(dateInput.value)}`);
        const slots = slotsData.slots || [];
        if (slots.length === 0) {
          timeSelect.innerHTML = '<option value="">Keine Termine (Ruhetag / Geschlossen)</option>';
        } else {
          timeSelect.innerHTML = slots.map(s => `<option value="${esc(s)}">${esc(s)} Uhr</option>`).join('');
        }
      } catch (e) {
        timeSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
      }
    }

    dateInput.addEventListener('change', updateSlots);
    await updateSlots();

    dialog.showModal();

    form.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = form.querySelector('[type="submit"]');
      submitBtn.disabled = true;
      try {
        const body = Object.fromEntries(new FormData(form));
        body.party = parseInt(body.party, 10);
        await api('reservations', { method: 'POST', body });
        dialog.close();
        showToast('Neue Reservierung gespeichert.');
        await loadReservations();
      } catch (err) {
        alert(err.message);
      } finally {
        submitBtn.disabled = false;
      }
    };
  }

  // =========================================================================
  // CUSTOMERS TAB CONTROLLER
  // =========================================================================
  const custSearchInput = document.getElementById('wto-cust-search');
  const custConsentSelect = document.getElementById('wto-cust-consent');
  let searchDebounce = null;

  if (custSearchInput) {
    custSearchInput.addEventListener('input', () => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(loadCustomers, 350);
    });
  }
  if (custConsentSelect) {
    custConsentSelect.addEventListener('change', loadCustomers);
  }

  async function loadCustomers() {
    const tableBody = document.getElementById('wto-cust-tbody');
    if (!tableBody) return;

    const q = new URLSearchParams({
      search: custSearchInput ? custSearchInput.value : '',
      state: custConsentSelect ? custConsentSelect.value : '',
      page: 1,
    });

    tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;">Lade Kunden …</td></tr>';
    try {
      const data = await api('contacts?' + q);
      const items = data.items || [];
      if (items.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--wto-ink-subtle);">Keine Kundenprofile gefunden.</td></tr>';
        return;
      }

      tableBody.innerHTML = items.map(c => {
        const primaryEmail = (c.channels || []).find(ch => ch.kind === 'email' && ch.is_primary) || {};
        const consent = primaryEmail.status || 'NOT_SET';
        const consentBadge = {
          SUBSCRIBED: '<span class="wto-res-badge badge-confirmed">✓ Angemeldet</span>',
          UNSUBSCRIBED: '<span class="wto-res-badge badge-cancelled">✕ Abgemeldet</span>',
          NOT_SET: '<span class="wto-res-badge badge-pending">Nicht festgelegt</span>',
        }[consent] || `<span class="wto-res-badge">${esc(consent)}</span>`;

        return `
          <tr>
            <td><strong>${esc(c.first_name)} ${esc(c.last_name)}</strong><br><small style="color:var(--wto-ink-subtle);">${esc(c.region || '')}</small></td>
            <td>${esc(c.email)}<br><small style="color:var(--wto-gold);">${esc(c.phone || '')}</small></td>
            <td>${consentBadge}</td>
            <td><span style="font-size:11px;color:var(--wto-ink-subtle);">${esc(c.source_site === 'a1e58c2c-1fca-4e4b-a015-e14b156d3a32' ? 'Wix Original' : 'WooCommerce')}</span></td>
            <td>
              ${c.phone ? `<a href="tel:${esc(c.phone)}" class="wto-btn btn-secondary" style="height:32px;padding:0 8px;font-size:11px;display:inline-flex;">📞 Anrufen</a>` : ''}
            </td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;padding:24px;">Fehler: ${esc(err.message)}</td></tr>`;
    }
  }

  // =========================================================================
  // STATS TAB CONTROLLER
  // =========================================================================
  async function loadStats() {
    const request = requestIds.summary = (requestIds.summary || 0) + 1;
    try {
      const stats = await read('summary');
      if (request !== requestIds.summary) return;
      if (typeof stats.is_open !== 'boolean' && stats.is_open !== null) throw new Error('Ungültiger Küchenstatus');
      const newIds = (stats.new_order_ids || []).map(String);
      if (!state.isInitialLoad && newIds.some(id => !state.knownOrders.has(id))) playChime();
      state.knownOrders = new Set(newIds); state.isInitialLoad = false;
      state.stats = stats;
      markChannel('summary', true);
      const pill = document.getElementById('wto-opening-status');
      pill.className = 'wto-status-pill ' + (stats.is_open === null ? 'unknown' : stats.is_open ? 'open' : 'closed');
      pill.textContent = stats.is_open === null ? 'Küchenstatus unbekannt' : stats.is_open ? 'Küche geöffnet' : 'Küche geschlossen / Pause';
      for (const [id, count] of [['wto-badge-res', stats.pending_reservations], ['wto-badge-orders', stats.new_orders]]) {
        const badge = document.getElementById(id);
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
        badge.classList.toggle('urgent', count > 0);
        badge.title = 'Offen · alle Tage';
      }
      feedback('wto-daily-backlog', `${stats.backlog_orders} aktive Bestellungen aus früheren Tagen · ${stats.active_orders} aktive Bestellungen insgesamt. Im Küchenboard prüfen.`);
    } catch (err) {
      if (request !== requestIds.summary) return;
      markChannel('summary', false);
      const pill = document.getElementById('wto-opening-status');
      pill.className = 'wto-status-pill unknown'; pill.textContent = 'Küchenstatus unbekannt · Abgleich fehlt';
    }
  }

  const dailyDate = document.getElementById('wto-daily-date');
  if (dailyDate) {
    dailyDate.value = state.dailyDate;
    dailyDate.addEventListener('change', () => {
      state.dailyDate = dailyDate.value || todayStr; state.dailyPage = 1;
      document.getElementById('wto-daily-orders').innerHTML = '';
      ['wto-stat-orders-today', 'wto-stat-revenue-today', 'wto-stat-res-today', 'wto-stat-guests-today'].forEach(id => { document.getElementById(id).textContent = '—'; });
      loadDaily();
    });
  }
  async function loadDaily() {
    const request = requestIds.daily = (requestIds.daily || 0) + 1;
    feedback('wto-daily-feedback', 'Tagesübersicht wird aktualisiert …');
    feedback('wto-daily-scope', formatDate(state.dailyDate) + ' · Wien · Bestellungen nach Erstellungsdatum');
    try {
      const data = await read(`daily?date=${state.dailyDate}&page=${state.dailyPage}`);
      if (request !== requestIds.daily) return;
      markChannel('daily', true);
      state.dailyPage = data.page;
      document.getElementById('wto-stat-orders-today').textContent = data.orders.total;
      const sums = Object.entries(data.currencies || {}).map(([currency, sum]) => `${minorMoney(sum.net_minor, currency)}`).join(' / ');
      document.getElementById('wto-stat-revenue-today').textContent = sums || '0,00 €';
      document.getElementById('wto-stat-res-today').textContent = data.reservations.total;
      document.getElementById('wto-stat-guests-today').textContent = data.reservations.guests;
      feedback('wto-daily-totals', Object.entries(data.currencies || {}).map(([c, v]) => `Bestellwert ${minorMoney(v.gross_minor, c)} − Erstattungen ${minorMoney(v.refund_minor, c)} = ${minorMoney(v.net_minor, c)}`).join(' · ') + ` · ${data.orders.excluded} storniert / fehlgeschlagen (nicht eingerechnet)`);
      patchHTML(document.getElementById('wto-daily-orders'), (data.items || []).map(o => `<details class="wto-daily-order" data-order="${esc(o.id)}"><summary><strong>#${esc(o.number)}</strong> · ${esc(formatDate(o.created, true))} · ${esc(statusNames[o.status] || o.status)} · ${esc(minorMoney(o.net_minor, o.currency))}</summary><p>Bestellwert: ${esc(minorMoney(o.gross_minor, o.currency))} · Erstattung: ${esc(minorMoney(o.refund_minor, o.currency))} · ${o.included ? 'Eingerechnet' : 'Nicht eingerechnet'}</p>${renderOrderCard(o, true)}</details>`).join('') || '<p class="wto-empty-col">Keine Bestellungen an diesem Tag.</p>');
      paginate('wto-daily-pagination', data, p => { state.dailyPage = p; loadDaily(); });
      feedback('wto-daily-feedback', 'Aktualisiert · Details per Klick oder Tastatur öffnen.');
    } catch (err) {
      if (request !== requestIds.daily) return;
      markChannel('daily', false);
      feedback('wto-daily-feedback', 'Tagesübersicht konnte nicht geladen werden. Vorhandene Werte können veraltet sein.', true);
    }
  }

  // --- Load Active Tab Data ---
  function loadTabData() {
    if (state.activeTab === 'orders') loadOrders();
    else if (state.activeTab === 'reservations') loadReservations();
    else if (state.activeTab === 'customers') loadCustomers();
    else if (state.activeTab === 'stats') loadDaily();
  }

  // Initial load
  loadTabData();
  loadStats();

  // Poller every 12 seconds
  state.pollTimer = setInterval(() => {
    if (state.activeTab === 'orders') loadOrders();
    else if (state.activeTab === 'reservations') loadReservations();
    else if (state.activeTab === 'stats') loadDaily();
    loadStats();
  }, 12000);

}
