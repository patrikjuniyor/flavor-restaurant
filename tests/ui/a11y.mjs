/*
 * Accessibility guard for the main pages and the one-click demos (prompt 51).
 *
 * Scans the surfaces the prompt names — home, menu, reservation, contact,
 * about, order tracking, cart, checkout and the twelve one-click demos —
 * with @axe-core/playwright under the wcag2a / wcag2aa / wcag21aa tags, and
 * fails on every serious or critical violation.
 *
 * The pages come from the repo's own offline fixtures, never from a
 * hand-written approximation:
 *
 *   php tests/ui/a11y-pages.php <outdir>            six real page templates
 *   bash dev-tools/preview/build-preview.sh <file>  twelve real demo skins
 *
 * both rendered through the offline WordPress mock with the theme's real
 * CSS served from flavor/assets. The cart drawer, checkout form and product
 * sheet are JS-opened panels inside the menu template; the guard reveals
 * them the way the shipped JS does (hidden=false plus the exact line/title
 * markup menu.js renders — see REVEAL comments) so axe reads real shipped
 * DOM, not invented markup.
 *
 * Usage: node tests/ui/a11y.mjs [--skip-demos]
 *   A11Y_BASE_URL=https://site.test/  scan a LIVE site's pages instead of
 *                                     the offline fixtures (demos always
 *                                     come from the offline preview build).
 *   QA_OUTPUT_DIR                     where the report/screenshots go
 *                                     (default .cache/ui-qa/a11y)
 *
 * Exit 0 = no serious/critical violation on any surface; 1 = violations
 * reported (or a fixture build that failed / emitted PHP diagnostics).
 */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const outdir = process.env.QA_OUTPUT_DIR
  ? path.join(process.env.QA_OUTPUT_DIR, 'a11y')
  : path.join(root, '.cache', 'ui-qa', 'a11y');
const shots = path.join(outdir, 'screenshots');
const LIVE = process.env.A11Y_BASE_URL || '';
const skipDemos = process.argv.includes('--skip-demos');

fs.mkdirSync(shots, { recursive: true });

/* ------------------------------------------------------------------ */
/* 1. Fixtures: real templates through the offline mock                */
/* ------------------------------------------------------------------ */

const diagnostics = /Fatal error|Warning:|Notice:|Deprecated:|Undefined (index|variable|array key)/;

function build(cmd, args, label) {
  const built = spawnSync(cmd, args, { encoding: 'utf8' });
  const output = `${built.stdout || ''}\n${built.stderr || ''}`;
  if (built.status !== 0) {
    process.stderr.write(output.replace(/^/gm, '  ') + `\n✗ ${label} failed (exit ${built.status})\n`);
    process.exit(1);
  }
  if (diagnostics.test(output)) {
    process.stderr.write(`✗ ${label} emitted PHP diagnostics:\n`);
    for (const line of output.split('\n').filter((l) => diagnostics.test(l)).slice(0, 5)) process.stderr.write('  ' + line.trim() + '\n');
    process.exit(1);
  }
  return output;
}

if (!LIVE) build('php', [path.join(here, 'a11y-pages.php'), outdir], 'a11y page fixtures');
if (!skipDemos) build('bash', [path.join(root, 'dev-tools/preview/build-preview.sh'), path.join(outdir, 'demos.html')], 'demo preview build');

/* ------------------------------------------------------------------ */
/* 2. Static server: fixtures + the theme's real assets                */
/* ------------------------------------------------------------------ */

const mime = { '.css': 'text/css', '.html': 'text/html', '.woff2': 'font/woff2', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer((req, res) => {
  const rel = decodeURIComponent(req.url.split('?')[0]);
  const mapping = rel.startsWith('/themes/flavor/')
    ? path.join(root, 'flavor', path.normalize(rel.slice('/themes/flavor/'.length)))
    : rel.startsWith('/assets/')
      ? path.join(root, 'flavor', 'assets', path.normalize(rel.slice('/assets/'.length)))
      : path.join(outdir, path.normalize(rel === '/' ? 'page-home.html' : rel));
  if (!mapping.startsWith(root) && !mapping.startsWith(outdir)) { res.writeHead(403); res.end(); return; }
  fs.readFile(mapping, (err, data) => {
    if (err) { res.writeHead(404); res.end('not found'); return; }
    res.writeHead(200, { 'Content-Type': mime[path.extname(mapping).toLowerCase()] || 'application/octet-stream' });
    res.end(data);
  });
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const base = LIVE || `http://127.0.0.1:${server.address().port}/`;

/* ------------------------------------------------------------------ */
/* 3. Browser + audit loop                                             */
/* ------------------------------------------------------------------ */

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const pageErrors = [];
page.on('pageerror', (e) => pageErrors.push(e.message));

const report = { generated: new Date().toISOString(), mode: LIVE ? 'live' : 'offline', surfaces: [], violations: [] };
const IMPACTS = ['critical', 'serious', 'moderate', 'minor'];
const tally = { critical: 0, serious: 0, moderate: 0, minor: 0 };

async function audit(label, shotName) {
  // Freeze motion in its final state: the entrance engine is gated on
  // html.flavor-anim-ready, so dropping that class reveals every element
  // exactly as a no-JS shop would show it. Auditing mid-transition would
  // blend partial opacity into text colors and fake contrast failures —
  // WCAG judges the resting state, so the scan judges the resting state.
  await page.evaluate(() => {
    document.documentElement.classList.remove('flavor-anim-ready');
    const style = document.createElement('style');
    style.textContent = '*,*::before,*::after{transition:none!important;animation:none!important;}';
    document.head.appendChild(style);
  });
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  const counts = { critical: 0, serious: 0, moderate: 0, minor: 0 };
  for (const v of result.violations) {
    const impact = IMPACTS.includes(v.impact) ? v.impact : 'serious';
    counts[impact] += 1;
    tally[impact] += 1;
    report.violations.push({
      surface: label,
      id: v.id,
      impact,
      help: v.help,
      nodes: v.nodes.length,
      targets: v.nodes.slice(0, 3).map((n) => String(n.target.join(' ')).slice(0, 140)),
      /* Contrast triage needs the measured pairs, not just selectors: keep the
         axe data (fg/bg/ratio/font) for the first few nodes of every rule. */
      details: v.nodes.slice(0, 24).map((n) => {
        const d = (n.any && n.any[0] && n.any[0].data) || {};
        return {
          target: String(n.target.join(' ')).slice(0, 140),
          html: String(n.html || '').slice(0, 120),
          fg: d.fgColor, bg: d.bgColor, ratio: d.contrastRatio,
          fontSize: d.fontSize, fontWeight: d.fontWeight, expected: d.expectedContrastRatio,
        };
      }),
    });
  }
  report.surfaces.push({ label, ...counts, passes: result.passes.length });
  const badge = (n) => (n ? `${n}` : '·');
  console.log(
    `  ${label.padEnd(28)} critical:${badge(counts.critical)} serious:${badge(counts.serious)} moderate:${badge(counts.moderate)} minor:${badge(counts.minor)}`
  );
  if (shotName) await page.screenshot({ path: path.join(shots, `${shotName}.png`), fullPage: false });
}

const pageSlugs = ['home', 'menu', 'reservation', 'contact', 'about', 'tracking'];
const livePaths = { home: '', menu: 'menu/', reservation: 'reservation/', contact: 'contact/', about: 'about/', tracking: 'tracking/' };

console.log(`\naxe scan (${LIVE ? 'live: ' + base : 'offline fixtures'}) — tags wcag2a/wcag2aa/wcag21aa\n`);

for (const slug of pageSlugs) {
  const url = LIVE ? new URL(livePaths[slug], base).href : new URL(`page-${slug}.html`, base).href;
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(url, { waitUntil: LIVE ? 'networkidle' : 'load' });
  await audit(`${slug}-desktop`, slug);
  await page.setViewportSize({ width: 390, height: 844 });
  await audit(`${slug}-mobile`);
}

/* --- cart / checkout / product sheet: JS-opened panels of the menu page ---
 * REVEAL: the shipped markup lives in template-parts/menu/cart-drawer.php
 * and item-modal.php with `hidden` until the UI JS opens the panels; the
 * cart line markup mirrors menu.js:315 exactly (article/h3/qty group with
 * aria-labels/output/remove), so axe scans what a live shop renders. */
await page.setViewportSize({ width: 1440, height: 1000 });
await page.goto(LIVE ? new URL('menu/', base).href : new URL('page-menu.html', base).href, { waitUntil: LIVE ? 'networkidle' : 'load' });

const reveal = (mode) => {
  const show = (id) => { const el = document.getElementById(id); if (el) el.hidden = false; };
  const hide = (id) => { const el = document.getElementById(id); if (el) el.hidden = true; };
  show('flavor-cart-panel');
  const loading = document.getElementById('flavor-cart-loading');
  if (loading) loading.hidden = true;
  if (mode === 'empty') {
    hide('flavor-cart-filled');
    show('flavor-cart-empty');
    return;
  }
  hide('flavor-cart-empty');
  show('flavor-cart-filled');
  show('flavor-cart-actions');
  if (mode === 'cart') {
    show('flavor-cart-review');
    hide('flavor-checkout');
    const lines = document.getElementById('flavor-cart-lines');
    if (lines && !lines.children.length) {
      lines.innerHTML = [
        ['چلو کباب سلطانی', '۲', '۹۶۰٬۰۰۰ تومان'],
        ['آش رشته سنتی', '۱', '۱۵۵٬۰۰۰ تومان'],
      ]
        .map(
          ([name, qty, price]) =>
            `<article class="flavor-cart-line" data-key="k-${name}">` +
            `<span class="flavor-cart-line__image" aria-hidden="true"></span>` +
            `<div><h3>${name}</h3>` +
            `<div class="flavor-cart-line__bottom">` +
            `<div class="flavor-qty" role="group" aria-label="تعداد ${name}">` +
            `<button type="button" data-ui-cart-qty="-1" aria-label="کم کردن یک عدد ${name}">−</button>` +
            `<output>${qty}</output>` +
            `<button type="button" data-ui-cart-qty="1" aria-label="افزودن یک عدد ${name}">+</button></div>` +
            `<button type="button" class="flavor-cart-line__remove" data-rm="k-${name}" aria-label="حذف ${name}">حذف</button>` +
            `<strong class="flavor-cart-line__price">${price}</strong></div></div></article>`
        )
        .join('');
    }
    return;
  }
  if (mode === 'checkout') {
    hide('flavor-cart-review');
    show('flavor-checkout');
    show('flavor-place');
    hide('flavor-cart-continue');
    show('flavor-cart-back');
    return;
  }
  if (mode === 'sheet') {
    show('flavor-sheet');
    const title = document.getElementById('flavor-sheet-title');
    if (title && !title.textContent) title.textContent = 'چلو کباب سلطانی';
    const price = document.getElementById('flavor-sheet-price');
    if (price && !price.textContent) price.textContent = '۴۸۰٬۰۰۰ تومان';
  }
};

await page.evaluate(reveal, 'empty');
await audit('cart-empty', 'cart-empty');
await page.evaluate(reveal, 'cart');
await audit('cart-filled', 'cart-filled');
await page.evaluate(reveal, 'checkout');
await audit('checkout', 'checkout');
await page.evaluate(reveal, 'sheet');
await audit('product-sheet', 'product-sheet');

/* --- one-click demos: the assembled motion preview, skin by skin --- */
if (!skipDemos) {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(new URL('demos.html', LIVE ? `http://127.0.0.1:${server.address().port}/` : base).href, { waitUntil: 'load' });
  await page.waitForTimeout(900);
  await page.click('#pv-pill');
  await page.waitForTimeout(200);
  const skins = await page.$$eval('#pv-skins button', (nodes) => nodes.map((n) => n.getAttribute('data-skin')));
  for (const skin of skins) {
    await page.click(`#pv-skins button[data-skin="${skin}"]`);
    await page.waitForTimeout(400);
    /* Walk the page so every IntersectionObserver reveals its content —
       unrevealed motion content sits at opacity 0 and would fake
       contrast violations. */
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 700) {
      await page.evaluate((yy) => window.scrollTo(0, yy), y);
      await page.waitForTimeout(60);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(500);
    await audit(`demo-${skin}`, `demo-${skin}`);
  }
}

/* ------------------------------------------------------------------ */
/* 4. Verdict                                                          */
/* ------------------------------------------------------------------ */

await browser.close();
server.close();

report.tally = tally;
report.page_errors = pageErrors;
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 2));

if (report.violations.length) {
  console.log('\nViolations:');
  for (const v of report.violations) {
    console.log(`  [${v.impact.toUpperCase()}] ${v.surface}: ${v.id} — ${v.help} (${v.nodes} node(s))`);
    for (const t of v.targets) console.log(`      ${t}`);
  }
}
if (pageErrors.length) {
  console.log('\nPage errors:');
  for (const e of pageErrors.slice(0, 8)) console.log('  ' + e);
}

const blocking = tally.critical + tally.serious;
console.log(`\n=======================================================`);
console.log(`a11y: ${report.surfaces.length} surfaces — critical:${tally.critical} serious:${tally.serious} (blocking) · moderate:${tally.moderate} minor:${tally.minor} (reported)`);
console.log(`report: ${path.join(outdir, 'report.json')}`);
console.log(`=======================================================`);

process.exit(blocking || pageErrors.length ? 1 : 0);
