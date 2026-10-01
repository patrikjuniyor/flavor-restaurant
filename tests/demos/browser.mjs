/* Real-browser smoke test. Import a demo into a disposable WP first. */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';

const base = process.env.SITE_URL || 'http://localhost:8080/';
const output = path.resolve(process.env.QA_OUTPUT_DIR || '../../.cache/demo-qa');
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400) errors.push(response.status() + ' ' + response.url()); });
const report = { site: base, widths: [], accessibility: [], interactions: [] };
try {
	await page.goto(base, { waitUntil: 'networkidle' });
	assert.equal(await page.locator('body.flavor-bespoke').count(), 1, 'Import a bespoke demo first.');
	const slug = await page.evaluate(() => Array.from(document.body.classList).find(name => name.startsWith('flavor-skin-')).replace('flavor-skin-', ''));
	report.demo = slug;
	assert.equal(await page.locator('h1').count(), 1, 'Duplicate hero/content.');
	assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
	const count = await page.locator('.fd-product').count();
	assert.ok(count >= 8, 'Import the full WooCommerce demo content.');
	await page.evaluate(async () => {
		await document.fonts.ready;
		await Promise.all(Array.from(document.images).map(image => { image.loading = 'eager'; return image.decode().catch(() => {}); }));
	});
	assert.equal(await page.locator('img').evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)), true, 'Broken image.');
	await page.screenshot({ path: path.join(output, slug + '-desktop.png'), fullPage: true });
	for (const width of [320, 390, 768, 1024, 1440]) {
		await page.setViewportSize({ width, height: 900 });
		const layout = await page.evaluate(() => ({ viewport: innerWidth, scroll: document.documentElement.scrollWidth,
			overflow: Array.from(document.querySelectorAll('main *')).filter(element => element.getClientRects().length && (element.getBoundingClientRect().right > innerWidth + 2 || element.getBoundingClientRect().left < -2)).slice(0, 5).map(element => element.className) }));
		assert.ok(layout.scroll <= width + 1, 'Horizontal scroll at ' + width);
		assert.deepEqual(layout.overflow, [], 'Clipped content at ' + width);
		report.widths.push(layout);
	}
	for (const width of [1440, 390]) {
		await page.setViewportSize({ width, height: 900 });
		const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
		report.accessibility.push({ width, violations: result.violations });
		assert.deepEqual(result.violations.map(violation => violation.id), [], 'Automated WCAG violations at ' + width);
	}
	await page.screenshot({ path: path.join(output, slug + '-mobile.png'), fullPage: true });
	await page.setViewportSize({ width: 1440, height: 900 });
	const filter = page.locator('[data-fd-filter]').nth(1);
	const category = await filter.getAttribute('data-fd-filter');
	const expected = await page.locator('[data-fd-categories]').evaluateAll((cards, selected) => cards.filter(card => card.dataset.fdCategories.split(' ').includes(selected)).length, category);
	await filter.click();
	assert.equal(await page.locator('.fd-product:visible').count(), expected);
	assert.equal(await filter.getAttribute('aria-pressed'), 'true');
	await page.locator('[data-fd-filter="all"]').click();
	assert.equal(await page.locator('.fd-product:visible').count(), count);
	const coverageForm = page.locator('[data-fd-coverage]');
	if (await coverageForm.count()) {
		const neighborhood = await page.locator('#fd-coverage-list option').first().getAttribute('value');
		await page.locator('#fd-coverage-hood').fill(neighborhood);
		await coverageForm.locator('[type="submit"]').click();
		await page.waitForSelector('[data-fd-coverage-result][data-available="yes"]');
		await page.locator('#fd-coverage-hood').fill('Uncovered Neighborhood');
		await coverageForm.locator('[type="submit"]').click();
		await page.waitForSelector('[data-fd-coverage-result][data-available="no"]');
		report.interactions.push('live delivery-zone eligibility accepts configured neighborhood and rejects an uncovered one');
	}
	await page.locator('.fd-faq summary').first().click();
	assert.equal(await page.locator('.fd-faq details').first().getAttribute('open'), '');
	report.interactions.push('category filter / aria-live / native FAQ');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.locator('#flavor-drawer-toggle').click();
	assert.equal(await page.locator('#flavor-mobile-drawer').getAttribute('aria-hidden'), 'false');
	assert.equal(await page.locator('#flavor-drawer-close').evaluate(element => element === document.activeElement), true);
	await page.keyboard.press('Shift+Tab');
	assert.equal(await page.locator('#flavor-mobile-drawer a').last().evaluate(element => element === document.activeElement), true);
	await page.keyboard.press('Escape');
	assert.equal(await page.locator('#flavor-mobile-drawer').getAttribute('aria-hidden'), 'true');
	assert.equal(await page.locator('#flavor-drawer-toggle').evaluate(element => element === document.activeElement), true);
	report.interactions.push('mobile drawer / focus trap / Escape / focus restoration');
	await page.setViewportSize({ width: 1440, height: 900 });
	const productHref = await page.locator('.fd-product__order').first().getAttribute('href');
	await page.goto(productHref, { waitUntil: 'networkidle' });
	await page.waitForSelector('#flavor-sheet:not([hidden])', { timeout: 20000 });
	assert.ok((await page.locator('#flavor-sheet-title').textContent()).trim());
	assert.equal(await page.locator('#flavor-cart-count').textContent(), '0', 'A deep link must not add a product automatically.');
	await page.locator('#flavor-sheet-add').click();
	await page.waitForFunction(() => Number(document.getElementById('flavor-cart-count').textContent) > 0);
	await page.reload({ waitUntil: 'networkidle' });
	assert.equal(await page.locator('#flavor-cart-count').textContent(), '1', 'Cart did not survive GET envelope/reload.');
	report.interactions.push('live REST menu / product fragment / explicit cart add / cart persistence');
	await page.locator('.flavor-sheet__close').click();
	await page.locator('#flavor-cart-toggle').click();
	const allowedModes = await page.evaluate(() => window.flavorData.orderModes || ['dine_in', 'takeaway', 'delivery']);
	assert.deepEqual(await page.locator('#flavor-modes [data-mode]:visible').evaluateAll(buttons => buttons.map(button => button.dataset.mode)), allowedModes);
	report.interactions.push('cart service modes match the imported branch');
	if (slug === 'cloud-kitchen') {
		await page.locator('#flavor-modes [data-mode="delivery"]').click();
		await page.locator('#flavor-city').fill('تهران');
		await page.locator('#flavor-hood').fill('ونک');
		await page.locator('#flavor-hood').press('Tab');
		await page.waitForFunction(() => document.getElementById('flavor-zone-msg').textContent.includes('حدود'));
		report.interactions.push('live checkout zone check (no order/SMS/payment submitted)');
	}

	await page.goto(base, { waitUntil: 'networkidle' });
	const reservationHref = await page.locator('.fd-header__cta').getAttribute('href');
	if (reservationHref.includes('/reservation/')) {
		await page.goto(reservationHref, { waitUntil: 'networkidle' });
		await page.waitForSelector('#flavor-res-branch option', { state: 'attached' });
		assert.equal(await page.locator('#flavor-res-branch').inputValue(), String(await page.evaluate(() => window.flavorData.branchId)));
		await page.locator('.flavor-cal__day:not([disabled])').nth(1).click();
		await page.waitForSelector('.flavor-slot:not([disabled])');
		await page.locator('.flavor-slot:not([disabled])').first().click();
		assert.equal(await page.locator('.flavor-slot.is-active').getAttribute('aria-pressed'), 'true');
		await page.locator('#flavor-res-party').fill('3');
		await page.locator('#flavor-res-party').press('Tab');
		await page.waitForFunction(() => !document.getElementById('flavor-res-slots').hasAttribute('aria-busy'));
		assert.equal(await page.locator('.flavor-slot.is-active').count(), 0, 'Changing capacity filters must clear the old time.');
		await page.locator('.flavor-slot:not([disabled])').first().click();
		const reservationAudit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
		report.accessibility.push({ page: 'reservation', violations: reservationAudit.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.target) })) });
		assert.equal(reservationAudit.violations.length, 0, 'Reservation accessibility violations.');
		report.interactions.push('live reservation branch / Jalali day / real slots / pressed state / filter reset (no booking submitted)');
	}

	const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
	const staticPage = await noJs.newPage();
	await staticPage.goto(base);
	assert.equal(await staticPage.locator('.fd-product:visible').count(), count);
	assert.equal(await staticPage.locator('[data-fd-filters]:visible').count(), 0);
	report.interactions.push('no-JS server-rendered products and FAQ');
	assert.deepEqual(errors, [], 'Browser or HTTP errors.');
	report.result = 'PASS';
	console.log('PASS ' + slug + ': 5 widths, axe, real menu/cart/modes, keyboard, no-JS and reservation when enabled.');
} catch (error) {
	report.result = 'FAIL'; report.error = error.message; throw error;
} finally {
	await fs.writeFile(path.join(output, (report.demo || 'demo') + '-report.json'), JSON.stringify(report, null, 2));
	await browser.close();
}
