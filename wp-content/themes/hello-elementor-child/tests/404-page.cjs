// Run: node 404-page.cjs <Studio site URL> [screenshot directory]. No account/cart writes.
const assert = require("node:assert/strict");
const path = require("node:path");
const { chromium } = require("playwright");

async function main() {
  assert.ok(process.argv[2], "Pass the Studio site URL.");
  const site = new URL(process.argv[2]);
  const missing = new URL("gpm-page-not-found-preview/", site).href;
  const screenshots = process.argv[3] || "C:/Users/donis/.codex/visualizations/2026/08/14/019fff21-5b23-7ae2-9c2a-25130fb61dbb";
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: "no-preference" });
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const root = page.locator("main.gpm-404");
  const scene = page.locator(".gpm-404__scene");
  const transform = () => scene.evaluate((el) => getComputedStyle(el).transform);
  const noOverflow = async () => assert.ok(await page.evaluate(() =>
    document.documentElement.scrollWidth <= window.innerWidth), "404 must not overflow horizontally.");
  const digitsRunning = () => page.locator(".gpm-404__digit").evaluateAll((nodes) =>
    nodes.some((node) => node.getAnimations().some((animation) => animation.playState === "running")));

  try {
    const response = await page.goto(missing);
    assert.equal(response.status(), 404, "Unknown URLs must retain HTTP 404.");
    assert.equal(await root.count(), 1, "404 template must render main.gpm-404.");
    assert.equal(await root.evaluate((el) => getComputedStyle(el).maxWidth), "none", "404 canvas must override the parent theme width.");
    assert.ok(await page.locator("header").first().isVisible(), "Native header must remain.");
    assert.ok(await page.locator("footer").first().isVisible(), "Native footer must remain.");
    assert.equal(await page.locator("#gpm-404-style-css").count(), 1);
    assert.equal(await page.locator("#gpm-404-motion-js").count(), 1);
    const home = root.getByRole("link", { name: "Back to Home", exact: true });
    const account = root.getByRole("link", { name: "My Account", exact: true });
    assert.equal(new URL(await home.getAttribute("href"), site).href, site.href);
    assert.equal(new URL(await account.getAttribute("href"), site).pathname, "/my-account/");
    await noOverflow();
    await page.waitForFunction(() => document.querySelector("main.gpm-404").dataset.motion === "running");
    assert.ok(await digitsRunning(), "404 digits should animate when motion is allowed.");

    const toggle = root.getByRole("button", { name: "Pause animation", exact: true });
    assert.equal(await toggle.getAttribute("aria-pressed"), "false");
    await page.mouse.move(0, 0);
    const neutral = await transform();
    const box = await scene.boundingBox();
    await page.mouse.move(box.x + box.width * 0.8, box.y + box.height * 0.3);
    await page.waitForFunction((initial) => getComputedStyle(document.querySelector(".gpm-404__scene")).transform !== initial, neutral);
    await toggle.focus();
    assert.ok(await toggle.evaluate((el) => el.matches(":focus-visible")));
    assert.notEqual(await toggle.evaluate((el) => getComputedStyle(el).outlineStyle), "none");
    await toggle.press("Enter");
    const resume = root.getByRole("button", { name: "Resume animation", exact: true });
    assert.equal(await resume.getAttribute("aria-pressed"), "true");
    assert.equal(await root.getAttribute("data-motion"), "paused");
    assert.equal(await digitsRunning(), false, "Pause must stop digit animations.");
    await page.waitForFunction((initial) => getComputedStyle(document.querySelector(".gpm-404__scene")).transform === initial, neutral);
    await page.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.7);
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await transform(), neutral, "Pointer movement must not tilt a paused scene.");
    await resume.press("Enter");
    assert.equal(await root.getAttribute("data-motion"), "running");
    assert.ok(await digitsRunning(), "Resume must restore digit animations.");

    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.waitForFunction(() => document.querySelector("main.gpm-404").dataset.motion === "paused");
    await page.reload();
    const reduced = root.getByRole("button", { name: "Motion reduced", exact: true });
    assert.ok(await reduced.isDisabled(), "System reduced motion must be respected.");
    assert.notEqual(await root.getAttribute("data-motion"), "running");
    assert.equal(await digitsRunning(), false, "Reduced motion must not start animations.");
    const reducedNeutral = await transform();
    const reducedBox = await scene.boundingBox();
    await page.mouse.move(reducedBox.x + reducedBox.width * 0.9, reducedBox.y + 10);
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await transform(), reducedNeutral, "Reduced motion must disable pointer tilt.");

    const search = root.getByLabel("Search the site", { exact: true });
    await search.fill("gpm missing test");
    await search.press("Enter");
    await page.waitForURL((url) => url.searchParams.get("s") === "gpm missing test");
    assert.equal(new URL(page.url()).searchParams.get("s"), "gpm missing test");
    await page.goto(missing);
    await root.getByRole("link", { name: "Back to Home", exact: true }).click();
    await page.waitForURL(site.href);
    for (const destination of [site.href, new URL("shop/", site).href]) {
      await page.goto(destination);
      assert.equal(await page.locator("#gpm-404-style-css, #gpm-404-motion-js").count(), 0,
        "404 assets must not load on storefront pages.");
    }

    await page.emulateMedia({ reducedMotion: "no-preference" });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(missing);
    await noOverflow();
    assert.equal(await root.evaluate((el) => getComputedStyle(el).paddingLeft), "22px");
    assert.ok(await root.getByRole("link", { name: "Back to Home", exact: true }).isVisible());
    assert.ok(await root.getByLabel("Search the site", { exact: true }).isVisible());
    assert.deepEqual(errors, [], "404 interactions must not cause JavaScript errors.");
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(screenshots, "404-mobile-20260915.png"), fullPage: true, animations: "disabled" });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(missing);
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(screenshots, "404-desktop-20260915.png"), fullPage: true, animations: "disabled" });
    const staticPage = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 320, height: 800 } });
    await staticPage.goto(missing);
    assert.ok(await staticPage.locator("#gpm-404-title").isVisible(), "Content must remain visible without JavaScript.");
    assert.equal(await staticPage.locator(".gpm-404__motion").isVisible(), false);
    assert.ok(await staticPage.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    await staticPage.getByRole("link", { name: "Back to Home", exact: true }).click();
    await staticPage.waitForURL(site.href);
    console.log("PASS: HTTP404, navigation/search, isolated assets, keyboard/motion controls, responsive layout and no-JS fallback.");
  } finally {
    await browser.close();
  }
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
