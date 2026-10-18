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
      if (createdMs > Date.now() + 30000) {
        createdMs -= 7200000;
      }
    } else if (b) {
      if (!isNaN(b) && Number(b) > 1000000000) {
        createdMs = Number(b) * 1000;
      } else if (typeof b === 'string' && b.includes('-')) {
        let d = new Date(b);
        createdMs = d.getTime();
        if (createdMs > Date.now() + 30000) {
          createdMs -= 7200000;
        }
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
    orderView: 'kanban', // 'kanban' or 'archive'
    orderFulfillment: 'all', // 'all', 'takeaway', 'delivery'
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

  // --- Web Audio Chimes (Delivery vs Pickup) ---
  function playPickupChime() {
    if (!state.soundOn) return;
    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
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
      console.warn('Pickup chime notice:', e);
    }
  }

  function playDeliveryFanfare() {
    if (!state.soundOn) return;
    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const now = audioCtx.currentTime;
      // Tone 1: 698.46 Hz (F5)
      const osc1 = audioCtx.createOscillator();
      const gain1 = audioCtx.createGain();
      osc1.type = 'triangle';
      osc1.frequency.setValueAtTime(698.46, now);
      gain1.gain.setValueAtTime(0.22, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.28);
      osc1.connect(gain1);
      gain1.connect(audioCtx.destination);
      osc1.start(now);
      osc1.stop(now + 0.28);

      // Tone 2: 880 Hz (A5)
      const osc2 = audioCtx.createOscillator();
      const gain2 = audioCtx.createGain();
      osc2.type = 'triangle';
      osc2.frequency.setValueAtTime(880, now + 0.14);
      gain2.gain.setValueAtTime(0.25, now + 0.14);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
      osc2.connect(gain2);
      gain2.connect(audioCtx.destination);
      osc2.start(now + 0.14);
      osc2.stop(now + 0.45);

      // Tone 3: 1046.50 Hz (C6)
      const osc3 = audioCtx.createOscillator();
      const gain3 = audioCtx.createGain();
      osc3.type = 'sine';
      osc3.frequency.setValueAtTime(1046.50, now + 0.28);
      gain3.gain.setValueAtTime(0.3, now + 0.28);
      gain3.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
      osc3.connect(gain3);
      gain3.connect(audioCtx.destination);
      osc3.start(now + 0.28);
      osc3.stop(now + 0.75);
    } catch (e) {
      console.warn('Delivery fanfare notice:', e);
    }
  }

  function playChime(isDelivery = false) {
    if (isDelivery) {
      playDeliveryFanfare();
    } else {
      playPickupChime();
    }
  }

  // --- REST API Fetcher ---
  async function api(path, options = {}) {
    const res = await fetch(cfg.api + path, {
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
      loadOrders();
    });
  });

  const fulfillmentButtons = document.querySelectorAll('.fulfillment-filters .filter-btn, #wto-fulfillment-switch button, .wto-ff-btn');
  fulfillmentButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetFilter = btn.dataset.filter || btn.dataset.fulfillment || 'all';
      state.orderFulfillment = targetFilter;
      fulfillmentButtons.forEach(b => {
        const bFilter = b.dataset.filter || b.dataset.fulfillment || 'all';
        const isMatch = (bFilter === targetFilter) || 
          (targetFilter === 'takeaway' && bFilter === 'pickup') || 
          (targetFilter === 'pickup' && bFilter === 'takeaway');
        b.classList.toggle('active', isMatch);
      });
      renderOrdersUI();
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
        board.classList.remove('show-new', 'show-prep', 'show-ready', 'show-complete');
        board.classList.add(`show-${col}`);
      }
    });
  });

  async function loadOrders() {
    try {
      const data = await api(`orders?view=${state.orderView === 'archive' ? 'archive' : 'active'}&fulfillment=all`);
      setNetworkStatus(true);
      state.orders = data.items || [];

      // Calculate counts across all active orders
      const allOrders = state.orders;
      const allCount = allOrders.length;
      const pickupCount = allOrders.filter(o => !o.is_delivery && o.fulfillment_type !== 'delivery' && o.fulfillment_type !== 'lieferung').length;
      const deliveryCount = allOrders.filter(o => Boolean(o.is_delivery || o.fulfillment_type === 'delivery' || o.fulfillment_type === 'lieferung')).length;

      // Real-time fulfillment count badges updated on poll
      document.querySelectorAll('.count-all, #wto-count-all').forEach(el => el.textContent = allCount);
      document.querySelectorAll('.count-pickup, #wto-count-pickup').forEach(el => el.textContent = pickupCount);
      document.querySelectorAll('.count-delivery, #wto-count-delivery').forEach(el => el.textContent = deliveryCount);

      // Update tab badge for new incoming orders
      const newOrders = state.orders.filter(o => o.stage === 'new' || o.order_state === 'new');
      const badge = document.getElementById('wto-badge-orders');
      if (badge) {
        badge.textContent = newOrders.length;
        badge.style.display = newOrders.length > 0 ? 'inline-flex' : 'none';
        badge.classList.toggle('urgent', newOrders.length > 0);
      }

      // Check if new incoming orders exist to chime (distinct chime for delivery vs pickup)
      if (!state.isInitialLoad) {
        const unseen = newOrders.filter(o => !state.knownOrders.has(String(o.id)));
        if (unseen.length > 0) {
          const hasDelivery = unseen.some(o => o.is_delivery || o.fulfillment_type === 'delivery' || o.fulfillment_type === 'lieferung');
          playChime(hasDelivery);
        }
      }
      newOrders.forEach(o => state.knownOrders.add(String(o.id)));
      state.isInitialLoad = false;

      renderOrdersUI();
    } catch (err) {
      setNetworkStatus(false);
      console.warn('Orders poll error:', err);
    }
  }

  function renderOrdersUI() {
    if (state.orderView === 'archive') {
      const container = document.getElementById('wto-archive-list');
      if (!container) return;
      let displayOrders = state.orders;
      if (state.orderFulfillment === 'delivery' || state.orderFulfillment === 'lieferung') {
        displayOrders = displayOrders.filter(o => o.is_delivery || o.fulfillment_type === 'delivery' || o.fulfillment_type === 'lieferung');
      } else if (state.orderFulfillment === 'pickup' || state.orderFulfillment === 'takeaway' || state.orderFulfillment === 'abholung') {
        displayOrders = displayOrders.filter(o => !o.is_delivery && o.fulfillment_type !== 'delivery' && o.fulfillment_type !== 'lieferung');
      }
      if (displayOrders.length === 0) {
        container.innerHTML = '<p class="wto-empty-col">Keine archivierten Bestellungen vorhanden.</p>';
        return;
      }
      container.innerHTML = displayOrders.map(renderOrderCard).join('');
      attachOrderCardEvents(container);
      return;
    }

    // 4 Kanban Columns
    const colNew = document.getElementById('wto-col-new-cards');
    const colPrep = document.getElementById('wto-col-prep-cards');
    const colReady = document.getElementById('wto-col-ready-cards');
    const colComp = document.getElementById('wto-col-complete-cards');

    let filtered = state.orders;
    if (state.orderFulfillment === 'delivery' || state.orderFulfillment === 'lieferung') {
      filtered = filtered.filter(o => o.is_delivery || o.fulfillment_type === 'delivery' || o.fulfillment_type === 'lieferung');
    } else if (state.orderFulfillment === 'pickup' || state.orderFulfillment === 'takeaway' || state.orderFulfillment === 'abholung') {
      filtered = filtered.filter(o => !o.is_delivery && o.fulfillment_type !== 'delivery' && o.fulfillment_type !== 'lieferung');
    }

    const newOrders = filtered.filter(o => o.stage === 'new' || o.order_state === 'new');
    const prepOrders = filtered.filter(o => o.stage === 'preparing' || o.order_state === 'preparing');
    const readyOrders = filtered.filter(o => o.stage === 'ready' || o.stage === 'dispatched' || o.order_state === 'ready' || o.order_state === 'dispatched');
    const compOrders = filtered.filter(o => o.stage === 'completed' || o.order_state === 'completed' || o.status === 'completed');

    const cNew = document.getElementById('wto-count-new');
    if (cNew) cNew.textContent = newOrders.length;
    const cPrep = document.getElementById('wto-count-prep');
    if (cPrep) cPrep.textContent = prepOrders.length;
    const cReady = document.getElementById('wto-count-ready');
    if (cReady) cReady.textContent = readyOrders.length;
    const cComp = document.getElementById('wto-count-complete');
    if (cComp) cComp.textContent = compOrders.length;

    const mNew = document.getElementById('wto-m-count-new');
    if (mNew) mNew.textContent = newOrders.length;
    const mPrep = document.getElementById('wto-m-count-prep');
    if (mPrep) mPrep.textContent = prepOrders.length;
    const mReady = document.getElementById('wto-m-count-ready');
    if (mReady) mReady.textContent = readyOrders.length;
    const mComp = document.getElementById('wto-m-count-complete');
    if (mComp) mComp.textContent = compOrders.length;

    document.querySelectorAll('#wto-orders-col-switcher .wto-mcol-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.col === (state.mobileOrdersCol || 'new'));
    });

    if (colNew) {
      colNew.innerHTML = newOrders.length > 0 
        ? newOrders.map(renderOrderCard).join('') 
        : '<p class="wto-empty-col">Keine neuen Bestellungen.</p>';
    }
    if (colPrep) {
      colPrep.innerHTML = prepOrders.length > 0 
        ? prepOrders.map(renderOrderCard).join('') 
        : '<p class="wto-empty-col">Küche ist aktuell frei.</p>';
    }
    if (colReady) {
      colReady.innerHTML = readyOrders.length > 0 
        ? readyOrders.map(renderOrderCard).join('') 
        : '<p class="wto-empty-col">Keine abholbereiten Speisen.</p>';
    }
    if (colComp) {
      colComp.innerHTML = compOrders.length > 0 
        ? compOrders.map(renderOrderCard).join('') 
        : '<p class="wto-empty-col">Keine abgeschlossenen Bestellungen heute.</p>';
    }

    const board = document.getElementById('wto-kanban-board');
    if (board) {
      attachOrderCardEvents(board);
      initDragAndDrop(board);
    }
  }

  function renderOrderCard(o) {
    const isNew = o.stage === 'new' || o.order_state === 'new';
    const isDelivery = Boolean(o.is_delivery || o.fulfillment_type === 'delivery' || o.delivery_type === 'delivery');
    const isPaid = Boolean(o.payment_details?.is_paid || o.payment_status === 'paid' || o.payment_status === 'paid_online' || o.payment === 'Bezahlt');
    const currentStage = o.stage || o.order_state || 'new';

    const cardClass = [
      'wto-order-card',
      isDelivery ? 'wto-order-card--delivery' : 'wto-order-card--pickup',
      isNew ? 'urgent' : '',
    ].filter(Boolean).join(' ');

    const paymentBadge = isPaid
      ? `<span class="wto-payment-badge paid">[✓ ONLINE BEZAHLT]</span>`
      : `<span class="wto-payment-badge cod">[⚠️ BARZAHLUNG: ${esc(formatMoney(o.total))}]</span>`;

    const zoneBadge = isDelivery && (o.delivery_zone_name || o.delivery_zone)
      ? `<span style="font-size:10px;font-weight:700;background:rgba(230,126,34,0.2);color:#f39c12;padding:2px 6px;border-radius:4px;border:1px solid rgba(230,126,34,0.3);">${esc(o.delivery_zone_name || o.delivery_zone)}</span>`
      : '';

    const etaText = isDelivery
      ? (o.delivery_eta ? `ETA: ca. ${o.delivery_eta}` : (o.pickup || 'ca. 30–45 Min.'))
      : (o.pickup || 'Sofort (ASAP)');

    let addressHtml = '';
    if (isDelivery && o.delivery_address) {
      const addr = o.delivery_address;
      const addrQuery = encodeURIComponent(`${addr.street || ''}, ${addr.postcode || ''} ${addr.city || ''}`);
      addressHtml = `
        <div class="wto-card-delivery-address">
          <div style="font-weight:700;color:#93c5fd;font-size:11px;letter-spacing:0.5px;margin-bottom:2px;display:flex;justify-content:space-between;align-items:center;">
            <span>📍 LIEFERADRESSE:</span>
            <a href="https://maps.google.com/?q=${addrQuery}" target="_blank" rel="noopener" style="font-size:10px;text-decoration:none;">🗺️ Route ↗</a>
          </div>
          <div><strong>${esc(addr.street)}</strong>${addr.details ? ` (${esc(addr.details)})` : ''}</div>
          <div>${esc(addr.postcode)} ${esc(addr.city)}</div>
        </div>
      `;
    }

    return `
      <article class="${cardClass}" 
               data-id="${esc(o.id)}" 
               data-version="${esc(o.version)}"
               data-stage="${esc(currentStage)}"
               data-is-delivery="${isDelivery ? '1' : '0'}"
               draggable="true">
        <div class="wto-card-top">
          <span class="wto-card-num">#${esc(o.number || o.id)}</span>
          <span class="wto-card-age wto-live-timer" data-created="${esc(o.created)}" data-ts="${esc(o.created_ts || '')}">⏱️ ${formatElapsed(o.created_ts, o.created)}</span>
        </div>
        
        <div class="wto-card-pickup-banner" style="${isDelivery ? 'background:rgba(230,126,34,0.12);border-color:rgba(230,126,34,0.4);color:#f39c12;' : ''}">
          <span style="display:inline-flex;align-items:center;gap:6px;">🕒 ${esc(etaText)} ${zoneBadge}</span>
          <span class="wto-badge-fulfillment ${isDelivery ? 'wto-badge-delivery' : 'wto-badge-pickup'}">
            ${isDelivery ? '🛵 LIEFERUNG' : '🥡 ABHOLUNG'}
          </span>
        </div>

        <div style="margin:-4px 0 2px;">
          ${paymentBadge}
        </div>

        <div class="wto-card-guest">
          <strong>${esc(o.name || 'Gast')}</strong>
          ${o.phone ? `<a href="tel:${esc(o.phone)}">📞 ${esc(o.phone)}</a>` : ''}
        </div>

        ${addressHtml}

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

        <div class="wto-card-actions">
          ${(currentStage === 'new') ? `
            <button type="button" class="wto-btn btn-primary btn-order-accept" data-id="${esc(o.id)}">✓ Annehmen</button>
            <button type="button" class="wto-btn btn-danger btn-order-reject" data-id="${esc(o.id)}">✕ Ablehnen</button>
          ` : ''}

          ${(currentStage === 'preparing') ? `
            <button type="button" class="wto-btn btn-success btn-order-ready" data-id="${esc(o.id)}">${isDelivery ? '🛵 Übergabebereit' : '🥡 Abholbereit'}</button>
            <button type="button" class="wto-btn btn-print-bon btn-order-print-kitchen btn-order-print" data-id="${esc(o.id)}" data-url="${esc(o.print_url || o.kitchen_bon_url || '')}">🖨️ Bon</button>
            ${isDelivery ? `<button type="button" class="wto-btn btn-print-driver btn-order-print-driver" data-id="${esc(o.id)}" data-url="${esc(o.driver_bon_url || '')}">🛵 Beleg</button>` : ''}
          ` : ''}

          ${(currentStage === 'ready' || currentStage === 'dispatched') ? `
            ${isDelivery && currentStage !== 'dispatched' ? `
              <button type="button" class="wto-btn btn-warning btn-order-dispatch" data-id="${esc(o.id)}" style="background:#e67e22;color:#fff;border-color:#d35400;">🛵 Ausliefern</button>
            ` : ''}
            <button type="button" class="wto-btn btn-primary btn-order-complete" data-id="${esc(o.id)}">${isDelivery ? '✓ Zugestellt' : '✓ Abgeholt'}</button>
            <button type="button" class="wto-btn btn-print-bon btn-order-print-kitchen btn-order-print" data-id="${esc(o.id)}" data-url="${esc(o.print_url || o.kitchen_bon_url || '')}">🖨️ Bon</button>
            ${isDelivery ? `<button type="button" class="wto-btn btn-print-driver btn-order-print-driver" data-id="${esc(o.id)}" data-url="${esc(o.driver_bon_url || '')}">🛵 Beleg</button>` : ''}
          ` : ''}

          ${(currentStage === 'completed') ? `
            <span style="font-size:11px;color:var(--wto-green);font-weight:700;display:inline-flex;align-items:center;gap:4px;padding:6px 0;">✓ Abgeschlossen</span>
            <button type="button" class="wto-btn btn-print-bon btn-order-print-kitchen btn-order-print" data-id="${esc(o.id)}" data-url="${esc(o.print_url || o.kitchen_bon_url || '')}" style="max-width:90px;height:34px;font-size:11px;">🖨️ Bon</button>
            ${isDelivery ? `<button type="button" class="wto-btn btn-print-driver btn-order-print-driver" data-id="${esc(o.id)}" data-url="${esc(o.driver_bon_url || '')}" style="max-width:90px;height:34px;font-size:11px;">🛵 Beleg</button>` : ''}
          ` : ''}
        </div>
      </article>
    `;
  }

  async function handleStateTransition(id, targetStateOrAction, version) {
    try {
      await api(`orders/${id}/state`, {
        method: 'POST',
        body: { target_state: targetStateOrAction, action: targetStateOrAction, version }
      });
      showToast(`Bestellung #${id} erfolgreich aktualisiert!`);
      await loadOrders();
    } catch (err) {
      if (err.message && err.message.includes('409')) {
        showToast(`Konflikt: Bestellung #${id} wurde zwischenzeitlich von einem anderen Mitarbeiter geändert (HTTP 409). Ansicht aktualisiert.`, true);
      } else {
        showToast(`Fehler: ${err.message}`, true);
      }
      await loadOrders(); // rollback optimistic update
    }
  }

  function attachOrderCardEvents(container) {
    // Accept
    container.querySelectorAll('.btn-order-accept').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card ? card.dataset.version : '';
        btn.disabled = true;
        await handleStateTransition(id, 'accept', ver);
      });
    });

    // Reject
    container.querySelectorAll('.btn-order-reject').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card ? card.dataset.version : '';
        if (!confirm(`Möchten Sie die Bestellung #${id} wirklich ablehnen?`)) return;
        btn.disabled = true;
        await handleStateTransition(id, 'reject', ver);
      });
    });

    // Ready
    container.querySelectorAll('.btn-order-ready').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card ? card.dataset.version : '';
        btn.disabled = true;
        await handleStateTransition(id, 'ready', ver);
      });
    });

    // Dispatch (Ausliefern)
    container.querySelectorAll('.btn-order-dispatch').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card ? card.dataset.version : '';
        btn.disabled = true;
        await handleStateTransition(id, 'dispatch', ver);
      });
    });

    // Complete (Picked up / Delivered)
    container.querySelectorAll('.btn-order-complete').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const card = btn.closest('.wto-order-card');
        const ver = card ? card.dataset.version : '';
        btn.disabled = true;
        await handleStateTransition(id, 'complete', ver);
      });
    });

    // Print Driver Thermal Receipt
    container.querySelectorAll('.btn-order-print-driver, .btn-print-driver').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const url = btn.dataset.url;
        if (url) {
          window.open(url, '_blank', 'width=420,height=600');
        } else {
          const id = btn.dataset.id;
          const order = state.orders.find(o => String(o.id) === String(id));
          if (order && order.driver_bon_url) {
            window.open(order.driver_bon_url, '_blank', 'width=420,height=600');
          }
        }
      });
    });

    // Print Thermal Bon (80 mm)
    container.querySelectorAll('.btn-order-print, .btn-print-bon, .btn-order-print-kitchen').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        const order = state.orders.find(o => String(o.id) === String(id));
        if (order) printBon(order);
      });
    });
  }

  // --- Drag and Drop State Machine Transitions ---
  function initDragAndDrop(board) {
    const cards = board.querySelectorAll('.wto-order-card');
    cards.forEach(card => {
      card.addEventListener('dragstart', (e) => {
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify({
          id: card.dataset.id,
          version: card.dataset.version,
          stage: card.dataset.stage
        }));
      });
      card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        board.querySelectorAll('.wto-kanban-col').forEach(c => c.classList.remove('drag-over'));
      });
    });

    const columns = board.querySelectorAll('.wto-kanban-col');
    columns.forEach(col => {
      col.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('drag-over');
      });
      col.addEventListener('dragleave', (e) => {
        if (!col.contains(e.relatedTarget)) {
          col.classList.remove('drag-over');
        }
      });
      col.addEventListener('drop', async (e) => {
        e.preventDefault();
        col.classList.remove('drag-over');
        try {
          const raw = e.dataTransfer.getData('text/plain');
          if (!raw) return;
          const data = JSON.parse(raw);
          const targetCol = col.dataset.col;
          const fromStage = data.stage;

          if (targetCol === fromStage) return;

          if (targetCol === 'prep' && fromStage === 'new') {
            await handleStateTransition(data.id, 'accept', data.version);
          } else if (targetCol === 'ready' && fromStage === 'preparing') {
            await handleStateTransition(data.id, 'ready', data.version);
          } else if (targetCol === 'complete' && (fromStage === 'ready' || fromStage === 'dispatched' || fromStage === 'preparing')) {
            await handleStateTransition(data.id, 'complete', data.version);
          } else {
            showToast('Diese Statusänderung ist nicht zulässig.', true);
          }
        } catch (err) {
          console.warn('Drop error:', err);
        }
      });
    });
  }

  // --- Thermal 80mm Bon Print ---
  function printBon(order) {
    const printArea = document.getElementById('wto-print-area');
    if (!printArea) return;

    const b = (window.wtoPortalConfig && window.wtoPortalConfig.branch) ? window.wtoPortalConfig.branch : {
      name: 'WOW THAI STEYR',
      street_short: 'City Point · 1. OG',
      street: 'Leopold-Werndl-Str. 2',
      city: 'Steyr',
      phone: '+43 7252 74973'
    };
    const bHeader = esc((b.name || 'WOW THAI STEYR').toUpperCase());
    const bSub = esc(`${b.street_short ? b.street_short + ' · ' : ''}${b.street || ''} · ${b.city || 'Steyr'}`);
    const bPhone = esc(b.phone || '+43 7252 74973');

    printArea.innerHTML = `
      <div class="bon-center bon-bold" style="font-size:16px;">${bHeader}</div>
      <div class="bon-center" style="font-size:11px;">${bSub}<br>Tel: ${bPhone}</div>
      <div class="bon-double"></div>

      <div style="background:#000;color:#fff;padding:6px;text-align:center;margin:6px 0;">
        <div style="font-size:10px;font-weight:bold;letter-spacing:1px;">${order.is_delivery ? 'LIEFERZEIT:' : 'ABHOLZEIT:'}</div>
        <div class="bon-huge">${esc(order.pickup || (order.is_delivery ? 'LIEFERUNG' : 'SOFORT (ASAP)'))}</div>
      </div>

      <div class="bon-row bon-bold">
        <span>BESTELLUNG #${esc(order.number)}</span>
        <span>${new Intl.DateTimeFormat('de-AT',{timeStyle:'short'}).format(new Date())} Uhr</span>
      </div>
      <div>Gast: <strong>${esc(order.name)}</strong></div>
      ${order.phone ? `<div>Tel: ${esc(order.phone)}</div>` : ''}
      ${order.is_delivery && order.delivery_address ? `
        <div style="margin-top:4px;font-size:11px;border:1px dashed #000;padding:4px;line-height:1.35;">
          <strong>LIEFERADRESSE:</strong><br>
          ${esc(order.delivery_address.formatted || `${order.delivery_address.street}, ${order.delivery_address.postcode} ${order.delivery_address.city}`)}
        </div>
      ` : ''}
      
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
      state.resDate = resDatePicker.value;
      loadReservations();
    });
  }

  document.querySelectorAll('.wto-res-filters button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.wto-res-filters button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.resFilter = btn.dataset.view;
      loadReservations();
    });
  });

  document.querySelectorAll('.wto-shift-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.wto-shift-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.resShift = btn.dataset.shift;
      renderReservationsUI();
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
    try {
      const data = await api(`reservations?view=${state.resFilter}&date=${encodeURIComponent(state.resDate)}`);
      setNetworkStatus(true);
      state.reservations = data.items || [];

      // Update KPI
      document.getElementById('wto-kpi-res-total').textContent = data.total || 0;
      document.getElementById('wto-kpi-res-guests').textContent = data.guests_on_page || 0;

      // Update badge
      const pendingCount = state.reservations.filter(r => r.status === 'pending').length;
      const badge = document.getElementById('wto-badge-res');
      if (badge) {
        badge.textContent = pendingCount;
        badge.style.display = pendingCount > 0 ? 'inline-flex' : 'none';
        badge.classList.toggle('urgent', pendingCount > 0);
      }

      renderReservationsUI();
    } catch (err) {
      setNetworkStatus(false);
      console.warn('Reservations poll error:', err);
    }
  }

  function renderReservationsUI() {
    const container = document.getElementById('wto-res-list');
    if (!container) return;

    if (state.reservations.length === 0) {
      container.innerHTML = '<p class="wto-empty-col" style="padding:40px;text-align:center;">Keine Reservierungen für dieses Datum vorhanden.</p>';
      return;
    }

    // Filter by shift if selected
    let list = state.reservations;
    if (state.resShift === 'lunch') {
      list = list.filter(r => (r.time || '') >= '11:00' && (r.time || '') <= '15:00'); // hours-ok
    } else if (state.resShift === 'dinner') {
      list = list.filter(r => (r.time || '') >= '17:00' && (r.time || '') <= '22:00'); // hours-ok
    }

    const pending = list.filter(r => r.status === 'pending');
    const confirmed = list.filter(r => r.status === 'confirmed');
    const arrived = list.filter(r => r.status === 'arrived');
    const cancelled = list.filter(r => r.status === 'cancelled');

    const pendingGuests = pending.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);
    const confirmedGuests = confirmed.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);
    const arrivedGuests = arrived.reduce((sum, r) => sum + (parseInt(r.party) || 0), 0);

    // Update mobile switcher badges
    const mResNew = document.getElementById('wto-m-res-new');
    if (mResNew) mResNew.textContent = pending.length;
    const mResPrep = document.getElementById('wto-m-res-prep');
    if (mResPrep) mResPrep.textContent = confirmed.length;
    const mResReady = document.getElementById('wto-m-res-ready');
    if (mResReady) mResReady.textContent = arrived.length;

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
            <span class="wto-col-count">${pending.length} · ${pendingGuests} Gäste</span>
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
            <span class="wto-col-count">${confirmed.length} · ${confirmedGuests} Gäste</span>
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
            <span class="wto-col-count">${arrived.length} · ${arrivedGuests} Gäste</span>
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

    container.innerHTML = html;
    attachReservationEvents(container);
  }

  function renderReservationCard(r) {
    const statusLabels = {
      pending: 'Anfrage (Neu)',
      confirmed: 'Bestätigt',
      arrived: 'Gast da (Platziert)',
      cancelled: 'Storniert'
    };

    const isLunch = (r.time || '') >= '11:00' && (r.time || '') <= '15:00'; // hours-ok
    const shiftBadge = isLunch 
      ? '<span class="wto-shift-pill lunch">☀️ Mittag</span>' 
      : '<span class="wto-shift-pill dinner">🌙 Abend</span>';

    return `
      <article class="wto-res-card status-${esc(r.status)}" data-id="${esc(r.id)}" data-version="${esc(r.version)}">
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
              <button type="button" class="wto-btn btn-danger btn-res-action" data-action="cancelled" data-id="${esc(r.id)}" style="height:32px;padding:0 8px;font-size:11px;">✕</button>
            ` : ''}
            ${r.status === 'confirmed' ? `
              <button type="button" class="wto-btn btn-primary btn-res-action" data-action="arrived" data-id="${esc(r.id)}" style="height:32px;padding:0 14px;font-size:11px;font-weight:700;background:linear-gradient(135deg, #d4af37, #b8860b);color:#141210;">🍽️ Gast da</button>
              <button type="button" class="wto-btn btn-danger btn-res-action" data-action="cancelled" data-id="${esc(r.id)}" style="height:32px;padding:0 8px;font-size:11px;">Storno</button>
            ` : ''}
            ${r.status === 'arrived' ? `
              <button type="button" class="wto-btn btn-secondary btn-res-action" data-action="confirmed" data-id="${esc(r.id)}" style="height:32px;padding:0 8px;font-size:11px;" title="Zurück zu Erwartet">↩</button>
            ` : ''}
          </div>
        </div>
      </article>
    `;
  }

  function attachReservationEvents(container) {
    container.querySelectorAll('.btn-res-action').forEach(btn => {
      btn.addEventListener('click', async () => {
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
          await loadReservations();
        } catch (err) {
          showToast(`Fehler: ${err.message}`, true);
          btn.disabled = false;
        }
      });
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
    try {
      const stats = await api('stats');
      state.stats = stats;
      document.getElementById('wto-stat-orders-today').textContent = (stats.orders?.completed || 0) + (stats.orders?.new || 0) + (stats.orders?.preparing || 0) + (stats.orders?.ready || 0);
      document.getElementById('wto-stat-revenue-today').textContent = stats.orders?.revenue || '0,00 €';
      document.getElementById('wto-stat-res-today').textContent = stats.reservations?.total || 0;
      document.getElementById('wto-stat-guests-today').textContent = stats.reservations?.total_guests || 0;
    } catch (err) {
      console.warn('Stats error:', err);
    }
  }

  // --- Load Active Tab Data ---
  function loadTabData() {
    if (state.activeTab === 'orders') loadOrders();
    else if (state.activeTab === 'reservations') loadReservations();
    else if (state.activeTab === 'customers') loadCustomers();
    else if (state.activeTab === 'stats') loadStats();
  }

  // Initial load
  loadTabData();
  loadStats();

  // Poller every 12 seconds
  state.pollTimer = setInterval(() => {
    if (state.activeTab === 'orders') loadOrders();
    else if (state.activeTab === 'reservations') loadReservations();
    loadStats();
  }, 12000);

  // Real-time 1-second ticker for live order elapsed timers
  setInterval(() => {
    document.querySelectorAll('.wto-live-timer').forEach(el => {
      const created = el.dataset.created;
      if (created) {
        el.textContent = '⏱️ ' + formatElapsed(created);
      }
    });
  }, 1000);
}
