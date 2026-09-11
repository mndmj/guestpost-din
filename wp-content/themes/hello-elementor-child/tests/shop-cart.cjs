const assert = require("node:assert/strict");
const path = require("node:path");
const os = require("node:os");
const { chromium } = require("playwright");

async function main() {
  assert.ok(process.argv[2], "Pass the Studio site URL as the first argument.");
  const site = new URL(process.argv[2]);
  const shopUrl = new URL("shop/", site).href;
  const cartApi = new URL("wp-json/wc/store/v1/cart", site).href;
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const errors = [];

  async function cart(context) {
    const response = await context.request.get(cartApi);
    assert.ok(response.ok(), "Store API cart request failed.");
    const data = await response.json();
    return data.items.map((item) => ({ id: item.id, quantity: item.quantity }));
  }

  try {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto(shopUrl);
    assert.deepEqual(
      await cart(context),
      [],
      "The test session must start empty.",
    );

    const stalePage = await context.newPage();
    stalePage.on("pageerror", (error) => errors.push(error.message));
    await stalePage.goto(shopUrl);

    const selector = 'a.gpm-pricing-card__button-link[data-gpm_shop_cart="1"]';
    const firstButton = page.locator(selector).first();
    const firstProductId = Number(
      await firstButton.getAttribute("data-product_id"),
    );
    assert.ok(firstProductId > 0, "No purchasable Shop product found.");

    await Promise.all([
      page.waitForURL((url) => url.pathname.endsWith("/cart/")),
      firstButton.click(),
    ]);
    const originalCart = await cart(context);
    assert.deepEqual(originalCart, [{ id: firstProductId, quantity: 1 }]);

    const secondButtons = stalePage.locator(selector);
    const secondIndex = (await secondButtons.count()) > 1 ? 1 : 0;
    await secondButtons.nth(secondIndex).click();
    const modal = stalePage.locator("#gpm-shop-cart-modal[open]");
    await modal.waitFor({ state: "visible" });
    assert.deepEqual(
      await cart(context),
      originalCart,
      "A stale tab added another product.",
    );
    assert.ok(await modal.evaluate((element) => element.matches(":modal")));

    await stalePage.keyboard.press("Escape");
    await modal.waitFor({ state: "hidden" });
    await stalePage.locator(selector).first().click();
    await modal.waitFor({ state: "visible" });
    assert.deepEqual(
      await cart(context),
      originalCart,
      "A repeat click increased quantity.",
    );

    await stalePage.setViewportSize({ width: 390, height: 844 });
    const bounds = await modal.boundingBox();
    assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 390);
    const screenshot = path.join(os.tmpdir(), "gpm-shop-cart-modal-mobile.png");
    await stalePage.screenshot({ path: screenshot });
    await Promise.all([
      stalePage.waitForURL((url) => url.pathname.endsWith("/cart/")),
      modal.getByRole("link", { name: "Go to Cart" }).click(),
    ]);

    const noScriptContext = await browser.newContext({
      javaScriptEnabled: false,
    });
    const noScriptPage = await noScriptContext.newPage();
    await noScriptPage.goto(shopUrl);
    await Promise.all([
      noScriptPage.waitForURL((url) => url.pathname.endsWith("/cart/")),
      noScriptPage.locator(selector).first().click(),
    ]);
    const noScriptCart = await cart(noScriptContext);
    assert.equal(noScriptCart.length, 1);
    await noScriptPage.goto(shopUrl);
    await noScriptPage.locator(selector).first().click();
    await noScriptPage
      .locator(".woocommerce-error")
      .waitFor({ state: "visible" });
    assert.deepEqual(await cart(noScriptContext), noScriptCart);

    await page.goto(site.href);
    assert.equal(await page.locator("[data-gpm_shop_cart]").count(), 0);
    assert.deepEqual(errors, [], "Browser JavaScript errors detected.");
    console.log(
      JSON.stringify(
        {
          result: "PASS",
          checks: [
            "first add redirects",
            "stale tab blocked",
            "repeat quantity blocked",
            "Escape closes",
            "mobile modal fits",
            "Cart CTA",
            "no-JS fallback",
            "front page scope",
          ],
          screenshot,
        },
        null,
        2,
      ),
    );
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
