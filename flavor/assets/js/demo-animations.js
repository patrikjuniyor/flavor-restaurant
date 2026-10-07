/**
 * Flavor Demo Animations — scroll-driven entrance, parallax, ripple,
 * counter, stagger and reduced-motion aware.
 * Zero dependencies; enqueued only on bespoke demo pages.
 *
 * @version 1.0.0
 * @license GPL-2.0-or-later
 */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/*  Guard: only run on bespoke demo pages when JS is available.       */
	/* ------------------------------------------------------------------ */
	if (!document.body.classList.contains('flavor-bespoke')) return;
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	/* ------------------------------------------------------------------ */
	/*  Utility helpers                                                   */
	/* ------------------------------------------------------------------ */
	var raf = window.requestAnimationFrame || function (cb) { return setTimeout(cb, 16); };

	function debounce(fn, ms) {
		var timer;
		return function () {
			clearTimeout(timer);
			timer = setTimeout(fn, ms);
		};
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
					}
				}
			}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

			for (var k = 0; k < targets.length; k++) {
				observer.observe(targets[k]);
			}
		} else {
			/* Fallback: reveal everything immediately */
			for (var m = 0; m < targets.length; m++) {
				targets[m].classList.add(VISIBLE_CLASS);
			}
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
	/* ------------------------------------------------------------------ */
	var parallaxTarget = null;
	var parallaxSpeed  = 0.15;

	function initParallax() {
		parallaxTarget = document.querySelector('.fd-hero__image');
		if (!parallaxTarget) return;
		parallaxTarget.style.willChange = 'transform';
		onScrollParallax();
	}

	function onScrollParallax() {
		if (!parallaxTarget) return;
		var rect = parallaxTarget.getBoundingClientRect();
		var vh   = window.innerHeight;
		if (rect.bottom < 0 || rect.top > vh) return;
		var offset = (rect.top - vh / 2) * parallaxSpeed;
		parallaxTarget.style.transform = 'translateY(' + offset.toFixed(1) + 'px) scale(1.02)';
	}

	/* ------------------------------------------------------------------ */
	/*  3. Ripple effect on primary buttons                               */
	/* ------------------------------------------------------------------ */
	function initRipple() {
		document.addEventListener('click', function (event) {
			var btn = event.target.closest('.fd-button--primary, .fd-product__order');
			if (!btn) return;

			var rect = btn.getBoundingClientRect();
			var x    = event.clientX - rect.left;
			var y    = event.clientY - rect.top;
			var size = Math.max(rect.width, rect.height) * 2;

			var ripple = document.createElement('span');
			ripple.className = 'fd-anim-ripple';
			ripple.style.width  = size + 'px';
			ripple.style.height = size + 'px';
			ripple.style.left   = (x - size / 2) + 'px';
			ripple.style.top    = (y - size / 2) + 'px';
			btn.appendChild(ripple);

			ripple.addEventListener('animationend', function () { ripple.remove(); });
		});
	}

	/* ------------------------------------------------------------------ */
	/*  4. Animated number counters (data-fd-count attribute)             */
	/* ------------------------------------------------------------------ */
	function initCounters() {
		var counters = document.querySelectorAll('[data-fd-count]');
		if (!counters.length) return;

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
	}

	function animateCounter(el) {
		var target = parseInt(el.getAttribute('data-fd-count'), 10);
		if (isNaN(target)) return;
		var duration = 1200;
		var start    = performance.now();
		var initial  = 0;

		function step(now) {
			var progress = Math.min((now - start) / duration, 1);
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

		/* Listen for hidden changes on cards */
		var cards = Array.from(section.querySelectorAll('[data-fd-categories]'));
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
	}

	/* ------------------------------------------------------------------ */
	/*  6. Magnetic tilt on hero image (desktop only)                     */
	/* ------------------------------------------------------------------ */
	var tiltTarget = null;
	var tiltFrame  = null;

	function initTilt() {
		if (window.matchMedia('(max-width: 991px)').matches) return;
		tiltTarget = document.querySelector('.fd-hero__art');
		if (!tiltTarget) return;
		tiltTarget.addEventListener('mousemove', onTiltMove);
		tiltTarget.addEventListener('mouseleave', onTiltLeave);
	}

	function onTiltMove(event) {
		if (!tiltTarget) return;
		if (tiltFrame) cancelAnimationFrame(tiltFrame);
		tiltFrame = raf(function () {
			var rect  = tiltTarget.getBoundingClientRect();
			var x     = (event.clientX - rect.left) / rect.width  - 0.5;
			var y     = (event.clientY - rect.top)  / rect.height - 0.5;
			var img   = tiltTarget.querySelector('.fd-hero__image');
			if (img) {
				img.style.transform = 'perspective(800px) rotateY(' + (x * 6).toFixed(2) + 'deg) rotateX(' + (-y * 6).toFixed(2) + 'deg) scale(1.02)';
				img.style.transition = 'transform 0.1s ease-out';
			}
			tiltFrame = null;
		});
	}

	function onTiltLeave() {
		if (!tiltTarget) return;
		var img = tiltTarget.querySelector('.fd-hero__image');
		if (img) {
			img.style.transform = '';
			img.style.transition = 'transform 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
		}
	}

	/* ------------------------------------------------------------------ */
	/*  7. Typewriter effect for hero h1 em elements                      */
	/* ------------------------------------------------------------------ */
	function initTypewriter() {
		var ems = document.querySelectorAll('.fd-hero h1 em');
		if (!ems.length) return;

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
	}

	function typewrite(el) {
		var text = el.textContent.trim();
		if (!text) return;
		el.textContent = '';
		el.style.borderInlineEnd = '2px solid currentColor';
		el.classList.add('fd-anim-typewriter');
		var i = 0;
		function tick() {
			if (i < text.length) {
				el.textContent += text[i];
				i++;
				setTimeout(tick, 55 + Math.random() * 35);
			} else {
				/* Blink cursor twice then remove */
				setTimeout(function () {
					el.style.borderInlineEnd = '';
					el.classList.remove('fd-anim-typewriter');
				}, 600);
			}
		}
		setTimeout(tick, 300);
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
	}

	/* ------------------------------------------------------------------ */
	/*  9. Smooth scroll-to for anchor links                              */
	/* ------------------------------------------------------------------ */
	function initSmoothAnchors() {
		document.addEventListener('click', function (event) {
			var link = event.target.closest('a[href^="#"]');
			if (!link) return;
			var id = link.getAttribute('href');
			if (!id || id === '#') return;
			var target = document.querySelector(id);
			if (!target) return;
			event.preventDefault();
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			history.pushState(null, '', id);
		});
	}

	/* ------------------------------------------------------------------ */
	/*  10. Header hide/show on scroll direction                          */
	/* ------------------------------------------------------------------ */
	function initSmartHeader() {
		var header = document.getElementById('flavor-site-header');
		if (!header) return;
		var lastY = window.scrollY;
		var threshold = 80;

		function onScroll() {
			var currentY = window.scrollY;
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
	}

	/* ------------------------------------------------------------------ */
	/*  11. Image lazy-fade-in                                            */
	/* ------------------------------------------------------------------ */
	function initImageFade() {
		var images = document.querySelectorAll('.fd-hero__image, .fd-product__media img, .fd-story__media img');
		if (!images.length) return;

		for (var i = 0; i < images.length; i++) {
			if (images[i].complete) {
				images[i].classList.add('fd-anim-img-loaded');
			} else {
				images[i].addEventListener('load', function () {
					this.classList.add('fd-anim-img-loaded');
				});
				images[i].addEventListener('error', function () {
					this.classList.add('fd-anim-img-loaded');
				});
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/*  12. Magnetic cursor glow on dark-luxe cards                       */
	/* ------------------------------------------------------------------ */
	function initCursorGlow() {
		if (!document.body.classList.contains('flavor-skin-dark-luxe')) return;
		if (window.matchMedia('(max-width: 991px)').matches) return;

		var cards = document.querySelectorAll('.fd-product');
		if (!cards.length) return;

		for (var i = 0; i < cards.length; i++) {
			cards[i].classList.add('fd-anim-glow-target');
			cards[i].addEventListener('mousemove', onGlowMove);
			cards[i].addEventListener('mouseleave', onGlowLeave);
		}
	}

	function onGlowMove(event) {
		var card = event.currentTarget;
		var rect = card.getBoundingClientRect();
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
		initScrollAnimations();
		initParallax();
		initRipple();
		initCounters();
		initFilterTransitions();
		initTilt();
		initTypewriter();
		initFloatingShapes();
		initSmoothAnchors();
		initSmartHeader();
		initImageFade();
		initCursorGlow();
	}

	/* Run on DOM ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	/* Scroll-bound animations on rAF throttle */
	var scrollTicking = false;
	window.addEventListener('scroll', function () {
		if (!scrollTicking) {
			scrollTicking = true;
			raf(function () {
				onScrollParallax();
				scrollTicking = false;
			});
		}
	}, { passive: true });

})();