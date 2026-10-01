/* Catering-specific checks: a local brief is not a submitted event/quote. */
import assert from 'node:assert/strict';
import AxeBuilder from '@axe-core/playwright';
import path from 'node:path';

export async function testCateringPlanner(page, context, report, base, output) {
	const form = page.locator('[data-fd-proposal]');
	assert.equal(await form.isVisible(), true, 'Planner did not progressively enhance.');
	assert.ok((await page.locator('.fd-header__cta').getAttribute('href')).endsWith('#proposal'));
	assert.equal(await page.locator('.fd-mizan-service').count(), 3);
	assert.equal(await page.locator('.fd-product__prep').count(), 0, 'Per-item prep must not imply event lead time.');
	const requests = [];
	const onRequest = request => { if (!['GET', 'HEAD'].includes(request.method())) requests.push(request.url()); };
	page.on('request', onRequest);
	try {
		await page.locator('.fd-mizan-service [data-fd-service="events"]').last().click();
		assert.equal(await page.locator('#fd-proposal-service').inputValue(), 'events');
		assert.equal(await page.locator('#fd-proposal-service').evaluate(element => element === document.activeElement), true);
		await page.locator('#fd-proposal-date').fill('۲۵ مهر، ساعت ۱۳');
		await page.locator('#fd-proposal-location').fill('تهران، دفتر ونک');
		await page.locator('#fd-proposal-guests').fill('۰');
		await form.locator('[type="submit"]').click();
		assert.equal(await page.locator('[data-fd-proposal-result]').isVisible(), false);
		assert.ok(await page.locator('#fd-proposal-guests').evaluate(element => element.validationMessage.length > 0));
		await page.locator('#fd-proposal-guests').fill('۱۰۰۱');
		await form.locator('[type="submit"]').click();
		assert.equal(await page.locator('[data-fd-proposal-result]').isVisible(), false);
		await page.locator('#fd-proposal-guests').fill('٤٢');
		await page.locator('#fd-proposal-date').fill('   ');
		await form.locator('[type="submit"]').click();
		assert.equal(await page.locator('[data-fd-proposal-result]').isVisible(), false);
		await page.locator('#fd-proposal-date').fill('۲۵ مهر، ساعت ۱۳');
		const hostileNotes = '<img src="x" onerror="window.quoteXss=1"> & حساسیت غذایی';
		await page.locator('#fd-proposal-notes').fill(hostileNotes);
		await form.locator('[type="submit"]').click();
		assert.equal(await page.locator('[data-fd-proposal-result]').isVisible(), true);
		const brief = await page.locator('[data-fd-summary]').inputValue();
		assert.ok(brief.includes('رویدادها و مراسم') && brief.includes('۴۲ نفر') && brief.includes(hostileNotes));
		assert.equal(await page.locator('img[src="x"]').count(), 0);
		assert.equal(await page.evaluate(() => window.quoteXss), undefined, 'Planner interpreted input as markup.');
		assert.ok((await page.locator('#fd-summary-title').textContent()).includes('ارسال نشده'));

		await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: new URL(base).origin });
		await page.locator('[data-fd-copy]').click();
		await page.waitForFunction(() => document.querySelector('[data-fd-copy-status]').textContent.includes('خلاصه کپی شد'));
		assert.equal(await page.evaluate(() => navigator.clipboard.readText()), brief);

		// Prove the permission-denied fallback reports the truth, not "sent".
		await page.evaluate(() => {
			Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: () => Promise.reject(new Error('Blocked clipboard')) } });
			document.execCommand = () => false;
		});
		await page.locator('[data-fd-copy]').click();
		await page.waitForFunction(() => document.querySelector('[data-fd-copy-status]').textContent.includes('دستی کپی'));
		assert.equal(await page.locator('[data-fd-summary]').evaluate(element => element === document.activeElement && element.selectionEnd === element.value.length), true);
		assert.deepEqual(requests, [], 'Planner submitted data to a server.');
		const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
		report.accessibility.push({ page: 'catering-planner-result', violations: audit.violations });
		assert.equal(audit.violations.length, 0, 'Planner-result accessibility violations.');
		await page.screenshot({ path: path.join(output, 'catering-planner.png'), fullPage: true });

		await page.locator('#fd-proposal-notes').fill('توضیحات جدید');
		assert.equal(await page.locator('[data-fd-proposal-result]').isVisible(), false, 'Edited fields leave a stale quote summary.');
		assert.equal(await page.locator('[data-fd-summary]').inputValue(), '');
		await page.locator('.fd-mizan-service [data-fd-service="office"]').last().click();
		assert.equal(await page.locator('#fd-proposal-service').inputValue(), 'office');
		report.interactions.push('catering service presets / Persian and Arabic digits / invalid range and whitespace / plain-text XSS safety / explicit clipboard / denied-clipboard fallback / stale-result reset / no submission');
	} finally {
		page.off('request', onRequest);
	}
}
