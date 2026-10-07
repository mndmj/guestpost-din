// Run: node notifications-pagination.cjs [screenshot-directory]. CSS-only fixtures; no site or database writes.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');
const theme = path.resolve(__dirname, '..');

function links(current) {
  const link = (number, label = number, extra = '') => `<li><a class="page-numbers ${extra}" href="/my-account/notifications/${number}/">${label}</a></li>`;
  return `<ul class="page-numbers">${current > 1 ? link(current - 1, '← Previous', 'prev') : ''}${[1, 2, 3, '…', 9, 10].map(number => number === '…' ? '<li><span class="page-numbers dots">…</span></li>' : number === current ? `<li><span aria-current="page" class="page-numbers current">${number}</span></li>` : link(number)).join('')}${current < 10 ? link(current + 1, 'Next →', 'next') : ''}</ul>`;
}
const styles = locator => locator.evaluate(el => {
  const s = getComputedStyle(el);
  return [s.borderRadius, s.minWidth, s.minHeight, s.borderTopWidth, s.backgroundColor, s.color, s.boxShadow, s.fontFamily, s.fontWeight];
});

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 768, 390, 320]) {
      for (const current of [1, 3, 10]) {
        await page.setViewportSize({ width, height: 900 });
        await page.setContent(`<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Notification pagination fixture</title><style>*{box-sizing:border-box}body{margin:16px;background:#f4f6ff}h2{font:700 22px Arial,sans-serif}</style></head><body class="woocommerce-account woocommerce-notifications woocommerce-din-packages"><main class="woocommerce-MyAccount-content"><section class="gpm-notifications-page"><h2>Notifications</h2><nav class="gpm-notification-pagination" aria-label="Notification pages">${links(current)}</nav></section><nav class="gpm-orders-pagination" aria-label="Orders reference">${links(current)}</nav><nav class="din-packages__pagination" aria-label="Packages reference">${links(current)}</nav></main></body></html>`);
        await page.addStyleTag({ path: path.join(theme, 'style.css') });
        await page.addStyleTag({ path: path.join(theme, 'assets/css/my-account.css') });
        // No account sidebar in this component fixture; pagination styles remain unmodified.
        await page.addStyleTag({ content: '.woocommerce-account .woocommerce-MyAccount-content{width:100%;margin:0;position:static;float:none;min-width:0}' });
        const nav = page.getByRole('navigation', { name: 'Notification pages' });
        const active = nav.locator('[aria-current="page"]');
        const normal = nav.locator('li a').first();
        assert.equal((await styles(normal))[0], '999px', 'Notifications must use pill-shaped pagination.');
        assert.deepEqual((await styles(normal)).slice(1, 4), ['44px', '44px', '2px'], 'Controls need 44px targets and a 2px border.');
        assert.deepEqual((await styles(active)).slice(4, 7), ['rgb(61, 92, 255)', 'rgb(255, 255, 255)', 'rgb(18, 19, 26) 3px 3px 0px 0px']);
        for (const reference of ['.gpm-orders-pagination', '.din-packages__pagination']) {
          assert.deepEqual(await styles(normal), await styles(page.locator(reference + ' li a').first()));
          assert.deepEqual(await styles(active), await styles(page.locator(reference + ' .current')));
        }
        const list = nav.locator('ul.page-numbers');
        assert.equal(await list.evaluate(el => getComputedStyle(el).justifyContent), 'center');
        assert.equal(await list.evaluate(el => getComputedStyle(el).gap), '8px');
        assert.equal(await nav.locator('.dots').evaluate(el => getComputedStyle(el).borderTopColor), 'rgba(0, 0, 0, 0)');
        const boxes = await nav.locator('li .page-numbers').evaluateAll(els => els.map(el => { const r = el.getBoundingClientRect(); return { x:r.x, y:r.y, right:r.right, bottom:r.bottom }; }));
        assert.ok(boxes.every(b => b.x >= 0 && b.right <= width), 'Pagination must stay inside the viewport.');
        assert.ok(boxes.every((b, i) => boxes.slice(i + 1).every(c => b.right <= c.x || c.right <= b.x || b.bottom <= c.y || c.bottom <= b.y)), 'Controls must not overlap.');
        if (width <= 390) assert.ok(new Set(boxes.map(b => b.y)).size > 1, 'Small screens must wrap controls.');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No page overflow.');
        const href = await normal.getAttribute('href');
        await normal.hover();
        assert.equal((await styles(normal))[4], 'rgb(0, 229, 163)');
        await page.mouse.move(0, 0);
        await page.keyboard.press('Tab');
        assert.ok(await normal.evaluate(el => el.matches(':focus-visible')));
        assert.equal(await normal.evaluate(el => getComputedStyle(el).outlineWidth), '3px');
        assert.equal((await styles(normal))[4], 'rgb(0, 229, 163)');
        assert.equal(await normal.getAttribute('href'), href, 'Styling must preserve navigation URLs.');
        if (process.argv[2] && current === 3) await page.locator('.gpm-notifications-page').screenshot({ path: path.join(process.argv[2], `notifications-${width}.png`) });
      }
      console.log(`PASS ${width}px: first/middle/last, matching pagination styles, active/hover/focus, centered wrapping and no overlap.`);
    }
    assert.deepEqual(errors, []);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
