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

	// The product sheet is opened asynchronously by the real menu script.
	var productSheet = document.getElementById('flavor-sheet');
	if (productSheet) {
		var sheetReturnFocus = null;
		function sheetItems() {
			return Array.from(productSheet.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')).filter(function (el) { return el.getClientRects().length; });
		}
		new MutationObserver(function () {
			if (!productSheet.hidden) {
				sheetReturnFocus = document.activeElement;
				var first = sheetItems()[0];
				if (first) first.focus({ preventScroll: true });
			} else if (sheetReturnFocus) {
				sheetReturnFocus.focus({ preventScroll: true });
				sheetReturnFocus = null;
			}
		}).observe(productSheet, { attributes: true, attributeFilter: ['hidden'] });
		document.addEventListener('keydown', function (event) {
			if (event.key !== 'Tab' || productSheet.hidden) return;
			var items = sheetItems();
			if (!items.length) return;
			if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
			else if (!event.shiftKey && document.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
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
