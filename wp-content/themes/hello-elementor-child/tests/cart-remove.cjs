// Run: node cart-remove.cjs <Studio site URL>. Fresh guest cart only; never places an order.
const assert = require("node:assert/strict");
const { chromium } = require("playwright");

async function main() {
  assert.ok(process.argv[2], "Pass the Studio site URL.");
  const cartUrl = new URL("cart/", process.argv[2]).href;
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  let removals = 0;
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("request", (request) => {
    if (`${request.url()} ${request.postData() || ""}`.includes("/cart/remove-item")) removals++;
  });
  const row = page.locator(".wc-block-cart-items__row[data-cart-item-key]");
  const remove = row.locator("button.wc-block-cart-item__remove-link");
  const dialog = page.locator("dialog#gpm-cart-remove-dialog");
  const cancel = dialog.getByRole("button", { name: "Cancel", exact: true });
  const confirm = dialog.getByRole("button", { name: "Yes, remove", exact: true });
  const empty = page.locator(".wp-block-woocommerce-empty-cart-block");

  async function openWithKeyboard() {
    await remove.focus();
    await page.keyboard.press("Enter");
    await dialog.waitFor({ state: "visible" });
    assert.ok(await dialog.evaluate((el) => el.matches(":modal")), "Confirmation must be modal.");
    assert.ok(await dialog.getByRole("heading", { name: "Remove this item?", exact: true }).isVisible());
    assert.ok((await dialog.innerText()).includes(productName), "Confirm the selected product by name.");
    assert.ok(await cancel.evaluate((el) => el === document.activeElement), "Cancel must receive initial focus.");
    const red = await cancel.evaluate((el) => getComputedStyle(el).backgroundColor.match(/\d+/g).slice(0, 3).map(Number));
    const grey = await confirm.evaluate((el) => getComputedStyle(el).backgroundColor.match(/\d+/g).slice(0, 3).map(Number));
    assert.ok(red[0] > red[1] + 60 && red[0] > red[2] + 60, "Cancel must use the Logout modal's red background.");
    assert.ok(Math.max(...grey) - Math.min(...grey) < 30, "Yes, remove must use the Logout modal's grey background.");
    assert.equal(await row.count(), 1, "Opening confirmation must not remove the product.");
    assert.equal(removals, 0, "Opening confirmation must not send a remove request.");
  }

  let productName;
  try {
    await page.goto(cartUrl);
    await empty.waitFor();
    const addUrl = await page.locator("a[data-product_id]").first().evaluate((el) => el.href);
    await page.goto(addUrl);
    await row.waitFor();
    // Reload the cart, not the add-to-cart action URL (which would add the item again).
    await page.goto(cartUrl);
    await row.waitFor();
    productName = (await row.locator(".wc-block-components-product-name").innerText()).trim();
    assert.equal(await dialog.count(), 1, "Cart needs one native removal-confirmation dialog.");

    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 1000 });
      const total = await row.locator(".wc-block-cart-item__total .wc-block-components-formatted-money-amount").boundingBox();
      const button = await remove.boundingBox();
      assert.ok(button.x >= total.x + total.width, `Remove must sit right of the line total at ${width}px.`);
      assert.ok(Math.abs(button.y + button.height / 2 - total.y - total.height / 2) < 12,
        `Remove and total must share a visual row at ${width}px.`);
      assert.ok(button.width >= 44 && button.height >= 44, "Remove needs a 44px touch target.");
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
        `Cart must not overflow horizontally at ${width}px.`);

      await openWithKeyboard();
      const dialogBox = await dialog.boundingBox();
      assert.ok(dialogBox.x >= 0 && dialogBox.x + dialogBox.width <= width,
        `Confirmation must fit the ${width}px viewport.`);
      await page.keyboard.press("Tab");
      assert.ok(await confirm.evaluate((el) => el === document.activeElement), "Tab must reach Yes, remove.");
      await page.keyboard.press("Shift+Tab");
      assert.ok(await cancel.evaluate((el) => el === document.activeElement));
      await page.keyboard.press("Enter");
      await dialog.waitFor({ state: "hidden" });
      assert.ok(await remove.evaluate((el) => el === document.activeElement), "Cancel must restore trigger focus.");
      await page.reload();
      await row.waitFor();
      assert.equal(await row.count(), 1, "Cancel must retain the product after reload.");
      assert.equal(removals, 0, "Cancel must not send a remove request.");

      await openWithKeyboard();
      await page.keyboard.press("Escape");
      await dialog.waitFor({ state: "hidden" });
      assert.ok(await remove.evaluate((el) => el === document.activeElement), "Escape must restore trigger focus.");
      assert.equal(await row.count(), 1, "Escape must retain the product.");
      assert.equal(removals, 0, "Escape must not send a remove request.");
      console.log(`PASS: ${width}px layout, keyboard, Cancel and Escape.`);
    }

    await openWithKeyboard();
    await confirm.click();
    await empty.waitFor();
    assert.equal(await row.count(), 0, "Yes, remove must remove the selected product.");
    assert.equal(removals, 1, "Confirmation must invoke the existing WooCommerce removal once.");
    await page.reload();
    await empty.waitFor();
    assert.equal(await row.count(), 0, "Confirmed removal must persist after reload.");
    assert.deepEqual(errors, [], "Cart must not produce JavaScript errors.");
    console.log("PASS: Confirmed removal runs once and persists in the isolated guest cart.");
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
