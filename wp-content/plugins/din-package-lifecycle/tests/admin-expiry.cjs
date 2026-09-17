// Run: node admin-expiry.cjs <php executable>. Uses rendered fixture HTML; no WordPress writes.
const assert = require("node:assert/strict");
const { execFileSync } = require("node:child_process");
const { existsSync } = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");

async function main() {
  const html = execFileSync(process.argv[2] || "php", [path.join(__dirname, "admin-smoke.php"), "--render-expiry"], { encoding: "utf8" });
  const browser = await chromium.launch({ channel: "chrome", headless: true });
  const page = await browser.newPage({ timezoneId: "America/Los_Angeles" });
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  try {
    await page.setContent(`<form id="order"><div id="din-packages">${html}</div></form>`);
    await page.addStyleTag({ path: path.join(__dirname, "../assets/admin-orders.css") });
    const script = path.join(__dirname, "../assets/admin-expiry.js");
    if (existsSync(script)) await page.addScriptTag({ path: script });
    const duration = page.locator('[name="din_packages_expiry[1][extension]"]');
    const years = page.locator('[name="din_packages_expiry[1][years]"]');
    const date = page.locator('[name="din_packages_expiry[1][date]"]');
    const reason = page.locator('[name="din_packages_expiry[1][reason]"]');
    const apply = page.locator('[name="din_packages_expiry[1][apply]"]');
    const valid = () => page.locator("#order").evaluate((form) => form.checkValidity());
    assert.equal(await duration.locator('option[value="custom"]').count(), 1, "Duration choices must include Custom.");
    assert.equal(await years.isVisible(), false);
    assert.equal(await years.isEnabled(), false);
    assert.equal(await page.locator('[name="din_packages_expiry[1][price]"]').count(), 0, "Custom prices must come from the catalog, never an admin input.");
    assert.equal(await date.isVisible(), false, "Initial date input must be hidden until a duration is chosen.");
    assert.equal(await date.isEnabled(), false);
    assert.equal(await valid(), true, "An unrelated Update must remain valid.");

    await apply.check();
    assert.equal(await valid(), false, "Creating an order requires choosing a duration.");
    await duration.selectOption("1");
    assert.equal(await date.isVisible(), true);
    assert.equal(await date.isEnabled(), true);
    assert.equal(await date.inputValue(), "2031-10-05", "One year uses the server-computed default.");
    assert.equal(await valid(), false, "A buyer-visible reason is required for payment requests.");
    await reason.fill("Buyer requested an extension.");
    assert.equal(await valid(), true);
    await date.fill("2030-10-05");
    assert.equal(await valid(), false, "The current expiry cannot be submitted as an extension.");
    await apply.uncheck();
    assert.equal(await valid(), true, "An unchecked request with an out-of-range date must not block ordinary Update.");
    await apply.check();
    assert.equal(await valid(), false, "Rechecking the request must restore the minimum-date validation.");
    await date.fill("2031-11-09");
    await apply.uncheck();
    await apply.check();
    assert.equal(await date.inputValue(), "2031-11-09", "Checkbox changes must preserve an admin-edited date.");
    assert.equal(await valid(), true);

    await duration.selectOption("2");
    assert.equal(await date.inputValue(), "2032-10-05");
    await duration.selectOption("custom");
    assert.equal(await years.isVisible(), true);
    assert.equal(await years.isEnabled(), true);
    assert.equal(await date.isVisible(), true);
    assert.equal(await date.inputValue(), "", "Custom dates remain empty until whole years are supplied.");
    assert.equal(await valid(), false);
    for (const invalidYears of ["0", "-1", "1.5", "1.0", "3e0", "03", "7970"]) {
      await years.fill(invalidYears);
      assert.equal(await date.inputValue(), "", `Invalid years must not be rounded or prefilled: ${invalidYears}`);
      assert.equal(await valid(), false, `Invalid years must block payment requests: ${invalidYears}`);
    }
    await apply.uncheck();
    assert.equal(await valid(), true, "An unchecked Custom request must not block an ordinary Update.");
    await apply.check();
    assert.equal(await valid(), false);
    await years.fill("3");
    assert.equal(await date.inputValue(), "2033-10-05");
    assert.equal(await valid(), true);
    await date.fill("2033-12-09");
    await apply.uncheck();
    await apply.check();
    assert.equal(await date.inputValue(), "2033-12-09", "Custom expiry edits must survive confirmation changes.");
    assert.deepEqual(await page.locator("#order").evaluate((form) => {
      const data = new FormData(form);
      return [data.get("din_packages_expiry[1][extension]"), data.get("din_packages_expiry[1][years]"), data.get("din_packages_expiry[1][date]")];
    }), ["custom", "3", "2033-12-09"]);
    await years.fill("7969");
    assert.equal(await date.inputValue(), "9999-10-05", "The maximum allowed year remains representable as YYYY-MM-DD.");
    await years.fill("3");
    await duration.selectOption("lifetime");
    assert.equal(await years.isVisible(), false);
    assert.equal(await years.isEnabled(), false);
    assert.equal(await date.isVisible(), false);
    assert.equal(await date.isEnabled(), false);
    assert.equal(await date.inputValue(), "", "Lifetime clears the inactive date.");
    assert.equal(await page.getByText("No expiration date", { exact: true }).isVisible(), true);
    assert.equal(await valid(), true);
    assert.equal(await page.locator("#order").evaluate((form) => new FormData(form).has("din_packages_expiry[1][date]")), false, "Lifetime must not submit a stale date.");
    await reason.fill("");
    assert.equal(await valid(), false);
    await apply.uncheck();
    assert.equal(await valid(), true, "Unchecked requests must not block ordinary saves.");
    await duration.selectOption("none");
    assert.equal(await date.isVisible(), false);
    assert.equal(await date.isEnabled(), false);
    const leapHtml = execFileSync(process.argv[2] || "php", [path.join(__dirname, "admin-smoke.php"), "--render-expiry", "--leap-day"], { encoding: "utf8" });
    await page.setContent(`<form id="order"><div id="din-packages">${leapHtml}</div></form>`);
    await page.addScriptTag({ path: script });
    await duration.selectOption("custom");
    for (const [count, expected] of [["1", "2033-02-28"], ["4", "2036-02-29"], ["68", "2100-02-28"]]) {
      await years.fill(count);
      assert.equal(await date.inputValue(), expected, "Custom years must clamp leap day using the server calendar date, not browser-local parsing.");
    }
    assert.deepEqual(errors, []);
    console.log("PASS: duration-first expiry, Custom whole years, leap-day clamp, editable dates, Lifetime and ordinary Update.");
  } finally {
    await browser.close();
  }
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
