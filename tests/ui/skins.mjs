/* Destructive imports ONLY in opted-in disposable WordPress; public UI reads only. */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

assert.equal(process.env.FLAVOR_DEMO_QA, '1', 'Disposable import matrix requires FLAVOR_DEMO_QA=1.');
assert.ok(process.env.WP_PATH && process.env.WP_CLI, 'Specify WP_PATH and WP_CLI for the disposable site.');
const base = process.env.SITE_URL || 'http://localhost:8080/';
const root = path.resolve('../..'), output = path.resolve(process.env.QA_OUTPUT_DIR || '../../.cache/ui-qa/skins');
const skins = ['modern-restaurant', 'luxury-dining', 'persian-traditional', 'fast-food', 'cafe-bistro', 'pizza-italian', 'bakery-pastry', 'juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering'];
await fs.mkdir(output, { recursive: true });
function importDemo(skin) {
 const result = execFileSync(process.env.PHP_BIN || 'php', [process.env.WP_CLI, '--path=' + process.env.WP_PATH, 'eval-file', path.join(root, 'tests/demos/import.php')], { env: { ...process.env, DEMO_SLUG: skin }, encoding: 'utf8', timeout: 90000 });
 assert.ok(result.includes('PASS ' + skin + ':'), 'Repeated import did not pass for ' + skin);
}
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const report = { result: 'FAIL', scope: 'Actual 12-skin menu/list/product UI; imports are destructive only in local/development WordPress.', skins: [] };
try {
 for (const skin of skins) {
  importDemo(skin);
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [];
  page.on('pageerror', error => errors.push(error.message));
  let submissions = 0;
  page.on('request', req => { if (req.method() === 'POST' && /\/(?:checkout|reservations)(?:\?|$)/.test(req.url())) submissions++; });
  const item = { skin, result: 'FAIL', widths: [], accessibility: [] }; report.skins.push(item);
  async function audit(label) {
   const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
   item.accessibility.push({ label, violations: result.violations });
   assert.equal(result.violations.length, 0, skin + '/' + label + ': ' + result.violations.map(v => v.id).join(', '));
  }
  try {
   await page.goto(new URL('menu/', base).href, { waitUntil: 'networkidle' });
   await page.waitForFunction(() => document.querySelectorAll('#flavor-menu-grid [data-add]').length === 8);
   assert.equal(await page.locator('body.flavor-skin-' + skin + '.flavor-ui').count(), 1);
   for (const view of ['grid', 'list']) {
    await page.locator('[data-ui-view="' + view + '"]').click();
    for (const width of [320, 390, 768, 1024, 1440]) {
     await page.setViewportSize({ width, height: 960 });
     const scroll = await page.evaluate(() => document.documentElement.scrollWidth);
     assert.ok(scroll <= width + 1, skin + '/' + view + ' overflow at ' + width); item.widths.push({ view, width, scroll });
    }
    await audit(view + '-desktop'); await page.setViewportSize({ width: 390, height: 844 }); await audit(view + '-mobile');
   }
   await page.setViewportSize({ width: 1440, height: 1000 }); await page.locator('[data-ui-view="grid"]').click();
   await page.screenshot({ path: path.join(output, skin + '-menu-desktop.png'), fullPage: true });
   await page.locator('#flavor-menu-grid [data-add]').first().click(); await page.waitForSelector('#flavor-sheet:not([hidden])');
   assert.equal(await page.locator('#flavor-cart-count').textContent(), '0', 'Opening details implicitly added a product.');
   await audit('product-desktop'); await page.setViewportSize({ width: 390, height: 844 }); await audit('product-mobile');
   assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
   await page.keyboard.press('Escape'); assert.equal(await page.locator('#main').getAttribute('inert'), null);
   await page.screenshot({ path: path.join(output, skin + '-menu-mobile.png'), fullPage: true });
   assert.deepEqual(errors, []); assert.equal(submissions, 0); item.result = 'PASS';
   console.log('PASS ' + skin + ': repeated import, grid/list × 5 widths, 6 axe states, explicit-only detail and no submission.');
  } finally { await context.close(); }
 }
 report.result = 'PASS';
} catch (error) { report.error = error.message; throw error; }
finally {
 await fs.writeFile(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
 await browser.close();
 // Leave the reproducible integrated UI fixture on its documented demo.
 importDemo(process.env.QA_RESTORE_DEMO || 'cloud-kitchen');
}
