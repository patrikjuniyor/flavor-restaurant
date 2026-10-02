/** Shared, dependency-free storefront primitives. No checkout business rules. */
(function () {
	'use strict';
	var cfg = window.flavorData || {};
	var stack = [];
	function esc(value) { return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
	function digits(value) { return String(value).replace(/\d/g, function (n) { return '۰۱۲۳۴۵۶۷۸۹'[n]; }); }
	function latin(value) { return String(value).replace(/[۰-۹]/g, function (n) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(n); }).replace(/[٠-٩]/g, function (n) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(n); }); }
	function money(amount) { return Number(amount || 0).toLocaleString('fa-IR') + ' ' + ((cfg.currency && cfg.currency.label) || 'تومان'); }
	function announce(message) {
		var status = document.getElementById('flavor-ui-announcement');
		if (!status) { status = document.createElement('p'); status.id = 'flavor-ui-announcement'; status.className = 'screen-reader-text'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); document.body.appendChild(status); }
		var root = stack.length ? stack[stack.length - 1].host.querySelector('[role="dialog"]') || stack[stack.length - 1].host : document.body;
		if (!root.contains(status)) { status.removeAttribute('inert'); root.appendChild(status); }
		status.textContent = message;
	}
	function focusables(root) {
		return Array.from(root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')).filter(function (el) { return el.getClientRects().length && !el.closest('[inert]'); });
	}
	function openDialog(host, trigger) {
		if (!host || stack.some(function (entry) { return entry.host === host; })) return;
		var record = { host: host, trigger: trigger || document.activeElement, inert: [], overflow: document.body.style.overflow };
		host.hidden = false;
		// Inert siblings along the ancestry path, not the ancestor of the dialog.
		var node = host;
		while (node && node.parentElement && node.parentElement !== document.documentElement) {
			Array.from(node.parentElement.children).forEach(function (sibling) {
				if (sibling !== node && !['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName) && !sibling.hasAttribute('inert')) { sibling.setAttribute('inert', ''); record.inert.push(sibling); }
			});
			node = node.parentElement;
		}
		stack.push(record);
		document.body.style.overflow = 'hidden';
		var first = focusables(host)[0] || host.querySelector('[role="dialog"]');
		if (first) first.focus({ preventScroll: true });
		host.dispatchEvent(new CustomEvent('flavor:dialog-opened'));
	}
	function closeDialog(host) {
		var index = stack.findIndex(function (entry) { return entry.host === host; });
		if (index < 0) { if (host) host.hidden = true; return; }
		var record = stack.splice(index, 1)[0];
		record.host.hidden = true;
		record.inert.forEach(function (element) { element.removeAttribute('inert'); });
		document.body.style.overflow = stack.length ? 'hidden' : record.overflow;
		record.host.dispatchEvent(new CustomEvent('flavor:dialog-closed'));
		if (record.trigger && record.trigger.isConnected && record.trigger.getClientRects().length) record.trigger.focus({ preventScroll: true });
	}
	document.addEventListener('keydown', function (event) {
		var entry = stack[stack.length - 1];
		if (!entry) return;
		if (event.key === 'Escape') { event.preventDefault(); closeDialog(entry.host); return; }
		if (event.key !== 'Tab') return;
		var items = focusables(entry.host);
		if (!items.length) { event.preventDefault(); return; }
		if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
		else if (!event.shiftKey && document.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
	});
	// Legacy code may hide a dialog directly; still restore inert/scroll/focus.
	['flavor-sheet', 'flavor-cart-panel'].forEach(function (id) {
		var host = document.getElementById(id);
		if (host) new MutationObserver(function () { if (host.hidden && stack.some(function (entry) { return entry.host === host; })) closeDialog(host); }).observe(host, { attributes: true, attributeFilter: ['hidden'] });
	});
	function headers(json) {
		var result = { 'X-WP-Nonce': cfg.nonce || '' };
		if (json) result['Content-Type'] = 'application/json';
		try { var token = sessionStorage.getItem('flavorCartToken'); if (token) result['X-Cart-Token'] = token; } catch (ignore) { /* Cookie sessions remain supported. */ }
		return result;
	}
	function request(route, options) {
		options = Object.assign({ credentials: 'same-origin' }, options || {});
		options.headers = Object.assign({}, headers(!!options.body), options.headers || {});
		return fetch(cfg.rest + route, options).then(function (response) {
			return response.json().catch(function () { throw new Error('پاسخ سرور قابل خواندن نیست. دوباره تلاش کنید.'); }).then(function (json) {
				if (!response.ok || json.success === false) {
					var problem = json.errors && json.errors[0];
					var error = new Error((problem && problem.message) || json.message || 'ارتباط با سرور ناموفق بود.');
					error.code = (problem && problem.code) || json.code; error.status = response.status; error.details = (problem && problem.details) || json.details;
					throw error;
				}
				var data = json.success === true ? json.data : json;
				if (data && data.cart_token) { try { sessionStorage.setItem('flavorCartToken', data.cart_token); } catch (ignore) {} }
				return data;
			});
		});
	}
	function refreshNonce() {
		return fetch(cfg.ajax + '?action=rest-nonce', { credentials: 'same-origin', cache: 'no-store' }).then(function (response) {
			return response.text().then(function (nonce) { if (!response.ok || !/^[a-zA-Z0-9]{8,16}$/.test(nonce.trim())) throw new Error('نشست ورود تازه‌سازی نشد؛ صفحه را دوباره باز کنید.'); cfg.nonce = nonce.trim(); if (window.flavorSearchData) window.flavorSearchData.nonce = cfg.nonce; return cfg.nonce; });
		});
	}

	var header = document.getElementById('flavor-site-header');
	function headerOffset() { if (header) document.body.style.setProperty('--ui-header-offset', ((document.body.classList.contains('flavor-header-not-sticky') ? 0 : header.offsetHeight) + (document.getElementById('wpadminbar') ? document.getElementById('wpadminbar').offsetHeight : 0) + 8) + 'px'); }
	headerOffset();
	document.addEventListener('flavor:ui-header-changed', headerOffset);
	if (header && typeof ResizeObserver !== 'undefined') new ResizeObserver(headerOffset).observe(header);
	var views = document.querySelector('[data-ui-menu-view]');
	if (views) {
		function paintView(mode) { document.body.classList.toggle('flavor-ui-menu-layout-list', mode === 'list'); document.body.classList.toggle('flavor-ui-menu-layout-grid', mode === 'grid'); views.querySelectorAll('button').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.uiView === mode)); }); }
		paintView((cfg.ui && cfg.ui.menu_layout) || 'grid');
		views.addEventListener('click', function (event) { var button = event.target.closest('[data-ui-view]'); if (button) { paintView(button.dataset.uiView); announce(button.dataset.uiView === 'list' ? 'منو به صورت فهرستی نمایش داده می‌شود.' : 'منو به صورت کارتی نمایش داده می‌شود.'); } });
		document.addEventListener('flavor:ui-settings-changed', function (event) { if (event.detail.menu_layout) paintView(event.detail.menu_layout); });
		views.hidden = false;
	}

	window.FlavorUI = { esc: esc, digits: digits, latin: latin, money: money, announce: announce, request: request, headers: headers, refreshNonce: refreshNonce, openDialog: openDialog, closeDialog: closeDialog };
})();
