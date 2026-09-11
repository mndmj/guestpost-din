// Run via Playwright browser_run_code (filename) on the logged-in Studio site.
// GET-only: no addresses are submitted or saved.
async (page) => {
  const origin = await page.evaluate(() => location.origin);
  const results = [];
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const endpoint of ['', 'billing/', 'shipping/']) {
      await page.goto(origin + '/my-account/edit-address/' + endpoint, { waitUntil: 'domcontentloaded' });
      await page.locator('.woocommerce-MyAccount-content').waitFor();
      await page.evaluate(() => document.fonts.ready);
      const result = await page.evaluate(() => {
        const content = document.querySelector('.woocommerce-MyAccount-content');
        const card = content.querySelector('.woocommerce-Address, form');
        const button = content.querySelector('.woocommerce-Address .edit, button[name=save_address]');
        const input = content.querySelector('.woocommerce-address-fields input.input-text');
        const select = content.querySelector('.select2-selection--single');
        const nav = document.querySelector('.woocommerce-MyAccount-navigation');
        const heading = content.querySelector('.woocommerce-Address-title h2');
        return {
          shadow: getComputedStyle(card).boxShadow,
          radius: getComputedStyle(card).borderRadius,
          buttonBg: getComputedStyle(button).backgroundColor,
          buttonRadius: getComputedStyle(button).borderRadius,
          inputRadius: input && getComputedStyle(input).borderRadius,
          selectHeight: select && select.getBoundingClientRect().height,
          overflow: document.documentElement.scrollWidth > innerWidth,
          overlap: innerWidth < 768 && nav.getBoundingClientRect().bottom > content.getBoundingClientRect().top,
          headingInset: heading && heading.getBoundingClientRect().left - heading.parentElement.getBoundingClientRect().left,
          navInset: innerWidth < 768 && nav.getBoundingClientRect().top - content.parentElement.getBoundingClientRect().top,
        };
      });
      if (result.shadow === 'none' || result.radius !== '12px' ||
          result.buttonBg !== 'rgb(61, 92, 255)' || result.buttonRadius !== '999px' ||
          (result.inputRadius !== null && result.inputRadius !== '10px') ||
          (result.selectHeight !== null && result.selectHeight < 48) || result.overflow || result.overlap ||
          result.headingInset > 1 || result.navInset > 1) {
        throw new Error(endpoint + ' at ' + width + ': Addresses style mismatch ' + JSON.stringify(result));
      }
      results.push({ endpoint, width, ...result });
    }
  }
  return results;
}
