// Run via Playwright browser_run_code (filename) with the Studio site open.
// GET-only: never submits a form or sends a reset email.
async (page) => {
  const origin = await page.evaluate(() => location.origin);
  const results = [];
  for (const path of ['/my-account/lost-password/', '/wp-login.php?action=lostpassword']) {
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(origin + path);
      await page.evaluate(() => document.fonts.ready);
      const result = await page.evaluate(() => {
        const form = document.querySelector('form.woocommerce-ResetPassword, #lostpasswordform');
        const button = form.querySelector('[type=submit]');
        const input = form.querySelector('#user_login');
        const card = form.closest('.woocommerce') || form;
        return {
          bg: getComputedStyle(button).backgroundColor,
          radius: getComputedStyle(button).borderRadius,
          inputBorder: getComputedStyle(input).borderColor,
          inputRadius: getComputedStyle(input).borderRadius,
          shadow: getComputedStyle(card).boxShadow,
          cardWidth: card.getBoundingClientRect().width,
          required: input.required,
          overflow: document.documentElement.scrollWidth > innerWidth,
        };
      });
      if (result.bg !== 'rgb(61, 92, 255)' || result.radius !== '999px' ||
          result.inputBorder !== 'rgb(18, 19, 26)' || result.inputRadius !== '10px' ||
          result.shadow === 'none' || result.cardWidth > 560 || !result.required || result.overflow) {
        throw new Error(path + ' at ' + width + ': recovery style mismatch ' + JSON.stringify(result));
      }
      results.push({ path, width, ...result });
    }
  }
  return results;
}
