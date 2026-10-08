#!/usr/bin/env python3
"""Append a tailored motion art-direction block to the seven classic skins.

Gating convention (this is what makes the choreography fire at the right time):

  * Above-the-fold hero elements gate on `html.flavor-anim-ready` alone, so
    they play on first paint.
  * Everything inside a section gates on an ancestor that motion.js actually
    reveals — `.flavor-section.flavor-anim-in`, `.flavor-food-card.flavor-anim-in`
    and so on — so a section far down the page animates when it is scrolled to,
    not silently at page load.
  * Hover / :active / [aria-pressed] rules need no gate at all.

Every block also carries its own prefers-reduced-motion guard naming its own
selectors (the standard the 1.4.0 release set) and declares no font-size, so it
cannot trip the sub-12px sweep in tests/ui/responsive.mjs.
"""
from pathlib import Path

SKINS = Path("flavor/assets/css/skins")
BLOCKS = {}

BLOCKS["modern-restaurant"] = """
/* ================================================================== */
/*  Motion art direction — editorial modern                           */
/*                                                                     */
/*  Signature: type behaves like print. Headings are masked and pulled */
/*  up into place, a saffron rule draws itself beneath the section tag,*/
/*  and cards carry one slow warm sheen instead of a bounce. Nothing   */
/*  overshoots; the preset reads as calm and expensive.                */
/* ================================================================== */
body.flavor-skin-modern-restaurant {
	--flavor-m-signature: cubic-bezier(0.22, 1, 0.36, 1);
	--flavor-m-hero: cubic-bezier(0.22, 1, 0.36, 1);
	--flavor-m-spring: cubic-bezier(0.33, 1, 0.68, 1); /* deliberately no bounce */
	--flavor-m-rise: 22px;
	--flavor-m-step: 70ms;
}

/* Section titles arrive as masked lines, when their section is scrolled to. */
html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-section.flavor-anim-in .flavor-section-header__title {
	animation: flavor-m-mr-mask var(--flavor-slow) var(--flavor-m-signature) both;
}

.flavor-skin-modern-restaurant .flavor-section-header__tag {
	position: relative;
}

html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-section.flavor-anim-in .flavor-section-header__tag::after {
	content: "";
	position: absolute;
	inset-inline: 0;
	bottom: -6px;
	height: 2px;
	border-radius: 2px;
	background: linear-gradient(to left, var(--flavor-accent), transparent);
	transform: scaleX(0);
	transform-origin: inline-start;
	animation: flavor-m-mr-rule 720ms var(--flavor-m-signature) 160ms both;
	pointer-events: none;
}

/* Cards are already positioned by the skin; the sheen is the only addition. */
.flavor-skin-modern-restaurant .flavor-food-card {
	isolation: isolate;
}

/* A warm sheen crosses each dish card once it lands, then never repeats. */
html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-food-card.flavor-anim-in::after {
	content: "";
	position: absolute;
	inset: 0;
	border-radius: inherit;
	background: linear-gradient(104deg, transparent 34%, rgba(214, 166, 79, 0.16) 50%, transparent 66%);
	animation: flavor-m-mr-sheen 1.1s var(--flavor-m-signature) 220ms both;
	pointer-events: none;
	z-index: 1;
}

/* Hero art settles with a long, almost imperceptible push-in. */
html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-hero__bg {
	animation-duration: var(--flavor-cinematic);
	animation-timing-function: var(--flavor-m-hero);
}

/* Category tiles: the caption nudges while the image drifts underneath. */
.flavor-skin-modern-restaurant .flavor-cat-card:hover .flavor-cat-card__title {
	animation: flavor-m-mr-nudge 420ms var(--flavor-m-signature);
}

/* Intro value strip lights up in sequence along its connecting rule. */
html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-intro__card.flavor-anim-in .flavor-intro__icon {
	animation: flavor-m-mr-icon 620ms var(--flavor-m-signature) both;
}

/* Prices are the decision point; they settle a beat after the card. */
html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-food-card.flavor-anim-in .flavor-food-card__price {
	animation: flavor-m-mr-settle 560ms var(--flavor-m-signature) 180ms both;
}

@keyframes flavor-m-mr-mask {
	from { opacity: 0; transform: translate3d(0, 0.7em, 0); clip-path: inset(0 0 100% 0); }
	60%  { clip-path: inset(0 0 0 0); }
	to   { opacity: 1; transform: none; clip-path: inset(0 0 -0.1em 0); }
}

@keyframes flavor-m-mr-rule {
	from { transform: scaleX(0); }
	to   { transform: scaleX(1); }
}

@keyframes flavor-m-mr-sheen {
	from { opacity: 0; transform: translate3d(-38%, 0, 0); }
	30%  { opacity: 1; }
	to   { opacity: 0; transform: translate3d(38%, 0, 0); }
}

@keyframes flavor-m-mr-nudge {
	0%, 100% { transform: none; }
	40%      { transform: translate3d(-4px, 0, 0); }
}

@keyframes flavor-m-mr-icon {
	from { opacity: 0; transform: translate3d(0, 8px, 0) scale(0.86); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-mr-settle {
	from { opacity: 0; transform: translate3d(0, 6px, 0); }
	to   { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-section.flavor-anim-in .flavor-section-header__title,
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-section.flavor-anim-in .flavor-section-header__tag::after,
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-food-card.flavor-anim-in::after,
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-food-card.flavor-anim-in .flavor-food-card__price,
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-hero__bg,
	html.flavor-anim-ready .flavor-skin-modern-restaurant .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
	.flavor-skin-modern-restaurant .flavor-cat-card:hover .flavor-cat-card__title {
		animation: none !important;
		transform: none !important;
		clip-path: none !important;
		opacity: 1 !important;
	}
}
"""

BLOCKS["luxury-dining"] = """
/* ================================================================== */
/*  Motion art direction — luxury / champagne                         */
/*                                                                     */
/*  Signature: slow, gilt, and completely without bounce. A hairline   */
/*  of champagne draws across the hero title, the night background     */
/*  breathes like candlelight, and cards are illuminated rather than   */
/*  lifted — an overshoot would read as cheap against a 2px radius.    */
/* ================================================================== */
body.flavor-skin-luxury-dining {
	--flavor-m-signature: cubic-bezier(0.16, 1, 0.3, 1);
	--flavor-m-hero: cubic-bezier(0.33, 0, 0.15, 1);
	--flavor-m-spring: cubic-bezier(0.33, 1, 0.68, 1); /* luxury never springs */
	--flavor-m-rise: 18px;
	--flavor-m-slide: 24px;
	--flavor-m-step: 95ms; /* a longer beat reads as ceremony, not as lag */
	--flavor-base: 560ms;
	--flavor-slow: 860ms;
}

html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-hero__title {
	position: relative;
}

/* A gilt line sweeps the hero title once, then leaves it alone. */
html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-hero__title::after {
	content: "";
	position: absolute;
	inset-inline: 0;
	bottom: -0.35em;
	height: 1px;
	background: linear-gradient(to left, transparent, var(--flavor-primary) 22%, var(--flavor-accent) 50%, var(--flavor-primary) 78%, transparent);
	transform: scaleX(0);
	animation: flavor-m-lx-gilt 1.5s var(--flavor-m-hero) 420ms both;
	pointer-events: none;
}

/* Candlelight: the overlay brightens and dims on a long, soft loop. */
html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-hero__overlay {
	animation: flavor-m-lx-candle 7.5s ease-in-out 900ms infinite;
}

/* Cards do not lift — they are illuminated. A copper edge appears on hover. */
.flavor-skin-luxury-dining .flavor-food-card,
.flavor-skin-luxury-dining .flavor-cat-card {
	transition: box-shadow var(--flavor-slow) var(--flavor-m-signature),
	            border-color var(--flavor-slow) var(--flavor-m-signature);
}

.flavor-skin-luxury-dining .flavor-food-card:hover,
.flavor-skin-luxury-dining .flavor-cat-card:hover {
	box-shadow: 0 0 0 1px rgba(203, 177, 134, 0.42), 0 26px 60px rgba(0, 0, 0, 0.5);
}

/* The category count is engraved: it fades up rather than sliding in. */
html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-cat-card.flavor-anim-in .flavor-cat-card__count {
	animation: flavor-m-lx-engrave var(--flavor-slow) var(--flavor-m-signature) both;
}

/* Gallery plates cross-fade behind a still frame, like a slideshow in a
   dining room rather than a swipe on a phone. */
.flavor-skin-luxury-dining .flavor-gallery__item img {
	transition: transform 1.6s var(--flavor-m-hero), filter 1.6s var(--flavor-m-hero);
}

.flavor-skin-luxury-dining .flavor-gallery__item:hover img {
	filter: saturate(1.12) brightness(1.06);
}

/* Stats count in gold, and the label settles a beat after the number. */
html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-about__stat.flavor-anim-in .flavor-about__stat-num {
	color: var(--flavor-primary);
	animation: flavor-m-lx-engrave var(--flavor-slow) var(--flavor-m-hero) both;
}

html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-about__stat.flavor-anim-in .flavor-about__stat-lbl {
	animation: flavor-m-lx-engrave var(--flavor-base) var(--flavor-m-signature) 260ms both;
}

/* Review stars fill slowly; five quick pops would fight the whole preset. */
.flavor-skin-luxury-dining .flavor-review-card.flavor-anim-in .flavor-review-card__stars svg {
	animation-duration: 700ms;
	animation-timing-function: var(--flavor-m-signature);
}

/* Section titles rise behind a soft vignette instead of a hard mask. */
html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-section.flavor-anim-in .flavor-section-header__title {
	animation: flavor-m-lx-vignette var(--flavor-slow) var(--flavor-m-hero) both;
}

@keyframes flavor-m-lx-gilt {
	0%   { transform: scaleX(0); opacity: 0; }
	45%  { opacity: 1; }
	100% { transform: scaleX(1); opacity: 0.9; }
}

@keyframes flavor-m-lx-candle {
	0%, 100% { opacity: 1; }
	42%      { opacity: 0.86; }
	68%      { opacity: 0.95; }
}

@keyframes flavor-m-lx-engrave {
	from { opacity: 0; transform: translate3d(0, 10px, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-lx-vignette {
	from { opacity: 0; transform: translate3d(0, 14px, 0); filter: blur(5px); }
	to   { opacity: 1; transform: none; filter: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-hero__title::after,
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-hero__overlay,
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-cat-card.flavor-anim-in .flavor-cat-card__count,
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-about__stat.flavor-anim-in .flavor-about__stat-num,
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-about__stat.flavor-anim-in .flavor-about__stat-lbl,
	html.flavor-anim-ready .flavor-skin-luxury-dining .flavor-section.flavor-anim-in .flavor-section-header__title,
	.flavor-skin-luxury-dining .flavor-review-card.flavor-anim-in .flavor-review-card__stars svg,
	.flavor-skin-luxury-dining .flavor-gallery__item img {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		opacity: 1 !important;
	}

	.flavor-skin-luxury-dining .flavor-food-card:hover,
	.flavor-skin-luxury-dining .flavor-cat-card:hover {
		box-shadow: none;
	}
}
"""

BLOCKS["persian-traditional"] = """
/* ================================================================== */
/*  Motion art direction — Persian traditional                        */
/*                                                                     */
/*  Signature: geometry in motion. Icons turn like a girih tile, the   */
/*  section tag shifts lapis → crimson → brass the way glazed tilework */
/*  changes across a wall, and borders unroll from the centre outward  */
/*  rather than sliding in — Persian ornament is always symmetrical.   */
/* ================================================================== */
body.flavor-skin-persian-traditional {
	--flavor-m-signature: cubic-bezier(0.34, 0.9, 0.36, 1);
	--flavor-m-hero: cubic-bezier(0.22, 1, 0.36, 1);
	--flavor-m-rise: 16px;   /* short travel: ornament settles, it does not fly */
	--flavor-m-slide: 16px;
	--flavor-m-step: 80ms;
}

/* Section tags cycle the tile glazes while their section is on screen. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-section.flavor-anim-in .flavor-section-header__tag {
	background-size: 260% 100%;
	animation: flavor-m-pt-glaze 9s ease-in-out infinite;
}

/* Intro icons rotate an eighth-turn and stop, like a tile locking into place. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-contact-row.flavor-anim-in .flavor-contact-row__icon {
	animation: flavor-m-pt-girih 780ms var(--flavor-m-signature) both;
}

.flavor-skin-persian-traditional .flavor-intro__card:hover .flavor-intro__icon {
	animation: flavor-m-pt-girih 780ms var(--flavor-m-signature);
}

/* Borders unroll from the centre on both axes — the way a carpet runner is
   laid, which is why the clip origin is the middle and not an edge. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hours-card.flavor-anim-in,
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-food-card.flavor-anim-in {
	animation: flavor-m-pt-unroll 640ms var(--flavor-m-signature) both;
}

/* The hero badge dot becomes a small brass rosette that turns slowly. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hero__badge-dot {
	animation: flavor-m-pt-rosette 14s linear infinite;
}

/* Category tiles warm toward saffron instead of only zooming. */
.flavor-skin-persian-traditional .flavor-cat-card__bg {
	transition: transform var(--flavor-slow) var(--flavor-m-signature),
	            filter var(--flavor-slow) var(--flavor-m-signature);
}

.flavor-skin-persian-traditional .flavor-cat-card:hover .flavor-cat-card__bg {
	filter: saturate(1.15) hue-rotate(-6deg);
}

/* The experience badge is a seal; it is pressed on, not dropped in. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-about__media.flavor-anim-in .flavor-about__exp-badge {
	animation: flavor-m-pt-seal 700ms var(--flavor-m-signature) both;
}

/* Reservation perks converge symmetrically toward the middle. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-res-banner__content.flavor-anim-in .flavor-res-banner__perk:nth-child(odd) {
	animation: flavor-m-pt-converge-a 560ms var(--flavor-m-signature) both;
}

html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-res-banner__content.flavor-anim-in .flavor-res-banner__perk:nth-child(even) {
	animation: flavor-m-pt-converge-b 560ms var(--flavor-m-signature) both;
}

/* The "open now" lamp turns slowly inside the hours card. */
html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hours-card.flavor-anim-in .flavor-live-status--open {
	animation: flavor-m-pt-lamp 4.6s ease-in-out infinite;
}

@keyframes flavor-m-pt-glaze {
	0%, 100% { background-position: 0% 50%; }
	33%      { background-position: 50% 50%; }
	66%      { background-position: 100% 50%; }
}

@keyframes flavor-m-pt-girih {
	from { opacity: 0; transform: rotate(-45deg) scale(0.72); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-pt-unroll {
	from { opacity: 0; transform: scale(0.94); clip-path: inset(0 50% 0 50%); }
	55%  { clip-path: inset(0 0 0 0); }
	to   { opacity: 1; transform: none; clip-path: inset(0 0 -0.05em 0); }
}

@keyframes flavor-m-pt-rosette {
	from { transform: rotate(0deg); }
	to   { transform: rotate(360deg); }
}

@keyframes flavor-m-pt-seal {
	0%   { opacity: 0; transform: scale(1.5) rotate(9deg); }
	62%  { opacity: 1; transform: scale(0.95) rotate(-2deg); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-pt-converge-a {
	from { opacity: 0; transform: translate3d(-14px, 0, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-pt-converge-b {
	from { opacity: 0; transform: translate3d(14px, 0, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-pt-lamp {
	0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(13, 92, 99, 0.36); }
	50%      { opacity: 0.74; box-shadow: 0 0 0 7px rgba(13, 92, 99, 0); }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-section.flavor-anim-in .flavor-section-header__tag,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-contact-row.flavor-anim-in .flavor-contact-row__icon,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hours-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-food-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hero__badge-dot,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-about__media.flavor-anim-in .flavor-about__exp-badge,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-res-banner__content.flavor-anim-in .flavor-res-banner__perk,
	html.flavor-anim-ready .flavor-skin-persian-traditional .flavor-hours-card.flavor-anim-in .flavor-live-status--open,
	.flavor-skin-persian-traditional .flavor-intro__card:hover .flavor-intro__icon,
	.flavor-skin-persian-traditional .flavor-cat-card__bg,
	.flavor-skin-persian-traditional .flavor-cat-card:hover .flavor-cat-card__bg {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		clip-path: none !important;
		filter: none !important;
		box-shadow: none !important;
		opacity: 1 !important;
	}
}
"""

BLOCKS["cafe-bistro"] = """
/* ================================================================== */
/*  Motion art direction — café & bistro                              */
/*                                                                     */
/*  Signature: paper and steam. Cards lift like a coaster raised off a */
/*  wooden counter, hot items carry a slow rising steam on hover, and  */
/*  everything settles on soft curves. Movement is small and unhurried */
/*  — a café that animates quickly feels like a fast-food joint.       */
/* ================================================================== */
body.flavor-skin-cafe-bistro {
	--flavor-m-signature: cubic-bezier(0.33, 1, 0.68, 1);
	--flavor-m-hero: cubic-bezier(0.33, 1, 0.68, 1);
	--flavor-m-spring: cubic-bezier(0.34, 1.24, 0.64, 1); /* a whisper of lift */
	--flavor-m-rise: 18px;
	--flavor-m-step: 85ms;
	--flavor-base: 480ms;
}

.flavor-skin-cafe-bistro .flavor-food-card__media {
	position: relative;
}

/* Steam over the featured dishes: three wisps, offset, pure ornament. It is
   drawn only on hover so it never runs unattended on a static page. */
.flavor-skin-cafe-bistro .flavor-food-card__media::after {
	content: "";
	position: absolute;
	inset-inline-start: 18%;
	bottom: 42%;
	width: 64%;
	height: 46%;
	background:
		radial-gradient(ellipse at 22% 100%, rgba(255, 255, 255, 0.34), transparent 62%),
		radial-gradient(ellipse at 54% 100%, rgba(255, 255, 255, 0.26), transparent 58%),
		radial-gradient(ellipse at 82% 100%, rgba(255, 255, 255, 0.3), transparent 60%);
	filter: blur(5px);
	opacity: 0;
	pointer-events: none;
}

.flavor-skin-cafe-bistro .flavor-food-card:hover .flavor-food-card__media::after {
	animation: flavor-m-cb-steam 3.4s var(--flavor-m-signature) infinite;
}

/* Cards lift off the counter with a shadow that grows as they rise. */
.flavor-skin-cafe-bistro .flavor-food-card,
.flavor-skin-cafe-bistro .flavor-intro__card {
	transition: transform 260ms var(--flavor-m-signature), box-shadow 260ms var(--flavor-m-signature);
}

.flavor-skin-cafe-bistro .flavor-food-card:hover,
.flavor-skin-cafe-bistro .flavor-intro__card:hover {
	transform: translate3d(0, -6px, 0) rotate(-0.35deg);
	box-shadow: 0 18px 34px rgba(60, 44, 30, 0.16);
}

/* Dashed separators draw themselves, like a chalk line on a board. */
html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-section.flavor-anim-in .flavor-section-header__desc::before {
	content: "";
	display: block;
	width: 62px;
	height: 0;
	border-top: 2px dashed var(--flavor-primary);
	opacity: 0.5;
	margin: 0 auto 14px;
	animation: flavor-m-cb-chalk 620ms var(--flavor-m-signature) both;
}

/* The "open now" dot becomes a slow breathing lamp rather than a pulse. */
html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-hours-card.flavor-anim-in .flavor-live-status--open {
	animation: flavor-m-cb-lamp 4.2s ease-in-out infinite;
}

/* Gallery prints tilt slightly as they come in — photographs pinned to a wall
   are never perfectly straight, and they stay that way. */
html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-gallery__item.flavor-anim-in:nth-child(odd) {
	animation: flavor-m-cb-pin-a 620ms var(--flavor-m-signature) both;
}

html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-gallery__item.flavor-anim-in:nth-child(even) {
	animation: flavor-m-cb-pin-b 620ms var(--flavor-m-signature) both;
}

/* Review cards arrive like notes passed across the table. */
html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-review-card.flavor-anim-in {
	animation: flavor-m-cb-note 560ms var(--flavor-m-signature) both;
}

/* Category tiles warm toward the wood tone on hover. */
.flavor-skin-cafe-bistro .flavor-cat-card__bg {
	transition: transform var(--flavor-slow) var(--flavor-m-signature),
	            filter var(--flavor-slow) var(--flavor-m-signature);
}

.flavor-skin-cafe-bistro .flavor-cat-card:hover .flavor-cat-card__bg {
	filter: saturate(1.08) sepia(0.14);
}

@keyframes flavor-m-cb-steam {
	0%   { opacity: 0; transform: translate3d(0, 12%, 0) scaleY(0.7); }
	28%  { opacity: 0.7; }
	70%  { opacity: 0.28; }
	100% { opacity: 0; transform: translate3d(0, -34%, 0) scaleY(1.25); }
}

@keyframes flavor-m-cb-chalk {
	from { width: 0; opacity: 0; }
	to   { width: 62px; opacity: 0.5; }
}

@keyframes flavor-m-cb-lamp {
	0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(107, 122, 74, 0.34); }
	50%      { opacity: 0.72; box-shadow: 0 0 0 7px rgba(107, 122, 74, 0); }
}

@keyframes flavor-m-cb-pin-a {
	from { opacity: 0; transform: translate3d(0, 16px, 0) rotate(-1.6deg); }
	to   { opacity: 1; transform: rotate(-0.7deg); }
}

@keyframes flavor-m-cb-pin-b {
	from { opacity: 0; transform: translate3d(0, 16px, 0) rotate(1.6deg); }
	to   { opacity: 1; transform: rotate(0.7deg); }
}

@keyframes flavor-m-cb-note {
	from { opacity: 0; transform: translate3d(12px, 10px, 0) rotate(1.1deg); }
	to   { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-section.flavor-anim-in .flavor-section-header__desc::before,
	html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-hours-card.flavor-anim-in .flavor-live-status--open,
	html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-gallery__item.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-cafe-bistro .flavor-review-card.flavor-anim-in,
	.flavor-skin-cafe-bistro .flavor-food-card,
	.flavor-skin-cafe-bistro .flavor-intro__card,
	.flavor-skin-cafe-bistro .flavor-food-card:hover,
	.flavor-skin-cafe-bistro .flavor-intro__card:hover,
	.flavor-skin-cafe-bistro .flavor-food-card:hover .flavor-food-card__media::after,
	.flavor-skin-cafe-bistro .flavor-cat-card__bg,
	.flavor-skin-cafe-bistro .flavor-cat-card:hover .flavor-cat-card__bg {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		box-shadow: none !important;
		opacity: 1 !important;
	}

	/* The steam wisp is pure ornament; with motion off it stays hidden rather
	   than freezing as a white smear over the dish photography. */
	.flavor-skin-cafe-bistro .flavor-food-card__media::after {
		display: none !important;
	}
}
"""

BLOCKS["fast-food"] = """
/* ================================================================== */
/*  Motion art direction — fast food / burger bar                     */
/*                                                                     */
/*  Signature: poster energy. Headings slam in, stickers wobble, cards */
/*  pop against hard ink shadows and everything overshoots on purpose. */
/*  This is the one preset where a spring curve is the right answer.   */
/* ================================================================== */
body.flavor-skin-fast-food {
	--flavor-m-signature: cubic-bezier(0.34, 1.56, 0.64, 1);
	--flavor-m-hero: cubic-bezier(0.2, 1.5, 0.4, 1);
	--flavor-m-spring: cubic-bezier(0.34, 1.78, 0.5, 1);
	--flavor-m-rise: 34px;  /* bigger travel reads as energy, not as sloppiness */
	--flavor-m-slide: 40px;
	--flavor-m-scale: 0.9;
	--flavor-m-step: 55ms;  /* fast beats: a queue is reading, not lingering */
	--flavor-base: 340ms;
	--flavor-slow: 520ms;
}

/* The hero title slams down and settles with one overshoot. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-hero__title {
	animation: flavor-m-ff-slam 620ms var(--flavor-m-hero) both;
}

/* Sticker badges wobble on a loop — they are meant to look stuck on by hand. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-hero__badge {
	animation: flavor-m-ff-wobble 3.6s ease-in-out 700ms infinite;
}

html.flavor-anim-ready .flavor-skin-fast-food .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__badge,
html.flavor-anim-ready .flavor-skin-fast-food .flavor-res-banner__content.flavor-anim-in .flavor-res-banner__tag {
	animation: flavor-m-ff-wobble 3.6s ease-in-out 300ms infinite;
}

/* Cards pop in against a hard ink shadow rather than a soft blur. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-food-card.flavor-anim-in,
html.flavor-anim-ready .flavor-skin-fast-food .flavor-cat-card.flavor-anim-in {
	animation: flavor-m-ff-pop 420ms var(--flavor-m-signature) both;
}

.flavor-skin-fast-food .flavor-food-card,
.flavor-skin-fast-food .flavor-cat-card {
	transition: transform 180ms var(--flavor-m-signature), box-shadow 180ms var(--flavor-m-signature);
}

.flavor-skin-fast-food .flavor-food-card:hover,
.flavor-skin-fast-food .flavor-cat-card:hover {
	transform: translate3d(-4px, -6px, 0) rotate(-1.1deg);
	box-shadow: 8px 8px 0 rgba(32, 22, 18, 0.85);
}

/* The category badge on each dish flips over like a price tag. */
.flavor-skin-fast-food .flavor-food-card:hover .flavor-food-card__cat-badge {
	animation: flavor-m-ff-tag 460ms var(--flavor-m-signature);
}

/* Prices get a beat of their own — they are the thing being sold. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-food-card.flavor-anim-in .flavor-food-card__price {
	animation: flavor-m-ff-price 520ms var(--flavor-m-signature) 140ms both;
}

/* Intro icons bounce once, hard, then hold. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-intro__card.flavor-anim-in .flavor-intro__icon {
	animation: flavor-m-ff-bounce 620ms var(--flavor-m-spring) both;
}

/* Buttons press down into the ink shadow instead of lifting away from it. */
.flavor-skin-fast-food .flavor-btn:active {
	transform: translate3d(2px, 3px, 0) scale(0.98);
	transition: transform 90ms var(--flavor-m-signature);
}

/* Gallery tiles tilt alternately, like posters taped to a wall. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-gallery__item.flavor-anim-in:nth-child(3n+1) {
	animation: flavor-m-ff-tilt-a 420ms var(--flavor-m-signature) both;
}

html.flavor-anim-ready .flavor-skin-fast-food .flavor-gallery__item.flavor-anim-in:nth-child(3n+2) {
	animation: flavor-m-ff-tilt-b 420ms var(--flavor-m-signature) both;
}

/* The offer banner title lands with the same slam as the hero. */
html.flavor-anim-ready .flavor-skin-fast-food .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__title {
	animation: flavor-m-ff-slam 540ms var(--flavor-m-hero) 120ms both;
}

@keyframes flavor-m-ff-slam {
	0%   { opacity: 0; transform: translate3d(0, -46px, 0) scale(1.12); }
	62%  { opacity: 1; transform: translate3d(0, 6px, 0) scale(0.98); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-ff-wobble {
	0%, 100% { transform: rotate(-2.4deg); }
	25%      { transform: rotate(2.2deg) scale(1.03); }
	50%      { transform: rotate(-1.6deg); }
	75%      { transform: rotate(2.8deg) scale(1.02); }
}

@keyframes flavor-m-ff-pop {
	0%   { opacity: 0; transform: scale(0.82) rotate(-2.5deg); }
	68%  { opacity: 1; transform: scale(1.05) rotate(0.8deg); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-ff-tag {
	0%   { transform: perspective(420px) rotateY(0deg); }
	45%  { transform: perspective(420px) rotateY(-84deg); }
	100% { transform: perspective(420px) rotateY(0deg); }
}

@keyframes flavor-m-ff-price {
	0%   { opacity: 0; transform: scale(0.6); }
	70%  { opacity: 1; transform: scale(1.16); }
	100% { opacity: 1; transform: scale(1); }
}

@keyframes flavor-m-ff-bounce {
	0%   { opacity: 0; transform: translate3d(0, -22px, 0) scale(0.7); }
	55%  { opacity: 1; transform: translate3d(0, 5px, 0) scale(1.12); }
	78%  { transform: translate3d(0, -2px, 0) scale(0.97); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-ff-tilt-a {
	from { opacity: 0; transform: translate3d(0, 22px, 0) rotate(-3deg); }
	to   { opacity: 1; transform: rotate(-1.2deg); }
}

@keyframes flavor-m-ff-tilt-b {
	from { opacity: 0; transform: translate3d(0, 22px, 0) rotate(3deg); }
	to   { opacity: 1; transform: rotate(1.2deg); }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-hero__title,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-hero__badge,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__badge,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__title,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-res-banner__content.flavor-anim-in .flavor-res-banner__tag,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-food-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-cat-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-food-card.flavor-anim-in .flavor-food-card__price,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
	html.flavor-anim-ready .flavor-skin-fast-food .flavor-gallery__item.flavor-anim-in,
	.flavor-skin-fast-food .flavor-food-card,
	.flavor-skin-fast-food .flavor-cat-card,
	.flavor-skin-fast-food .flavor-food-card:hover,
	.flavor-skin-fast-food .flavor-cat-card:hover,
	.flavor-skin-fast-food .flavor-food-card:hover .flavor-food-card__cat-badge,
	.flavor-skin-fast-food .flavor-btn:active {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		opacity: 1 !important;
	}

	/* The hard ink shadow is part of the preset's identity, so a static
	   version stays — only the movement that carries it is removed. */
	.flavor-skin-fast-food .flavor-food-card:hover,
	.flavor-skin-fast-food .flavor-cat-card:hover {
		box-shadow: 4px 4px 0 rgba(32, 22, 18, 0.85);
	}
}
"""

BLOCKS["pizza-italian"] = """
/* ================================================================== */
/*  Motion art direction — pizza & Italian trattoria                  */
/*                                                                     */
/*  Signature: the tricolore as a transition. A green-white-red edge   */
/*  wipes across a card on hover, dough-like cards prove with a soft   */
/*  give, and basil-leaf ornaments sway. Every curve is round; nothing */
/*  in a trattoria is angular.                                        */
/* ================================================================== */
body.flavor-skin-pizza-italian {
	--flavor-m-signature: cubic-bezier(0.34, 1.3, 0.64, 1);
	--flavor-m-hero: cubic-bezier(0.22, 1, 0.36, 1);
	--flavor-m-rise: 24px;
	--flavor-m-scale: 0.94;
	--flavor-m-step: 68ms;
}

.flavor-skin-pizza-italian .flavor-food-card,
.flavor-skin-pizza-italian .flavor-cat-card {
	position: relative;
	isolation: isolate;
}

/* The tricolore rule wipes across a card's top edge on hover or focus. */
.flavor-skin-pizza-italian .flavor-food-card::before,
.flavor-skin-pizza-italian .flavor-cat-card::before {
	content: "";
	position: absolute;
	inset-inline: 0;
	top: 0;
	height: 4px;
	background: linear-gradient(to left, #2f7d3a 0 33.3%, #fbf7ef 33.3% 66.6%, #c0392b 66.6% 100%);
	transform: scaleX(0);
	transform-origin: inline-start;
	transition: transform 460ms var(--flavor-m-signature);
	pointer-events: none;
	z-index: 2;
}

.flavor-skin-pizza-italian .flavor-food-card:hover::before,
.flavor-skin-pizza-italian .flavor-food-card:focus-within::before,
.flavor-skin-pizza-italian .flavor-cat-card:hover::before {
	transform: scaleX(1);
}

/* Dough give: cards scale past their size and settle, like dough proving. */
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-food-card.flavor-anim-in,
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-cat-card.flavor-anim-in {
	animation: flavor-m-pi-dough 560ms var(--flavor-m-signature) both;
}

.flavor-skin-pizza-italian .flavor-food-card {
	transition: transform 260ms var(--flavor-m-signature);
}

.flavor-skin-pizza-italian .flavor-food-card:hover {
	transform: scale(1.028);
}

/* Basil sway on the section tag and the intro icons. */
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-section.flavor-anim-in .flavor-section-header__tag {
	animation: flavor-m-pi-sway 4.4s ease-in-out 400ms infinite;
}

/* Hero features arrive like ingredients being laid on a base, one by one. */
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat {
	animation: flavor-m-pi-layer 520ms var(--flavor-m-signature) both;
}

html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat:nth-child(1) { animation-delay: 420ms; }
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat:nth-child(2) { animation-delay: 520ms; }
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat:nth-child(3) { animation-delay: 620ms; }
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat:nth-child(4) { animation-delay: 720ms; }

/* The category badge spins in like a tossed disc. */
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-food-card.flavor-anim-in .flavor-food-card__cat-badge {
	animation: flavor-m-pi-toss 640ms var(--flavor-m-signature) 180ms both;
}

/* Offer banner: the tomato-red field breathes warm, like an oven mouth. */
html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__badge {
	animation: flavor-m-pi-ember 3.2s ease-in-out infinite;
}

/* Gallery images warm toward the oven glow on hover. */
.flavor-skin-pizza-italian .flavor-gallery__item img {
	transition: transform var(--flavor-slow) var(--flavor-m-signature),
	            filter var(--flavor-slow) var(--flavor-m-signature);
}

.flavor-skin-pizza-italian .flavor-gallery__item:hover img {
	filter: saturate(1.18) brightness(1.05);
}

@keyframes flavor-m-pi-dough {
	0%   { opacity: 0; transform: scale(0.9) translate3d(0, 14px, 0); }
	64%  { opacity: 1; transform: scale(1.035); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-pi-sway {
	0%, 100% { transform: rotate(-4deg); }
	50%      { transform: rotate(4deg); }
}

@keyframes flavor-m-pi-layer {
	from { opacity: 0; transform: translate3d(0, -16px, 0) scale(0.9); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-pi-toss {
	0%   { opacity: 0; transform: rotate(-160deg) scale(0.5); }
	70%  { opacity: 1; transform: rotate(12deg) scale(1.08); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-pi-ember {
	0%, 100% { transform: scale(1); filter: brightness(1); }
	50%      { transform: scale(1.06); filter: brightness(1.14); }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-food-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-cat-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-intro__card.flavor-anim-in .flavor-intro__icon,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-section.flavor-anim-in .flavor-section-header__tag,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-hero__feat,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-food-card.flavor-anim-in .flavor-food-card__cat-badge,
	html.flavor-anim-ready .flavor-skin-pizza-italian .flavor-offer-banner.flavor-anim-in .flavor-offer-banner__badge,
	.flavor-skin-pizza-italian .flavor-food-card::before,
	.flavor-skin-pizza-italian .flavor-cat-card::before,
	.flavor-skin-pizza-italian .flavor-food-card,
	.flavor-skin-pizza-italian .flavor-food-card:hover,
	.flavor-skin-pizza-italian .flavor-gallery__item img,
	.flavor-skin-pizza-italian .flavor-gallery__item:hover img {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		opacity: 1 !important;
	}

	/* The tricolore stays as a static band on hover: it is brand identity, not
	   decoration, so removing it would cost meaning. */
	.flavor-skin-pizza-italian .flavor-food-card:hover::before,
	.flavor-skin-pizza-italian .flavor-food-card:focus-within::before,
	.flavor-skin-pizza-italian .flavor-cat-card:hover::before {
		transform: scaleX(1);
	}
}
"""

BLOCKS["bakery-pastry"] = """
/* ================================================================== */
/*  Motion art direction — patisserie & bakery                        */
/*                                                                     */
/*  Signature: soft rise and gentle squish. Cards prove like a soufflé,*/
/*  buttons compress like a macaron, and pastel ornament drifts. Every */
/*  curve is round — sharp timing reads as brittle against a palette   */
/*  of macaron pink and pistachio.                                    */
/* ================================================================== */
body.flavor-skin-bakery-pastry {
	--flavor-m-signature: cubic-bezier(0.34, 1.34, 0.5, 1);
	--flavor-m-hero: cubic-bezier(0.33, 1, 0.68, 1);
	--flavor-m-spring: cubic-bezier(0.34, 1.4, 0.64, 1);
	--flavor-m-rise: 20px;
	--flavor-m-scale: 0.95;
	--flavor-m-step: 78ms;
	--flavor-base: 500ms;
	--flavor-slow: 760ms;
}

/* Soufflé rise: cards swell past their size and settle back. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-food-card.flavor-anim-in,
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-cat-card.flavor-anim-in {
	animation: flavor-m-bp-souffle 720ms var(--flavor-m-signature) both;
}

/* Macaron squish on press — the whole preset's tactile signature. */
.flavor-skin-bakery-pastry .flavor-btn,
.flavor-skin-bakery-pastry .flavor-food-card {
	transition: transform 180ms var(--flavor-m-spring);
}

.flavor-skin-bakery-pastry .flavor-btn:active,
.flavor-skin-bakery-pastry .flavor-food-card:active {
	transform: scale(0.94) translate3d(0, 2px, 0);
}

.flavor-skin-bakery-pastry .flavor-btn:hover {
	transform: translate3d(0, -3px, 0) scale(1.02);
}

.flavor-skin-bakery-pastry .flavor-hero__container {
	position: relative;
}

/* Sprinkles drift across the hero. Pure ornament behind the copy, and it
   never receives a pointer event. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-hero__container::before {
	content: "";
	position: absolute;
	inset: 0;
	background-image:
		radial-gradient(circle at 12% 18%, rgba(232, 158, 178, 0.55) 0 2.5px, transparent 3px),
		radial-gradient(circle at 34% 62%, rgba(163, 206, 176, 0.5) 0 2.5px, transparent 3px),
		radial-gradient(circle at 58% 28%, rgba(240, 205, 150, 0.55) 0 2.5px, transparent 3px),
		radial-gradient(circle at 76% 72%, rgba(200, 176, 224, 0.5) 0 2.5px, transparent 3px),
		radial-gradient(circle at 90% 40%, rgba(232, 158, 178, 0.45) 0 2.5px, transparent 3px);
	background-size: 100% 220%;
	animation: flavor-m-bp-sprinkle 16s linear infinite;
	pointer-events: none;
	opacity: 0.7;
}

/* Pastry-arch images soften and lift; they never hard-zoom. */
.flavor-skin-bakery-pastry .flavor-food-card__media img {
	transition: transform 900ms var(--flavor-m-hero), filter 900ms var(--flavor-m-hero);
}

.flavor-skin-bakery-pastry .flavor-food-card:hover .flavor-food-card__media img {
	transform: scale(1.06);
	filter: saturate(1.08) brightness(1.03);
}

/* The category badge is piped on like icing. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-food-card.flavor-anim-in .flavor-food-card__cat-badge {
	animation: flavor-m-bp-pipe 640ms var(--flavor-m-signature) 200ms both;
}

/* Review avatars bob gently, one after another. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-review-card.flavor-anim-in .flavor-review-card__avatar {
	animation: flavor-m-bp-bob 3.4s ease-in-out infinite;
}

/* Intro cards rise with a slight round tilt, like trays on a cooling rack. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-intro__card.flavor-anim-in {
	animation: flavor-m-bp-tray 640ms var(--flavor-m-signature) both;
}

/* Stats count up inside a soft halo that fades as the number lands. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-about__stat.flavor-anim-in {
	animation: flavor-m-bp-halo 900ms var(--flavor-m-hero) both;
}

/* Section titles rise softly, with no mask and no edge. */
html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-section.flavor-anim-in .flavor-section-header__title {
	animation: flavor-m-bp-rise var(--flavor-slow) var(--flavor-m-hero) both;
}

@keyframes flavor-m-bp-souffle {
	0%   { opacity: 0; transform: scale(0.9) translate3d(0, 18px, 0); }
	58%  { opacity: 1; transform: scale(1.045) translate3d(0, -3px, 0); }
	80%  { transform: scale(0.995); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-bp-sprinkle {
	from { background-position: 0 0; }
	to   { background-position: 0 220%; }
}

@keyframes flavor-m-bp-pipe {
	0%   { opacity: 0; transform: scale(0.3) translate3d(0, -8px, 0); }
	62%  { opacity: 1; transform: scale(1.14); }
	100% { opacity: 1; transform: none; }
}

@keyframes flavor-m-bp-bob {
	0%, 100% { transform: translate3d(0, 0, 0); }
	50%      { transform: translate3d(0, -5px, 0); }
}

@keyframes flavor-m-bp-tray {
	from { opacity: 0; transform: translate3d(0, 22px, 0) rotate(-1.4deg); }
	to   { opacity: 1; transform: none; }
}

@keyframes flavor-m-bp-halo {
	0%   { opacity: 0; transform: scale(0.94); box-shadow: 0 0 0 0 rgba(232, 158, 178, 0.4); }
	60%  { opacity: 1; box-shadow: 0 0 0 12px rgba(232, 158, 178, 0); }
	100% { opacity: 1; transform: none; box-shadow: 0 0 0 0 rgba(232, 158, 178, 0); }
}

@keyframes flavor-m-bp-rise {
	from { opacity: 0; transform: translate3d(0, 16px, 0) scale(0.985); }
	to   { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-food-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-cat-card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-hero__container::before,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-food-card.flavor-anim-in .flavor-food-card__cat-badge,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-review-card.flavor-anim-in .flavor-review-card__avatar,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-intro__card.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-about__stat.flavor-anim-in,
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-section.flavor-anim-in .flavor-section-header__title,
	.flavor-skin-bakery-pastry .flavor-btn,
	.flavor-skin-bakery-pastry .flavor-food-card,
	.flavor-skin-bakery-pastry .flavor-btn:active,
	.flavor-skin-bakery-pastry .flavor-btn:hover,
	.flavor-skin-bakery-pastry .flavor-food-card:active,
	.flavor-skin-bakery-pastry .flavor-food-card__media img,
	.flavor-skin-bakery-pastry .flavor-food-card:hover .flavor-food-card__media img {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		box-shadow: none !important;
		opacity: 1 !important;
	}

	/* Sprinkles are pure decoration; with motion off they are removed rather
	   than frozen, so no static speckle sits over the hero copy. */
	html.flavor-anim-ready .flavor-skin-bakery-pastry .flavor-hero__container::before {
		display: none !important;
	}
}
"""

for slug, block in BLOCKS.items():
    path = SKINS / f"{slug}.css"
    text = path.read_text(encoding="utf-8")
    if "Motion art direction" in text:
        print(f"SKIP {slug}: motion block already present")
        continue
    if not text.endswith("\n"):
        text += "\n"
    path.write_text(text + block.lstrip("\n"), encoding="utf-8")
    print(f"OK   {slug}: +{len(block.strip().splitlines())} lines")
