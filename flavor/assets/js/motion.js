/**
 * Flavor Motion — shared animation engine for every demo.
 *
 * Counterpart to assets/css/motion.css. The bespoke five demos keep their own
 * `demo-animations.js` for the `fd-*` landing templates; this file drives the
 * `flavor-*` vocabulary the seven classic demos use, plus every shared commerce
 * surface all twelve skins render.
 *
 * Design contract:
 *
 *   - Progressive enhancement. Nothing is hidden until this script sets
 *     `html.flavor-anim-ready`, and that class is only ever set when motion is
 *     allowed. No script, no IntersectionObserver, or `prefers-reduced-motion`
 *     all leave the page exactly as it was before this file existed.
 *   - Never own behaviour that another script owns. premium.js filters the
 *     menu, menu.js renders it, main.js runs the drawer. This script listens to
 *     the custom events they already dispatch (`flavor:menu-ready`,
 *     `flavor:cart-updated`, `flavor:dialog-opened`) and only adds choreography.
 *   - Reduced motion is re-checked live. A visitor who flips the OS setting
 *     mid-session gets the still page without a reload.
 *
 * @version 1.0.0
 * @license GPL-2.0-or-later
 */
(function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;

	/* ------------------------------------------------------------------ */
	/*  0. Capability and preference gates                                */
	/* ------------------------------------------------------------------ */

	var reduceQuery = window.matchMedia
		? window.matchMedia('(prefers-reduced-motion: reduce)')
		: { matches: false, addEventListener: null, addListener: null };

	var finePointer = window.matchMedia
		? window.matchMedia('(hover: hover) and (pointer: fine)')
		: { matches: true };

	var desktop = window.matchMedia ? window.matchMedia('(min-width: 992px)') : { matches: true };

	function reduced() {
		return !!reduceQuery.matches;
	}

	/* Every long-lived effect registers here so reduced-motion changes and
	   page-hide can tear things down instead of leaking observers. */
	var teardown = [];

	function cleanup() {
		while (teardown.length) {
			var fn = teardown.pop();
			try { fn(); } catch (error) { /* a broken effect must not break the page */ }
		}
		root.classList.remove('flavor-anim-ready');
		root.classList.remove('flavor-anim-progress');
	}

	/**
	 * Run an effect only while motion is allowed, and make it re-runnable.
	 *
	 * @param {Function} fn Initialiser; may return its own teardown function.
	 */
	function motionEffect(fn) {
		if (reduced()) { return; }
		var dispose = fn();
		if (typeof dispose === 'function') { teardown.push(dispose); }
	}

	function onReduceChange() {
		if (reduced()) {
			cleanup();
			return;
		}
		boot();
	}

	if (reduceQuery.addEventListener) {
		reduceQuery.addEventListener('change', onReduceChange);
	} else if (reduceQuery.addListener) {
		reduceQuery.addListener(onReduceChange);
	}

	window.addEventListener('pagehide', cleanup);

	var raf = window.requestAnimationFrame || function (cb) { return window.setTimeout(cb, 16); };
	var cancelRaf = window.cancelAnimationFrame || window.clearTimeout;

	function debounce(fn, ms) {
		var timer;
		return function () {
			var args = arguments;
			window.clearTimeout(timer);
			timer = window.setTimeout(function () { fn.apply(null, args); }, ms);
		};
	}

	function all(selector, context) {
		return Array.prototype.slice.call((context || doc).querySelectorAll(selector));
	}

	function one(selector, context) {
		return (context || doc).querySelector(selector);
	}

	function persianDigits(value) {
		var map = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return String(value).replace(/[0-9]/g, function (d) { return map[Number(d)]; });
	}

	/* The site is RTL and Persian, so any number this script writes into the
	   DOM has to arrive as Persian digits or it will read as a different
	   language mid-sentence. */
	function localiseNumber(value) {
		return doc.dir === 'rtl' || (doc.body && doc.body.classList.contains('flavor-theme'))
			? persianDigits(value)
			: String(value);
	}

	/* ------------------------------------------------------------------ */
	/*  1. Scroll reveal engine                                           */
	/* ------------------------------------------------------------------ */

	var REVEAL_SELECTOR = [
		'.flavor-section',
		'.flavor-about__media',
		'.flavor-about__content',
		'.flavor-about__stat',
		'.flavor-intro__card',
		'.flavor-featured__grid > *',
		'.flavor-categories__grid > *',
		'.flavor-gallery__item',
		'.flavor-testimonials__grid > *',
		'.flavor-review-card',
		'.flavor-hours-card',
		'.flavor-offer-banner',
		'.flavor-offers .flavor-food-card',
		'.flavor-res-banner__content',
		'.flavor-res-banner__media',
		'.flavor-res-cta',
		'.flavor-footer__col',
		'.flavor-contact-row',
		'.flavor-schedule-list li',
		'.flavor-food-card',
		'.flavor-cat-card',
		'.flavor-card',
		'.flavor-ui-panel',
		'.flavor-menu-masthead',
		'.flavor-tracking-result',
		'.flavor-branch-card'
	].join(', ');

	/* Containers whose children stagger instead of arriving together. */
	var STAGGER_PARENTS = [
		'.flavor-featured__grid',
		'.flavor-categories__grid',
		'.flavor-gallery__grid',
		'.flavor-testimonials__grid',
		'.flavor-intro__grid',
		'.flavor-about__stats',
		'.flavor-footer__grid',
		'.flavor-res-banner__perks',
		'.flavor-schedule-list',
		'#flavor-menu-grid'
	].join(', ');

	/* Entrance direction per element kind. Chosen so copy enters from the RTL
	   reading edge and imagery enters from the opposite side. */
	function entranceFor(el) {
		if (el.matches('.flavor-about__media, .flavor-res-banner__media, .flavor-hero__media')) { return 'end'; }
		if (el.matches('.flavor-about__content, .flavor-res-banner__content')) { return 'start'; }
		if (el.matches('.flavor-gallery__item, .flavor-cat-card')) { return 'zoom'; }
		if (el.matches('.flavor-ui-panel, .flavor-tracking-result, .flavor-hours-card')) { return 'flip'; }
		if (el.matches('.flavor-about__stat')) { return 'up'; }
		return 'up';
	}

	var revealObserver = null;
	var revealed = new WeakSet();

	function stepToken() {
		var value = getComputedStyle(root).getPropertyValue('--flavor-m-step');
		var parsed = parseFloat(value);
		return parsed > 0 ? parsed : 65;
	}

	function staggerIndex(el) {
		var parent = el.parentElement;
		if (!parent || !parent.matches(STAGGER_PARENTS)) { return 0; }
		var siblings = all('*', parent).filter(function (child) {
			return child.parentElement === parent && revealed.has(child);
		});
		var index = siblings.indexOf(el);
		return index < 0 ? 0 : index;
	}

	function reveal(el) {
		if (revealed.has(el)) { return; }
		revealed.add(el);

		var delay = Math.min(staggerIndex(el), 6) * stepToken();
		if (delay) { el.style.setProperty('--flavor-m-delay', delay + 'ms'); }

		el.classList.add('flavor-anim-in');

		/* Release the compositing hint once the element has landed. */
		var settle = function () {
			el.classList.add('flavor-anim-settled');
			el.style.removeProperty('--flavor-m-delay');
			el.removeEventListener('transitionend', settle);
		};
		el.addEventListener('transitionend', settle);
		window.setTimeout(settle, 1400);
	}

	function initReveal() {
		var targets = all(REVEAL_SELECTOR).filter(function (el) {
			/* The mobile drawer is inert and off-canvas; animating it would
			   leave items invisible the moment the drawer opens. */
			if (el.closest('.flavor-drawer, .fd-drawer, [inert], [aria-hidden="true"]')) { return false; }
			if (el.closest('#flavor-cart-panel, #flavor-sheet, .flavor-qv, .flavor-wish')) { return false; }
			return true;
		});

		if (!targets.length) { return; }

		targets.forEach(function (el) {
			if (!el.hasAttribute('data-flavor-anim')) {
				el.setAttribute('data-flavor-anim', entranceFor(el));
			}
			if (el.matches('[data-flavor-anim="mask"]') && !el.parentElement.matches('.flavor-anim-mask')) {
				el.parentElement.classList.add('flavor-anim-mask');
			}
			el.classList.add('flavor-anim-enter');
		});

		if (!('IntersectionObserver' in window)) {
			targets.forEach(reveal);
			return;
		}

		revealObserver = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				reveal(entry.target);
				revealObserver.unobserve(entry.target);
			});
		}, { threshold: 0.08, rootMargin: '0px 0px -8% 0px' });

		targets.forEach(function (el) { revealObserver.observe(el); });

		/* Failsafe: if an observer never fires (a hidden tab, a browser that
		   throttles them, a section inside a collapsed accordion) the content
		   must still appear. Four seconds is longer than any real scroll. */
		var failsafe = window.setTimeout(function () {
			if (!revealObserver) { return; }
			targets.forEach(function (el) {
				if (!revealed.has(el)) { reveal(el); }
				revealObserver.unobserve(el);
			});
		}, 4000);

		return function () {
			window.clearTimeout(failsafe);
			if (revealObserver) { revealObserver.disconnect(); revealObserver = null; }
		};
	}

	/* ------------------------------------------------------------------ */
	/*  2. Cards injected after load (assets/js/menu.js)                  */
	/* ------------------------------------------------------------------ */

	function initInjectedCards() {
		var grid = one('#flavor-menu-grid');
		if (!grid) { return; }

		function sweep() {
			var cards = all('.flavor-food-card, .flavor-card', grid).filter(function (card) {
				return !card.classList.contains('flavor-anim-injected') && !revealed.has(card);
			});

			cards.forEach(function (card, index) {
				revealed.add(card);
				card.style.setProperty('--flavor-m-delay', Math.min(index, 7) * stepToken() + 'ms');
				card.classList.add('flavor-anim-injected');
				if (revealObserver) { revealObserver.unobserve(card); }
			});

			/* Clear the inline delay after the animation so a later re-render
			   or a filter FLIP is not stuck with a stale offset. */
			window.setTimeout(function () {
				cards.forEach(function (card) { card.style.removeProperty('--flavor-m-delay'); });
			}, 900);
		}

		doc.addEventListener('flavor:menu-ready', sweep);

		/* menu.js also re-renders on search and category changes without
		   re-dispatching the event, so watch the subtree as a backstop. */
		if (!('MutationObserver' in window)) { return; }

		var observer = new MutationObserver(debounce(sweep, 60));
		observer.observe(grid, { childList: true, subtree: true });

		sweep();

		return function () {
			observer.disconnect();
			doc.removeEventListener('flavor:menu-ready', sweep);
		};
	}

	/* ------------------------------------------------------------------ */
	/*  3. Counted statistics                                             */
	/* ------------------------------------------------------------------ */

	function initCounters() {
		/* Explicit opt-in via data attribute, plus the about-section stats the
		   classic demos already render (their number lives in its own span). */
		var targets = all('[data-flavor-count]').concat(
			all('.flavor-about__stat-num').filter(function (el) { return !el.hasAttribute('data-flavor-count'); })
		);

		if (!targets.length) { return; }

		function targetOf(el) {
			if (el.hasAttribute('data-flavor-count')) {
				return parseFloat(el.getAttribute('data-flavor-count'));
			}
			/* "۱۲" or "12+" or "+۱۲" — keep any affix, animate the number. */
			var match = String(el.textContent).replace(/[۰-۹]/g, function (d) {
				return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
			}).match(/-?\d+(?:\.\d+)?/);
			return match ? parseFloat(match[0]) : NaN;
		}

		function affixes(el) {
			var text = String(el.textContent);
			var match = text.match(/-?\d+(?:\.\d+)?|[۰-۹]+(?:[٫.][۰-۹]+)?/);
			if (!match) { return { prefix: '', suffix: '' }; }
			return { prefix: text.slice(0, match.index), suffix: text.slice(match.index + match[0].length) };
		}

		function run(el) {
			var target = targetOf(el);
			if (!isFinite(target)) { return; }

			var parts = affixes(el);
			var decimals = (String(target).split('.')[1] || '').length;
			var duration = Math.min(1400, 500 + Math.abs(target) * 6);
			var start = performance.now();

			el.classList.add('flavor-anim-counting');

			function frame(now) {
				var progress = Math.min((now - start) / duration, 1);
				var eased = 1 - Math.pow(1 - progress, 3);
				var value = (target * eased).toFixed(decimals);
				el.textContent = parts.prefix + localiseNumber(value) + parts.suffix;
				if (progress < 1) {
					raf(frame);
				} else {
					el.textContent = parts.prefix + localiseNumber(target.toFixed(decimals)) + parts.suffix;
					window.setTimeout(function () { el.classList.remove('flavor-anim-counting'); }, 320);
				}
			}

			raf(frame);
		}

		if (!('IntersectionObserver' in window)) {
			targets.forEach(run);
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				run(entry.target);
				observer.unobserve(entry.target);
			});
		}, { threshold: 0.5 });

		targets.forEach(function (el) { observer.observe(el); });

		return function () { observer.disconnect(); };
	}

	/* ------------------------------------------------------------------ */
	/*  4. Pointer ripple                                                 */
	/* ------------------------------------------------------------------ */

	var RIPPLE_SELECTOR = [
		'.flavor-btn',
		'.flavor-dock__btn',
		'.flavor-filters__chip',
		'.flavor-qv-btn',
		'.flavor-wish-btn',
		'.flavor-qty button',
		'.flavor-cat-card',
		'.flavor-ui-icon-button'
	].join(', ');

	function initRipple() {
		function onClick(event) {
			/* Keyboard activation should not draw a ripple at 0,0 — there is no
			   pointer to originate it from. */
			if (event.detail === 0) { return; }

			var host = event.target.closest(RIPPLE_SELECTOR);
			if (!host || host.disabled) { return; }

			var style = getComputedStyle(host);
			if (style.position === 'static') { host.style.position = 'relative'; }
			if (style.overflow !== 'hidden') { host.style.overflow = 'hidden'; }

			var box = host.getBoundingClientRect();
			var size = Math.max(box.width, box.height) * 2.1;
			var span = doc.createElement('span');

			span.className = 'flavor-anim-ripple';
			span.setAttribute('aria-hidden', 'true');
			span.style.width = size + 'px';
			span.style.height = size + 'px';
			span.style.left = (event.clientX - box.left - size / 2) + 'px';
			span.style.top = (event.clientY - box.top - size / 2) + 'px';

			host.appendChild(span);

			var remove = function () {
				if (span.parentNode) { span.parentNode.removeChild(span); }
			};
			span.addEventListener('animationend', remove);
			window.setTimeout(remove, 900);
		}

		doc.addEventListener('click', onClick, true);
		return function () { doc.removeEventListener('click', onClick, true); };
	}

	/* ------------------------------------------------------------------ */
	/*  5. Menu filters — FLIP + resort + result-count flash              */
	/* ------------------------------------------------------------------ */

	function initFilters() {
		var wrap = one('[data-flavor-filters]');
		var grid = one('#flavor-menu-grid');
		if (!wrap || !grid) { return; }

		var count = one('[data-flavor-filter-count]', wrap);
		var sort = one('select[data-flavor-sort]', wrap);
		var before = null;

		function snapshot() {
			if (reduced()) { return; }
			before = new Map();
			all('.flavor-food-card', grid).forEach(function (card) {
				if (card.classList.contains('is-filtered-out')) { return; }
				before.set(card, card.getBoundingClientRect());
			});
		}

		function play() {
			var prior = before;
			before = null;
			if (!prior || reduced()) { return; }

			all('.flavor-food-card', grid).forEach(function (card) {
				if (card.classList.contains('is-filtered-out')) { return; }
				var first = prior.get(card);
				if (!first) { return; }

				var last = card.getBoundingClientRect();
				var dx = first.left - last.left;
				var dy = first.top - last.top;
				if (Math.abs(dx) < 1 && Math.abs(dy) < 1) { return; }

				card.classList.remove('flavor-anim-flip');
				/* Force the removal to commit so re-adding restarts the run. */
				void card.offsetWidth;
				card.style.setProperty('--flavor-m-flip-x', dx + 'px');
				card.style.setProperty('--flavor-m-flip-y', dy + 'px');
				card.classList.add('flavor-anim-flip');
			});

			if (count) {
				count.classList.remove('flavor-anim-flash');
				void count.offsetWidth;
				count.classList.add('flavor-anim-flash');
			}
		}

		function onClear(event) {
			var card = event.target.closest('.flavor-anim-flip');
			if (!card) { return; }
			card.classList.remove('flavor-anim-flip');
			card.style.removeProperty('--flavor-m-flip-x');
			card.style.removeProperty('--flavor-m-flip-y');
		}

		function onResort() {
			if (reduced()) { return; }
			grid.classList.remove('flavor-anim-resort');
			void grid.offsetWidth;
			grid.classList.add('flavor-anim-resort');
			window.setTimeout(function () { grid.classList.remove('flavor-anim-resort'); }, 620);
		}

		/* Capture phase: this runs before premium.js's own bubble listener has
		   changed any class, which is exactly when the "before" boxes must be
		   measured. The "after" boxes are read on the next frame. */
		wrap.addEventListener('click', function (event) {
			if (!event.target.closest('[data-flavor-filter]')) { return; }
			snapshot();
			raf(raf(play));
		}, true);

		if (sort) {
			sort.addEventListener('change', function () {
				onResort();
			}, true);
		}

		grid.addEventListener('animationend', onClear);

		return function () {
			grid.removeEventListener('animationend', onClear);
			grid.classList.remove('flavor-anim-resort');
		};
	}

	/* ------------------------------------------------------------------ */
	/*  6. Hero parallax                                                  */
	/* ------------------------------------------------------------------ */

	function initParallax() {
		if (!desktop.matches) { return; }

		var bg = one('.flavor-hero__bg');
		if (!bg) { return; }

		var ticking = false;

		function update() {
			ticking = false;
			var box = bg.getBoundingClientRect();
			if (box.bottom < 0 || box.top > window.innerHeight) { return; }
			var offset = (box.top + box.height / 2 - window.innerHeight / 2) * -0.08;
			/* main.css/motion.css already scale the background to 1.06; the
			   translation stays inside that margin so no edge is exposed. */
			bg.style.transform = 'scale(1.06) translate3d(0,' + offset.toFixed(2) + 'px,0)';
		}

		function onScroll() {
			if (ticking) { return; }
			ticking = true;
			raf(update);
		}

		bg.style.willChange = 'transform';
		window.addEventListener('scroll', onScroll, { passive: true });
		update();

		return function () {
			window.removeEventListener('scroll', onScroll);
			bg.style.transform = '';
			bg.style.willChange = '';
		};
	}

	/* ------------------------------------------------------------------ */
	/*  7. Pointer tilt                                                   */
	/* ------------------------------------------------------------------ */

	function initTilt() {
		if (!finePointer.matches || !desktop.matches) { return; }

		var cards = all('.flavor-food-card, .flavor-cat-card').slice(0, 24);
		if (!cards.length) { return; }

		function onMove(event) {
			var card = event.currentTarget;
			var box = card.getBoundingClientRect();
			if (!box.width || !box.height) { return; }
			var x = ((event.clientX - box.left) / box.width - 0.5) * 5;
			var y = ((event.clientY - box.top) / box.height - 0.5) * -4;
			card.style.setProperty('--flavor-m-tilt-x', x.toFixed(2));
			card.style.setProperty('--flavor-m-tilt-y', y.toFixed(2));
		}

		function onLeave(event) {
			var card = event.currentTarget;
			card.style.removeProperty('--flavor-m-tilt-x');
			card.style.removeProperty('--flavor-m-tilt-y');
		}

		cards.forEach(function (card) {
			card.classList.add('flavor-anim-tilt');
			card.addEventListener('pointermove', onMove);
			card.addEventListener('pointerleave', onLeave);
		});

		return function () {
			cards.forEach(function (card) {
				card.classList.remove('flavor-anim-tilt');
				card.removeEventListener('pointermove', onMove);
				card.removeEventListener('pointerleave', onLeave);
				onLeave({ currentTarget: card });
			});
		};
	}

	/* ------------------------------------------------------------------ */
	/*  8. Night-scheme switch wash                                       */
	/* ------------------------------------------------------------------ */

	function initSchemeWash() {
		var toggles = all('.flavor-scheme-toggle');
		if (!toggles.length) { return; }

		function onClick(event) {
			var toggle = event.target.closest('.flavor-scheme-toggle');
			if (!toggle || reduced()) { return; }

			/* Origin of the wash follows the button that was pressed, so the
			   change reads as coming from where the visitor touched. */
			var box = toggle.getBoundingClientRect();
			root.style.setProperty('--flavor-m-scheme-x', (box.left + box.width / 2) + 'px');
			root.style.setProperty('--flavor-m-scheme-y', (box.top + box.height / 2) + 'px');

			toggles.forEach(function (item) {
				item.classList.remove('flavor-anim-switching');
				void item.offsetWidth;
				item.classList.add('flavor-anim-switching');
			});

			window.setTimeout(function () {
				toggles.forEach(function (item) { item.classList.remove('flavor-anim-switching'); });
			}, 560);
		}

		doc.addEventListener('click', onClick);
		return function () { doc.removeEventListener('click', onClick); };
	}

	/* ------------------------------------------------------------------ */
	/*  9. Countdown + copy feedback                                      */
	/* ------------------------------------------------------------------ */

	function initCountdownFeedback() {
		var clocks = all('[data-flavor-countdown]');
		if (!clocks.length) { return; }

		var observers = [];

		clocks.forEach(function (clock) {
			var units = all('.flavor-countdown__unit', clock);
			if (!units.length || !('MutationObserver' in window)) { return; }

			var observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					var unit = mutation.target.closest
						? mutation.target.closest('.flavor-countdown__unit')
						: null;
					if (!unit || reduced()) { return; }
					unit.classList.remove('flavor-anim-tick');
					void unit.offsetWidth;
					unit.classList.add('flavor-anim-tick');
					window.setTimeout(function () { unit.classList.remove('flavor-anim-tick'); }, 400);
				});
			});

			units.forEach(function (unit) {
				observer.observe(unit, { childList: true, subtree: true, characterData: true });
			});
			observers.push(observer);

			/* premium.js dispatches this when an offer expires. */
			clock.addEventListener('flavor:countdown-ended', function () {
				clock.classList.remove('flavor-anim-urgent');
			});
		});

		return function () { observers.forEach(function (o) { o.disconnect(); }); };
	}

	function initCopyFeedback() {
		/* Coupon codes and popup codes are copied by premium.js; this only
		   acknowledges the gesture visually. */
		var codes = all('.flavor-offer-coupon__code, .flavor-popup__code, .flavor-promo-code');
		if (!codes.length) { return; }

		function onClick(event) {
			var code = event.target.closest('.flavor-offer-coupon__code, .flavor-popup__code, .flavor-promo-code');
			if (!code || reduced()) { return; }
			code.classList.remove('flavor-anim-copied');
			void code.offsetWidth;
			code.classList.add('flavor-anim-copied');
			window.setTimeout(function () { code.classList.remove('flavor-anim-copied'); }, 480);
		}

		doc.addEventListener('click', onClick);
		return function () { doc.removeEventListener('click', onClick); };
	}

	/* ------------------------------------------------------------------ */
	/*  10. Cart / wishlist count bump                                    */
	/* ------------------------------------------------------------------ */

	function initCountBump() {
		var badges = all('[data-ui-cart-count], .flavor-cart__count, .flavor-wish-count, .flavor-mobile-nav__amount');
		if (!badges.length) { return; }

		function bump(el) {
			if (reduced()) { return; }
			el.classList.remove('flavor-anim-bump');
			void el.offsetWidth;
			el.classList.add('flavor-anim-bump');
			window.setTimeout(function () { el.classList.remove('flavor-anim-bump'); }, 520);
		}

		function onCart(event) {
			badges.forEach(bump);
			/* menu.js hands the new count over; nothing to read back. */
			void event;
		}

		var observer = null;
		if ('MutationObserver' in window) {
			/* Wishlist and mobile-nav badges have no event, so watch the text. */
			observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) { bump(mutation.target); });
			});
			badges.forEach(function (badge) {
				observer.observe(badge, { childList: true, characterData: true, subtree: true });
			});
		}

		doc.addEventListener('flavor:cart-updated', onCart);

		return function () {
			doc.removeEventListener('flavor:cart-updated', onCart);
			if (observer) { observer.disconnect(); }
		};
	}

	/* ------------------------------------------------------------------ */
	/*  11. Cart lines, dialogs and toasts                                */
	/* ------------------------------------------------------------------ */

	function initDialogChoreography() {
		function staggerLines(host) {
			if (reduced()) { return; }
			all('.flavor-cart-line', host).forEach(function (line, index) {
				line.style.setProperty('--flavor-m-delay', Math.min(index, 8) * 40 + 'ms');
			});
		}

		function onOpened(event) {
			staggerLines(event.target);
		}

		function onClosed(event) {
			all('.flavor-cart-line', event.target).forEach(function (line) {
				line.style.removeProperty('--flavor-m-delay');
			});
		}

		doc.addEventListener('flavor:dialog-opened', onOpened);
		doc.addEventListener('flavor:dialog-closed', onClosed);

		/* The cart panel re-renders its lines on every mutation, outside any
		   dialog event, so the stagger has to be reapplied. */
		var panel = one('#flavor-cart-panel');
		var observer = null;
		if (panel && 'MutationObserver' in window) {
			observer = new MutationObserver(debounce(function () { staggerLines(panel); }, 50));
			observer.observe(panel, { childList: true, subtree: true });
		}

		return function () {
			doc.removeEventListener('flavor:dialog-opened', onOpened);
			doc.removeEventListener('flavor:dialog-closed', onClosed);
			if (observer) { observer.disconnect(); }
		};
	}

	function initToastExit() {
		/* main.js removes a toast after 4500ms with no exit, so the message
		   disappears mid-read. Fade it out first, then let main.js collect it. */
		var original = window.flavorToast;
		if (typeof original !== 'function') { return; }

		window.flavorToast = function (message, type) {
			original(message, type);
			if (reduced()) { return; }

			var container = one('#flavor-toast-container');
			if (!container) { return; }
			var toast = container.lastElementChild;
			if (!toast) { return; }

			window.setTimeout(function () {
				toast.classList.add('flavor-anim-out');
			}, 4000);
		};
	}

	/* ------------------------------------------------------------------ */
	/*  12. Free-delivery threshold                                       */
	/* ------------------------------------------------------------------ */

	function initShippingCelebration() {
		var fill = one('.flavor-ship__fill');
		if (!fill || !('MutationObserver' in window)) { return; }

		var celebrated = false;

		var observer = new MutationObserver(function () {
			var width = parseFloat(fill.style.width || '0');
			if (width >= 100 && !celebrated) {
				celebrated = true;
				if (!reduced()) {
					fill.classList.add('flavor-anim-complete');
					window.setTimeout(function () { fill.classList.remove('flavor-anim-complete'); }, 820);
				}
			} else if (width < 100) {
				celebrated = false;
			}
		});

		observer.observe(fill, { attributes: true, attributeFilter: ['style', 'class'] });

		return function () { observer.disconnect(); };
	}

	/* ------------------------------------------------------------------ */
	/*  13. Schedule: mark today                                          */
	/* ------------------------------------------------------------------ */

	function initTodayMarker() {
		var rows = all('.flavor-schedule-list li');
		if (!rows.length) { return; }

		/* Rows are Sat→Fri in the Iranian week; the template does not mark the
		   current day, so derive it from the row count and today's weekday. */
		var today = new Date().getDay(); // 0 = Sunday
		var iranian = (today + 1) % 7;    // 0 = Saturday
		if (rows.length === 7 && rows[iranian]) {
			rows[iranian].classList.add('flavor-anim-today');
		}
	}

	/* ------------------------------------------------------------------ */
	/*  14. Marquee                                                       */
	/* ------------------------------------------------------------------ */

	function initMarquee() {
		var hosts = all('.flavor-anim-marquee');
		if (!hosts.length) { return; }

		function size() {
			hosts.forEach(function (host) {
				var span = one('span', host);
				if (!span) { return; }
				/* Longer text needs more time, not more speed. */
				var seconds = Math.max(12, Math.round(span.scrollWidth / 42));
				host.style.setProperty('--flavor-m-marquee-duration', seconds + 's');
			});
		}

		size();
		var onResize = debounce(size, 200);
		window.addEventListener('resize', onResize);
		return function () { window.removeEventListener('resize', onResize); };
	}

	/* ------------------------------------------------------------------ */
	/*  15. Scroll progress hairline (opt-in per skin)                    */
	/* ------------------------------------------------------------------ */

	function initProgress() {
		/* A skin opts in by setting --flavor-m-progress-opt-in: 1. Reading the
		   token keeps the decision in CSS, where the art direction lives. */
		var optedIn = getComputedStyle(root).getPropertyValue('--flavor-m-progress-opt-in').trim() === '1';
		if (!optedIn) { return; }

		root.classList.add('flavor-anim-progress');
		var ticking = false;

		function update() {
			ticking = false;
			var scrollable = doc.documentElement.scrollHeight - window.innerHeight;
			var progress = scrollable > 0 ? Math.min(1, window.scrollY / scrollable) : 0;
			root.style.setProperty('--flavor-m-progress', progress.toFixed(4));
		}

		function onScroll() {
			if (ticking) { return; }
			ticking = true;
			raf(update);
		}

		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', debounce(update, 150));
		update();

		return function () {
			window.removeEventListener('scroll', onScroll);
			root.style.removeProperty('--flavor-m-progress');
		};
	}

	/* ------------------------------------------------------------------ */
	/*  16. In-page anchors                                               */
	/* ------------------------------------------------------------------ */

	function initAnchors() {
		/* The five bespoke demos already own this behaviour in
		   demo-animations.js, which understands their `.fd-header` offset. Two
		   handlers on one click would scroll twice and fight over focus. */
		if (doc.body && doc.body.classList.contains('flavor-bespoke')) { return; }

		function onClick(event) {
			if (event.defaultPrevented || event.button !== 0) { return; }
			if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }

			var link = event.target.closest('a[href^="#"]');
			if (!link) { return; }

			var hash = link.getAttribute('href');
			if (!hash || hash === '#' || hash.length < 2) { return; }

			/* Let real navigation win: a different document, a download, or a
			   control that already owns its click (cart, drawer, filters). */
			if (link.target && link.target !== '_self') { return; }
			if (link.closest('[data-flavor-filters], #flavor-cart-panel, .flavor-drawer, .fd-drawer')) { return; }
			if (link.closest('button, [role="tab"], [data-ui-view]')) { return; }

			var target = doc.getElementById(hash.slice(1));
			if (!target) { return; }

			event.preventDefault();

			var top = target.getBoundingClientRect().top + window.scrollY;
			var header = one('#flavor-site-header, .fd-header');
			if (header) { top -= header.offsetHeight + 12; }

			window.scrollTo({ top: Math.max(0, top), behavior: reduced() ? 'auto' : 'smooth' });

			/* Announce the move for assistive tech without stealing focus from
			   the link the visitor actually activated. */
			target.setAttribute('tabindex', '-1');
			window.setTimeout(function () {
				try { target.focus({ preventScroll: true }); } catch (error) { target.focus(); }
			}, reduced() ? 0 : 480);

			/* replaceState, not pushState: an in-page jump should not add a
			   history entry the visitor then has to click Back through. */
			if (window.history && history.replaceState) { history.replaceState(null, '', hash); }
		}

		doc.addEventListener('click', onClick);
		return function () { doc.removeEventListener('click', onClick); };
	}

	/* ------------------------------------------------------------------ */
	/*  Boot                                                              */
	/* ------------------------------------------------------------------ */

	var booted = false;

	function boot() {
		if (reduced()) {
			cleanup();
			return;
		}

		/* Announce that motion is live. CSS hides nothing until this exists. */
		root.classList.add('flavor-anim-ready');

		motionEffect(initReveal);
		motionEffect(initInjectedCards);
		motionEffect(initCounters);
		motionEffect(initRipple);
		motionEffect(initFilters);
		motionEffect(initParallax);
		motionEffect(initTilt);
		motionEffect(initSchemeWash);
		motionEffect(initCountdownFeedback);
		motionEffect(initCopyFeedback);
		motionEffect(initCountBump);
		motionEffect(initDialogChoreography);
		motionEffect(initShippingCelebration);
		motionEffect(initMarquee);
		motionEffect(initProgress);
		motionEffect(initAnchors);

		/* These two only add classes/tokens; they are safe either way. */
		initToastExit();
		initTodayMarker();

		booted = true;
		doc.dispatchEvent(new CustomEvent('flavor:motion:ready', { detail: { reduced: false } }));
	}

	function start() {
		if (booted) { return; }
		boot();
	}

	if (doc.readyState === 'loading') {
		doc.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}

	/* Late renders (menu.js resolves after the REST call) need a second sweep. */
	doc.addEventListener('flavor:menu-ready', function () {
		if (!booted || reduced()) { return; }
		motionEffect(initInjectedCards);
	});

	/* Public API for skins, child themes and the Customizer preview. */
	window.FlavorMotion = {
		version: '1.0.0',
		reduced: reduced,
		ready: function () { return booted && !reduced(); },
		reveal: reveal,
		scan: function () {
			if (reduced()) { return 0; }
			root.classList.add('flavor-anim-ready');
			var found = all(REVEAL_SELECTOR).filter(function (el) {
				return !revealed.has(el) && !el.classList.contains('flavor-anim-enter');
			});
			found.forEach(function (el) {
				el.setAttribute('data-flavor-anim', entranceFor(el));
				el.classList.add('flavor-anim-enter');
				if (revealObserver) { revealObserver.observe(el); } else { reveal(el); }
			});
			return found.length;
		},
		digits: persianDigits,
		destroy: cleanup,
		restart: function () { cleanup(); booted = false; start(); }
	};

})();
