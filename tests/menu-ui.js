/** Run with: node tests/menu-ui.js */
const { chromium } = require('playwright');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/usr/bin/chromium', args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    await page.setContent('<button class="dish-control" id="pad">Pad Thai</button><button class="dish-control" id="ka">Pad Ka Prao</button><div id="sheet-root"></div><div id="cart-root"></div><div id="toast-root"></div>');
    await page.evaluate(() => {
      window.calls = [];
      window.wowthaiConfig = { ajax_url: '/ajax', checkout_url: '/checkout', nonce: 'test' };
      window.fetch = async (_url, options = {}) => {
        if (options.method === 'POST') window.calls.push(Object.fromEntries(new URLSearchParams(options.body)));
        return { json: async () => ({ success: true, data: { count: 1, subtotal: '€22,90', items: [] } }) };
      };
      Object.assign(document.getElementById('pad').dataset, {
        productId: '56', hasVariants: 'yes', hasModifiers: 'yes', title: 'H5. Pad Thai', description: '',
        variants: JSON.stringify([
          { id: 'vegi', name: 'Vegetarisch', price: '13.50', formatted: '€13,50' },
          { id: 'shrimp', name: 'mit Garnelen', price: '22.90', formatted: '€22,90' },
        ]),
        modifierGroups: JSON.stringify([{ id: 'noodles', name: 'Nudeln', min: 1, max: 1,
          options: [{ id: 'rice', name: 'mit Reisbandnudeln', price: '0.00' }, { id: 'glass', name: 'mit Glasnudeln', price: '0.00' }],
        }]),
      });
      Object.assign(document.getElementById('ka').dataset, {
        productId: '47', hasVariants: 'yes', hasModifiers: 'yes', title: 'Pad Ka Prao', description: '',
        variants: JSON.stringify([{ id: 'chicken', name: 'mit Huhn', price: '17.50', formatted: '€17,50' }]),
        modifierGroups: JSON.stringify([{ id: 'extra', name: 'Extra', min: 0, max: 1,
          options: [{ id: 'egg', name: 'mit extra Spiegelei', price: '2.60' }],
        }]),
      });
    });
    await page.addScriptTag({ path: require('path').join(__dirname, '../assets/js/wowthai.js') });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

    await page.locator('#pad').click();
    assert((await page.locator('.dish-modal').innerText()).includes('mit Garnelen'));
    await page.locator('#btn-add-modal').click();
    assert((await page.locator('#dish-validation').innerText()).includes('Nudeln'));
    assert.equal(await page.evaluate(() => window.calls.length), 0);
    await page.locator('input[name="dish_variant"][value="shrimp"]').check();
    await page.locator('input[name="modifier_noodles"][value="glass"]').check();
    await page.locator('#btn-add-modal').click();
    await page.waitForFunction(() => window.calls.length === 1);
    const first = await page.evaluate(() => window.calls[0]);
    assert.equal(first.variant_id, 'shrimp');
    assert.equal(first.modifier_ids, '["glass"]');

    await page.locator('#ka').click();
    assert((await page.locator('.dish-modal').innerText()).includes('2,60'));
    await page.locator('input[name="modifier_extra"][value="egg"]').check();
    await page.locator('#btn-add-modal').click();
    await page.waitForFunction(() => window.calls.length === 2);
    const second = await page.evaluate(() => window.calls[1]);
    assert.equal(second.modifier_ids, '["egg"]');
    console.log('Menu UI choices, validation and request payload passed');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
