/* Real Customizer preview + publish/restore. Disposable, explicitly opted-in only. */
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import AxeBuilder from '@axe-core/playwright';

export async function testCustomizer(browser, report, base, output) {
 assert.equal(process.env.FLAVOR_DEMO_QA, '1', 'Customizer writes require FLAVOR_DEMO_QA=1 and a disposable local/development site.');
 const admin = JSON.parse(await fs.readFile(process.env.QA_ADMIN_FILE || path.resolve('../../.cache/ui-qa/admin.json'), 'utf8'));
 assert.ok(['local', 'development'].includes(admin.environment), 'Admin fixture is not from a disposable environment.');
 assert.equal(new URL(admin.site_url).origin, new URL(base).origin, 'Admin fixture belongs to a different site.');
 const adminContext = await browser.newContext({ viewport: { width: 1600, height: 1100 } });
 const publicContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
 const page = await adminContext.newPage(), publicPage = await publicContext.newPage();
 const errors = []; page.on('pageerror', error => errors.push(error.message));
 const ids = ['menu_layout', 'density', 'image_ratio', 'card_style', 'mobile_nav'].map(key => 'flavor_ui_' + key).concat(['flavor_font_heading', 'flavor_font_body', 'flavor_header_layout', 'flavor_header_sticky']);
 let original = null;
 async function preview(pathname) {
  await page.waitForFunction(expected => [...document.querySelectorAll('#customize-preview iframe')].some(el => {
   try { return el.contentWindow.location.pathname === expected && el.contentDocument?.querySelector('body.flavor-theme'); } catch { return false; }
  }), pathname);
  const frame = page.frames().find(candidate => candidate.parentFrame() && new URL(candidate.url()).pathname === pathname);
  if (frame) return frame;
  throw new Error('The requested preview frame is unavailable.');
 }
 async function publish() {
  await page.waitForFunction(() => !wp.customize.state('saved').get() && !wp.customize.state('saving').get());
  await page.locator('#save').click();
  await page.waitForFunction(() => wp.customize.state('saved').get() && !wp.customize.state('saving').get(), undefined, { timeout: 20000 });
 }
 async function select(id, value) {
  await page.locator('#customize-control-' + id + ' select').selectOption(value);
 }
 async function audit(label) {
  const result = await new AxeBuilder({ page: publicPage }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  report.accessibility.push({ label, violations: result.violations });
  assert.equal(result.violations.length, 0, label + ': ' + result.violations.map(v => v.id).join(', '));
 }
 try {
  await page.goto(new URL('wp-login.php', base).href, { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(admin.username); await page.locator('#user_pass').fill(admin.password);
  await Promise.all([page.waitForURL('**/wp-admin/'), page.locator('#wp-submit').click()]);
  const url = new URL('wp-admin/customize.php', base); url.searchParams.set('url', new URL('menu/', base).href);
  await page.goto(url.href, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => window.wp?.customize?.has('flavor_ui_menu_layout'));
  original = await page.evaluate(keys => Object.fromEntries(keys.map(key => [key, wp.customize(key).get()])), ids);
  await page.evaluate(() => wp.customize.section('flavor_section_ui').expand());
  await page.locator('[data-flavor-ui-preview-url]').filter({ hasText: 'صفحهٔ نخست' }).click();
  let frame = await preview('/'); await frame.waitForSelector('body.flavor-bespoke');
  assert.equal(await frame.locator('body.flavor-ui').count(), 0, 'Menu classes changed the bespoke landing composition.');
  await page.locator('[data-flavor-ui-preview-url]').filter({ hasText: 'پیش‌نمایش منو' }).click();
  frame = await preview('/menu/'); await frame.waitForFunction(() => window.FlavorCartUI && FlavorCartUI.state() && document.querySelectorAll('#flavor-menu-grid [data-add]').length >= 8);
  await frame.evaluate(() => { window.uiQaDocument = 'same-preview-document'; });
  const beforeCart = await frame.evaluate(() => ({ count: FlavorCartUI.state().count, total: FlavorCartUI.state().total }));
  await frame.locator('#flavor-menu-grid [data-add]').first().click();
  await frame.waitForSelector('#flavor-sheet:not([hidden])');
  await frame.locator('[data-q="1"]').click(); await frame.locator('#flavor-instr').fill('یادداشت محلی پیش‌نمایش؛ ارسال نشود');
  const values = { menu_layout: 'list', density: 'compact', image_ratio: 'square', card_style: 'sharp', mobile_nav: 'minimal' };
  for (const [key, value] of Object.entries(values)) {
   await select('flavor_ui_' + key, value);
   await frame.waitForFunction(({ key, value }) => document.body.classList.contains('flavor-ui-' + key.replace(/_/g, '-') + '-' + value), { key, value });
  }
  assert.equal(await frame.locator('[data-ui-view="list"]').getAttribute('aria-pressed'), 'true');
  assert.equal(await frame.locator('#flavor-instr').inputValue(), 'یادداشت محلی پیش‌نمایش؛ ارسال نشود');
  assert.equal(await frame.locator('#flavor-qty').textContent(), '۲');
  assert.deepEqual(await frame.evaluate(() => ({ count: FlavorCartUI.state().count, total: FlavorCartUI.state().total })), beforeCart, 'Preview mutated the cart.');
  await frame.locator('.flavor-sheet__close').click();
  assert.equal(await frame.locator('.flavor-food-card__body').first().evaluate(el => getComputedStyle(el).paddingTop), '16px');
  assert.equal(await frame.locator('.flavor-food-card').first().evaluate(el => getComputedStyle(el).borderTopRightRadius), '2px');
  await select('flavor_ui_menu_layout', 'grid');
  await frame.waitForFunction(() => document.body.classList.contains('flavor-ui-menu-layout-grid'));
  const dimensions = await frame.locator('.flavor-food-card__media').first().evaluate(el => ({ width: el.getBoundingClientRect().width, height: el.getBoundingClientRect().height }));
  assert.ok(Math.abs(dimensions.width - dimensions.height) < 2, 'Square photo setting is only a nominal CSS property.');
  await page.evaluate(() => wp.customize.previewedDevice.set('mobile'));
  await frame.waitForFunction(() => innerWidth <= 991);
  assert.equal(await frame.locator('.flavor-mobile-nav > a:visible').count(), 3);
  assert.equal(await frame.locator('[data-ui-nav-home]').isVisible(), false);
  await select('flavor_ui_mobile_nav', 'contextual');
  await frame.waitForFunction(() => !document.querySelector('.flavor-mobile-nav').classList.contains('flavor-mobile-nav--minimal'));
  assert.equal(await frame.locator('.flavor-mobile-nav > a:visible').count(), 4);
  await select('flavor_ui_mobile_nav', 'minimal');
  await page.evaluate(() => wp.customize.previewedDevice.set('desktop'));
  await frame.waitForFunction(() => innerWidth >= 992);
  await page.evaluate(() => wp.customize.section('flavor_section_layout').expand());
  await select('flavor_font_body', 'Estedad'); await select('flavor_font_heading', 'Vazirmatn');
  await frame.waitForFunction(() => getComputedStyle(document.body).fontFamily.includes('Estedad'));
  assert.ok(await frame.evaluate(async () => (await document.fonts.load('16px Estedad')).length > 0), 'Bundled font did not load.');
  await page.evaluate(() => wp.customize.section('flavor_section_header').expand());
  await select('flavor_header_layout', 'minimal');
  await frame.waitForFunction(() => getComputedStyle(document.querySelector('.flavor-header__inner')).minHeight === '64px');
  await select('flavor_header_layout', 'centered');
  await frame.waitForFunction(() => getComputedStyle(document.querySelector('.flavor-header__inner')).display === 'grid');
  await select('flavor_header_layout', 'transparent');
  await frame.waitForFunction(() => document.body.classList.contains('flavor-header-transparent'));
  await page.locator('#customize-control-flavor_header_sticky input[type=checkbox]').uncheck();
  await frame.waitForFunction(() => document.body.classList.contains('flavor-header-not-sticky'));
  assert.equal(await frame.locator('.flavor-header').evaluate(el => getComputedStyle(el).position), 'relative');
  assert.ok(await frame.evaluate(() => parseInt(document.body.style.getPropertyValue('--ui-header-offset'), 10) < 60), 'Nonsticky header leaves a dead gap above sticky categories.');
  assert.equal(await frame.evaluate(() => window.uiQaDocument), 'same-preview-document', 'postMessage settings reloaded the menu.');
  await publicPage.goto(new URL('menu/', base).href, { waitUntil: 'networkidle' });
  assert.equal(await publicPage.locator('body.flavor-ui-density-compact').count(), 0, 'Unpublished settings leaked to visitors.');
  await publish();
  await publicPage.reload({ waitUntil: 'networkidle' });
  assert.equal(await publicPage.locator('body.flavor-ui-density-compact.flavor-ui-image-ratio-square.flavor-ui-card-style-sharp.flavor-header-transparent.flavor-header-not-sticky').count(), 1);
  for (const width of [320, 390, 768, 1024, 1440]) {
   await publicPage.setViewportSize({ width, height: 960 });
   assert.ok(await publicPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Published custom settings overflow at ' + width);
  }
  await audit('published-custom-grid-desktop');
  await publicPage.screenshot({ path: path.join(output, 'custom-menu-desktop.png'), fullPage: true });
  await publicPage.setViewportSize({ width: 390, height: 844 });
  await audit('published-custom-grid-mobile');
  assert.equal(await publicPage.locator('.flavor-mobile-nav > a:visible').count(), 3);
  assert.equal(await publicPage.locator('.flavor-food-card__footer .flavor-btn').first().evaluate(el => el.getBoundingClientRect().height >= 44), true);
  await publicPage.screenshot({ path: path.join(output, 'custom-menu-mobile.png'), fullPage: true });
  await page.evaluate(() => wp.customize.section('flavor_section_ui').expand());
  await page.waitForFunction(() => { const field = document.querySelector('#customize-control-flavor_ui_menu_layout select'), area = document.getElementById('customize-controls'); if (!field || !area) return false; const f = field.getBoundingClientRect(), a = area.getBoundingClientRect(); return f.width > 0 && f.left >= a.left && f.right <= a.right + 1; });
  await page.locator('#customize-control-flavor_ui_preview').scrollIntoViewIfNeeded();
  await page.screenshot({ path: path.join(output, 'customizer-desktop.png') });
  report.checks.push('real Customizer controls and navigation to home/menu preview', 'postMessage preserves document/product quantity/note/cart', 'actual compact list padding, square grid image and corner geometry', 'live minimal/contextual mobile nav in both directions', 'bundled Persian font and real header layout/sticky controls', 'unpublished preview isolated from visitors; real publish and restored original settings', 'published custom menu: five widths, desktop/mobile axe and >=44px actions');
  assert.deepEqual(errors, []);
 } finally {
  if (original) {
   await page.evaluate(values => Object.entries(values).forEach(([key, value]) => wp.customize(key).set(value)), original);
   if (!await page.evaluate(() => wp.customize.state('saved').get())) await publish();
  }
  await adminContext.close(); await publicContext.close();
 }
}
