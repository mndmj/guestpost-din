// Run: node cart-header.cjs <Studio site URL>. Uses a fresh guest cart; never places an order.
const assert = require("node:assert/strict");
const { chromium } = require("playwright");

async function main() {
  assert.ok(process.argv[2], "Pass the Studio site URL.");
  const site = new URL(process.argv[2]);
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const cartUrl = new URL("cart/", site).href;
  const homeUrl = site.href;
  const accountUrl = new URL("my-account/", site).href;

  async function checkHeader() {
    const header = page.locator("#site-header");
    assert.equal(await header.count(), 1, "Cart must have exactly one header.");
    const link = header.getByRole("link", { name: "Back to Home Page" });
    assert.ok(await link.isVisible(), "Home link must be visible.");
    assert.equal(await link.getAttribute("href"), homeUrl);
    const headerBox = await header.boundingBox();
    const cartBox = await page.locator(".wp-block-woocommerce-cart").boundingBox();
    assert.ok(headerBox.y + headerBox.height <= cartBox.y, "Header must precede Cart.");
    assert.ok(Math.abs(headerBox.x - cartBox.x) < 2, "Header must align with Cart.");
    await link.focus();
    assert.ok(await link.evaluate((el) => el.matches(":focus-visible")));
    assert.notEqual(await link.evaluate((el) => getComputedStyle(el).outlineStyle), "none");
    const focusContrast = await link.evaluate((el) => {
      const luminance = (rgb) => rgb.match(/[\d.]+/g).slice(0, 3).map(Number)
        .map((value) => value / 255)
        .map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
        .reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
      const outline = luminance(getComputedStyle(el).outlineColor);
      const background = luminance(getComputedStyle(document.body).backgroundColor);
      return (Math.max(outline, background) + 0.05) / (Math.min(outline, background) + 0.05);
    });
    assert.ok(focusContrast >= 3, "Keyboard focus must contrast against the page background.");
    assert.equal(await page.locator("#gpm-checkout-style-css").count(), 0,
      "Cart must not load the full checkout stylesheet.");
  }

  try {
    await page.goto(cartUrl);
    await page.locator(".wp-block-woocommerce-empty-cart-block").waitFor();
    await checkHeader();
    const addUrl = await page.locator(".gpm-pricing-card__button-link[data-product_id]").first().getAttribute("href");
    await page.locator("#site-header a").click();
    await page.waitForURL(homeUrl);
    assert.ok(await page.locator("body.home").isVisible(),
      "Guest should reach the homepage, not the account login form.");

    await page.goto(new URL(addUrl, cartUrl).href);
    await page.locator(".wc-block-cart-items__row[data-cart-item-key]").waitFor();
    assert.equal(await page.locator(".wc-block-cart-items__row[data-cart-item-key]").count(), 1);
    await checkHeader();
    await page.setViewportSize({ width: 390, height: 844 });
    await checkHeader();
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
      "Mobile Cart must not overflow horizontally.");

    await page.goto(new URL("checkout/", site).href);
    await page.locator("#site-header .gpm-checkout-back-link").waitFor();
    assert.equal(await page.locator("#site-header").count(), 1, "Checkout header must not duplicate.");
    assert.equal(await page.locator("#site-header a").getAttribute("href"), accountUrl);
    assert.ok(await page.locator("#site-header").getByRole("link", { name: "Back to Dashboard" }).isVisible());

    for (const path of ["", "shop/"]) {
      await page.goto(new URL(path, site).href);
      assert.equal(await page.locator(".gpm-checkout-back-link").count(), 0,
        "Dashboard back button must not replace the normal storefront navbar.");
    }
    assert.deepEqual(errors, [], "Pages must not produce JavaScript errors.");
    console.log("PASS: Cart header, guest login link, filled/empty Cart, mobile, checkout and storefront isolation.");
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
