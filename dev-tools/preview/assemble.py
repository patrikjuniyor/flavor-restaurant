#!/usr/bin/env python3
"""Assemble the self-contained motion preview.


Reads the pages rendered by render.php plus the theme's own stylesheets and
scripts, and writes one HTML file with everything inlined: no network, no
build step, no WordPress. The point is to be able to *watch* the motion layer
that ships in flavor/assets/css/motion.css and flavor/assets/js/motion.js,
against all twelve skins, without standing up a site.

Usage: python3 assemble.py [output.html]

Environment:
  FLAVOR_REPO           Theme checkout to read (default: two levels above this file)
  FLAVOR_PREVIEW_PAGES  Directory holding the per-skin JSON from render.php
"""

import base64
import json
import os
import re
import sys

REPO = os.environ.get('FLAVOR_REPO') or os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
THEME = os.path.join(REPO, 'flavor')
CSS_DIR = os.path.join(THEME, 'assets/css')
JS_DIR = os.path.join(THEME, 'assets/js')
FONT_DIR = os.path.join(THEME, 'assets/fonts')

SKINS = [
    'modern-restaurant', 'luxury-dining', 'persian-traditional', 'pizza-italian',
    'fast-food', 'cafe-bistro', 'bakery-pastry', 'juice-bar', 'dark-luxe',
    'minimal-clean', 'cloud-kitchen', 'catering',
]
BESPOKE = {'juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering'}

# The order the theme enqueues them in, so the cascade matches the real site.
BASE_CSS = [
    'fonts.css', 'main.css', 'marketing.css', 'rtl.css', 'ui.css', 'ui-menu.css',
    'ui-navigation.css', 'ui-pages.css', 'premium.css', 'dark-mode.css',
    'bespoke-demos.css', 'demo-animations.css', 'motion.css',
]

# main.js first: motion.js decorates the toast helper it defines.
JS_FILES = ['main.js', 'premium.js', 'motion.js']
# demo-animations.js returns early unless body carries `flavor-bespoke`, a class
# the theme adds for the bespoke five only. A page that starts on a classic demo
# and switches to a bespoke one therefore has to install this layer afterwards,
# exactly as a fresh page load would. The chrome below does that.
LAZY_JS = ['demo-animations.js']

# Vazirmatn Medium/Bold are folded onto the Regular file: the browser
# synthesises the weight, and 160 KB of near-identical binary is not worth
# carrying inside a preview whose subject is motion.
FONT_ALIASES = {
    'vazirmatn/Vazirmatn-Medium.woff2': 'vazirmatn/Vazirmatn-Regular.woff2',
    'vazirmatn/Vazirmatn-Bold.woff2': 'vazirmatn/Vazirmatn-Regular.woff2',
}


def read(path: str) -> str:
    with open(path, encoding='utf-8') as handle:
        return handle.read()


def strip_comments(css: str) -> str:
    """Remove /* … */ — the shipped sheets are heavily commented and the
    preview does not need to carry 200 KB of rationale."""
    return re.sub(r'/\*.*?\*/', '', css, flags=re.S)


def drop_duplicate_font_faces(css: str) -> str:
    """Remove @font-face blocks from a skin sheet.

    Six skin sheets redeclare the same Estedad file that fonts.css already
    declares. In a browser the second declaration is a cache hit; inlined as a
    data URI it is 171 KB of identical base64 per sheet, which is most of the
    preview's weight. The family is still declared once, in fonts.css.
    """
    out, index = [], 0
    for match in re.finditer(r'@font-face\s*\{', css):
        if match.start() < index:
            continue
        depth, cursor = 1, match.end()
        while cursor < len(css) and depth:
            if css[cursor] == '{':
                depth += 1
            elif css[cursor] == '}':
                depth -= 1
            cursor += 1
        out.append(css[index:match.start()])
        index = cursor
    out.append(css[index:])
    return ''.join(out)


def media_blocks(css: str, needle: str) -> list:
    """Brace-matched bodies of every @media block whose header contains `needle`."""
    out = []
    for match in re.finditer(r'@media[^{}]*\{', css):
        if needle not in match.group(0):
            continue
        depth, index = 1, match.end()
        while index < len(css) and depth:
            char = css[index]
            if char == '{':
                depth += 1
            elif char == '}':
                depth -= 1
            index += 1
        out.append(css[match.end():index - 1])
    return out


def inline_fonts(css: str, sheet_path: str) -> str:
    """Point every @font-face at a data URI so the file needs no asset folder.

    URLs are resolved against the stylesheet's own directory: a skin sheet
    lives two levels down, so "../../fonts/estedad/..." and "../fonts/..."
    have to land on the same file. A font the repository does not ship (the
    licensed IRANSans drop-in is a documented bring-your-own) loses its url()
    and keeps its local() fallback, which is what a browser with the font
    installed would use anyway.
    """
    cache = {}
    base = os.path.dirname(sheet_path)
    fonts_dir = os.path.join(THEME, 'assets', 'fonts')
    quote_chars = '\'"'
    pattern = re.compile(
        r'url\(([' + quote_chars + r']?)([^' + quote_chars + r'()]*fonts/'
        r'[^' + quote_chars + r'()]+)\1\)(?:\s*format\("woff2"\))?'
    )

    def replace(match):
        raw = match.group(2)
        path = os.path.normpath(os.path.join(base, raw))
        alias = FONT_ALIASES.get(os.path.relpath(path, fonts_dir))
        if alias:
            path = os.path.join(fonts_dir, alias)
        if path not in cache:
            if os.path.isfile(path):
                with open(path, 'rb') as handle:
                    cache[path] = base64.b64encode(handle.read()).decode('ascii')
            else:
                cache[path] = None
        if cache[path] is None:
            return 'local("IRANSans")'
        return 'url(data:font/woff2;base64,%s) format("woff2")' % cache[path]

    return pattern.sub(replace, css)



def placeholder_image(url: str) -> str:
    """A deterministic gradient stand-in for every photograph.

    The preview is a motion harness: it has to stay self-contained, and the
    demo JPGs are 12 MB. A hash of the original URL picks the hues, so the
    same dish keeps the same colour on every build and the entrance, scale and
    parallax effects still have something to move.
    """
    digest = abs(sum(ord(char) * (index + 7) for index, char in enumerate(url)))
    hue = digest % 360
    width, height = 1200, 800
    svg = (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d">'
        '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        '<stop offset="0" stop-color="hsl(%d 30%% 64%%)"/>'
        '<stop offset="1" stop-color="hsl(%d 38%% 34%%)"/>'
        '</linearGradient></defs>'
        '<rect width="%d" height="%d" fill="url(#g)"/>'
        '<circle cx="%d" cy="%d" r="%d" fill="hsl(%d 45%% 80%%)" opacity=".32"/>'
        '<circle cx="%d" cy="%d" r="%d" fill="hsl(%d 30%% 20%%)" opacity=".22"/>'
        '</svg>'
    ) % (
        width, height, hue, (hue + 34) % 360, width, height,
        int(width * 0.7), int(height * 0.42), int(min(width, height) * 0.3), hue,
        int(width * 0.24), int(height * 0.72), int(min(width, height) * 0.2), (hue + 34) % 360,
    )
    return 'data:image/svg+xml;base64,' + base64.b64encode(svg.encode('utf-8')).decode('ascii')


def neutralise(html: str) -> str:
    """Keep the visuals, drop every way out of the page.

    Links would navigate the preview away from itself and forms would reload
    it, so both are defused. Everything else is the template's own markup,
    untouched.
    """
    html = re.sub(r'src="https?://[^"]+"', lambda m: 'src="%s"' % placeholder_image(m.group(0)), html)
    html = re.sub(r'(<(?:a|link)\b[^>]*?)\shref="(?!#)[^"]*"', r'\1 href="#"', html)
    html = html.replace('<form ', '<form data-pv-form ')
    return html


PAGES = os.environ.get('FLAVOR_PREVIEW_PAGES', '/tmp')
OUTPUT_DEFAULT = os.path.join(REPO, 'flavor-motion-preview.html')


def load_page(skin: str) -> dict:
    data = json.loads(read(os.path.join(PAGES, 'page-%s.json' % skin)))
    page = data['parts']['page']
    if page['status'] != 'ok':
        raise SystemExit('render.php failed for %s: %s' % (skin, page.get('message')))

    match = re.search(r'<body([^>]*)>(.*)</body>', page['html'], re.S)
    attributes, inner = match.group(1), match.group(2)
    body_class = re.search(r'class="([^"]*)"', attributes)
    return {
        'html': neutralise(inner.strip()),
        'bodyClass': body_class.group(1) if body_class else 'flavor-theme',
        'extras': data['extras'],
        'cart': neutralise(data['parts']['cart_drawer']['html']),
        'kind': 'bespoke' if skin in BESPOKE else 'classic',
    }


def main() -> None:
    output = sys.argv[1] if len(sys.argv) > 1 else OUTPUT_DEFAULT

    pages = {skin: load_page(skin) for skin in SKINS}
    catalogue = json.loads(read(os.path.join(PAGES, 'page-modern-restaurant.json')))['skins']

    # ---- base stylesheets ------------------------------------------------
    base_css = []
    reduced_css = []
    for name in BASE_CSS:
        sheet_path = os.path.join(CSS_DIR, name)
        css = strip_comments(read(sheet_path))
        css = inline_fonts(css, sheet_path)
        reduced_css.extend(media_blocks(css, 'prefers-reduced-motion'))
        base_css.append('/* ===== %s ===== */\n%s' % (name, css))

    # ---- per-skin sheets and night palettes ------------------------------
    skin_blocks = []
    night_blocks = []
    for skin in SKINS:
        sheet_path = os.path.join(CSS_DIR, 'skins', '%s.css' % skin)
        sheet = drop_duplicate_font_faces(inline_fonts(strip_comments(read(sheet_path)), sheet_path))
        reduced_css.extend(media_blocks(sheet, 'prefers-reduced-motion'))
        skin_blocks.append('<style data-pv-skin="%s" disabled>%s</style>' % (skin, sheet))
        night_blocks.append(
            '<style data-pv-night="%s" disabled>%s</style>' % (skin, pages[skin]['extras']['night_css'])
        )

    # The reduced-motion rules the sheets already carry, lifted out of their
    # media query so a toggle can apply exactly them. Same declarations, no
    # reimplementation to drift.
    reduced_sheet = '\n'.join(block.strip() for block in reduced_css if block.strip())

    # ---- scripts ---------------------------------------------------------
    lazy = {}
    for name in LAZY_JS:
        lazy[name] = read(os.path.join(JS_DIR, name))

    scripts = []
    for name in JS_FILES:
        code = read(os.path.join(JS_DIR, name))
        # Each file is isolated: a theme script that leans on WordPress data
        # this preview has no counterpart for must not take the layer down.
        scripts.append(
            '<script>/* ===== %s ===== */\ntry {\n%s\n} catch (error) { console.warn("%s", error); }\n</script>'
            % (name, code.replace('</script', '<\\/script'), name)
        )

    payload = {
        skin: {
            'html': pages[skin]['html'],
            'bodyClass': pages[skin]['bodyClass'],
            'kind': pages[skin]['kind'],
            'title': catalogue[skin]['title'],
            'desc': catalogue[skin]['desc'],
        }
        for skin in SKINS
    }
    tray_markup = {
        skin: {
            'dock': pages[skin]['extras']['dock'],
            'cart': pages[skin]['cart'],
            'countdown': pages[skin]['extras']['countdown'],
            'wishlist': pages[skin]['extras']['wishlist'],
            'ship': pages[skin]['extras']['free_delivery'],
            'cartLines': pages[skin]['extras']['cart_lines'],
        }
        for skin in SKINS
    }

    data_json = json.dumps(payload, ensure_ascii=False).replace('</', '<\\/')
    tray_json = json.dumps(tray_markup, ensure_ascii=False).replace('</', '<\\/')

    html = TEMPLATE
    for token, value in (
        ('{{base_css}}', '\n'.join(base_css)),
        ('{{skin_blocks}}', '\n'.join(skin_blocks)),
        ('{{night_blocks}}', '\n'.join(night_blocks)),
        ('{{reduced_sheet}}', reduced_sheet),
        ('{{data_json}}', data_json),
        ('{{tray_json}}', tray_json),
        ('{{scripts}}', '\n'.join(scripts)),
        ('{{lazy_json}}', json.dumps(lazy, ensure_ascii=False).replace('</', '<\\/')),
        ('{{skins}}', json.dumps(SKINS)),
    ):
        html = html.replace(token, value)

    with open(output, 'w', encoding='utf-8') as handle:
        handle.write(html)

    print('%s — %.1f KB' % (output, os.path.getsize(output) / 1024))


TEMPLATE = r'''<!doctype html>
<html lang="fa" dir="rtl" data-flavor-scheme="light">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>پیش‌نمایش لایهٔ حرکتی — دوازده دمو</title>
<meta name="description" content="پیش‌نمایش زندهٔ حرکت دوازده دمو؛ CSS و JS واقعی پروژه، بدون وردپرس." />
<style>
/* ------------------------------------------------------------------ *
 * Everything below this banner is the theme's own CSS, in the order the
 * theme enqueues it. Nothing here has been altered except comment removal
 * and the inlining of @font-face files and image sources.
 * ------------------------------------------------------------------ */
{{base_css}}
</style>
{{skin_blocks}}
{{night_blocks}}
<style id="pv-reduced" disabled>
/* The prefers-reduced-motion rules lifted from every sheet above, verbatim.
   The toggle applies exactly what the browser would apply. */
{{reduced_sheet}}
</style>
<style>
/* ------------------------------------------------------------------ *
 * Preview chrome only. Not part of the theme; namespaced to #pv-*.
 * ------------------------------------------------------------------ */
:root { --pv-ink: #10151c; --pv-panel: rgba(16, 21, 28, .96); --pv-line: rgba(255, 255, 255, .16); --pv-accent: #e0a33a; }
#pv-pill, #pv-tray, #pv-tray * { box-sizing: border-box; font-family: "Vazirmatn", "Estedad", system-ui, sans-serif; }
#pv-pill { position: fixed; inset-inline-start: 18px; bottom: 18px; z-index: 2147483000; display: flex; align-items: center; gap: 8px; margin: 0; padding: 10px 16px; border: 1px solid var(--pv-line); border-radius: 999px; background: var(--pv-panel); color: #fff; font-size: 13px; font-weight: 500; line-height: 1; cursor: pointer; box-shadow: 0 12px 32px rgba(0, 0, 0, .28); }
#pv-pill:hover { background: #1a2230; }
#pv-pill:focus-visible, #pv-tray button:focus-visible { outline: 2px solid var(--pv-accent); outline-offset: 2px; }
#pv-pill b { color: var(--pv-accent); font-weight: 700; }
#pv-tray { position: fixed; inset-inline-start: 18px; bottom: 74px; z-index: 2147483000; width: min(340px, calc(100vw - 36px)); max-height: min(72vh, 640px); overflow: auto; padding: 14px; border: 1px solid var(--pv-line); border-radius: 16px; background: var(--pv-panel); color: #eef2f7; font-size: 13px; line-height: 1.7; box-shadow: 0 18px 50px rgba(0, 0, 0, .38); backdrop-filter: blur(8px); }
#pv-tray[hidden] { display: none; }
#pv-tray h2 { margin: 0 0 8px; font-size: 12px; font-weight: 500; letter-spacing: .02em; color: #9fb0c4; }
#pv-tray section + section { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--pv-line); }
#pv-tray .pv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
#pv-tray button { margin: 0; padding: 7px 10px; border: 1px solid var(--pv-line); border-radius: 9px; background: rgba(255, 255, 255, .06); color: inherit; font-size: 12px; line-height: 1.5; cursor: pointer; text-align: center; }
#pv-tray button:hover { background: rgba(255, 255, 255, .13); }
#pv-tray button[aria-pressed="true"] { border-color: var(--pv-accent); background: rgba(224, 163, 58, .18); color: #fff; }
#pv-tray .pv-row { display: flex; flex-wrap: wrap; gap: 6px; }
#pv-tray .pv-row button { flex: 1 1 auto; }
#pv-note { margin: 10px 0 0; font-size: 12px; color: #9fb0c4; }
#pv-log { margin: 8px 0 0; padding: 8px 10px; border-radius: 9px; background: rgba(255, 255, 255, .06); font-size: 12px; color: #d8e2ee; min-height: 34px; }
#pv-log strong { color: var(--pv-accent); font-weight: 500; }
#pv-stage { display: block; }
#pv-stage [hidden] { display: none; }
@media (prefers-reduced-motion: reduce) { #pv-pill { transition: none; } }
</style>
</head>
<body class="flavor-theme">
<div id="pv-stage"></div>

<div id="pv-tray" hidden aria-label="کنترل پیش‌نمایش">
	<section>
		<h2>پوستهٔ دمو</h2>
		<div class="pv-grid" id="pv-skins"></div>
	</section>
	<section>
		<h2>حالت نمایش</h2>
		<div class="pv-row">
			<button type="button" id="pv-night" aria-pressed="false">حالت شب</button>
			<button type="button" id="pv-reduce" aria-pressed="false">شبیه‌سازی کاهش حرکت</button>
			<button type="button" id="pv-replay">بازپخش ورودها</button>
		</div>
	</section>
	<section>
		<h2>سطوح تجاری و رویدادها</h2>
		<div class="pv-row">
			<button type="button" id="pv-cart">افزودن به سبد</button>
			<button type="button" id="pv-ship">تکمیل ارسال رایگان</button>
			<button type="button" id="pv-toast">توست</button>
			<button type="button" id="pv-dialog">سبد خرید</button>
			<button type="button" id="pv-countdown">پایان شمارش معکوس</button>
			<button type="button" id="pv-dock">داک شناور</button>
		</div>
		<p id="pv-note">همهٔ دکمه‌ها همان رویدادها و کلاس‌هایی را صدا می‌زنند که سایت واقعی صدا می‌زند.</p>
		<p id="pv-log" role="status" aria-live="polite">آماده.</p>
	</section>
</div>

<div id="pv-tray-tools" hidden></div>

<button type="button" id="pv-pill" aria-expanded="false" aria-controls="pv-tray">پیش‌نمایش حرکت <b id="pv-current">رستوران مدرن</b></button>

<script>
window.PV_DATA = {{data_json}};
window.PV_TRAY = {{tray_json}};
window.PV_SKINS = {{skins}};
/* The bespoke layer, installed on demand: see LAZY_JS in assemble.py. */
window.PV_LAZY = {{lazy_json}};
</script>

<script>
/* Preview chrome. Small on purpose: it switches skins, applies the night
   palette and the reduced-motion rules, and drives the events the demo
   surfaces listen for. The animation itself is entirely the theme's. */
(function () {
	'use strict';
	var stage = document.getElementById('pv-stage');
	var tray = document.getElementById('pv-tray');
	var pill = document.getElementById('pv-pill');
	var log = document.getElementById('pv-log');
	var current = document.getElementById('pv-current');
	var skin = 'modern-restaurant';
	var night = false;
	var reduce = false;

	function say(message) { log.innerHTML = message; }

	function skinStyles(target) {
		Array.prototype.forEach.call(document.querySelectorAll('style[data-pv-skin]'), function (node) {
			node.disabled = node.getAttribute('data-pv-skin') !== target;
		});
		Array.prototype.forEach.call(document.querySelectorAll('style[data-pv-night]'), function (node) {
			node.disabled = !night || node.getAttribute('data-pv-night') !== target;
		});
	}

	function loadTray(target) {
		var parts = window.PV_TRAY[target] || {};
		var host = document.getElementById('pv-tray-tools');
		host.innerHTML = (parts.dock || '') + (parts.ship || '') + (parts.wishlist || '')
			+ (parts.countdown || '') + '<ul class="flavor-cart__lines" id="pv-cart-lines">' + (parts.cartLines || '') + '</ul>';
		var ship = host.querySelector('.flavor-ship');
		if (ship) { ship.removeAttribute('hidden'); }
		installBespeakLayer();
	}

	/* The bespoke layer refuses to boot on a page whose body lacks
	   `flavor-bespoke`, and it decides that once, at parse time. Switching
	   demos in place therefore has to hand it a fresh document state: tear the
	   old instance down, then evaluate the same source again. This is why the
	   layer is stored as text rather than shipped as a static <script>. */
	function installBespeakLayer() {
		Array.prototype.forEach.call(document.querySelectorAll('script[data-pv-layer]'), function (node) { node.remove(); });
		if (window.FlavorDemoAnimations && window.FlavorDemoAnimations.destroy) {
			window.FlavorDemoAnimations.destroy();
		}
		window.FlavorDemoAnimations = null;
		Object.keys(window.PV_LAZY).forEach(function (name) {
			var node = document.createElement('script');
			node.setAttribute('data-pv-layer', name);
			node.textContent = window.PV_LAZY[name];
			document.body.appendChild(node);
		});
		if (window.FlavorMotion) { window.FlavorMotion.restart(); }
	}

	function activate(target, announce) {
		skin = target;
		var page = window.PV_DATA[target];
		skinStyles(target);
		document.body.className = page.bodyClass;
		stage.innerHTML = page.html;
		current.textContent = page.title;
		loadTray(target);
		window.scrollTo({ top: 0, behavior: 'auto' });
		Array.prototype.forEach.call(document.querySelectorAll('#pv-skins button'), function (node) {
			node.setAttribute('aria-pressed', String(node.getAttribute('data-skin') === target));
		});
		if (announce !== false) { say(page.desc + ' — ' + (page.kind === 'bespoke' ? 'پک اختصاصی با کلاس‌های fd-*' : 'دموی کلاسیک با واژگان flavor-*')); }
	}

	/* Build the skin picker from the theme's own catalogue. */
	var picker = document.getElementById('pv-skins');
	window.PV_SKINS.forEach(function (slug) {
		var button = document.createElement('button');
		button.type = 'button';
		button.textContent = window.PV_DATA[slug].title;
		button.setAttribute('data-skin', slug);
		button.setAttribute('aria-pressed', 'false');
		button.addEventListener('click', function () { activate(slug); });
		picker.appendChild(button);
	});

	pill.addEventListener('click', function () {
		var open = tray.hasAttribute('hidden');
		if (open) { tray.removeAttribute('hidden'); } else { tray.setAttribute('hidden', ''); }
		pill.setAttribute('aria-expanded', String(open));
	});

	document.getElementById('pv-night').addEventListener('click', function () {
		night = !night;
		this.setAttribute('aria-pressed', String(night));
		document.documentElement.setAttribute('data-flavor-scheme', night ? 'dark' : 'light');
		document.documentElement.classList.add('flavor-scheme-switching');
		window.setTimeout(function () { document.documentElement.classList.remove('flavor-scheme-switching'); }, 560);
		skinStyles(skin);
		say(night ? 'پالت شب همین پوسته فعال شد.' : 'پالت روز فعال شد.');
	});

	document.getElementById('pv-reduce').addEventListener('click', function () {
		reduce = !reduce;
		this.setAttribute('aria-pressed', String(reduce));
		document.getElementById('pv-reduced').disabled = !reduce;
		say(reduce ? 'قواعد prefers-reduced-motion هر شیت، عیناً اعمال شد.' : 'حالت عادی.');
	});

	document.getElementById('pv-replay').addEventListener('click', function () {
		window.scrollTo({ top: 0, behavior: 'auto' });
		if (window.FlavorMotion) { window.FlavorMotion.restart(); }
		if (window.FlavorDemoAnimations) { window.FlavorDemoAnimations.restart(); }
		say('ورودها از نو اجرا شدند.');
	});

	/* The theme's own night-scheme buttons, in every header. premium.js binds
	   these once at load; after a skin switch the chrome rebinds them, so the
	   button in the header keeps working on every demo. */
	document.addEventListener('click', function (event) {
		var toggle = event.target.closest ? event.target.closest('.flavor-scheme-toggle') : null;
		if (!toggle) { return; }
		night = !night;
		document.getElementById('pv-night').setAttribute('aria-pressed', String(night));
		document.documentElement.setAttribute('data-flavor-scheme', night ? 'dark' : 'light');
		skinStyles(skin);
		say('کلید حالت شب داخل هدر همین پوسته.');
	});

	var subtotal = 0;
	function cartEvent(amount) {
		subtotal = amount;
		document.dispatchEvent(new CustomEvent('flavor:cart-updated', {
			detail: { count: Math.max(1, Math.round(amount / 150000)), total: '۲۹۵٬۰۰۰ تومان', subtotal: amount, subtotalHtml: '۲۹۵٬۰۰۰ تومان' },
		}));
	}

	document.getElementById('pv-cart').addEventListener('click', function () {
		cartEvent(subtotal + 95000);
		say('<strong>flavor:cart-updated</strong> — نشان سبد و نوار ارسال رایگان به‌روز شد.');
	});

	document.getElementById('pv-ship').addEventListener('click', function () {
		var ship = document.querySelector('#pv-tray-tools .flavor-ship');
		var threshold = ship ? Number(ship.getAttribute('data-flavor-ship-threshold') || 0) : 0;
		cartEvent(threshold);
		if (ship) {
			ship.querySelector('.flavor-ship__fill').style.width = '100%';
			ship.querySelector('.flavor-ship__text').textContent = 'ارسال این سفارش رایگان است.';
		}
		say('<strong>flavor:cart-updated</strong> — آستانه رد شد؛ جشن نوار ارسال رایگان.');
	});

	document.getElementById('pv-toast').addEventListener('click', function () {
		if (window.flavorToast) { window.flavorToast('این توست همان چیزی است که سایت نشان می‌دهد.', 'success'); }
		else { say('flavorToast در دسترس نیست.'); return; }
		say('<strong>flavorToast()</strong> — ورود و خروج توست.');
	});

	document.getElementById('pv-dialog').addEventListener('click', function () {
		var lines = document.getElementById('pv-cart-lines');
		lines.hidden = !lines.hidden;
		if (!lines.hidden) {
			lines.setAttribute('role', 'dialog');
			lines.setAttribute('aria-label', 'سبد سفارش');
			lines.dispatchEvent(new CustomEvent('flavor:dialog-opened', { bubbles: true }));
			say('<strong>flavor:dialog-opened</strong> — خطوط سبد پشت سر هم می‌آیند.');
		} else {
			lines.dispatchEvent(new CustomEvent('flavor:dialog-closed', { bubbles: true }));
			say('بسته شد.');
		}
	});

	document.getElementById('pv-countdown').addEventListener('click', function () {
		var clock = document.querySelector('#pv-tray-tools [data-flavor-countdown]');
		if (!clock) { return; }
		clock.hidden = false;
		clock.setAttribute('data-flavor-countdown', String(Math.floor(Date.now() / 1000) + 2));
		say('<strong>flavor:countdown-ended</strong> — پایان کمپین تا دو ثانیه دیگر.');
	});

	document.getElementById('pv-dock').addEventListener('click', function () {
		var dock = document.querySelector('#pv-tray-tools #flavor-dock');
		if (dock) { dock.classList.add('is-visible'); }
		say('داک شناور — در سایت واقعی با اسکرول بیش از ۳۲۰ پیکسل ظاهر می‌شود.');
	});

	/* Links and forms stay put: this is a preview, not a site. */
	document.addEventListener('click', function (event) {
		var link = event.target.closest ? event.target.closest('a[href]') : null;
		if (link) { event.preventDefault(); }
	}, true);
	document.addEventListener('submit', function (event) { event.preventDefault(); }, true);

	/* The tray opens on demand: the first thing a visitor should see is the
	   hero of the first demo animating in, not a control panel over it. */
	activate('modern-restaurant', false);
	say('از پنل گوشهٔ پایین سمت راست صفحه، دوازده دمو و رویدادهای تجاری را امتحان کنید.');
})();
</script>

{{scripts}}
</body>
</html>
'''

if __name__ == '__main__':
    main()
