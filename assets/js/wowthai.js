/**
 * WOW Thai Steyr - Interactive Front-end & WooCommerce Cart Engine
 */
document.addEventListener('DOMContentLoaded', () => {
  const esc = (s = '') => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ajaxUrl = window.wowthaiConfig?.ajax_url || '/wp-admin/admin-ajax.php';
  const checkoutUrl = window.wowthaiConfig?.checkout_url || '/checkout/';
  const nonce = window.wowthaiConfig?.nonce || '';

  // Elements
  const cartTrigger = document.getElementById('cart-trigger');
  const cartCountEl = document.getElementById('cart-count');
  const cartRoot = document.getElementById('cart-root');
  const sheetRoot = document.getElementById('sheet-root');
  const toastRoot = document.getElementById('toast-root');

  let currentCart = { count: 0, subtotal: '€0,00', items: [] };

  // Toast notification
  function showToast(message, isError = false) {
    if (!toastRoot) return;
    const toast = document.createElement('div');
    toast.className = `toast${isError ? ' error' : ''}`;
    toast.textContent = message;
    toastRoot.appendChild(toast);
    setTimeout(() => {
      toast.style.transition = 'opacity .4s ease, transform .4s ease';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 400);
    }, 3200);
  }

  // Helper: Native <dialog closedby="any"> light-dismiss with Safari/legacy coordinate fallback
  function attachDialogLightDismiss(dialog, onClose) {
    if (!dialog) return;
    dialog.addEventListener('close', () => {
      if (typeof onClose === 'function') onClose();
    });
    if (typeof HTMLDialogElement !== 'undefined' && !('closedBy' in HTMLDialogElement.prototype)) {
      dialog.addEventListener('click', (e) => {
        if (e.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        const isInDialog =
          rect.top <= e.clientY &&
          e.clientY <= rect.top + rect.height &&
          rect.left <= e.clientX &&
          e.clientX <= rect.left + rect.width;
        if (!isInDialog && dialog.open) {
          dialog.close();
        }
      });
    }
  }

  // Update Cart Count & Accessible Name (WCAG 2.5.3 Label in Name)
  function updateCartBadge(count) {
    const num = Number(count || 0);
    if (cartCountEl) {
      cartCountEl.textContent = String(num);
    }
    if (cartTrigger) {
      cartTrigger.setAttribute('aria-label', `Bestellung (${num} Artikel)`);
    }
  }

  // Render Cart Drawer using native <dialog closedby="any">
  function renderCartDrawer(isOpen = false) {
    if (!cartRoot) return;
    if (!isOpen) {
      const existing = cartRoot.querySelector('dialog');
      if (existing && existing.open) {
        existing.close();
      }
      cartRoot.innerHTML = '';
      return;
    }

    const items = currentCart.items || [];
    const hasItems = items.length > 0;

    cartRoot.innerHTML = `
      <dialog class="wowthai-drawer-dialog drawer" id="cart-overlay" closedby="any" aria-labelledby="cart-drawer-title">
        <div class="drawer-head">
          <h2 id="cart-drawer-title">Ihre Bestellung</h2>
          <button class="icon-close" id="cart-close" type="button" aria-label="Schließen">&times;</button>
        </div>
        <div class="drawer-body">
          ${!hasItems ? `
            <div class="empty-cart">
              <p>Ihr Warenkorb ist noch leer.</p>
              <p><small>Wählen Sie Ihre Lieblingsgerichte aus unserer Speisekarte.</small></p>
            </div>
          ` : `
            <div class="drawer-timing-banner">
              <span>🕒 <strong>Abholung</strong>: City Point Steyr</span>
              <span class="badge-asap">${esc(window.wowthaiConfig?.prep_time || 'ca. 25–35 Min.')}</span>
            </div>
            <div class="cart-lines">
              ${items.map(item => `
                <div class="cart-line" data-key="${esc(item.key)}">
                  <div>
                    <h3>${esc(item.name)}</h3>
                    ${item.variant_name ? `<p><small>${esc(item.variant_name)}</small></p>` : ''}
                    ${(item.modifier_names || []).map(choice => `<p><small>${esc(choice.group)}: ${esc(choice.name)}</small></p>`).join('')}
                    <strong>${esc(item.line_total)}</strong>
                  </div>
                  <div class="cart-line-actions">
                    <div class="quantity-control">
                      <button type="button" class="btn-qty-minus" data-key="${esc(item.key)}" aria-label="Menge verringern">−</button>
                      <span>${item.quantity}</span>
                      <button type="button" class="btn-qty-plus" data-key="${esc(item.key)}" aria-label="Menge erhöhen">+</button>
                    </div>
                    <button type="button" class="remove-line" data-key="${esc(item.key)}">Entfernen</button>
                  </div>
                </div>
              `).join('')}
            </div>

            ${(window.wowthaiConfig?.upsell_items || []).length > 0 ? `
              <div class="drawer-upsell-box">
                <p class="drawer-upsell-title">✨ Dazu passt perfekt:</p>
                <div class="drawer-upsell-list">
                  ${(window.wowthaiConfig.upsell_items).map((u, idx) => `
                    <div class="drawer-upsell-item">
                      <span class="upsell-emoji" aria-hidden="true">${esc(u.icon || '🥤')}</span>
                      <div class="upsell-text">
                        <strong>${esc(u.title)}</strong>
                        <small>${esc(u.price)}</small>
                      </div>
                      <button type="button" class="btn-add-upsell" data-idx="${idx}">+ Hinzufügen</button>
                    </div>
                  `).join('')}
                </div>
              </div>
            ` : ''}
          `}
        </div>
        ${hasItems ? `
          <div class="drawer-foot">
            <div class="subtotal">
              <span>Zwischensumme:</span>
              <strong>${esc(currentCart.subtotal)}</strong>
            </div>
            <a href="${checkoutUrl}" class="button button-dark checkout-button">Zur Kasse (Abholung) &rarr;</a>
          </div>
        ` : ''}
      </dialog>
    `;

    const dialog = document.getElementById('cart-overlay');
    if (dialog && typeof dialog.showModal === 'function') {
      dialog.showModal();
      attachDialogLightDismiss(dialog, () => {
        cartRoot.innerHTML = '';
      });
    }

    document.getElementById('cart-close')?.addEventListener('click', () => renderCartDrawer(false));

    // Upsell button handlers
    cartRoot.querySelectorAll('.btn-add-upsell').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx, 10);
        const item = window.wowthaiConfig?.upsell_items?.[idx];
        if (item) {
          btn.disabled = true;
          btn.textContent = '✓';
          addToCart(item.product_id);
        }
      });
    });

    // Quantity / Remove handlers
    cartRoot.querySelectorAll('.btn-qty-plus').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.key;
        const it = items.find(i => i.key === key);
        if (it) updateCartItem(key, it.quantity + 1);
      });
    });
    cartRoot.querySelectorAll('.btn-qty-minus').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.key;
        const it = items.find(i => i.key === key);
        if (it) updateCartItem(key, it.quantity - 1);
      });
    });
    cartRoot.querySelectorAll('.remove-line').forEach(btn => {
      btn.addEventListener('click', () => {
        updateCartItem(btn.dataset.key, 0);
      });
    });
  }

  // Fetch Cart State
  async function fetchCart() {
    try {
      const res = await fetch(`${ajaxUrl}?action=wowthai_get_cart&nonce=${nonce}`);
      const data = await res.json();
      if (data.success) {
        currentCart = data.data;
        updateCartBadge(currentCart.count);
      }
    } catch (e) {
      console.warn('Cart load error:', e);
    }
  }

  // Add Item to Cart
  async function addToCart(productId, quantity = 1, variantId = '', modifierIds = []) {
    try {
      const body = new URLSearchParams({
        action: 'wowthai_add_to_cart',
        product_id: productId,
        quantity: quantity,
        variant_id: variantId,
        modifier_ids: JSON.stringify(modifierIds),
        nonce: nonce
      });
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (data.success) {
        currentCart = data.data;
        updateCartBadge(currentCart.count);
        showToast('Gericht zum Warenkorb hinzugefügt!');
        renderCartDrawer(true);
      } else {
        showToast(data.data?.message || 'Fehler beim Hinzufügen', true);
      }
    } catch (e) {
      showToast('Verbindungsfehler', true);
    }
  }

  // Update Cart Quantity
  async function updateCartItem(cartItemKey, newQty) {
    try {
      const body = new URLSearchParams({
        action: 'wowthai_update_cart_quantity',
        cart_item_key: cartItemKey,
        quantity: newQty,
        nonce: nonce
      });
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      const data = await res.json();
      if (data.success) {
        currentCart = data.data;
        updateCartBadge(currentCart.count);
        renderCartDrawer(true);
      }
    } catch (e) {
      showToast('Aktualisierungsfehler', true);
    }
  }

  // Cart trigger
  if (cartTrigger) {
    cartTrigger.addEventListener('click', () => {
      renderCartDrawer(true);
    });
  }

  // Modal for Dish Variants using native <dialog closedby="any">
  function openDishModal(dish) {
    if (!sheetRoot) return;
    const variants = dish.variants || [];
    const modifierGroups = dish.modifierGroups || [];
    const formatPrice = amount => new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' }).format(Number(amount));

    sheetRoot.innerHTML = `
      <dialog class="wowthai-dialog dish-modal" id="dish-modal-wrap" closedby="any" aria-labelledby="dish-modal-title">
        <div class="modal-head">
          <h2 id="dish-modal-title">${esc(dish.title)}</h2>
          <button type="button" class="icon-close" id="dish-modal-close" aria-label="Schließen">&times;</button>
        </div>
        ${dish.description ? `<p class="modal-description">${esc(dish.description)}</p>` : ''}
        ${variants.length ? `<div class="choice-group">
          <div class="choice-head">
            <strong>Variante wählen</strong>
            <span>Preis je Variante</span>
          </div>
          ${variants.map((v, idx) => `
            <label class="choice-option">
              <input type="radio" name="dish_variant" value="${esc(v.id)}" ${idx === 0 ? 'checked' : ''} data-name="${esc(v.name)}" data-price="${esc(v.price)}">
              <span>${esc(v.name)}</span>
              <small>${esc(v.formatted)}</small>
            </label>
          `).join('')}
        </div>` : ''}
        ${modifierGroups.map(group => {
          const single = Number(group.max) === 1;
          return `<fieldset class="choice-group modifier-group" data-group-id="${esc(group.id)}" data-min="${esc(group.min)}" data-max="${esc(group.max)}">
            <legend>${esc(group.name)} ${Number(group.min) > 0 ? '<span class="required-mark">*</span>' : '<span class="optional-mark">(optional)</span>'}</legend>
            ${single && Number(group.min) === 0 ? `<label class="choice-option"><input type="radio" name="modifier_${esc(group.id)}" value="" checked><span>Kein Extra</span></label>` : ''}
            ${(group.options || []).map(option => `<label class="choice-option">
              <input type="${single ? 'radio' : 'checkbox'}" name="modifier_${esc(group.id)}" value="${esc(option.id)}">
              <span>${esc(option.name)}</span>
              <small>${Number(option.price) > 0 ? `+${esc(formatPrice(option.price))}` : 'Inklusive'}</small>
            </label>`).join('')}
          </fieldset>`;
        }).join('')}
        <p class="sheet-validation" id="dish-validation" role="alert" hidden></p>
        <div class="sheet-bottom">
          <input type="number" id="dish-qty" value="1" min="1" max="50" aria-label="Menge">
          <button type="button" class="button button-dark" id="btn-add-modal">In den Warenkorb</button>
        </div>
      </dialog>
    `;

    const dialog = document.getElementById('dish-modal-wrap');
    const closeBtn = document.getElementById('dish-modal-close');
    const close = () => {
      if (dialog && dialog.open) dialog.close();
      sheetRoot.innerHTML = '';
    };

    if (dialog && typeof dialog.showModal === 'function') {
      dialog.showModal();
      attachDialogLightDismiss(dialog, () => {
        sheetRoot.innerHTML = '';
      });
    }

    closeBtn?.addEventListener('click', close);

    document.getElementById('btn-add-modal')?.addEventListener('click', () => {
      const checked = sheetRoot.querySelector('input[name="dish_variant"]:checked');
      const vId = checked ? checked.value : '';
      const qty = parseInt(document.getElementById('dish-qty')?.value || '1', 10);
      const selected = [];
      for (const group of sheetRoot.querySelectorAll('.modifier-group')) {
        const ids = [...group.querySelectorAll('input:checked')].map(input => input.value).filter(Boolean);
        if (ids.length < Number(group.dataset.min) || ids.length > Number(group.dataset.max)) {
          const message = document.getElementById('dish-validation');
          message.textContent = `Bitte wählen Sie eine gültige Option für ${group.querySelector('legend')?.textContent.trim() || 'dieses Gericht'}.`;
          message.hidden = false;
          return;
        }
        selected.push(...ids);
      }
      close();
      addToCart(dish.id, qty, vId, selected);
    });
  }

  // Dish Card event delegation
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.dish-control');
    if (!btn) return;
    e.preventDefault();

    if (btn.disabled) return;
    const productId = btn.dataset.productId;
    const hasVariants = btn.dataset.hasVariants === 'yes';
    const hasModifiers = btn.dataset.hasModifiers === 'yes';

    if (hasVariants || hasModifiers) {
      try {
        const variants = JSON.parse(btn.dataset.variants || '[]');
        const modifierGroups = JSON.parse(btn.dataset.modifierGroups || '[]');
        const title = btn.dataset.title || '';
        const description = btn.dataset.description || '';
        openDishModal({ id: productId, title, description, variants, modifierGroups });
      } catch (err) {
        console.error('Variant parse error:', err);
      }
    } else {
      addToCart(productId, 1);
    }
  });

  // Menu Tab Switching (Restaurant vs Sushi)
  const menuTabs = document.querySelectorAll('.menu-tab');
  const menuSections = document.querySelectorAll('.menu-section');
  const sectionLinks = document.querySelectorAll('.section-link');

  // Live Menu Search & Dietary Filter Engine
  const dishSearchInput = document.getElementById('dish-search-input');
  const dishSearchClear = document.getElementById('dish-search-clear');
  const dishResetBtn = document.getElementById('dish-reset-search');
  const dishFilterChips = document.querySelectorAll('#dish-filter-chips .filter-chip');
  const dishSearchEmpty = document.getElementById('dish-search-empty');
  const dishResultStatus = document.getElementById('dish-result-status');

  let currentActiveTab = 'restaurant';
  let currentChipFilter = 'all';
  let currentSearchQuery = '';

  function filterMenuDishes(announce = false) {
    let totalVisible = 0;
    const query = currentSearchQuery.trim().toLowerCase();

    menuSections.forEach(sec => {
      const secMenuType = sec.dataset.menuType;
      const matchesTab = (query.length > 0) ? true : (secMenuType === currentActiveTab);

      if (!matchesTab) {
        sec.style.display = 'none';
        return;
      }

      let secVisibleCount = 0;
      const cards = sec.querySelectorAll('.dish-card');

      cards.forEach(card => {
        const searchMeta = card.dataset.dishSearch || '';
        const labelsMeta = card.dataset.dishLabels || '';
        const titleMeta = card.dataset.dishTitle || '';

        const matchesQuery = !query || searchMeta.includes(query) || titleMeta.includes(query);

        let matchesChip = true;
        if (currentChipFilter === 'veggie') {
          matchesChip = searchMeta.includes('veggie') || searchMeta.includes('vegan') || searchMeta.includes('vegetarisch') || searchMeta.includes('tofu');
        } else if (currentChipFilter === 'spicy') {
          matchesChip = searchMeta.includes('scharf') || searchMeta.includes('pikant') || searchMeta.includes('chili') || searchMeta.includes('🌶');
        } else if (currentChipFilter === 'popular') {
          matchesChip = searchMeta.includes('beliebt') || searchMeta.includes('bestseller') || searchMeta.includes('chef') || searchMeta.includes('⭐') || labelsMeta.length > 0;
        }

        if (matchesQuery && matchesChip) {
          card.style.display = '';
          secVisibleCount++;
          totalVisible++;
        } else {
          card.style.display = 'none';
        }
      });

      if (secVisibleCount > 0 && (secMenuType === currentActiveTab || query.length > 0)) {
        sec.style.display = 'block';
        const countEl = sec.querySelector('.section-count');
        if (countEl) {
          countEl.textContent = `${secVisibleCount} ${secMenuType === 'sushi' ? 'Sushi-Kreationen' : 'Spezialitäten'}`;
        }
      } else {
        sec.style.display = 'none';
      }
    });

    if (dishSearchEmpty) {
      dishSearchEmpty.style.display = (totalVisible === 0) ? 'block' : 'none';
    }

    if (dishSearchClear) {
      dishSearchClear.style.display = query.length > 0 ? 'block' : 'none';
    }

    if (dishResultStatus && announce) {
      dishResultStatus.textContent = totalVisible === 0
        ? 'Keine Gerichte gefunden.'
        : `${totalVisible} ${totalVisible === 1 ? 'Gericht' : 'Gerichte'} angezeigt.`;
    }
  }

  if (dishSearchInput) {
    dishSearchInput.addEventListener('input', (e) => {
      currentSearchQuery = e.target.value;
      filterMenuDishes(true);
    });

    if (dishSearchClear) {
      dishSearchClear.addEventListener('click', () => {
        dishSearchInput.value = '';
        currentSearchQuery = '';
        dishSearchInput.focus();
        filterMenuDishes(true);
      });
    }

    if (dishResetBtn) {
      dishResetBtn.addEventListener('click', () => {
        dishSearchInput.value = '';
        currentSearchQuery = '';
        currentChipFilter = 'all';
        dishFilterChips.forEach(c => {
          if (c.id === 'btn-open-allergens') return;
          const isAll = c.dataset.filter === 'all';
          c.classList.toggle('active', isAll);
          c.setAttribute('aria-pressed', isAll ? 'true' : 'false');
        });
        filterMenuDishes(true);
      });
    }

    dishFilterChips.forEach(chip => {
      chip.addEventListener('click', () => {
        if (chip.id === 'btn-open-allergens') return;
        dishFilterChips.forEach(c => {
          if (c.id === 'btn-open-allergens') return;
          c.classList.remove('active');
          c.setAttribute('aria-pressed', 'false');
        });
        chip.classList.add('active');
        chip.setAttribute('aria-pressed', 'true');
        currentChipFilter = chip.dataset.filter || 'all';
        filterMenuDishes(true);
      });
    });
  }

  // Allergen Modal Toggle (native <dialog closedby="any"> with light-dismiss fallback)
  const btnOpenAllergens = document.getElementById('btn-open-allergens');
  const allergenModalWrap = document.getElementById('allergen-modal-wrap');
  const allergenModalClose = document.getElementById('allergen-modal-close');

  if (btnOpenAllergens && allergenModalWrap) {
    attachDialogLightDismiss(allergenModalWrap);

    btnOpenAllergens.addEventListener('click', () => {
      if (typeof allergenModalWrap.showModal === 'function') {
        if (!allergenModalWrap.open) allergenModalWrap.showModal();
      } else {
        allergenModalWrap.style.display = 'grid';
      }
    });

    allergenModalClose?.addEventListener('click', () => {
      if (typeof allergenModalWrap.close === 'function' && allergenModalWrap.open) {
        allergenModalWrap.close();
      } else {
        allergenModalWrap.style.display = 'none';
      }
    });
  }

  if (menuTabs.length > 0) {
    menuTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const targetMenu = tab.dataset.menu; // 'restaurant' or 'sushi'
        currentActiveTab = targetMenu;
        menuTabs.forEach(t => {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');

        // Filter section badges
        sectionLinks.forEach(link => {
          if (link.dataset.menuType === targetMenu) {
            link.style.display = 'inline-block';
          } else {
            link.style.display = 'none';
          }
        });

        // Toggle Sushi Monday Banner if present
        const sushiBanner = document.getElementById('sushi-monday-banner');
        if (sushiBanner) {
          sushiBanner.style.display = (targetMenu === 'sushi') ? 'flex' : 'none';
        }

        filterMenuDishes(false);
      });
    });

    // Initial activation: activate first tab
    if (menuTabs[0]) {
      menuTabs[0].click();
    }
  }

  // Smooth scroll for section badges
  sectionLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href.startsWith('#')) {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth' });
        }
      }
    });
  });

  // Update current year in footers
  document.querySelectorAll('[data-year]').forEach(el => el.textContent = String(new Date().getFullYear()));

  // Checkout Timing Selector Handler (Segmented Control: ASAP vs For Later)
  function initCheckoutTiming() {
    const btnAsap = document.getElementById('btn-asap');
    const btnLater = document.getElementById('btn-later');
    const asapBox = document.getElementById('asap-box');
    const laterBox = document.getElementById('later-box');
    const modeInput = document.getElementById('pickup_mode');
    const dateSelect = document.getElementById('pickup_date_select');
    const timeSelect = document.getElementById('pickup_time_select');

    if (!btnAsap || !btnLater || !modeInput || modeInput.dataset.initialized === 'yes') return;
    modeInput.dataset.initialized = 'yes';

    function updatePreorderSlots() {
      if (!dateSelect || !timeSelect) return;
      const previous = timeSelect.value;
      let slots = [];
      try {
        slots = JSON.parse(dateSelect.selectedOptions[0]?.dataset.slots || '[]');
      } catch (e) {
        slots = [];
      }
      timeSelect.replaceChildren(...slots.map(slot => {
        const option = document.createElement('option');
        option.value = slot;
        option.textContent = `${slot} Uhr`;
        return option;
      }));
      if (slots.includes(previous)) timeSelect.value = previous;
    }

    btnAsap.addEventListener('click', () => {
      if (btnAsap.disabled) return;
      btnAsap.classList.add('active');
      btnLater.classList.remove('active');
      if (asapBox) asapBox.style.display = 'flex';
      if (laterBox) laterBox.style.display = 'none';
      modeInput.value = 'asap';
    });

    btnLater.addEventListener('click', () => {
      btnLater.classList.add('active');
      btnAsap.classList.remove('active');
      if (asapBox) asapBox.style.display = 'none';
      if (laterBox) laterBox.style.display = 'block';
      modeInput.value = 'later';
      updatePreorderSlots();
    });

    dateSelect?.addEventListener('change', updatePreorderSlots);
    updatePreorderSlots();
  }

  initCheckoutTiming();
  document.body.addEventListener('updated_checkout', initCheckoutTiming);

  let pickupRequestGeneration = 0;
  const pickupSelection = { date: '', time: '', mode: '' };

  function pickupAvailabilityState(state, message) {
    const mode = document.getElementById('pickup_mode');
    if (!mode) return;
    mode.dataset.availabilityState = state;
    const dates = document.getElementById('pickup_date_select');
    const times = document.getElementById('pickup_time_select');
    if (state !== 'ready') {
      mode.value = '';
      dates?.replaceChildren();
      times?.replaceChildren();
      const asap = document.getElementById('btn-asap');
      if (asap) asap.disabled = true;
      const ready = document.querySelector('#asap-box strong');
      if (ready) ready.textContent = '';
      const asapBox = document.getElementById('asap-box');
      if (asapBox) asapBox.style.display = 'none';
    }
    if (dates) dates.disabled = state !== 'ready' || dates.options.length === 0;
    if (times) times.disabled = state !== 'ready' || times.options.length === 0;
    let status = document.getElementById('pickup_availability_status');
    if (!status) {
      status = document.createElement('p');
      status.id = 'pickup_availability_status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      mode.parentElement.appendChild(status);
    }
    status.textContent = message;
  }

  async function refreshPickupAvailability() {
    if (document.hidden || (!document.getElementById('pickup_mode') && !document.getElementById('cart-overlay'))) return;
    const generation = ++pickupRequestGeneration;
    const mode = document.getElementById('pickup_mode');
    const dates = document.getElementById('pickup_date_select');
    const times = document.getElementById('pickup_time_select');
    // Keep a valid user's choice privately while pending/error controls hold no stale submitted values.
    if (dates?.value) pickupSelection.date = dates.value;
    if (times?.value) pickupSelection.time = times.value;
    if (mode?.value) pickupSelection.mode = mode.value;
    pickupAvailabilityState('pending', 'Abholzeiten werden geladen …');
    document.querySelectorAll('.badge-asap').forEach(el => { el.textContent = 'Zeiten werden geprüft …'; });
    try {
      const response = await fetch(`${ajaxUrl}?action=wowthai_pickup_availability`, { cache: 'no-store' });
      if (!response.ok) throw new Error('availability');
      const result = await response.json();
      // Polling, focus and a schedule change may overlap. Only the newest request owns every mutation.
      if (generation !== pickupRequestGeneration) return;
      if (!result.success || !Array.isArray(result.data?.dates) || result.data.dates.some(date => !Array.isArray(date.slots))) throw new Error('availability');
      const data = result.data;
      if (window.wowthaiConfig) window.wowthaiConfig.prep_time = data.prep_time;
      document.querySelectorAll('.badge-asap').forEach(el => { el.textContent = data.asap ? data.prep_time : 'Vorbestellung'; });
      const currentMode = document.getElementById('pickup_mode');
      const currentDates = document.getElementById('pickup_date_select');
      const currentTimes = document.getElementById('pickup_time_select');
      if (!currentMode || !currentDates || !currentTimes) return;
      currentDates.replaceChildren(...data.dates.map(date => {
        const option = document.createElement('option');
        option.value = date.value; option.textContent = date.label;
        option.dataset.slots = JSON.stringify(date.slots);
        return option;
      }));
      if (data.dates.some(date => date.value === pickupSelection.date)) currentDates.value = pickupSelection.date;
      currentDates.dispatchEvent(new Event('change'));
      if ([...currentTimes.options].some(option => option.value === pickupSelection.time)) currentTimes.value = pickupSelection.time;
      const asap = document.getElementById('btn-asap');
      if (asap) {
        asap.disabled = !data.asap;
        const pill = asap.querySelector('.seg-pill');
        if (pill) pill.textContent = data.asap ? data.prep_time : 'Nicht verfügbar';
      }
      const ready = document.querySelector('#asap-box strong');
      if (ready) ready.textContent = data.asap ? `${data.asap} Uhr` : '';
      currentMode.value = pickupSelection.mode === 'asap' && data.asap ? 'asap' : 'later';
      const selectedButton = document.getElementById(currentMode.value === 'asap' ? 'btn-asap' : 'btn-later');
      selectedButton?.click();
      pickupAvailabilityState('ready', !data.asap && !data.dates.length ? 'Keine Abholzeiten verfügbar.' : '');
    } catch (error) {
      if (generation !== pickupRequestGeneration) return;
      pickupAvailabilityState('error', 'Abholzeiten konnten nicht geladen werden. Bitte erneut versuchen.');
      document.querySelectorAll('.badge-asap').forEach(el => { el.textContent = 'Zeiten derzeit nicht verfügbar'; });
      console.warn('Abholzeiten konnten nicht aktualisiert werden.');
    }
  }

  document.addEventListener('wowthai:hours-changed', refreshPickupAvailability);
  window.addEventListener('focus', refreshPickupAvailability);

  // Visibility-aware pickup availability polling (pauses when tab is hidden)
  let pickupPollTimer = null;
  function startPickupPolling() {
    if (pickupPollTimer) return;
    pickupPollTimer = setInterval(refreshPickupAvailability, 30000);
  }
  function stopPickupPolling() {
    if (pickupPollTimer) {
      clearInterval(pickupPollTimer);
      pickupPollTimer = null;
    }
  }
  refreshPickupAvailability();
  if (!document.hidden) startPickupPolling();
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopPickupPolling();
    } else {
      refreshPickupAvailability();
      startPickupPolling();
    }
  });

  // Client-side Validation for Checkout Fields
  function validateCheckoutFields(showFeedback = true) {
    const fields = [
      {
        id: 'billing_first_name',
        validate: (v) => v.trim().length > 0,
        errorMsg: 'Bitte geben Sie Ihren Vornamen ein.'
      },
      {
        id: 'billing_last_name',
        validate: (v) => v.trim().length > 0,
        errorMsg: 'Bitte geben Sie Ihren Nachnamen ein.'
      },
      {
        id: 'billing_phone',
        validate: (v) => {
          const cleaned = v.replace(/[\s\-\(\)\/\+]/g, '');
          return cleaned.length >= 6;
        },
        errorMsg: 'Bitte geben Sie eine gültige Telefonnummer ein.'
      },
      {
        id: 'billing_email',
        validate: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()),
        errorMsg: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
      }
    ];

    let firstInvalid = null;
    let hasErrors = false;

    fields.forEach(f => {
      const el = document.getElementById(f.id);
      if (!el) return;
      const box = el.closest('.field-box');
      const msgEl = document.getElementById('err-' + f.id);
      const val = el.value || '';

      if (!f.validate(val)) {
        hasErrors = true;
        if (showFeedback) {
          el.classList.add('input-error');
          if (box) {
            box.classList.remove('has-error');
            void box.offsetWidth; // restart animation
            box.classList.add('has-error');
          }
          if (msgEl) {
            msgEl.textContent = f.errorMsg;
            msgEl.style.display = 'flex';
          }
          if (!firstInvalid) firstInvalid = el;
        }
      } else {
        el.classList.remove('input-error');
        if (box) box.classList.remove('has-error');
        if (msgEl) {
          msgEl.textContent = '';
          msgEl.style.display = 'none';
        }
      }
    });

    if (hasErrors && showFeedback && firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus();
      showToast('Bitte füllen Sie alle erforderlichen Pflichtfelder (*) aus.', true);
      return false;
    }

    return !hasErrors;
  }

  // Clear errors dynamically as the user types
  ['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
      const box = el.closest('.field-box');
      const msgEl = document.getElementById('err-' + id);
      if (el.value.trim().length > 0) {
        el.classList.remove('input-error');
        if (box) box.classList.remove('has-error');
        if (msgEl) {
          msgEl.textContent = '';
          msgEl.style.display = 'none';
        }
      }
    });
  });

  // Intercept click on Place Order button and prevent submit if fields are missing
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#place_order');
    if (!btn) return;

    if (!validateCheckoutFields(true)) {
      e.preventDefault();
      e.stopImmediatePropagation();
      e.stopPropagation();
      return false;
    }
  }, true); // Capture phase: intercepts BEFORE WooCommerce checkout.js

  // Also intercept form submit
  document.addEventListener('submit', (e) => {
    if (e.target && e.target.classList.contains('woocommerce-checkout')) {
      if (!validateCheckoutFields(true)) {
        e.preventDefault();
        e.stopImmediatePropagation();
        e.stopPropagation();
        return false;
      }
    }
  }, true);

  // Event-driven WooCommerce checkout unblocker (no continuous 1s polling loop)
  if (window.jQuery) {
    const $ = window.jQuery;
    let unblockTimeout = null;
    const unblockTargets = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form.checkout, #order_review';

    const scheduleCheckoutUnblock = (delayMs = 0) => {
      if (unblockTimeout) clearTimeout(unblockTimeout);
      if (delayMs <= 0) {
        $(unblockTargets).unblock();
      } else {
        unblockTimeout = setTimeout(() => {
          if ($('.blockUI.blockOverlay').length > 0) {
            $(unblockTargets).unblock();
          }
        }, delayMs);
      }
    };

    $(document.body).on('updated_checkout checkout_error payment_method_selected', () => {
      scheduleCheckoutUnblock(0);
    });
    $(document.body).on('update_checkout checkout_place_order', () => {
      scheduleCheckoutUnblock(2500);
    });
  }

  // Open the correct selector when a WooCommerce product link lands on the menu.
  const requestedDish = new URLSearchParams(window.location.search).get('dish');
  if (requestedDish && /^\d+$/.test(requestedDish)) {
    const dishButton = document.querySelector(`.dish-control[data-product-id="${requestedDish}"]`);
    if (dishButton) {
      const section = dishButton.closest('.menu-section');
      const menuType = section?.dataset.menuType;
      if (menuType && menuType !== 'restaurant') {
        document.querySelector(`.menu-tab[data-menu="${menuType}"]`)?.click();
      }
      dishButton.scrollIntoView({ block: 'center' });
      dishButton.click();
    }
  }

  // Modern web accessibility: Escape key fallback if native <dialog> is not used
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
      if (sheetRoot && sheetRoot.innerHTML !== '') {
        const dlg = sheetRoot.querySelector('dialog');
        if (dlg && dlg.open) dlg.close();
        sheetRoot.innerHTML = '';
      } else if (navDrawer && navDrawer.classList.contains('open')) {
        toggleMobileNav(false);
      } else if (cartRoot && cartRoot.innerHTML !== '') {
        renderCartDrawer(false);
      }
    }
  });

  // --- Mobile Navigation Drawer Controller ---
  const navToggle = document.getElementById('mobile-nav-toggle');
  const navDrawer = document.getElementById('mobile-nav-drawer');
  const navClose = document.getElementById('mobile-nav-close');
  const navBackdrop = document.getElementById('mobile-nav-backdrop');

  function toggleMobileNav(open) {
    if (!navDrawer) return;
    const shouldOpen = (open !== undefined) ? open : !navDrawer.classList.contains('open');
    navDrawer.classList.toggle('open', shouldOpen);
    navDrawer.setAttribute('aria-hidden', String(!shouldOpen));
    if (navToggle) {
      navToggle.setAttribute('aria-expanded', String(shouldOpen));
      navToggle.classList.toggle('active', shouldOpen);
    }
    document.body.classList.toggle('mobile-nav-locked', shouldOpen);
  }

  if (navToggle) navToggle.addEventListener('click', () => toggleMobileNav());
  if (navClose) navClose.addEventListener('click', () => toggleMobileNav(false));
  if (navBackdrop) navBackdrop.addEventListener('click', () => toggleMobileNav(false));
  navDrawer?.querySelectorAll('.mobile-nav-links a').forEach(a => {
    a.addEventListener('click', () => toggleMobileNav(false));
  });
  // Initial cart load
  fetchCart();
});
