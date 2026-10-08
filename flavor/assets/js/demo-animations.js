/**
 * Flavor Demo Animations — scroll-driven entrance, parallax, ripple,
 * counter, stagger and reduced-motion aware.
 * Zero dependencies; enqueued only on bespoke demo pages.
 *
 * 1.1.0 fixes four defects found while harmonising the layer with the motion
 * work that landed after it:
 *
 *   - Parallax and tilt both assigned `transform` on the same hero image, so
 *     they cancelled each other out. Both now write custom properties that
 *     demo-animations.css composes into one transform.
 *   - The anchor handler claimed every `a[href^="#"]` on the document and
 *     pushed a history entry per click, which broke in-panel links (cart,
 *     filters, drawer) and left visitors clicking Back through jumps.
 *   - `initCounters` and `initFilterTransitions` constructed an
 *     IntersectionObserver / MutationObserver without the capability check
 *     `initScrollAnimations` already had.
 *   - Reduced motion was read once at load. Flipping the OS setting now takes
 *     effect without a reload, and `html.fd-anim-ready` is only set while
 *     motion is allowed, which is what keeps the image fade from hiding
 *     photography (see the note at the top of demo-animations.css).
 *
 * @version 1.1.0
 * @license GPL-2.0-or-later
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/*  Guard: only run on bespoke demo pages when JS is available.       */
	/* ------------------------------------------------------------------ */
	if (!document.body.classList.contains('flavor-bespoke')) return;

	var root = document.documentElement;
	var reduceQuery = window.matchMedia
		? window.matchMedia('(prefers-reduced-motion: reduce)')
		: { matches: false, addEventListener: null, addListener: null };

	function reduced() {
		return !!reduceQuery.matches;
	}

	/* Everything the boot registers, so a mid-session preference change can be
	   undone instead of leaving hidden content behind. */
	var disposers = [];
	var booted = false;

	function teardown() {
		while (disposers.length) {
			var fn = disposers.pop();
			try { fn(); } catch (error) { /* one broken effect must not break the page */ }
		}
		root.classList.remove('fd-anim-ready');
		revealedAll();
	}

	/* If motion is turned off after the page hid things, everything hidden by
	   the entrance engine has to be shown again immediately. */
	function revealedAll() {
		var hidden = document.querySelectorAll('.fd-anim-enter:not(.fd-anim-visible)');
		for (var i = 0; i < hidden.length; i++) { hidden[i].classList.add('fd-anim-visible'); }
	}

	function effect(fn) {
		if (reduced()) return;
		var dispose = fn();
		if (typeof dispose === 'function') disposers.push(dispose);
	}

	function onReduceChange() {
		if (reduced()) { teardown(); return; }
		boot();
	}

	if (reduceQuery.addEventListener) {
		reduceQuery.addEventListener('change', onReduceChange);
	} else if (reduceQuery.addListener) {
		reduceQuery.addListener(onReduceChange);
	}

	if (reduced()) {
		/* Still expose the API so skins can query it, but run no effects and
		   never set fd-anim-ready — that class is what gates the image fade. */
		window.FlavorDemoAnimations = { version: '1.1.0', reduced: reduced, ready: function () { return false; } };
		return;
	}

	/* ------------------------------------------------------------------ */
	/*  Utility helpers                                                   */
	/* ------------------------------------------------------------------ */
	var raf = window.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
	/* The rAF fallback returns a timer id, so the matching cancel has to follow
	   it — calling cancelAnimationFrame on a setTimeout handle is a no-op and
	   leaked a pending frame on every pointer move in browsers without rAF. */
	var cancelRaf = window.cancelAnimationFrame || window.clearTimeout;

	function debounce(fn, ms) {
		var timer;
		return function () {
			clearTimeout(timer);
			timer = setTimeout(fn, ms);
		};
	}

	/* True when the pointer can hover and the viewport is desktop-wide. Both
	   tilt and cursor glow are hover-only affordances; a thumb cannot trigger
	   them and they would only cost frames. */
	function fineDesktop() {
		var fine = window.matchMedia
			? window.matchMedia('(hover: hover) and (pointer: fine)').matches
			: true;
		var wide = window.matchMedia ? window.matchMedia('(min-width: 992px)').matches : true;
		return fine && wide;
	}

	/* ------------------------------------------------------------------ */
	/*  1. Scroll-triggered entrance animations  (IntersectionObserver)   */
	/* ------------------------------------------------------------------ */
	var ANIM_SELECTOR = [
		'.fd-section',
		'.fd-hero__copy',
		'.fd-hero__art',
		'.fd-product',
		'.fd-process__steps li',
		'.fd-faq details',
		'.fd-story__grid > *',
		'.fd-visit__card',
		'.fd-footer__grid > div',
		'.fd-freshness__item',
		'.fd-juice-feature__card',
		'.fd-noir-experience__grid > *',
		'.fd-noir-booking',
		'.fd-pack-team__card',
		'.fd-coverage__card',
		'.fd-mizan-service',
		'.fd-mizan-proposal__panel',
		'.fd-form-experience',
		'.fd-form-hero__art'
	].join(', ');

	var ENTER_CLASS  = 'fd-anim-enter';
	var VISIBLE_CLASS = 'fd-anim-visible';

	function initScrollAnimations() {
		var targets = document.querySelectorAll(ANIM_SELECTOR);
		if (!targets.length) return;

		/* Tag each element so CSS can set initial hidden state */
		for (var i = 0; i < targets.length; i++) {
			targets[i].classList.add(ENTER_CLASS);
		}

		/* Use IntersectionObserver when available */
		if ('IntersectionObserver' in window) {
			var observer = new IntersectionObserver(function (entries) {
				for (var j = 0; j < entries.length; j++) {
					if (entries[j].isIntersecting) {
						var el = entries[j].target;
						/* Stagger children inside grids */
						var delay = getStaggerDelay(el);
						if (delay) el.style.transitionDelay = delay + 'ms';
						el.classList.add(VISIBLE_CLASS);
						observer.unobserve(el);
						/* Release the compositing hint once the element landed;
						   transitionDelay has to go too, or a later re-reveal
						   (a filter, a tab switch) inherits a stale offset. */
						window.setTimeout(function (node) {
							return function () {
								node.style.transitionDelay = '';
								node.style.willChange = 'auto';
							};
						}(el), 1200);
					}
				}
			}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

			for (var k = 0; k < targets.length; k++) {
				observer.observe(targets[k]);
			}

			return function () {
				observer.disconnect();
				revealedAll();
			};
		}

		/* Fallback: reveal everything immediately */
		for (var m = 0; m < targets.length; m++) {
			targets[m].classList.add(VISIBLE_CLASS);
		}
	}

	/**
	 * Calculate a stagger delay based on sibling index inside a grid parent.
	 */
	function getStaggerDelay(el) {
		var parent = el.parentElement;
		if (!parent) return 0;
		var siblings = Array.from(parent.children).filter(function (c) {
			return c.classList.contains(ENTER_CLASS) || c.classList.contains(VISIBLE_CLASS);
		});
		var idx = siblings.indexOf(el);
		if (idx < 1) return 0;
		/* Cap at 6 items so total delay ≤ 360ms */
		return Math.min(idx, 5) * 60;
	}

	/* ------------------------------------------------------------------ */
	/*  2. Parallax hero image on scroll                                  */
	/*                                                                     */
	/*  Writes --fd-anim-parallax rather than `transform` so the tilt in    */
	/*  section 6 can coexist with it; demo-animations.css composes both.  */
	/* ------------------------------------------------------------------ */
	var parallaxTarget = null;
	var parallaxSpeed  = 0.15;
	var parallaxFrame  = null;

	function initParallax() {
		parallaxTarget = document.querySelector('.fd-hero__image');
		if (!parallaxTarget) return;
		/* `will-change` lives in CSS now, next to the compositor that needs it. */
		onScrollParallax();
	}

	function onScrollParallax() {
		if (!parallaxTarget || reduced()) return;
		var rect = parallaxTarget.getBoundingClientRect();
		var vh   = window.innerHeight;
		if (rect.bottom < 0 || rect.top > vh) return;
		var offset = (rect.top - vh / 2) * parallaxSpeed;
		parallaxTarget.style.setProperty('--fd-anim-parallax', offset.toFixed(1) + 'px');
		/* The zoom used to be baked into the same transform string; it is now a
		   separate property so a skin can restate it without touching JS. */
		parallaxTarget.style.setProperty('--fd-anim-zoom', '1.02');
	}

	/* ------------------------------------------------------------------ */
	/*  3. Ripple effect on primary buttons                               */
	/* ------------------------------------------------------------------ */
	function initRipple() {
		function onClick(event) {
			/* detail 0 is a keyboard activation: there is no pointer to
			   originate a ripple from, so drawing one at 0,0 is just noise. */
			if (event.detail === 0 || reduced()) return;

			var btn = event.target.closest('.fd-button--primary, .fd-product__order');
			if (!btn) return;

			var rect = btn.getBoundingClientRect();
			var x    = event.clientX - rect.left;
			var y    = event.clientY - rect.top;
			var size = Math.max(rect.width, rect.height) * 2;

			var ripple = document.createElement('span');
			ripple.className = 'fd-anim-ripple';
			ripple.setAttribute('aria-hidden', 'true');
			ripple.style.width  = size + 'px';
			ripple.style.height = size + 'px';
			ripple.style.left   = (x - size / 2) + 'px';
			ripple.style.top    = (y - size / 2) + 'px';
			btn.appendChild(ripple);

			var remove = function () { if (ripple.parentNode) ripple.parentNode.removeChild(ripple); };
			ripple.addEventListener('animationend', remove);
			/* Belt and braces: if the animation never runs (a stylesheet that
			   failed to load, a browser that cancels it) the span would stay in
			   the button forever, one per click. */
			window.setTimeout(remove, 1000);
		}

		document.addEventListener('click', onClick);
		return function () { document.removeEventListener('click', onClick); };
	}

	/* ------------------------------------------------------------------ */
	/*  4. Animated number counters (data-fd-count attribute)             */
	/* ------------------------------------------------------------------ */
	function initCounters() {
		var counters = document.querySelectorAll('[data-fd-count]');
		if (!counters.length) return;

		/* Section 1 checks for IntersectionObserver before using it; this one
		   did not, so a browser without it threw here and took the rest of the
		   boot down with it. Same fallback: show the final number. */
		if (!('IntersectionObserver' in window)) {
			for (var f = 0; f < counters.length; f++) { finishCounter(counters[f]); }
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (!entries[i].isIntersecting) continue;
				animateCounter(entries[i].target);
				observer.unobserve(entries[i].target);
			}
		}, { threshold: 0.4 });

		for (var j = 0; j < counters.length; j++) {
			observer.observe(counters[j]);
		}

		return function () { observer.disconnect(); };
	}

	/**
	 * Write the final value with no animation, for the no-observer path and for
	 * a reduced-motion preference that arrives mid-count.
	 */
	function finishCounter(el) {
		var target = parseInt(el.getAttribute('data-fd-count'), 10);
		if (isNaN(target)) return;
		el.textContent = toPersianDigits(String(target));
	}

	function animateCounter(el) {
		var target = parseInt(el.getAttribute('data-fd-count'), 10);
		if (isNaN(target)) return;

		/* A reduced-motion preference can arrive after the count started; land
		   on the final number instead of finishing an animation nobody wants. */
		if (reduced()) { finishCounter(el); return; }

		var duration = 1200;
		var now      = window.performance && performance.now ? performance.now() : Date.now();
		var start    = now;
		var initial  = 0;

		function step(timestamp) {
			if (reduced()) { finishCounter(el); return; }
			var current  = timestamp || (window.performance && performance.now ? performance.now() : Date.now());
			var progress = Math.min((current - start) / duration, 1);
			/* ease-out cubic */
			var eased = 1 - Math.pow(1 - progress, 3);
			var value = Math.round(initial + (target - initial) * eased);
			el.textContent = toPersianDigits(String(value));
			if (progress < 1) raf(step);
		}
		raf(step);
	}

	function toPersianDigits(str) {
		return str.replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
	}

	/* ------------------------------------------------------------------ */
	/*  5. Smooth filter transition for menu section                      */
	/* ------------------------------------------------------------------ */
	function initFilterTransitions() {
		var filters = document.querySelector('[data-fd-filters]');
		if (!filters) return;
		var section = filters.closest('.fd-menu');
		if (!section) return;
		if (!('MutationObserver' in window)) return;

		/* Listen for hidden changes on cards */
		var cards = Array.prototype.slice.call(section.querySelectorAll('[data-fd-categories]'));
		if (!cards.length) return;

		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var target = mutations[i].target;
				if (mutations[i].attributeName === 'hidden') {
					if (target.hidden) {
						target.classList.add('fd-anim-filter-hide');
						target.classList.remove('fd-anim-filter-show');
					} else {
						target.classList.remove('fd-anim-filter-hide');
						target.classList.add('fd-anim-filter-show');
					}
				}
			}
		});

		for (var j = 0; j < cards.length; j++) {
			observer.observe(cards[j], { attributes: true, attributeFilter: ['hidden'] });
		}

		return function () { observer.disconnect(); };
	}

	/* ------------------------------------------------------------------ */
	/*  6. Magnetic tilt on hero image (desktop only)                     */
	/* ------------------------------------------------------------------ */
	var tiltTarget = null;
	var tiltFrame  = null;

	function initTilt() {
		if (!fineDesktop()) return;
		tiltTarget = document.querySelector('.fd-hero__art');
		if (!tiltTarget) return;
		/* Pointer events rather than mouse events: a pen or a touch that can
		   hover (Surface, iPad with trackpad) gets the same affordance, and a
		   plain tap still fires pointerleave so nothing is left tilted. */
		tiltTarget.addEventListener('pointermove', onTiltMove);
		tiltTarget.addEventListener('pointerleave', onTiltLeave);
		tiltTarget.addEventListener('pointercancel', onTiltLeave);

		return function () {
			if (!tiltTarget) return;
			tiltTarget.removeEventListener('pointermove', onTiltMove);
			tiltTarget.removeEventListener('pointerleave', onTiltLeave);
			tiltTarget.removeEventListener('pointercancel', onTiltLeave);
			var img = tiltTarget.querySelector('.fd-hero__image');
			if (img) {
				img.style.removeProperty('--fd-anim-tilt-x');
				img.style.removeProperty('--fd-anim-tilt-y');
				img.classList.remove('fd-anim-tilt-return');
			}
			tiltTarget = null;
		};
	}

	function onTiltMove(event) {
		if (!tiltTarget || reduced()) return;
		if (tiltFrame) cancelRaf(tiltFrame);
		/* Captured per event: the rAF callback runs after the pointer moved on. */
		var clientX = event.clientX;
		var clientY = event.clientY;
		tiltFrame = raf(function () {
			tiltFrame = null;
			if (!tiltTarget) return;
			var rect = tiltTarget.getBoundingClientRect();
			if (!rect.width || !rect.height) return;
			var x   = (clientX - rect.left) / rect.width  - 0.5;
			var y   = (clientY - rect.top)  / rect.height - 0.5;
			var img = tiltTarget.querySelector('.fd-hero__image');
			if (!img) return;
			/* Custom properties, not `transform`: section 2 composes these with
			   the parallax offset in CSS, so the two effects no longer fight. */
			img.style.setProperty('--fd-anim-tilt-y', (x * 6).toFixed(2) + 'deg');
			img.style.setProperty('--fd-anim-tilt-x', (-y * 6).toFixed(2) + 'deg');
			img.style.setProperty('--fd-anim-zoom', '1.02');
			img.classList.remove('fd-anim-tilt-return', 'fd-anim-settled');
		});
	}

	function onTiltLeave() {
		if (!tiltTarget) return;
		if (tiltFrame) { cancelRaf(tiltFrame); tiltFrame = null; }
		var img = tiltTarget.querySelector('.fd-hero__image');
		if (!img) return;
		img.style.setProperty('--fd-anim-tilt-x', '0deg');
		img.style.setProperty('--fd-anim-tilt-y', '0deg');
		img.classList.add('fd-anim-tilt-return');
		window.setTimeout(function () {
			img.classList.remove('fd-anim-tilt-return');
			/* Nothing is tracking the pointer any more, so the layer can go. */
			img.classList.add('fd-anim-settled');
		}, 560);
	}

	/* ------------------------------------------------------------------ */
	/*  7. Typewriter effect for hero h1 em elements                      */
	/* ------------------------------------------------------------------ */
	function initTypewriter() {
		var ems = document.querySelectorAll('.fd-hero h1 em');
		if (!ems.length) return;

		/* Capability check, matching section 1: without it this threw on older
		   browsers and aborted everything the boot had left to do. */
		if (!('IntersectionObserver' in window)) return;

		var observer = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (!entries[i].isIntersecting) continue;
				typewrite(entries[i].target);
				observer.unobserve(entries[i].target);
			}
		}, { threshold: 0.5 });

		for (var j = 0; j < ems.length; j++) {
			observer.observe(ems[j]);
		}

		return function () {
			observer.disconnect();
			restoreTypewriters();
		};
	}

	/* Every element mid-type, so an interruption can put the real words back. */
	var typing = [];

	/**
	 * Restore the full heading text on anything currently being typed.
	 *
	 * The effect empties the h1's `em` and rebuilds it a character at a time.
	 * If that is interrupted — a reduced-motion preference flipped mid-type, a
	 * teardown, a pagehide — the site's main heading would otherwise stay
	 * truncated, which is a content bug, not a cosmetic one.
	 */
	function restoreTypewriters() {
		while (typing.length) {
			var entry = typing.pop();
			entry.el.textContent = entry.text;
			entry.el.style.borderInlineEnd = '';
			entry.el.classList.remove('fd-anim-typewriter');
		}
	}

	function typewrite(el) {
		if (reduced()) return;

		var text = el.textContent.trim();
		if (!text) return;

		/* Iterate code points, not UTF-16 units: Persian copy regularly carries
		   ZWNJ and the occasional emoji, and slicing those in half renders a
		   stray replacement glyph in the middle of the headline. */
		var chars = Array.from ? Array.from(text) : text.split('');

		el.textContent = '';
		el.style.borderInlineEnd = '2px solid currentColor';
		el.classList.add('fd-anim-typewriter');

		var entry = { el: el, text: text };
		typing.push(entry);

		var i = 0;
		function tick() {
			/* Stop and restore rather than finishing an unwanted animation. */
			if (reduced()) { restoreOne(entry); return; }
			if (i < chars.length) {
				el.textContent += chars[i];
				i++;
				setTimeout(tick, 55 + Math.random() * 35);
			} else {
				/* Blink the cursor twice, then hand the element back clean. */
				setTimeout(function () { restoreOne(entry); }, 600);
			}
		}
		setTimeout(tick, 300);
	}

	function restoreOne(entry) {
		var index = typing.indexOf(entry);
		if (index >= 0) typing.splice(index, 1);
		entry.el.textContent = entry.text;
		entry.el.style.borderInlineEnd = '';
		entry.el.classList.remove('fd-anim-typewriter');
	}

	/* ------------------------------------------------------------------ */
	/*  8. Floating decorative shapes (CSS-only particles)                */
	/* ------------------------------------------------------------------ */
	function initFloatingShapes() {
		var hero = document.querySelector('.fd-hero');
		if (!hero) return;
		if (window.matchMedia('(max-width: 700px)').matches) return;

		var container = document.createElement('div');
		container.className = 'fd-anim-shapes';
		container.setAttribute('aria-hidden', 'true');
		var shapes = ['circle', 'square', 'triangle'];
		for (var i = 0; i < 5; i++) {
			var shape = document.createElement('span');
			shape.className = 'fd-anim-shape fd-anim-shape--' + shapes[i % shapes.length];
			shape.style.setProperty('--fd-shape-delay', (i * 1.2) + 's');
			shape.style.setProperty('--fd-shape-x', (15 + Math.random() * 70) + '%');
			shape.style.setProperty('--fd-shape-size', (8 + Math.random() * 14) + 'px');
			container.appendChild(shape);
		}
		hero.appendChild(container);

		/* These are five infinite animations. If motion gets switched off they
		   have to come out of the DOM, not just be told to stop: a paused
		   particle layer still costs a compositing layer for the whole page. */
		return function () {
			if (container.parentNode) container.parentNode.removeChild(container);
		};
	}

	/* ------------------------------------------------------------------ */
	/*  9. Smooth scroll-to for anchor links                              */
	/* ------------------------------------------------------------------ */
	function initSmoothAnchors() {
		function onClick(event) {
			/* Only a plain primary click. Modifier-clicks mean "open this
			   elsewhere" and must reach the browser untouched. */
			if (event.defaultPrevented || event.button !== 0) return;
			if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

			var link = event.target.closest('a[href^="#"]');
			if (!link) return;

			var id = link.getAttribute('href');
			if (!id || id.length < 2) return;

			/* Let controls that already own their click keep it. Claiming the
			   whole document is what made the cart panel, the mobile drawer and
			   the menu filters scroll the page instead of doing their job. */
			if (link.target && link.target !== '_self') return;
			if (link.closest('.fd-drawer, .flavor-drawer, .fd-cart, #flavor-cart-panel, [data-fd-filters], [data-flavor-filters]')) return;

			/* getElementById, not querySelector: a slug beginning with a digit
			   ("#2024-menu") is an invalid selector and threw here. */
			var target = document.getElementById(id.slice(1));
			if (!target) return;

			event.preventDefault();

			var top = target.getBoundingClientRect().top + window.scrollY;
			var header = document.querySelector('.fd-header');
			if (header) top -= header.offsetHeight + 12;

			window.scrollTo({ top: Math.max(0, top), behavior: reduced() ? 'auto' : 'smooth' });

			/* Move focus so a screen reader follows the jump, without stealing
			   the activation from the link the visitor actually pressed. */
			if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
			window.setTimeout(function () {
				try { target.focus({ preventScroll: true }); } catch (error) { target.focus(); }
			}, reduced() ? 0 : 520);

			/* replaceState: an in-page jump is not a navigation, so it must not
			   add a history entry the visitor then has to click Back through. */
			if (window.history && history.replaceState) history.replaceState(null, '', id);
		}

		document.addEventListener('click', onClick);
		return function () { document.removeEventListener('click', onClick); };
	}

	/* ------------------------------------------------------------------ */
	/*  10. Header hide/show on scroll direction                          */
	/* ------------------------------------------------------------------ */
	function initSmartHeader() {
		var header = document.getElementById('flavor-site-header');
		if (!header) return;
		var lastY = window.scrollY;
		var threshold = 80;

		/**
		 * True while a modal surface owns the viewport.
		 *
		 * Sliding the header away while the cart, the product sheet or the
		 * mobile drawer is open strands the visitor: the header carries the
		 * close control's visual anchor and the cart count, and a dialog is
		 * exactly the moment those should stay put.
		 */
		function modalOpen() {
			return !!document.querySelector(
				'.flavor-drawer.is-active, .fd-drawer.is-active, ' +
				'#flavor-cart-panel:not([hidden]), #flavor-sheet:not([hidden]), ' +
				'.flavor-qv:not([hidden]), .flavor-wish:not([hidden]), ' +
				'[role="dialog"]:not([hidden]):not([inert])'
			);
		}

		function onScroll() {
			var currentY = window.scrollY;

			if (reduced() || modalOpen()) {
				header.classList.remove('fd-header-hidden');
				lastY = currentY;
				return;
			}

			if (currentY > threshold) {
				if (currentY > lastY + 5) {
					header.classList.add('fd-header-hidden');
					header.classList.remove('fd-header-visible');
				} else if (currentY < lastY - 5) {
					header.classList.remove('fd-header-hidden');
					header.classList.add('fd-header-visible');
				}
			} else {
				header.classList.remove('fd-header-hidden');
				header.classList.add('fd-header-visible');
			}
			lastY = currentY;
		}

		window.addEventListener('scroll', onScroll, { passive: true });

		return function () {
			window.removeEventListener('scroll', onScroll);
			header.classList.remove('fd-header-hidden', 'fd-header-visible');
		};
	}

	/* ------------------------------------------------------------------ */
	/*  11. Image lazy-fade-in                                            */
	/* ------------------------------------------------------------------ */
	function initImageFade() {
		var images = document.querySelectorAll('.fd-hero__image, .fd-product__media img, .fd-story__media img');
		if (!images.length) return;

		var pending = [];

		function mark(image) {
			image.classList.add('fd-anim-img-loaded');
		}

		for (var i = 0; i < images.length; i++) {
			if (images[i].complete) {
				mark(images[i]);
			} else {
				pending.push(images[i]);
				images[i].addEventListener('load', onLoad);
				images[i].addEventListener('error', onLoad);
			}
		}

		function onLoad() { mark(this); }

		/* Teardown has to reveal every image, including the ones still in
		   flight. The CSS hides them behind html.fd-anim-ready, so removing
		   that class is the real fix — but a cached image that already fired
		   `load` before the class came off would keep its state, so mark them
		   explicitly as well. */
		return function () {
			for (var j = 0; j < pending.length; j++) {
				pending[j].removeEventListener('load', onLoad);
				pending[j].removeEventListener('error', onLoad);
				mark(pending[j]);
			}
			pending = [];
		};
	}

	/* ------------------------------------------------------------------ */
	/*  12. Magnetic cursor glow on dark-luxe cards                       */
	/* ------------------------------------------------------------------ */
	function initCursorGlow() {
		if (!document.body.classList.contains('flavor-skin-dark-luxe')) return;
		if (!fineDesktop()) return;

		var cards = document.querySelectorAll('.fd-product');
		if (!cards.length) return;

		for (var i = 0; i < cards.length; i++) {
			cards[i].classList.add('fd-anim-glow-target');
			cards[i].addEventListener('pointermove', onGlowMove);
			cards[i].addEventListener('pointerleave', onGlowLeave);
		}

		return function () {
			for (var j = 0; j < cards.length; j++) {
				cards[j].classList.remove('fd-anim-glow-target', 'fd-anim-glow-active');
				cards[j].removeEventListener('pointermove', onGlowMove);
				cards[j].removeEventListener('pointerleave', onGlowLeave);
				cards[j].style.removeProperty('--fd-glow-x');
				cards[j].style.removeProperty('--fd-glow-y');
			}
		};
	}

	function onGlowMove(event) {
		if (reduced()) return;
		var card = event.currentTarget;
		var rect = card.getBoundingClientRect();
		if (!rect.width || !rect.height) return;
		var x = event.clientX - rect.left;
		var y = event.clientY - rect.top;
		card.style.setProperty('--fd-glow-x', x + 'px');
		card.style.setProperty('--fd-glow-y', y + 'px');
		card.classList.add('fd-anim-glow-active');
	}

	function onGlowLeave(event) {
		event.currentTarget.classList.remove('fd-anim-glow-active');
	}

	/* ------------------------------------------------------------------ */
	/*  Boot all animation modules                                        */
	/* ------------------------------------------------------------------ */
	function boot() {
		if (booted || reduced()) return;
		booted = true;

		/* This class is the gate every content-hiding rule in
		   demo-animations.css sits behind. Setting it before any effect runs
		   means a module that throws halfway through cannot leave the page
		   half-hidden: the CSS still shows everything. */
		root.classList.add('fd-anim-ready');

		effect(initImageFade);
		effect(initScrollAnimations);
		effect(initParallax);
		effect(initRipple);
		effect(initCounters);
		effect(initFilterTransitions);
		effect(initTilt);
		effect(initTypewriter);
		effect(initFloatingShapes);
		effect(initSmoothAnchors);
		effect(initSmartHeader);
		effect(initCursorGlow);

		window.FlavorDemoAnimations = {
			version: '1.1.0',
			reduced: reduced,
			ready: function () { return booted && !reduced(); },
			/** Re-sweep sections added after first paint (a builder block, a
			    Customizer preview refresh, a late WooCommerce render). */
			rescan: function () {
				if (reduced()) return 0;
				var dispose = initScrollAnimations();
				if (typeof dispose === 'function') disposers.push(dispose);
				return document.querySelectorAll('.fd-anim-enter').length;
			},
			destroy: function () { booted = false; teardown(); },
			restart: function () { booted = false; teardown(); boot(); }
		};
	}

	/* Run on DOM ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.addEventListener('pagehide', function () {
		booted = false;
		teardown();
	});

	/* Scroll-bound animations on rAF throttle */
	var scrollTicking = false;
	function onScrollFrame() {
		if (scrollTicking || reduced()) return;
		scrollTicking = true;
		raf(function () {
			onScrollParallax();
			scrollTicking = false;
		});
	}
	window.addEventListener('scroll', onScrollFrame, { passive: true });

})();
