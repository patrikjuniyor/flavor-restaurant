/** Small, dependency-free enhancements for the bespoke demo family only. */
(function () {
	'use strict';
	if (!document.body.classList.contains('flavor-bespoke')) return;

	// No hidden content or dead filtering controls when JavaScript is disabled.
	document.querySelectorAll('[data-fd-filters]').forEach(function (filters) {
		var section = filters.closest('.fd-menu');
		var cards = Array.from(section.querySelectorAll('[data-fd-categories]'));
		var status = section.querySelector('[data-fd-filter-status]');
		filters.addEventListener('click', function (event) {
			var button = event.target.closest('[data-fd-filter]');
			if (!button) return;
			var selected = button.dataset.fdFilter;
			var count = 0;
			filters.querySelectorAll('button').forEach(function (item) {
				item.setAttribute('aria-pressed', String(item === button));
			});
			cards.forEach(function (card) {
				var visible = selected === 'all' || card.dataset.fdCategories.split(' ').includes(selected);
				card.hidden = !visible;
				if (visible) count++;
			});
			if (status) status.textContent = String(count).replace(/\d/g, function (n) { return '۰۱۲۳۴۵۶۷۸۹'[n]; }) + ' انتخاب نمایش داده می‌شود.';
		});
		filters.hidden = false;
	});

	// Public eligibility only: never creates a cart, order, booking or SMS.
	var coverage = document.querySelector('[data-fd-coverage]');
	var cfg = window.flavorData || {};
	if (coverage && cfg.hasCore) {
		coverage.hidden = false;
		coverage.addEventListener('submit', function (event) {
			event.preventDefault();
			var button = coverage.querySelector('[type="submit"]');
			var result = coverage.querySelector('[data-fd-coverage-result]');
			button.disabled = true; result.textContent = 'در حال بررسی محله…'; result.dataset.available = 'pending';
			fetch(cfg.rest + 'zones/check', {
				method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
				body: JSON.stringify({ branch_id: Number(cfg.branchId) || 0, neighborhood: coverage.elements.neighborhood.value, city: coverage.elements.city.value })
			}).then(function (response) {
				return response.json().then(function (json) {
					if (!response.ok || json.success === false) throw new Error(json.message || 'بررسی محله ناموفق بود. دوباره تلاش کن یا تماس بگیر.');
					return json.success === true ? json.data : json;
				});
			}).then(function (zone) {
				result.dataset.available = zone.ok ? 'yes' : 'no';
				result.textContent = zone.ok ? zone.name + ' · هزینهٔ ارسال: ' + String(zone.delivery_fee_html || '').replace(/<[^>]*>/g, '') + ' · زمان برآوردی منطقه: حدود ' + zone.estimated_minutes + ' دقیقه؛ زمان تضمین‌شده نیست.' : (zone.message || 'خارج از محدودهٔ ارسال؛ دریافت بیرون‌بر را بررسی کن.');
			}).catch(function (error) { result.dataset.available = 'error'; result.textContent = error.message; })
			.finally(function () { button.disabled = false; });
		});
	}

	// The base script owns drawer visibility; this adds focus, inert and trapping.
	var drawer = document.getElementById('flavor-mobile-drawer');
	var toggle = document.getElementById('flavor-drawer-toggle');
	var close = document.getElementById('flavor-drawer-close');
	var overlay = document.getElementById('flavor-drawer-overlay');
	if (drawer && toggle && close) {
		var restoreFocus = null;
		function focusable() {
			return Array.from(drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')).filter(function (el) { return el.getClientRects().length; });
		}
		function onClose() {
			drawer.setAttribute('inert', '');
			if (restoreFocus) restoreFocus.focus({ preventScroll: true });
			restoreFocus = null;
		}
		toggle.addEventListener('click', function () {
			restoreFocus = document.activeElement;
			drawer.removeAttribute('inert');
			close.focus({ preventScroll: true });
		});
		close.addEventListener('click', onClose);
		if (overlay) overlay.addEventListener('click', onClose);
		drawer.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', function () { close.click(); }); });
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && restoreFocus) onClose();
			if (event.key !== 'Tab' || drawer.getAttribute('aria-hidden') !== 'false') return;
			var items = focusable();
			var first = items[0];
			var last = items[items.length - 1];
			if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
			else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
		});
		window.matchMedia('(min-width: 992px)').addEventListener('change', function (event) {
			if (event.matches && drawer.getAttribute('aria-hidden') === 'false') close.click();
		});
	}
})();
