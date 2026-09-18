/**
 * Flavor Theme Main Frontend Interactions.
 * Vanilla JS, mobile drawer, sticky header, toasts, and accessibility.
 */
(function () {
	'use strict';

	// 1. Mobile Off-Canvas Drawer Toggle
	var drawerToggle = document.getElementById('flavor-drawer-toggle');
	var drawerClose = document.getElementById('flavor-drawer-close');
	var drawer = document.getElementById('flavor-mobile-drawer');
	var overlay = document.getElementById('flavor-drawer-overlay');

	function openDrawer() {
		if (!drawer || !overlay) return;
		drawer.classList.add('is-active');
		overlay.classList.add('is-active');
		drawer.setAttribute('aria-hidden', 'false');
		overlay.setAttribute('aria-hidden', 'false');
		if (drawerToggle) drawerToggle.setAttribute('aria-expanded', 'true');
		document.body.style.overflow = 'hidden';
	}

	function closeDrawer() {
		if (!drawer || !overlay) return;
		drawer.classList.remove('is-active');
		overlay.classList.remove('is-active');
		drawer.setAttribute('aria-hidden', 'true');
		overlay.setAttribute('aria-hidden', 'true');
		if (drawerToggle) drawerToggle.setAttribute('aria-expanded', 'false');
		document.body.style.overflow = '';
	}

	if (drawerToggle) {
		drawerToggle.addEventListener('click', openDrawer);
	}
	if (drawerClose) {
		drawerClose.addEventListener('click', closeDrawer);
	}
	if (overlay) {
		overlay.addEventListener('click', closeDrawer);
	}

	// 2. Keyboard accessibility (Escape key closes drawer/modals)
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' || e.key === 'Esc') {
			closeDrawer();
			var sheet = document.getElementById('flavor-sheet');
			if (sheet && !sheet.hidden) {
				sheet.hidden = true;
				document.body.style.overflow = '';
			}
		}
	});

	// 3. Sticky Header scroll shadow
	var header = document.getElementById('flavor-site-header');
	if (header) {
		var lastY = window.scrollY;
		window.addEventListener('scroll', function () {
			var currY = window.scrollY;
			header.classList.toggle('is-scrolled', currY > 20);
			lastY = currY;
		}, { passive: true });
	}

	// 4. Mobile Bottom Bar Cart trigger
	var mobileCartBtn = document.getElementById('flavor-mobile-cart-btn');
	if (mobileCartBtn) {
		mobileCartBtn.addEventListener('click', function () {
			var cartPanel = document.getElementById('flavor-cart-panel');
			var menuCart = document.querySelector('.flavor-cart__handle');
			if (menuCart) {
				menuCart.click();
			} else {
				var menuPage = window.flavorData && window.flavorData.menuUrl;
				window.location.href = menuPage || '/menu/';
			}
		});
	}

	// 5. Global Toast Notification Helper
	window.flavorToast = function (message, type) {
		var existing = document.getElementById('flavor-toast-container');
		if (!existing) {
			existing = document.createElement('div');
			existing.id = 'flavor-toast-container';
			existing.style.cssText = 'position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none;';
			document.body.appendChild(existing);
		}

		var toast = document.createElement('div');
		var bg = type === 'error' ? '#dc2626' : (type === 'warning' ? '#d97706' : '#16a34a');
		toast.style.cssText = 'background: ' + bg + '; color: #fff; padding: 10px 20px; border-radius: 999px; font-weight: 600; font-size: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.25); animation: flavorFadeIn 0.25s ease; pointer-events: auto;';
		toast.textContent = message;

		existing.appendChild(toast);
		setTimeout(function () {
			toast.style.opacity = '0';
			toast.style.transition = 'opacity 0.3s';
			setTimeout(function () {
				if (toast.parentNode) toast.parentNode.removeChild(toast);
			}, 300);
		}, 3000);
	};
})();
