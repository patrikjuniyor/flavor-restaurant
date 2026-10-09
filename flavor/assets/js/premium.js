/**
 * Flavor premium layer — scheme toggle, wishlist, mega menu, sticky buy bar,
 * quick view, free-delivery progress, countdown, consent, opt-in form, menu
 * filters and the floating dock.
 *
 * One deferred file, no dependencies, no jQuery. Everything degrades: markup is
 * rendered by PHP, this script only wires behaviour.
 */
(function () {
	'use strict';

	var cfg = window.flavorPremium || {};
	var ui = window.FlavorUI || null;
	var doc = document;

	function $(selector, scope) { return (scope || doc).querySelector(selector); }
	function $$(selector, scope) { return Array.prototype.slice.call((scope || doc).querySelectorAll(selector)); }
	function esc(value) {
		return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function digits(value) { return ui ? ui.digits(value) : String(value); }
	function money(amount) { return ui ? ui.money(amount) : String(amount); }
	function announce(message) { if (ui) { ui.announce(message); } }
	function t(key, fallback) { return (cfg.i18n && cfg.i18n[key]) || fallback; }

	function store(key, value) {
		try {
			if (value === undefined) { return window.localStorage.getItem(key); }
			if (value === null) { window.localStorage.removeItem(key); return null; }
			window.localStorage.setItem(key, JSON.stringify(value));
			return value;
		} catch (error) { return null; }
	}

	/*
	 * One subscribe path for every [data-flavor-subscribe] form: the exit popup,
	 * the footer builder's newsletter column and anything a child theme adds.
	 * The form itself carries its status paragraph next to it, so the markup and
	 * the script stay in sync wherever the owner drops the column.
	 */
	function subscribeStatus(form) {
		var scope = form.parentElement || form;
		return scope.querySelector('[data-flavor-subscribe-status]');
	}

	function bindSubscribe(form, onSuccess) {
		if (!form || form.getAttribute('data-flavor-subscribe-bound')) { return; }
		form.setAttribute('data-flavor-subscribe-bound', 'yes');

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var field = form.querySelector('input[type="email"], input[type="text"]');
			var status = subscribeStatus(form);
			var email = field ? String(field.value).trim() : '';

			function say(message, failed) {
				if (!status) { return; }
				status.textContent = message;
				if (failed) { status.setAttribute('data-error', 'yes'); } else { status.removeAttribute('data-error'); }
			}

			if (!email || email.indexOf('@') < 1) {
				say('ایمیل معتبر وارد کنید.', true);
				if (field) { field.focus(); }
				return;
			}

			var data = new FormData();
			data.append('action', 'flavor_subscribe');
			data.append('nonce', cfg.nonce || '');
			data.append('email', email);

			say(t('loading', '…'));

			fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					var ok = !!(payload && payload.success);
					var message = (payload && payload.data && payload.data.message) || (ok ? t('saved', 'ثبت شد.') : t('failed', 'ثبت نشد.'));

					say(message, !ok);

					if (!ok) { return; }

					announce(message);
					if (field) { field.value = ''; }
					if (onSuccess) { onSuccess(); }
				})
				.catch(function () { say(t('failed', 'ثبت نشد؛ بعداً تلاش کنید.'), true); });
		});
	}

	function stored(key) {
		try {
			var raw = window.localStorage.getItem(key);
			return raw ? JSON.parse(raw) : null;
		} catch (error) { return null; }
	}

	function openDialog(host, trigger) {
		if (!host) { return; }
		if (ui) { ui.openDialog(host, trigger); return; }
		host.hidden = false;
		var focusable = $('button, [href], input, select, textarea', host);
		if (focusable) { focusable.focus({ preventScroll: true }); }
	}

	function closeDialog(host) {
		if (!host) { return; }
		if (ui) { ui.closeDialog(host); return; }
		host.hidden = true;
	}

	function reduced() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/* ------------------------------------------------------------- scheme */

	(function scheme() {
		var toggle = $('[data-flavor-scheme-toggle]');
		var root = doc.documentElement;

		if (!toggle) { return; }

		function resolved() {
			var mode = root.getAttribute('data-flavor-scheme') || 'auto';
			if (mode !== 'auto') { return mode; }
			return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
		}

		function paint() {
			var mode = resolved();
			var meta = $('meta[name="theme-color"]:not([media])');

			toggle.setAttribute('aria-pressed', String(mode === 'dark'));

			if (!meta) { return; }
			if (mode === 'dark') {
				var surface = window.getComputedStyle(root).getPropertyValue('--flavor-surface').trim();
				if (surface) {
					if (!meta.dataset.flavorLight) { meta.dataset.flavorLight = meta.getAttribute('content') || ''; }
					meta.setAttribute('content', surface);
				}
			} else if (meta.dataset.flavorLight) {
				meta.setAttribute('content', meta.dataset.flavorLight);
			}
		}

		toggle.addEventListener('click', function () {
			var next = resolved() === 'dark' ? 'light' : 'dark';

			if (!reduced()) {
				root.classList.add('flavor-scheme-switching');
				window.setTimeout(function () { root.classList.remove('flavor-scheme-switching'); }, 340);
			}

			root.setAttribute('data-flavor-scheme', next);
			store('flavorScheme', next);
			paint();
			announce(next === 'dark' ? 'حالت شب فعال شد.' : 'حالت روز فعال شد.');
		});

		if (window.matchMedia) {
			var media = window.matchMedia('(prefers-color-scheme: dark)');
			var onChange = function () { if ((root.getAttribute('data-flavor-scheme') || 'auto') === 'auto') { paint(); } };
			if (media.addEventListener) { media.addEventListener('change', onChange); } else if (media.addListener) { media.addListener(onChange); }
		}

		paint();
	})();

	/* ----------------------------------------------------------- wishlist */

	(function wishlist() {
		var host = $('[data-flavor-wishlist]');
		var list = $('#flavor-wish-items');
		var empty = $('#flavor-wish-empty');
		var footer = $('#flavor-wish-footer');
		var status = $('#flavor-wish-status');

		if (!host || !list) { return; }

		var config = {};
		try { config = JSON.parse(host.getAttribute('data-flavor-wishlist') || '{}'); } catch (error) { config = {}; }

		var key = config.key || 'flavorWishlist';
		var max = Number(config.max || 60);
		var i18n = config.i18n || {};
		var lastTrigger = null;

		function items() {
			var value = stored(key);
			return Array.isArray(value) ? value : [];
		}

		function save(next) {
			store(key, next);
			paint();
		}

		function has(id) {
			return items().some(function (entry) { return String(entry.id) === String(id); });
		}

		function snapshot(button) {
			return {
				id: String(button.getAttribute('data-wish-id') || ''),
				name: button.getAttribute('data-wish-name') || '',
				price: button.getAttribute('data-wish-price') || '',
				image: button.getAttribute('data-wish-image') || '',
				url: button.getAttribute('data-wish-url') || ''
			};
		}

		function paint() {
			var list_ = items();

			$$('[data-wishlist-count]').forEach(function (badge) {
				badge.textContent = digits(String(list_.length));
				badge.hidden = list_.length === 0;
			});

			$$('[data-wishlist-toggle]').forEach(function (button) {
				button.setAttribute('aria-pressed', String(has(button.getAttribute('data-wish-id'))));
			});

			if (empty) { empty.hidden = list_.length > 0; }
			if (footer) { footer.hidden = list_.length === 0; }

			list.innerHTML = list_.map(function (entry) {
				return '<li class="flavor-wish__item" data-wish-row="' + esc(entry.id) + '">'
					+ (entry.image ? '<img src="' + esc(entry.image) + '" alt="" width="64" height="64" loading="lazy" />' : '<span class="flavor-wish__item-thumb" aria-hidden="true"></span>')
					+ '<div>'
					+ (entry.url ? '<a class="flavor-wish__item-name" href="' + esc(entry.url) + '">' + esc(entry.name) + '</a>' : '<span class="flavor-wish__item-name">' + esc(entry.name) + '</span>')
					+ '<span class="flavor-wish__item-price">' + esc(entry.price) + '</span>'
					+ '</div>'
					+ '<div class="flavor-wish__item-actions">'
					+ '<button type="button" data-wishlist-add="' + esc(entry.id) + '">' + esc(i18n.addOne || 'افزودن به سبد') + '</button>'
					+ '<button type="button" data-wishlist-remove="' + esc(entry.id) + '">' + esc(i18n.remove || 'حذف') + '</button>'
					+ '</div></li>';
			}).join('');
		}

		function toggle(button) {
			var item = snapshot(button);
			if (!item.id) { return; }

			if (has(item.id)) {
				save(items().filter(function (entry) { return String(entry.id) !== item.id; }));
				announce(i18n.removed || 'حذف شد.');
				return;
			}

			if (items().length >= max) {
				announce(i18n.full || '');
				return;
			}

			var next = items();
			next.push(item);
			save(next);
			announce(i18n.added || 'اضافه شد.');
		}

		doc.addEventListener('click', function (event) {
			var toggleButton = event.target.closest('[data-wishlist-toggle]');
			if (toggleButton) {
				event.preventDefault();
				toggle(toggleButton);
				return;
			}

			var openButton = event.target.closest('[data-wishlist-open]');
			if (openButton) {
				event.preventDefault();
				lastTrigger = openButton;
				openDialog(host, openButton);
				if (openButton.setAttribute) { openButton.setAttribute('aria-expanded', 'true'); }
				return;
			}

			if (event.target.closest('[data-wishlist-close]')) {
				closeDialog(host);
				$$('[data-wishlist-open]').forEach(function (button) { button.setAttribute('aria-expanded', 'false'); });
				return;
			}

			var removeButton = event.target.closest('[data-wishlist-remove]');
			if (removeButton) {
				save(items().filter(function (entry) { return String(entry.id) !== removeButton.getAttribute('data-wishlist-remove'); }));
				announce(i18n.removed || 'حذف شد.');
				return;
			}

			if (event.target.closest('#flavor-wish-clear')) {
				save([]);
				announce(i18n.removed || 'حذف شد.');
				return;
			}

			var addButton = event.target.closest('[data-wishlist-add]');
			if (addButton) { addOne(addButton.getAttribute('data-wishlist-add')); return; }

			if (event.target.closest('#flavor-wish-add-all')) { addAll(); }
		});

		/* The list never talks to the cart itself: it presses the same button
		 * the menu renders, so modifiers, stock and pricing stay server-side. */
		function addOne(id) {
			var entry = items().filter(function (item) { return String(item.id) === String(id); })[0] || {};
			var direct = doc.querySelector('[data-add="' + id + '"]:not([disabled])');

			if (direct) {
				direct.click();
				announce(i18n.added || '');
				return 'added';
			}

			var detail = doc.querySelector('[data-detail="' + id + '"]');
			if (detail) {
				detail.click();
				announce(i18n.choose || '');
				return 'choose';
			}

			if (entry.url) {
				window.location.href = entry.url.indexOf('?') >= 0 ? entry.url + '&add-to-cart=' + encodeURIComponent(id) : entry.url + '?add-to-cart=' + encodeURIComponent(id);
				return 'redirected';
			}

			announce(i18n.offline || '');
			return 'offline';
		}

		function addAll() {
			var pending = items().slice();
			var added = 0;
			var needsChoice = 0;

			pending.forEach(function (entry) {
				if (doc.querySelector('[data-add="' + entry.id + '"]:not([disabled])')) { added += 1; } else { needsChoice += 1; }
			});

			if (!added) {
				if (needsChoice && addOne(pending[0].id)) { announce(i18n.partial || ''); }
				return;
			}

			pending.forEach(function (entry) {
				if (doc.querySelector('[data-add="' + entry.id + '"]:not([disabled])')) {
					doc.querySelector('[data-add="' + entry.id + '"]:not([disabled])').click();
				}
			});

			announce((i18n.added || '') + (needsChoice ? ' ' + (i18n.partial || '') : ''));
		}

		if (status) { status.textContent = ''; }

		// Cards rendered by the menu script get their heart when they appear.
		doc.addEventListener('flavor:menu-ready', function () {
			if (!config.canAdd && !cfg.wishlist) { return; }
			$$('#flavor-menu-grid .flavor-food-card').forEach(function (card) {
				if ($('[data-wishlist-toggle]', card)) { return; }
				var media = $('.flavor-food-card__media', card);
				var image = $('img', media || card);
				var title = $('.flavor-food-card__title', card);
				var price = $('.flavor-food-card__price', card);
				if (!media) { return; }

				var button = doc.createElement('button');
				button.type = 'button';
				button.className = 'flavor-wish-btn flavor-wish-btn--menu';
				button.setAttribute('data-wishlist-toggle', '');
				button.setAttribute('data-wish-id', card.getAttribute('data-id') || '');
				button.setAttribute('data-wish-name', (title && title.textContent || '').trim());
				button.setAttribute('data-wish-price', (price && price.textContent || '').trim());
				button.setAttribute('data-wish-image', (image && image.getAttribute('src')) || '');
				button.setAttribute('data-wish-url', card.getAttribute('data-url') || '');
				button.setAttribute('aria-pressed', 'false');
				button.setAttribute('aria-label', 'افزودن ' + ((title && title.textContent || '').trim()) + ' به علاقه‌مندی‌ها');
				button.innerHTML = '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7 2.9c0 5-7 9.5-7 9.5Z"/></svg>';

				media.appendChild(button);
			});
			paint();
		});

		paint();
	})();

	/* ---------------------------------------------------------- mega menu */

	(function mega() {
		if (!cfg.mega || !cfg.mega.enabled) { return; }

		var nav = $('.flavor-header__nav');
		if (!nav) { return; }

		function branches() {
			return $$('[data-flavor-nav="branch"]', nav);
		}

		function close(except) {
			branches().forEach(function (link) {
				if (link === except) { return; }
				link.setAttribute('aria-expanded', 'false');
			});
		}

		branches().forEach(function (link) {
			var item = link.parentElement;
			if (!item) { return; }

			function set(state) { link.setAttribute('aria-expanded', String(state)); }

			item.addEventListener('mouseenter', function () { close(link); set(true); });
			item.addEventListener('mouseleave', function () { set(false); });
			item.addEventListener('focusin', function () { close(link); set(true); });
			item.addEventListener('focusout', function (event) {
				if (!item.contains(event.relatedTarget)) { set(false); }
			});
			link.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') { set(false); link.blur(); }
			});
			// Touch devices never fire mouseenter; the first tap opens in place.
			link.addEventListener('click', function (event) {
				if (!window.matchMedia('(hover: none)').matches) { return; }
				if (link.getAttribute('aria-expanded') === 'true') { return; }
				event.preventDefault();
				close(link);
				set(true);
			});
		});
	})();

	/* ------------------------------------------------------ sticky buy bar */

	(function buyBar() {
		var bar = $('[data-flavor-buybar]');
		if (!bar) { return; }

		var form = $('form.cart');
		var button = $('[data-flavor-buybar-submit]', bar);

		function update() {
			var anchor = form || $('#flavor-buybar');
			var offset = anchor ? anchor.getBoundingClientRect() : null;
			var nearFooter = (window.innerHeight + window.scrollY) >= (doc.body.scrollHeight - 220);
			var visible = window.scrollY > 260 && (!offset || offset.bottom < 0) && !nearFooter;

			bar.classList.toggle('is-visible', visible);
			bar.hidden = false;
		}

		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update, { passive: true });
		update();

		if (!button) { return; }

		button.addEventListener('click', function () {
			if (!form) { return; }

			var needsChoice = $$('select, input[type="radio"]', form).some(function (field) {
				return field.required && !field.value && !field.checked;
			});

			if (needsChoice) {
				form.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'center' });
				announce('برای این غذا ابتدا ترکیبات را انتخاب کنید.');
				return;
			}

			if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
		});
	})();

	/* ---------------------------------------------------------- quick view */

	(function quickView() {
		var host = $('#flavor-quickview');
		if (!host || !cfg.quickView || !cfg.quickView.enabled) { return; }

		var body = $('#flavor-qv-body');
		var cache = {};

		function render(product) {
			var price = (product.prices && product.prices.price && product.prices.currency_minor_unit !== undefined)
				? Number(product.prices.price) / Math.pow(10, Number(product.prices.currency_minor_unit || 0))
				: null;
			var image = (product.images && product.images[0] && product.images[0].thumbnail) || '';
			var description = product.short_description || product.description || '';

			body.innerHTML = '<div class="flavor-qv__layout">'
				+ '<div class="flavor-qv__media">' + (image ? '<img src="' + esc(image) + '" alt="' + esc(product.name) + '" width="700" height="700" loading="lazy" />' : '') + '</div>'
				+ '<div class="flavor-qv__copy">'
				+ '<h3 class="flavor-qv__title">' + esc(product.name) + '</h3>'
				+ (price !== null ? '<span class="flavor-qv__price">' + esc(money(Math.round(price))) + '</span>' : '')
				+ '<div class="flavor-qv__desc">' + description.replace(/<[^>]*>/g, '') + '</div>'
				+ '<div class="flavor-qv__actions">'
				+ '<a class="flavor-btn flavor-btn--primary" href="' + esc(product.permalink) + '">' + esc(t('open', 'صفحهٔ کامل محصول')) + '</a>'
				+ '</div></div></div>';
		}

		function load(id, trigger) {
			if (cache[id]) { render(cache[id]); return; }
			body.innerHTML = '<p class="flavor-qv__loading" role="status">' + esc(t('loading', 'در حال دریافت…')) + '</p>';

			var url = cfg.quickView.store + encodeURIComponent(id);
			fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (response) {
					if (!response.ok) { throw new Error('bad status'); }
					return response.json();
				})
				.then(function (product) {
					cache[id] = product;
					render(product);
				})
				.catch(function () {
					// No Store API (old WooCommerce) — fall back to the page.
					var card = trigger && trigger.closest('li') ? trigger.closest('li') : null;
					var link = card ? $('a.woocommerce-LoopProduct-link, a', card) : null;
					body.innerHTML = '<p class="flavor-qv__loading">' + esc(t('error', 'دریافت اطلاعات ناموفق بود.')) + '</p>'
						+ (link ? '<p class="flavor-qv__actions"><a class="flavor-btn flavor-btn--primary" href="' + esc(link.href) + '">' + esc(t('open', 'صفحهٔ کامل محصول')) + '</a></p>' : '');
				});
		}

		doc.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-flavor-quickview]');
			if (trigger) {
				event.preventDefault();
				trigger.setAttribute('aria-expanded', 'true');
				openDialog(host, trigger);
				load(trigger.getAttribute('data-flavor-quickview'), trigger);
				return;
			}

			if (event.target.closest('[data-quickview-close]')) {
				closeDialog(host);
				$$('[data-flavor-quickview]').forEach(function (button) { button.setAttribute('aria-expanded', 'false'); });
			}
		});
	})();

	/* ----------------------------------------------- free-delivery progress */

	(function freeShip() {
		var bar = $('[data-flavor-ship]');
		if (!bar || !cfg.freeShip || !cfg.freeShip.enabled) { return; }

		var threshold = Number(bar.getAttribute('data-flavor-ship-threshold') || (cfg.freeShip.threshold || 0));
		var factor = Number(cfg.factor || 1);
		var text = $('[data-flavor-ship-text]', bar);
		var track = $('.flavor-ship__track', bar);
		var fill = $('.flavor-ship__fill', bar);
		var d = doc;

		if (!threshold) { return; }

		// On the menu page the bar travels into the order drawer; on the cart and
		// checkout pages it is already sitting where the server put it.
		var drawer = $('#flavor-cart-review') || $('.flavor-cart__body');
		if (drawer) {
			drawer.insertBefore(bar, drawer.firstChild);
			bar.hidden = false;
		} else if (cfg.inCart) {
			bar.hidden = false;
		}

		function render(subtotal) {
			var percent = Math.max(0, Math.min(100, Math.round((subtotal / threshold) * 100)));
			var remaining = Math.max(0, threshold - subtotal);
			var display = Math.round(remaining * factor);

			if (fill) { fill.style.width = percent + '%'; }
			if (track) { track.setAttribute('aria-valuenow', String(percent)); }
			bar.classList.toggle('is-complete', remaining <= 0);

			if (text) {
				text.textContent = remaining > 0
					? (cfg.freeShip.i18n && cfg.freeShip.i18n.remaining ? cfg.freeShip.i18n.remaining.replace('%s', money(display)) : money(display) + ' دیگر تا ارسال رایگان')
					: ((cfg.freeShip.i18n && cfg.freeShip.i18n.done) || 'ارسال این سفارش رایگان است.');
			}
		}

		var cart = window.flavorCartState;
		render(cart && typeof cart.subtotal === 'number' ? cart.subtotal : 0);

		d.addEventListener('flavor:cart-updated', function (event) {
			var detail = event.detail || {};
			render(typeof detail.subtotal === 'number' ? detail.subtotal : 0);
		});
	})();

	/* ----------------------------------------------------------- countdown */

	(function countdown() {
		var timers = $$('[data-flavor-countdown]');
		if (!timers.length) { return; }

		var units = { day: 'روز', hour: 'ساعت', minute: 'دقیقه', second: 'ثانیه' };

		function pad(value) { return String(value).padStart(2, '0'); }

		function tick(element) {
			var deadline = Number(element.getAttribute('data-flavor-countdown') || 0);
			var serverNow = Number(element.getAttribute('data-flavor-countdown-now') || 0);
			var offset = serverNow ? (serverNow - Math.floor(Date.now() / 1000)) : 0;
			var left = deadline - (Math.floor(Date.now() / 1000) + offset);

			if (left <= 0) {
				element.hidden = true;
				element.dispatchEvent(new CustomEvent('flavor:countdown-ended'));
				return false;
			}

			var parts = {
				day: Math.floor(left / 86400),
				hour: Math.floor((left % 86400) / 3600),
				minute: Math.floor((left % 3600) / 60),
				second: left % 60
			};

			Object.keys(units).forEach(function (unit) {
				var node = $('[data-cd="' + unit + '"]', element);
				if (node) { node.textContent = digits(pad(parts[unit])); }
			});

			return true;
		}

		var live = timers.filter(function (element) { return tick(element); });

		if (!live.length) { return; }

		window.setInterval(function () {
			live = live.filter(function (element) { return tick(element); });
		}, 1000);
	})();

	/* ------------------------------------------------------------- consent */

	(function consent() {
		var host = $('[data-flavor-consent]');
		var key = (cfg.consent && cfg.consent.key) || 'flavorConsent';

		function activateGated() {
			$$('script[type="text/plain"][data-flavor-consent-gate]').forEach(function (old) {
				var script = doc.createElement('script');
				Array.prototype.slice.call(old.attributes).forEach(function (attribute) { script.setAttribute(attribute.name, attribute.value); });
				script.type = 'text/javascript';
				script.text = old.textContent || '';
				old.parentNode.replaceChild(script, old);
			});
		}

		var choice = store(key);

		doc.documentElement.setAttribute('data-flavor-consent', choice || 'unset');

		if (choice === 'all') { activateGated(); }

		if (!host) { return; }

		if (!choice) {
			window.setTimeout(function () { host.hidden = false; }, 900);
		}

		host.addEventListener('click', function (event) {
			var button = event.target.closest('[data-flavor-consent-choice]');
			if (!button) { return; }

			var value = button.getAttribute('data-flavor-consent-choice');
			store(key, value);
			doc.documentElement.setAttribute('data-flavor-consent', value);
			host.hidden = true;

			if (value === 'all') { activateGated(); }

			doc.dispatchEvent(new CustomEvent('flavor:consent', { detail: { choice: value } }));
			announce(value === 'all' ? 'انتخاب شما ثبت شد.' : 'فقط کوکی ضروری فعال است.');
		});
	})();

	/* --------------------------------------------------------------- popup */

	(function popup() {
		var host = $('[data-flavor-popup]');
		if (!host) { return; }

		var key = (cfg.popup && cfg.popup.key) || 'flavorPopupSeen';
		var mode = host.getAttribute('data-popup-mode') || 'exit';
		var delay = Number(host.getAttribute('data-popup-delay') || 30);

		if (store(key) || doc.documentElement.getAttribute('data-flavor-consent') === 'essential') { return; }

		var shown = false;

		function show() {
			if (shown) { return; }
			shown = true;
			host.hidden = false;
			var field = $('#flavor-popup-email', host);
			if (field) { field.focus({ preventScroll: true }); }
		}

		function remember() {
			store(key, { at: Date.now() });
		}

		var mobile = window.matchMedia && window.matchMedia('(hover: none)').matches;
		var timer = window.setTimeout(show, Math.max(5, delay) * 1000);

		if (!mobile) {
			doc.addEventListener('mouseout', function (event) {
				if (shown || event.relatedTarget || event.clientY > 0) { return; }
				window.clearTimeout(timer);
				show();
			});
		}

		host.addEventListener('click', function (event) {
			if (event.target.closest('[data-flavor-popup-close]')) {
				host.hidden = true;
				remember();
			}
		});

		doc.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !host.hidden) { host.hidden = true; remember(); }
		});

		bindSubscribe($('[data-flavor-subscribe]', host), function () {
			remember();
			window.setTimeout(function () { host.hidden = true; }, 2600);
		});
	})();

	/* ------------------------------------------------------ footer newsletter */

	(function newsletterForms() {
		// The footer builder puts the column wherever the owner asks for it, so the
		// form is bound by attribute rather than by a position in the template.
		$$('[data-flavor-subscribe]').forEach(function (form) {
			if (form.closest('[data-flavor-popup]')) { return; }
			bindSubscribe(form, null);
		});
	})();

	/* -------------------------------------------------------- menu filters */

	(function filters() {
		var wrap = $('[data-flavor-filters]');
		var grid = $('#flavor-menu-grid');
		if (!wrap || !grid) { return; }

		var count = $('[data-flavor-filter-count]', wrap);
		var sort = $('select[data-flavor-sort]', wrap);
		var order = new WeakMap();

		function cards() { return $$('.flavor-food-card', grid); }

		function index() {
			cards().forEach(function (card, position) { order.set(card, position); });
		}

		function active(attribute) {
			return $$('[data-flavor-filter="' + attribute + '"][aria-pressed="true"]', wrap).map(function (chip) {
				return chip.getAttribute('data-flavor-value');
			});
		}

		function apply() {
			var diets = active('diet');
			var avoid = active('avoid');
			var visible = 0;

			cards().forEach(function (card) {
				var own = (card.getAttribute('data-dietary') || '').split(',').filter(Boolean);
				var allergens = (card.getAttribute('data-allergens') || '').split(',').filter(Boolean);

				var keep = diets.every(function (flag) { return own.indexOf(flag) >= 0; });
				if (keep && avoid.length) {
					keep = avoid.every(function (flag) { return allergens.indexOf(flag) < 0; });
				}

				card.classList.toggle('is-filtered-out', !keep);
				if (keep) { visible += 1; }
			});

			if (count) {
				count.textContent = visible === cards().length
					? digits(String(visible)) + ' غذا'
					: digits(String(visible)) + ' از ' + digits(String(cards().length)) + ' غذا';
			}

			announce((count ? count.textContent : '') + ' نمایش داده می‌شود.');
		}

		function sortCards() {
			if (!sort) { return; }
			var mode = sort.value;
			var sorted = cards().sort(function (a, b) {
				if (mode === 'default') { return (order.get(a) || 0) - (order.get(b) || 0); }
				var left = Number(a.getAttribute('data-' + mode) || 0);
				var right = Number(b.getAttribute('data-' + mode) || 0);
				return mode === 'price-desc' ? right - left : left - right;
			});

			sorted.forEach(function (card) { grid.appendChild(card); });
		}

		wrap.addEventListener('click', function (event) {
			var chip = event.target.closest('[data-flavor-filter]');
			if (!chip) { return; }
			chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
			apply();
		});

		if (sort) {
			sort.addEventListener('change', function () {
				sortCards();
				apply();
			});
		}

		doc.addEventListener('flavor:menu-ready', function () {
			index();
			sortCards();
			apply();
		});

		index();
		apply();
	})();

	/* ------------------------------------------------------- dock + copy */

	(function dock() {
		var dock = $('[data-flavor-dock]');
		if (!dock) { return; }

		var ring = $('[data-flavor-top-ring]');
		var circumference = ring ? Number(ring.getAttribute('stroke-dasharray') || 119.38) : 0;

		function update() {
			var scrollable = doc.documentElement.scrollHeight - window.innerHeight;
			var progress = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 0;

			dock.hidden = false;
			dock.classList.toggle('is-visible', window.scrollY > 320);

			if (ring && circumference) {
				ring.setAttribute('stroke-dashoffset', String(circumference * (1 - progress)));
			}
		}

		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update, { passive: true });
		update();

		dock.addEventListener('click', function (event) {
			if (!event.target.closest('[data-flavor-top]')) { return; }
			window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
			var header = $('#flavor-site-header');
			if (header) {
				var link = $('a', header);
				if (link) { link.focus({ preventScroll: true }); }
			}
		});
	})();

	(function copy() {
		doc.addEventListener('click', function (event) {
			var button = event.target.closest('[data-flavor-copy]');
			if (!button) { return; }

			var value = button.getAttribute('data-flavor-copy') || '';
			var label = $('[data-flavor-copy-label]', button);

			function done(ok) {
				if (label) {
					var original = label.textContent;
					label.textContent = ok ? 'کپی شد' : 'کپی نشد';
					window.setTimeout(function () { label.textContent = original; }, 2200);
				}
				announce(ok ? 'نشانی کپی شد.' : 'کپی نشد؛ دستی انتخاب کنید.');
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(function () { done(true); }).catch(function () { done(false); });
			} else {
				done(false);
			}
		});
	})();
})();
