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

  // Update Cart Count
  function updateCartBadge(count) {
    if (cartCountEl) {
      cartCountEl.textContent = String(count || 0);
    }
  }

  // Render Cart Drawer
  function renderCartDrawer(isOpen = false) {
    if (!cartRoot) return;
    if (!isOpen) {
      cartRoot.innerHTML = '';
      return;
    }

    const items = currentCart.items || [];
    const hasItems = items.length > 0;

    cartRoot.innerHTML = `
      <div class="overlay" id="cart-overlay">
        <aside class="drawer" role="dialog" aria-label="Ihre Bestellung">
          <div class="drawer-head">
            <h2>Ihre Bestellung</h2>
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
                <span class="badge-asap">ca. 25–35 Min.</span>
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
                        <button type="button" class="btn-qty-minus" data-key="${esc(item.key)}">−</button>
                        <span>${item.quantity}</span>
                        <button type="button" class="btn-qty-plus" data-key="${esc(item.key)}">+</button>
                      </div>
                      <button type="button" class="remove-line" data-key="${esc(item.key)}">Entfernen</button>
                    </div>
                  </div>
                `).join('')}
              </div>
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
        </aside>
      </div>
    `;

    document.getElementById('cart-close')?.addEventListener('click', () => renderCartDrawer(false));
    document.getElementById('cart-overlay')?.addEventListener('click', (e) => {
      if (e.target.id === 'cart-overlay') renderCartDrawer(false);
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

  // Modal for Dish Variants
  function openDishModal(dish) {
    if (!sheetRoot) return;
    const variants = dish.variants || [];
    const modifierGroups = dish.modifierGroups || [];
    const formatPrice = amount => new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' }).format(Number(amount));

    sheetRoot.innerHTML = `
      <div class="modal-wrap" id="dish-modal-wrap">
        <div class="dish-modal" role="dialog" aria-label="${esc(dish.title)}">
          <div class="modal-head">
            <h2>${esc(dish.title)}</h2>
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
        </div>
      </div>
    `;

    const closeBtn = document.getElementById('dish-modal-close');
    const wrap = document.getElementById('dish-modal-wrap');
    const close = () => { sheetRoot.innerHTML = ''; };

    closeBtn?.addEventListener('click', close);
    wrap?.addEventListener('click', (e) => {
      if (e.target.id === 'dish-modal-wrap') close();
    });

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

  if (menuTabs.length > 0) {
    menuTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const targetMenu = tab.dataset.menu; // 'restaurant' or 'sushi'
        menuTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Filter sections
        menuSections.forEach(sec => {
          if (sec.dataset.menuType === targetMenu) {
            sec.style.display = 'block';
          } else {
            sec.style.display = 'none';
          }
        });

        // Filter section badges
        sectionLinks.forEach(link => {
          if (link.dataset.menuType === targetMenu) {
            link.style.display = 'inline-block';
          } else {
            link.style.display = 'none';
          }
        });
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

  // Show only reservation times that the restaurant can accept on the chosen day.
  const reservationDate = document.getElementById('res_date');
  const reservationTime = document.getElementById('res_time');
  if (reservationDate && reservationTime) {
    const slotsBetween = (start, end) => {
      const toMinutes = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
      const result = [];
      for (let minute = toMinutes(start); minute <= toMinutes(end); minute += 30) {
        result.push(`${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`);
      }
      return result;
    };
    const updateReservationTimes = () => {
      const date = reservationDate.value;
      const previous = reservationTime.value;
      const sunday = date && new Date(`${date}T12:00:00Z`).getUTCDay() === 0;
      const slots = (sunday
        ? slotsBetween('11:30', '19:00')
        : [...slotsBetween('11:30', '14:00'), ...slotsBetween('17:00', '21:00')]
      ).filter(time => `${date} ${time}` >= reservationDate.dataset.earliest);
      reservationTime.replaceChildren(...slots.map(time => {
        const option = document.createElement('option');
        option.value = time;
        option.textContent = `${time} Uhr`;
        return option;
      }));
      if (slots.includes(previous)) reservationTime.value = previous;
    };
    reservationDate.addEventListener('change', updateReservationTimes);
    updateReservationTimes();
  }

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

  // Auto-unblock WooCommerce checkout if blockUI hangs
  if (window.jQuery) {
    const $ = window.jQuery;
    $(document.body).on('updated_checkout checkout_error payment_method_selected', () => {
      $('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form.checkout, #order_review').unblock();
    });

    // Failsafe unblocker: Ensures checkout is never permanently greyed out
    setInterval(() => {
      const $overlays = $('.blockUI.blockOverlay');
      if ($overlays.length > 0) {
        setTimeout(() => {
          if ($('.blockUI.blockOverlay').length > 0) {
            $('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, form.checkout, #order_review').unblock();
          }
        }, 1500);
      }
    }, 1000);
  }

  // Initial cart load
  fetchCart();

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
});
