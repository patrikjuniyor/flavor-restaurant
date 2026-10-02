/*
 * Responsiveness regression guard.
 *
 * Three faults shipped together and masked each other:
 *
 *   - .flavor-hours-card had a flat 40px padding and a non-wrapping flex row,
 *     so on a 320px phone the opening hours ran 51px off the viewport. Nobody
 *     saw a scrollbar, because body{overflow-x:hidden} clipped it -- the times
 *     were simply cut in half with no way to reach them.
 *   - .flavor-section-header--with-action put a nowrap button next to a
 *     heading with no flex-wrap, so the "view full menu" button left the
 *     screen. It was invisible in testing until the hours card was fixed,
 *     because the page only reports its widest overflow.
 *   - 134 font-size declarations sat below 12px, and five interactive
 *     elements were under the 24x24 minimum of WCAG 2.2 SC 2.5.8.
 *
 * This check needs no WordPress: it serves the real theme stylesheets against
 * markup copied verbatim from the rendered templates, at the viewport widths
 * real visitors use, across every skin.
 *
 * Usage: node responsive.mjs
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { hours, header, card, breadcrumb, nav } from './fixtures/section-markup.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const cssDir = path.resolve(here, '../../flavor/assets/css');

const classicSkins = [
  'modern-restaurant', 'luxury-dining', 'persian-traditional',
  'pizza-italian', 'fast-food', 'cafe-bistro', 'bakery-pastry',
];
const bespokeSkins = ['juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering'];
const skins = [...classicSkins, ...bespokeSkins];

const sheets = ['main', 'marketing', 'rtl', 'ui', 'ui-navigation', 'ui-pages', 'ui-menu'];
const tokens = '<style id="flavor-tokens">:root{--flavor-logo-height:52px;--flavor-logo-height-mobile:37px;--flavor-logo-max-width:208px}</style>';

const page = (skin) => {
  const bespoke = bespokeSkins.includes(skin);
  const links = sheets.map(s => `<link rel="stylesheet" href="/css/${s}.css" />`).join('\n')
    + (bespoke ? '\n<link rel="stylesheet" href="/css/bespoke-demos.css" />' : '')
    + `\n<link rel="stylesheet" href="/css/skins/${skin}.css" />`;
  return `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
${links}${tokens}</head>
<body class="flavor-theme flavor-ui${bespoke ? ' flavor-bespoke' : ''} flavor-skin-${skin} flavor-header-default">
<main id="main" class="flavor-main">
<div class="flavor-container">${breadcrumb}</div>
${hours}
<section class="flavor-section flavor-featured"><div class="flavor-container">
${header}
<div class="flavor-featured__grid">${card}${card}${card}</div>
</div></section>
</main>
${nav}
</body></html>`;
};

const pages = new Map(skins.map(s => [`/${s}`, page(s)]));

const mime = { '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg' };
const server = http.createServer((req, res) => {
  const url = decodeURIComponent(req.url.split('?')[0]);
  if (pages.has(url)) {
    res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
    return res.end(pages.get(url));
  }
  if (url.startsWith('/css/')) {
    return fs.readFile(path.join(cssDir, url.slice(5)), (err, body) => {
      if (err) { res.writeHead(404); return res.end('nf'); }
      res.writeHead(200, { 'content-type': mime[path.extname(url)] || 'text/plain' });
      res.end(body);
    });
  }
  // Images are irrelevant here; answer with a 1x1 so layout does not wait.
  res.writeHead(200, { 'content-type': 'image/gif' });
  res.end(Buffer.from('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', 'base64'));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;

const widths = [320, 360, 390, 414, 600, 768, 1024, 1440, 2560];
const MIN_TAP = 24;   // WCAG 2.2 SC 2.5.8
const MIN_FONT = 12;  // readability floor
const MAX_MEASURE = 1240; // container must not stretch on a 4K display

const probe = ({ minTap, minFont }) => {
  const de = document.documentElement;
  const vw = de.clientWidth;
  const out = { vw, scrollWidth: de.scrollWidth, overflow: [], small: [], tiny: [], container: null };

  const name = (el) => el.tagName.toLowerCase()
    + (el.className && typeof el.className === 'string'
      ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');

  const shown = (el, cs, r) =>
    cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) !== 0
    && r.width > 0 && r.height > 0
    && !el.closest('[aria-hidden="true"]')
    && !/screen-reader|sr-only|visually-hidden/.test(el.className || '');

  // An off-canvas drawer parked outside the viewport is the standard pattern,
  // not a bug; so is anything a parent deliberately clips or scrolls.
  const excused = (el) => {
    if (el.closest('.flavor-drawer, .fd-drawer')) return true;
    let p = el.parentElement;
    while (p && p !== de) {
      if (/hidden|auto|scroll|clip/.test(getComputedStyle(p).overflowX)) return true;
      p = p.parentElement;
    }
    return false;
  };

  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    if (!shown(el, cs, r)) continue;

    if (cs.position !== 'fixed' && !excused(el)) {
      const over = Math.round(Math.max(r.right - vw, -r.left));
      if (over > 1) out.overflow.push({ el: name(el), over });
    }

    const ownText = Array.from(el.childNodes).some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    if (ownText) {
      const size = parseFloat(cs.fontSize);
      if (size > 0 && size < minFont) out.tiny.push({ el: name(el), px: size });
    }
  }

  for (const el of document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role="button"]')) {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    if (!shown(el, cs, r)) continue;
    if (cs.display === 'inline' && el.closest('p, li')) continue; // inline prose links are exempt
    const w = Math.round(r.width), h = Math.round(r.height);
    if (w < minTap || h < minTap) out.small.push({ el: name(el), size: `${w}x${h}` });
  }

  const c = document.querySelector('.flavor-container');
  if (c) out.container = Math.round(c.getBoundingClientRect().width);
  return out;
};

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const results = [];
let failed = 0;

try {
  for (const skin of skins) {
    for (const width of widths) {
      const p = await browser.newPage({ viewport: { width, height: 900 } });
      await p.goto(`${base}/${skin}`, { waitUntil: 'load' });
      const m = await p.evaluate(probe, { minTap: MIN_TAP, minFont: MIN_FONT });
      await p.close();

      const row = { skin, width, container: m.container };
      results.push(row);

      /* Collect every category, rather than stopping at the first. These three
         faults hid behind each other once already; a report that short-circuits
         would let that happen again. */
      const problems = [];
      if (m.scrollWidth > m.vw + 1 || m.overflow.length) {
        const worst = m.overflow.sort((a, b) => b.over - a.over)[0];
        problems.push(`content leaves the viewport (scrollWidth ${m.scrollWidth} vs ${m.vw})`
          + (worst ? ` - worst: ${worst.el} by ${worst.over}px` : ''));
      }
      if (m.tiny.length) {
        problems.push(`text below ${MIN_FONT}px - ${m.tiny.slice(0, 3).map(t => `${t.el}@${t.px}px`).join(', ')}`
          + (m.tiny.length > 3 ? ` (+${m.tiny.length - 3} more)` : ''));
      }
      if (m.small.length) {
        problems.push(`tap target below ${MIN_TAP}x${MIN_TAP} - ${m.small.slice(0, 3).map(t => `${t.el}@${t.size}`).join(', ')}`
          + (m.small.length > 3 ? ` (+${m.small.length - 3} more)` : ''));
      }
      if (width >= 1440 && m.container > MAX_MEASURE) {
        problems.push(`container is ${m.container}px, should cap at ${MAX_MEASURE}px`);
      }

      row.pass = problems.length === 0;
      if (!row.pass) {
        failed++;
        for (const p of problems) console.error(`FAIL ${skin} @${width}px: ${p}`);
      }
    }
  }
} finally {
  await browser.close();
  server.close();
}

/* A static sweep catches a sub-12px rule anywhere, including the stylesheets
   this fixture never paints (checkout, account, search). */
console.log('\n--- stylesheet sweep: font-size below 12px ---');
const skipFiles = new Set(['builder-admin.css', 'print-receipt.css']); // admin screens and print
/* Ornament that is aria-hidden and locked to a fixed-size graphic. Larger text
   breaks the artwork and no information is lost, so the floor does not apply.
   Keep this list short, and only for decoration a screen reader never sees. */
const exemptSelectors = [/\.fd-fresh-seal\b/];
let tinyRules = 0;
const walk = (dir, prefix = '') => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) { walk(path.join(dir, entry.name), prefix + entry.name + '/'); continue; }
    if (!entry.name.endsWith('.css') || skipFiles.has(entry.name)) continue;
    const text = fs.readFileSync(path.join(dir, entry.name), 'utf8');
    const re = /font-size:\s*(\d+(?:\.\d+)?)px/g;
    let hit;
    while ((hit = re.exec(text))) {
      if (parseFloat(hit[1]) < MIN_FONT) {
        const rule = text.slice(text.lastIndexOf('}', hit.index) + 1, hit.index);
        if (exemptSelectors.some(rx => rx.test(rule))) continue;
        const line = text.slice(0, hit.index).split('\n').length;
        console.error(`FAIL ${prefix}${entry.name}:${line} declares ${hit[1]}px`);
        tinyRules++;
      }
    }
  }
};
walk(cssDir);
console.log(tinyRules ? `${tinyRules} rule(s) below ${MIN_FONT}px` : `clean - no rule below ${MIN_FONT}px`);
failed += tinyRules;

const byWidth = new Map();
for (const r of results) {
  if (!byWidth.has(r.width)) byWidth.set(r.width, { pass: 0, fail: 0, container: r.container });
  byWidth.get(r.width)[r.pass ? 'pass' : 'fail']++;
}
console.log('\nwidth   skins ok   container');
for (const [w, s] of byWidth) {
  console.log(`${String(w).padStart(5)}px  ${String(s.pass).padStart(2)}/${skins.length}      ${s.container}px`);
}

console.log(`\n${results.length - results.filter(r => !r.pass).length}/${results.length} responsive checks passed.`);
if (failed) process.exit(1);
