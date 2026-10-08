#!/usr/bin/env python3
"""Append a tailored motion art-direction block to the five bespoke skins.

These five render the `fd-*` landing templates, so they gate on
`html.fd-anim-ready` — the class demo-animations.js sets — rather than on
`flavor-anim-ready`. Both classes are present on a bespoke page (motion.js still
drives the shared commerce surfaces there), but each block keys off the script
that actually owns the elements it animates.

Three collisions were checked before writing, because each skin already spends
one of the two pseudo-elements:

  juice-bar   .fd-hero__art::before        -> liquid wipe uses ::after
  dark-luxe   .fd-noir-art__frame::before  -> light sweep uses ::after
  dark-luxe   .fd-product (glow)::before   -> copper edge uses ::after

And `.fd-hero__image` is owned by the parallax/tilt compositor in
demo-animations.css, so no skin here writes `transform` to it directly; the
juice-bar liquid wipe and the noir sweep are overlays instead.
"""
from pathlib import Path

SKINS = Path("flavor/assets/css/skins")
BLOCKS = {}

BLOCKS["juice-bar"] = """
/* ================================================================== */
/*  Motion art direction — juice bar / limo                           */
/*                                                                     */
/*  Signature: liquid. A citrus wash fills the hero arch from the      */
/*  bottom, bubbles rise through the freshness strip, and the fresh    */
/*  seal swings on its pin. Everything is round and buoyant; nothing   */
/*  in this preset moves in a straight line for long.                  */
/* ================================================================== */
body.flavor-skin-juice-bar {
	--fd-m-signature: cubic-bezier(0.34, 1.42, 0.5, 1);
	--fd-m-liquid: cubic-bezier(0.4, 0, 0.2, 1);
	--fd-m-rise: 22px;
	--fd-m-step: 80ms;
}

/* Liquid fill: a citrus gradient rises inside the hero arch and drains away.
   .fd-hero__art::before is already the outline ring this skin draws, so the
   wash goes on ::after and never receives a pointer event. */
.flavor-skin-juice-bar .fd-hero__art::after {
	content: "";
	position: absolute;
	inset: 0;
	border-radius: 180px 180px 36px 36px;
	background: linear-gradient(to top, rgba(244, 215, 139, 0.5), rgba(220, 233, 156, 0.34) 58%, transparent 82%);
	transform: translateY(101%);
	pointer-events: none;
	z-index: 1;
	mix-blend-mode: multiply;
}

html.fd-anim-ready .flavor-skin-juice-bar .fd-hero__art::after {
	animation: fd-m-jb-fill 1.5s var(--fd-m-liquid) 240ms both;
}

/* The fresh seal swings on its pin. Its resting state is rotate(-13deg), so
   every keyframe carries that offset — animating from 0 would snap the seal
   upright and break the composition the skin set up. */
html.fd-anim-ready .flavor-skin-juice-bar .fd-fresh-seal {
	animation: fd-m-jb-swing 4.6s ease-in-out 900ms infinite;
	transform-origin: 50% 12%;
}

/* Bubbles rise through the freshness strip, one per item, offset. */
.flavor-skin-juice-bar .fd-freshness__item {
	position: relative;
}

.flavor-skin-juice-bar .fd-freshness__icon {
	position: relative;
}

html.fd-anim-ready .flavor-skin-juice-bar .fd-freshness__item.fd-anim-visible .fd-freshness__icon::after {
	content: "";
	position: absolute;
	inset-inline-start: 22%;
	bottom: 10%;
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.55);
	animation: fd-m-jb-bubble 3.1s ease-in-out infinite;
	pointer-events: none;
}

html.fd-anim-ready .flavor-skin-juice-bar .fd-freshness__item.fd-anim-visible:nth-child(2) .fd-freshness__icon::after {
	animation-delay: 0.7s;
}

html.fd-anim-ready .flavor-skin-juice-bar .fd-freshness__item.fd-anim-visible:nth-child(3) .fd-freshness__icon::after {
	animation-delay: 1.4s;
}

/* Products bob in like fruit dropped into water: up past rest, then settle. */
html.fd-anim-ready .flavor-skin-juice-bar .fd-product.fd-anim-visible {
	animation: fd-m-jb-bob 620ms var(--fd-m-signature) both;
}

/* The round product image pours into its frame rather than fading. */
.flavor-skin-juice-bar .fd-product__media {
	overflow: hidden;
}

.flavor-skin-juice-bar .fd-product:hover .fd-product__media img {
	transform: scale(1.07) rotate(1.4deg);
	transition: transform 720ms var(--fd-m-liquid);
}

/* The feature card's circular image turns slowly, like a glass on a bar mat. */
html.fd-anim-ready .flavor-skin-juice-bar .fd-juice-feature__card.fd-anim-visible img {
	animation: fd-m-jb-pour 1.3s var(--fd-m-liquid) both;
}

.flavor-skin-juice-bar .fd-juice-feature__card:hover img {
	transform: rotate(7deg) scale(1.03);
	transition: transform 900ms var(--fd-m-liquid);
}

/* The hero caption floats; this skin gives it a little more travel than the
   shared layer, because the caption sits over a rounded arch. */
html.fd-anim-ready .flavor-skin-juice-bar .fd-hero__caption {
	animation-duration: 5.2s;
}

/* Brand mark squishes on press, like a citrus half. */
.flavor-skin-juice-bar .fd-brand__mark {
	transition: transform 220ms var(--fd-m-signature);
}

.flavor-skin-juice-bar .fd-brand__mark:hover {
	transform: scale(1.08) rotate(-4deg);
}

@keyframes fd-m-jb-fill {
	0%   { transform: translateY(101%); opacity: 0; }
	38%  { opacity: 1; }
	72%  { transform: translateY(0); opacity: 0.9; }
	100% { transform: translateY(0); opacity: 0; }
}

@keyframes fd-m-jb-swing {
	0%, 100% { transform: rotate(-13deg); }
	28%      { transform: rotate(-6deg); }
	56%      { transform: rotate(-17deg); }
	78%      { transform: rotate(-11deg); }
}

@keyframes fd-m-jb-bubble {
	0%   { opacity: 0; transform: translate3d(0, 6px, 0) scale(0.5); }
	22%  { opacity: 0.85; }
	70%  { opacity: 0.4; }
	100% { opacity: 0; transform: translate3d(-5px, -26px, 0) scale(1.15); }
}

@keyframes fd-m-jb-bob {
	0%   { opacity: 0; transform: translate3d(0, 26px, 0) scale(0.94); }
	56%  { opacity: 1; transform: translate3d(0, -7px, 0) scale(1.02); }
	78%  { transform: translate3d(0, 2px, 0) scale(0.995); }
	100% { opacity: 1; transform: none; }
}

@keyframes fd-m-jb-pour {
	0%   { opacity: 0; transform: rotate(-14deg) scale(0.82); }
	62%  { opacity: 1; transform: rotate(4deg) scale(1.04); }
	100% { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.fd-anim-ready .flavor-skin-juice-bar .fd-hero__art::after,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-fresh-seal,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-freshness__item.fd-anim-visible .fd-freshness__icon::after,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-product.fd-anim-visible,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-juice-feature__card.fd-anim-visible img,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-hero__caption,
	.flavor-skin-juice-bar .fd-product:hover .fd-product__media img,
	.flavor-skin-juice-bar .fd-juice-feature__card:hover img,
	.flavor-skin-juice-bar .fd-brand__mark,
	.flavor-skin-juice-bar .fd-brand__mark:hover {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		opacity: 1 !important;
	}

	/* The seal keeps the rotation the skin gave it; only the swing stops. */
	html.fd-anim-ready .flavor-skin-juice-bar .fd-fresh-seal {
		transform: rotate(-13deg) !important;
	}

	/* The liquid wash and the bubbles are ornament: remove them rather than
	   freeze them, so no gradient sits permanently over the photography. */
	html.fd-anim-ready .flavor-skin-juice-bar .fd-hero__art::after,
	html.fd-anim-ready .flavor-skin-juice-bar .fd-freshness__item.fd-anim-visible .fd-freshness__icon::after {
		display: none !important;
	}
}
"""

BLOCKS["dark-luxe"] = """
/* ================================================================== */
/*  Motion art direction — noir / dark luxe                           */
/*                                                                     */
/*  Signature: cinema. A letterbox closes and opens on the hero frame, */
/*  a single copper light sweeps each plate, and a fine grain sits     */
/*  over the artwork. Timing is long and unhurried — noir is patient.  */
/*  Every curve is an ease-out; nothing in this preset bounces.        */
/* ================================================================== */
body.flavor-skin-dark-luxe {
	--fd-m-signature: cubic-bezier(0.16, 1, 0.3, 1);
	--fd-m-noir: cubic-bezier(0.65, 0, 0.35, 1);
	--fd-m-rise: 18px;
	--fd-m-step: 110ms; /* the slowest beat of the twelve: ceremony */
	--fd-m-copper: rgba(209, 165, 134, 0.4);
}

/* .fd-noir-art__frame::before is already the copper picture rail this skin
   draws, so the light sweep rides on ::after and sits above the image. */
.flavor-skin-dark-luxe .fd-noir-art__frame::after {
	content: "";
	position: absolute;
	inset: 0;
	z-index: 3;
	background: linear-gradient(104deg, transparent 36%, var(--fd-m-copper) 50%, transparent 64%);
	transform: translate3d(-110%, 0, 0);
	pointer-events: none;
	mix-blend-mode: screen;
}

html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-art__frame.fd-anim-visible::after,
html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art .fd-noir-art__frame::after {
	animation: fd-m-dl-sweep 2.6s var(--fd-m-noir) 500ms both;
}

/* Letterbox: two bars close over the hero art and open again, like a shot
   being framed. Purely ornamental, and it never covers a control. */
html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::before,
html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::after {
	content: "";
	position: absolute;
	inset-inline: 0;
	height: 11%;
	background: #0e1116;
	z-index: 4;
	pointer-events: none;
	animation: fd-m-dl-letterbox 1.9s var(--fd-m-noir) both;
}

html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::before { inset-block-start: 0; transform-origin: 50% 0; }
html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::after  { inset-block-end: 0; transform-origin: 50% 100%; }

.flavor-skin-dark-luxe .fd-hero__art {
	position: relative;
}

/* Fine grain over the artwork. A tiny repeating gradient, shifted on a long
   loop; it reads as film stock rather than as a moving pattern. */
.flavor-skin-dark-luxe .fd-noir-art__frame > img,
.flavor-skin-dark-luxe .fd-product__media img,
.flavor-skin-dark-luxe .fd-story__media img {
	position: relative;
}

html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-experience {
	position: relative;
}

html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-experience::after {
	content: "";
	position: absolute;
	inset: 0;
	z-index: 0;
	background-image: repeating-conic-gradient(rgba(255, 255, 255, 0.028) 0% 0.0001%, transparent 0.0002% 0.0004%);
	background-size: 190px 190px;
	animation: fd-m-dl-grain 1.1s steps(3) infinite;
	pointer-events: none;
	opacity: 0.7;
}

/* Plates fade up on a long beat, and the copper edge draws with them.
   .fd-product::before is taken by the cursor glow, so the edge is ::after. */
.flavor-skin-dark-luxe .fd-product {
	position: relative;
	isolation: isolate;
}

.flavor-skin-dark-luxe .fd-product::after {
	content: "";
	position: absolute;
	inset-block: 0;
	inset-inline-start: -2px;
	width: 2px;
	background: linear-gradient(to bottom, transparent, var(--fd-m-copper), transparent);
	transform: scaleY(0);
	transform-origin: 50% 0;
	transition: transform 620ms var(--fd-m-signature);
	pointer-events: none;
	z-index: 2;
}

.flavor-skin-dark-luxe .fd-product:hover::after,
.flavor-skin-dark-luxe .fd-product:focus-within::after {
	transform: scaleY(1);
}

html.fd-anim-ready .flavor-skin-dark-luxe .fd-product.fd-anim-visible {
	animation: fd-m-dl-plate 900ms var(--fd-m-signature) both;
}

/* Process numerals are italic serif; they develop out of the dark like a
   print in a tray, rather than sliding. */
html.fd-anim-ready .flavor-skin-dark-luxe .fd-process__steps li.fd-anim-visible .fd-process__number {
	animation: fd-m-dl-develop 1.2s var(--fd-m-noir) both;
}

/* The booking panel's icon is the one bright thing on the page: it warms up
   slowly and holds, like a lamp being turned up. */
html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-booking.fd-anim-visible .fd-noir-booking__icon {
	animation: fd-m-dl-lamp 1.6s var(--fd-m-signature) 240ms both;
}

/* Noir meta line: the tracking-wide caption letters pace themselves in. */
html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-meta > span {
	animation: fd-m-dl-pace 1.1s var(--fd-m-signature) both;
}

/* Story values rule draws along with the section. */
html.fd-anim-ready .flavor-skin-dark-luxe .fd-story__grid > .fd-anim-visible .fd-story__values {
	animation: fd-m-dl-rule 1.1s var(--fd-m-signature) 200ms both;
}

@keyframes fd-m-dl-sweep {
	0%   { transform: translate3d(-110%, 0, 0); opacity: 0; }
	18%  { opacity: 0.85; }
	100% { transform: translate3d(110%, 0, 0); opacity: 0; }
}

@keyframes fd-m-dl-letterbox {
	0%   { transform: scaleY(0); }
	26%  { transform: scaleY(1); }
	68%  { transform: scaleY(1); }
	100% { transform: scaleY(0); }
}

@keyframes fd-m-dl-grain {
	0%   { background-position: 0 0; }
	33%  { background-position: -38px 26px; }
	66%  { background-position: 24px -32px; }
	100% { background-position: 0 0; }
}

@keyframes fd-m-dl-plate {
	from { opacity: 0; transform: translate3d(0, 20px, 0); filter: brightness(0.72); }
	to   { opacity: 1; transform: none; filter: none; }
}

@keyframes fd-m-dl-develop {
	0%   { opacity: 0; filter: blur(6px); transform: translate3d(0, 8px, 0); }
	100% { opacity: 1; filter: none; transform: none; }
}

@keyframes fd-m-dl-lamp {
	0%   { opacity: 0.2; filter: brightness(0.6); transform: scale(0.94); }
	70%  { opacity: 1; filter: brightness(1.18); }
	100% { opacity: 1; filter: none; transform: none; }
}

@keyframes fd-m-dl-pace {
	from { opacity: 0; letter-spacing: 0.42em; }
	to   { opacity: 1; letter-spacing: 0.22em; }
}

@keyframes fd-m-dl-rule {
	from { opacity: 0; transform: scaleX(0.4); }
	to   { opacity: 1; transform: scaleX(1); }
}

@media (prefers-reduced-motion: reduce) {
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-art__frame::after,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::before,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::after,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-experience::after,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-product.fd-anim-visible,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-process__steps li.fd-anim-visible .fd-process__number,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-booking.fd-anim-visible .fd-noir-booking__icon,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-meta > span,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-story__grid > .fd-anim-visible .fd-story__values,
	.flavor-skin-dark-luxe .fd-product::after {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		opacity: 1 !important;
		letter-spacing: 0.22em !important;
	}

	/* Letterbox bars and film grain are ornament that would otherwise sit over
	   the photography permanently, so they are removed, not frozen. */
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::before,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-hero__art::after,
	html.fd-anim-ready .flavor-skin-dark-luxe .fd-noir-experience::after {
		display: none !important;
	}

	/* The copper edge is identity, so it stays drawn on hover without moving. */
	.flavor-skin-dark-luxe .fd-product:hover::after,
	.flavor-skin-dark-luxe .fd-product:focus-within::after {
		transform: scaleY(1);
	}
}
"""

BLOCKS["minimal-clean"] = """
/* ================================================================== */
/*  Motion art direction — minimal / gallery white                    */
/*                                                                     */
/*  Signature: precision. One hairline draws, one opacity step, and    */
/*  nothing else. No overshoot, no rotation, no scale — this preset is */
/*  about whitespace, so motion has to be the quietest of the twelve.  */
/*  Durations are short because a long fade on a white page reads as   */
/*  the site being slow rather than as restraint.                     */
/* ================================================================== */
body.flavor-skin-minimal-clean {
	--fd-m-signature: cubic-bezier(0.33, 1, 0.68, 1); /* zero overshoot */
	--fd-m-rise: 10px;   /* the smallest travel of any demo */
	--fd-m-step: 48ms;
	--fd-m-hair: 320ms;
}

/* The hero rule draws itself once, from the reading edge. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__copy h1::after {
	content: "";
	display: block;
	width: 64px;
	height: 1px;
	background: var(--flavor-ink);
	margin-top: 26px;
	transform: scaleX(0);
	transform-origin: inline-start;
	animation: fd-m-mc-hairline var(--fd-m-hair) var(--fd-m-signature) 220ms both;
}

html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__copy h1 {
	position: relative;
}

/* One opacity step. No transform at all: on a pure-white page even a 10px
   slide reads as movement, and this preset is meant to feel static. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__art.fd-anim-enter,
html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-experience.fd-anim-enter,
html.fd-anim-ready .flavor-skin-minimal-clean .fd-product.fd-anim-enter,
html.fd-anim-ready .flavor-skin-minimal-clean .fd-section.fd-anim-enter {
	transform: none;
}

/* The caption rule under the hero image extends as the section lands. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__art.fd-anim-visible figcaption {
	animation: fd-m-mc-rule 620ms var(--fd-m-signature) both;
}

/* Product images resolve rather than zoom: a very small scale, over a long
   duration, which reads as focus pulling instead of as movement. */
.flavor-skin-minimal-clean .fd-product__media img {
	transition: transform 1.1s var(--fd-m-signature), filter 1.1s var(--fd-m-signature);
}

.flavor-skin-minimal-clean .fd-product:hover .fd-product__media img {
	transform: scale(1.022);
	filter: saturate(0.94);
}

/* The order button is a hairline circle; on hover the line closes around it. */
.flavor-skin-minimal-clean .fd-product__order {
	transition: transform 240ms var(--fd-m-signature), background-color 240ms var(--fd-m-signature), color 240ms var(--fd-m-signature);
}

.flavor-skin-minimal-clean .fd-product__order:hover {
	transform: scale(1.06);
}

/* Filter chips: the pressed one gets a rule under it, drawn not faded. */
.flavor-skin-minimal-clean .fd-menu__filters button {
	position: relative;
}

.flavor-skin-minimal-clean .fd-menu__filters button::after {
	content: "";
	position: absolute;
	inset-inline: 12px;
	bottom: 4px;
	height: 1px;
	background: currentColor;
	transform: scaleX(0);
	transform-origin: inline-start;
	transition: transform 280ms var(--fd-m-signature);
}

.flavor-skin-minimal-clean .fd-menu__filters button[aria-pressed="true"]::after,
.flavor-skin-minimal-clean .fd-menu__filters button:hover::after {
	transform: scaleX(1);
}

/* Process numerals count in on a single hairline that draws beneath them. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-process__steps li.fd-anim-visible .fd-process__number {
	animation: fd-m-mc-numeral 560ms var(--fd-m-signature) both;
}

/* The prose block's leading rule extends with the section. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-section.fd-anim-visible .fd-prose {
	animation: fd-m-mc-rule 700ms var(--fd-m-signature) 120ms both;
}

/* Experience hours: a single quiet fade, no travel. */
html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-experience.fd-anim-visible .fd-form-experience__hours {
	animation: fd-m-mc-fade 620ms var(--fd-m-signature) 200ms both;
}

@keyframes fd-m-mc-hairline {
	from { transform: scaleX(0); }
	to   { transform: scaleX(1); }
}

@keyframes fd-m-mc-rule {
	from { opacity: 0; transform: scaleX(0.55); transform-origin: inline-start; }
	to   { opacity: 1; transform: scaleX(1); }
}

@keyframes fd-m-mc-numeral {
	from { opacity: 0; }
	to   { opacity: 1; }
}

@keyframes fd-m-mc-fade {
	from { opacity: 0; }
	to   { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__copy h1::after,
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__art.fd-anim-visible figcaption,
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-process__steps li.fd-anim-visible .fd-process__number,
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-section.fd-anim-visible .fd-prose,
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-experience.fd-anim-visible .fd-form-experience__hours,
	.flavor-skin-minimal-clean .fd-product__media img,
	.flavor-skin-minimal-clean .fd-product:hover .fd-product__media img,
	.flavor-skin-minimal-clean .fd-product__order,
	.flavor-skin-minimal-clean .fd-product__order:hover,
	.flavor-skin-minimal-clean .fd-menu__filters button::after {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		filter: none !important;
		opacity: 1 !important;
	}

	/* The rules are identity, so they stay drawn; only the drawing stops. */
	html.fd-anim-ready .flavor-skin-minimal-clean .fd-form-hero__copy h1::after,
	.flavor-skin-minimal-clean .fd-menu__filters button[aria-pressed="true"]::after {
		transform: scaleX(1) !important;
	}
}
"""

BLOCKS["cloud-kitchen"] = """
/* ================================================================== */
/*  Motion art direction — cloud kitchen / blueprint                  */
/*                                                                     */
/*  Signature: an operations console booting up. Blueprint grids draw  */
/*  themselves in, a waypoint dot travels the delivery route, panels   */
/*  get one scan-line pass, and coverage chips pop in like a status    */
/*  board filling. Timing is crisp and slightly mechanical.            */
/* ================================================================== */
body.flavor-skin-cloud-kitchen {
	--fd-m-signature: cubic-bezier(0.22, 1, 0.36, 1);
	--fd-m-machine: cubic-bezier(0.65, 0, 0.35, 1);
	--fd-m-rise: 16px;
	--fd-m-step: 55ms; /* a status board fills fast */
	--fd-m-lemon: #d7f468;
}

/* Blueprint grid draws itself behind the pack hero. Two repeating gradients
   masked by a scaling box, so the grid appears to be plotted rather than
   faded in. */
.flavor-skin-cloud-kitchen .fd-pack-hero {
	position: relative;
	isolation: isolate;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-hero::before {
	content: "";
	position: absolute;
	inset: 0;
	z-index: 0;
	background-image:
		linear-gradient(to right, rgba(37, 70, 148, 0.075) 1px, transparent 1px),
		linear-gradient(to bottom, rgba(37, 70, 148, 0.075) 1px, transparent 1px);
	background-size: 34px 34px;
	animation: fd-m-ck-plot 1.5s var(--fd-m-machine) both;
	pointer-events: none;
}

/* One scan-line pass over the pack art, like a console reading a tray. */
.flavor-skin-cloud-kitchen .fd-pack-art {
	isolation: isolate;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art::after {
	content: "";
	position: absolute;
	inset: 18px;
	z-index: 2;
	border-radius: 23px;
	background: linear-gradient(to bottom, transparent 42%, rgba(215, 244, 104, 0.42) 50%, transparent 58%);
	transform: translate3d(0, -104%, 0);
	animation: fd-m-ck-scan 1.5s var(--fd-m-machine) 420ms both;
	pointer-events: none;
}

/* The lemon stamp slams into the corner and holds, like a dispatch label. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art__stamp {
	animation: fd-m-ck-stamp 520ms var(--fd-m-signature) 260ms both;
}

/* The status icon boots: a short flicker, then steady. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art__icon {
	animation: fd-m-ck-boot 720ms steps(6) 380ms both;
}

/* Waypoint dot travels the delivery route under the pack caption. */
.flavor-skin-cloud-kitchen .fd-pack-art figcaption {
	position: relative;
	overflow: hidden;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art figcaption::before {
	content: "";
	position: absolute;
	inset-block-end: 0;
	inset-inline: 0;
	height: 2px;
	background: repeating-linear-gradient(to left, var(--flavor-primary) 0 6px, transparent 6px 12px);
	opacity: 0.35;
	pointer-events: none;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art figcaption::after {
	content: "";
	position: absolute;
	inset-block-end: -3px;
	inset-inline-start: 0;
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: var(--fd-m-lemon);
	box-shadow: 0 0 0 3px rgba(215, 244, 104, 0.28);
	animation: fd-m-ck-route 4.4s var(--fd-m-machine) 900ms infinite;
	pointer-events: none;
}

/* Coverage chips pop in one after another, like a board reporting readiness. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li {
	animation: fd-m-ck-chip 380ms var(--fd-m-signature) both;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(1) { animation-delay: 120ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(2) { animation-delay: 175ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(3) { animation-delay: 230ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(4) { animation-delay: 285ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(5) { animation-delay: 340ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li:nth-child(n+6) { animation-delay: 395ms; }

/* The zone-check result flips its state colour with a short mechanical step. */
.flavor-skin-cloud-kitchen .fd-coverage__result {
	transition: color 220ms var(--fd-m-machine), background-color 220ms var(--fd-m-machine), border-color 220ms var(--fd-m-machine);
}

.flavor-skin-cloud-kitchen .fd-coverage__result[data-available="pending"] {
	animation: fd-m-ck-pending 1.05s steps(2) infinite;
}

/* Team cards slide in on the grid line rather than floating up. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-team__card.fd-anim-visible {
	animation: fd-m-ck-slide 480ms var(--fd-m-signature) both;
}

/* Hero perks report in like a checklist being ticked. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-hero__perks li {
	animation: fd-m-ck-chip 420ms var(--fd-m-signature) both;
}

html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-hero__perks li:nth-child(1) { animation-delay: 420ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-hero__perks li:nth-child(2) { animation-delay: 500ms; }
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-hero__perks li:nth-child(3) { animation-delay: 580ms; }

/* Process numerals step on like a display incrementing. */
html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-process__steps li.fd-anim-visible .fd-process__number {
	animation: fd-m-ck-boot 540ms steps(5) both;
}

@keyframes fd-m-ck-plot {
	0%   { opacity: 0; transform: scale(1.14); }
	60%  { opacity: 1; }
	100% { opacity: 1; transform: scale(1); }
}

@keyframes fd-m-ck-scan {
	0%   { transform: translate3d(0, -104%, 0); opacity: 0; }
	12%  { opacity: 1; }
	88%  { opacity: 1; }
	100% { transform: translate3d(0, 104%, 0); opacity: 0; }
}

@keyframes fd-m-ck-stamp {
	0%   { opacity: 0; transform: translate3d(-26px, -22px, 0) rotate(-9deg) scale(1.3); }
	64%  { opacity: 1; transform: translate3d(2px, 2px, 0) rotate(1deg) scale(0.98); }
	100% { opacity: 1; transform: none; }
}

@keyframes fd-m-ck-boot {
	0%   { opacity: 0.15; }
	30%  { opacity: 0.9; }
	45%  { opacity: 0.3; }
	70%  { opacity: 1; }
	100% { opacity: 1; }
}

@keyframes fd-m-ck-route {
	/* RTL: the dot runs from the inline-start edge toward the inline-end. */
	0%   { inset-inline-start: 0; opacity: 0; }
	8%   { opacity: 1; }
	88%  { opacity: 1; }
	100% { inset-inline-start: calc(100% - 8px); opacity: 0; }
}

@keyframes fd-m-ck-chip {
	from { opacity: 0; transform: translate3d(0, -8px, 0) scale(0.9); }
	to   { opacity: 1; transform: none; }
}

@keyframes fd-m-ck-pending {
	0%, 100% { opacity: 1; }
	50%      { opacity: 0.45; }
}

@keyframes fd-m-ck-slide {
	from { opacity: 0; transform: translate3d(-18px, 0, 0); }
	to   { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-hero::before,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art::after,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art__stamp,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art__icon,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art figcaption::after,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-coverage__card.fd-anim-visible .fd-coverage__hoods li,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-team__card.fd-anim-visible,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-hero__perks li,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-process__steps li.fd-anim-visible .fd-process__number,
	.flavor-skin-cloud-kitchen .fd-coverage__result,
	.flavor-skin-cloud-kitchen .fd-coverage__result[data-available="pending"] {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		opacity: 1 !important;
	}

	/* The scan line and the travelling waypoint are ornament that would
	   otherwise park over the artwork, so they are removed rather than frozen. */
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art::after,
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-art figcaption::after {
		display: none !important;
	}

	/* The blueprint grid and the dashed route line are identity; they stay. */
	html.fd-anim-ready .flavor-skin-cloud-kitchen .fd-pack-hero::before {
		opacity: 1 !important;
		transform: none !important;
	}
}
"""

BLOCKS["catering"] = """
/* ================================================================== */
/*  Motion art direction — catering / formal brass                    */
/*                                                                     */
/*  Signature: a dossier being laid out. Brass rules expand to their   */
/*  full width, panels arrive in a measured sequence, and the hero     */
/*  seal is pressed on last — the way a document is signed and then    */
/*  stamped. Timing is formal: long, even, and never bouncy.           */
/* ================================================================== */
body.flavor-skin-catering {
	--fd-m-signature: cubic-bezier(0.22, 1, 0.36, 1);
	--fd-m-formal: cubic-bezier(0.33, 0, 0.15, 1);
	--fd-m-rise: 16px;
	--fd-m-step: 100ms; /* a formal sequence is deliberately unhurried */
	--fd-m-brass: #997541;
}

/* The caption rule expands to its full 34px width, from the reading edge. */
.flavor-skin-catering .fd-mizan-hero__caption-line {
	transform-origin: inline-start;
}

html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__caption-line {
	animation: fd-m-ct-brass 820ms var(--fd-m-signature) 260ms both;
}

/* The seal is pressed on last. It lands heavy and does not bounce: wax, not
   rubber. This is the final beat of the hero sequence. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__seal {
	animation: fd-m-ct-seal 780ms var(--fd-m-formal) 900ms both;
}

/* The signature line draws itself, as if being written. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__signature {
	animation: fd-m-ct-sign 1.1s var(--fd-m-signature) 620ms both;
}

/* Hero art is unveiled from behind a brass edge rather than fading in. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__art {
	animation: fd-m-ct-unveil 1.2s var(--fd-m-formal) 180ms both;
}

/* Section heads get a rule that expands beneath them as the section lands. */
.flavor-skin-catering .fd-section-head {
	position: relative;
}

html.fd-anim-ready .flavor-skin-catering .fd-section.fd-anim-visible .fd-section-head::after {
	content: "";
	position: absolute;
	inset-inline: 0;
	bottom: -14px;
	height: 1px;
	background: linear-gradient(to left, var(--fd-m-brass), rgba(153, 117, 65, 0.16) 62%, transparent);
	transform: scaleX(0);
	transform-origin: inline-start;
	animation: fd-m-ct-rule 940ms var(--fd-m-signature) both;
	pointer-events: none;
}

/* Service rows arrive as a dossier: overline, then title, then body. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__overline {
	animation: fd-m-ct-line 520ms var(--fd-m-signature) both;
}

html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__title {
	animation: fd-m-ct-line 620ms var(--fd-m-signature) 110ms both;
}

html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__body {
	animation: fd-m-ct-line 680ms var(--fd-m-signature) 220ms both;
}

/* Service media is framed: the brass border closes around it. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__media {
	animation: fd-m-ct-frame 900ms var(--fd-m-formal) 140ms both;
}

/* The proposal panel is the decision point, so it settles last and heaviest. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible {
	animation: fd-m-ct-panel 1s var(--fd-m-formal) both;
}

html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-proposal__panel-head {
	animation: fd-m-ct-line 620ms var(--fd-m-signature) 260ms both;
}

/* Planner fields light up in reading order, which is what makes a long
   corporate form feel like it is being filled in rather than dumped. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field {
	animation: fd-m-ct-field 460ms var(--fd-m-signature) both;
}

html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field:nth-child(1) { animation-delay: 320ms; }
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field:nth-child(2) { animation-delay: 390ms; }
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field:nth-child(3) { animation-delay: 460ms; }
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field:nth-child(4) { animation-delay: 530ms; }
html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field:nth-child(n+5) { animation-delay: 600ms; }

/* A focused field gets a brass underline that draws itself — the planner's
   own focus ring is untouched, this is an addition, not a replacement. */
.flavor-skin-catering .fd-mizan-planner__field input:focus-visible,
.flavor-skin-catering .fd-mizan-planner__field select:focus-visible,
.flavor-skin-catering .fd-mizan-planner__field textarea:focus-visible {
	animation: fd-m-ct-focus 320ms var(--fd-m-signature);
}

/* Process numerals are set in brass and arrive on the same formal beat. */
html.fd-anim-ready .flavor-skin-catering .fd-process__steps li.fd-anim-visible .fd-process__number {
	animation: fd-m-ct-numeral 760ms var(--fd-m-formal) both;
}

/* Promises and summary settle quietly; they follow the panel, not lead it. */
html.fd-anim-ready .flavor-skin-catering .fd-mizan-promises li,
html.fd-anim-ready .flavor-skin-catering .fd-mizan-summary {
	animation: fd-m-ct-line 620ms var(--fd-m-signature) both;
}

@keyframes fd-m-ct-brass {
	from { transform: scaleX(0); opacity: 0; }
	to   { transform: scaleX(1); opacity: 1; }
}

@keyframes fd-m-ct-seal {
	0%   { opacity: 0; transform: scale(1.7) rotate(11deg); }
	58%  { opacity: 1; transform: scale(0.97) rotate(-1.5deg); }
	100% { opacity: 1; transform: none; }
}

@keyframes fd-m-ct-sign {
	from { opacity: 0; transform: translate3d(-14px, 0, 0); clip-path: inset(0 100% 0 0); }
	70%  { clip-path: inset(0 0 0 0); }
	to   { opacity: 1; transform: none; clip-path: inset(0 0 0 0); }
}

@keyframes fd-m-ct-unveil {
	from { opacity: 0; clip-path: inset(0 0 0 100%); }
	to   { opacity: 1; clip-path: inset(0 0 0 0); }
}

@keyframes fd-m-ct-rule {
	from { transform: scaleX(0); }
	to   { transform: scaleX(1); }
}

@keyframes fd-m-ct-line {
	from { opacity: 0; transform: translate3d(0, 9px, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes fd-m-ct-frame {
	from { opacity: 0; transform: scale(1.035); clip-path: inset(4% 4% 4% 4%); }
	to   { opacity: 1; transform: none; clip-path: inset(0 0 0 0); }
}

@keyframes fd-m-ct-panel {
	from { opacity: 0; transform: translate3d(0, 22px, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes fd-m-ct-field {
	from { opacity: 0; transform: translate3d(12px, 0, 0); }
	to   { opacity: 1; transform: none; }
}

@keyframes fd-m-ct-focus {
	from { box-shadow: 0 0 0 0 rgba(153, 117, 65, 0.4); }
	to   { box-shadow: 0 0 0 4px rgba(153, 117, 65, 0); }
}

@keyframes fd-m-ct-numeral {
	from { opacity: 0; transform: translate3d(0, 12px, 0); letter-spacing: 0.3em; }
	to   { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__caption-line,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__seal,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__signature,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__art,
	html.fd-anim-ready .flavor-skin-catering .fd-section.fd-anim-visible .fd-section-head::after,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__overline,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__title,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__body,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-service.fd-anim-visible .fd-mizan-service__media,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-proposal__panel-head,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-proposal__panel.fd-anim-visible .fd-mizan-planner__field,
	html.fd-anim-ready .flavor-skin-catering .fd-process__steps li.fd-anim-visible .fd-process__number,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-promises li,
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-summary,
	.flavor-skin-catering .fd-mizan-planner__field input:focus-visible,
	.flavor-skin-catering .fd-mizan-planner__field select:focus-visible,
	.flavor-skin-catering .fd-mizan-planner__field textarea:focus-visible {
		animation: none !important;
		transition: none !important;
		transform: none !important;
		clip-path: none !important;
		opacity: 1 !important;
	}

	/* Brass rules are identity: they stay drawn at full width, they just do
	   not expand. */
	html.fd-anim-ready .flavor-skin-catering .fd-mizan-hero__caption-line,
	html.fd-anim-ready .flavor-skin-catering .fd-section.fd-anim-visible .fd-section-head::after {
		transform: scaleX(1) !important;
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
