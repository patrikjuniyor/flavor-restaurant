/*
 * Keyboard-navigation suite for the surfaces prompt 52 names: the mega
 * menu / plain dropdown branches (class-nav-mega.php + premium.js), the
 * mobile drawer (main.js), the item sheet and cart dialog (menu.js +
 * ui.js), the smart-search combobox (search.js) and the wishlist dialog
 * (premium.js) — including the nested case (wishlist opened from inside
 * the drawer) where two independent layers must agree about who Escape
 * closes.
 *
 * Pages come from the repo's own offline fixtures — real templates, real
 * Nav_Mega walker attributes, real enqueue payloads and the real
 * storefront scripts, with a documented fetch stub answering the Flavor
 * Core REST routes in MenuController's own response shape:
 *
 *   php tests/ui/keyboard-pages.php <outdir>   menu + home, JS alive
 *
 * Every check drives the browser the way a keyboard-only shopper does:
 * real Tab/Shift+Tab/Enter/Escape/arrow keypresses, assertions on
 * document.activeElement, aria-expanded, inert and visibility. No
 * synthetic focus() calls except where a check isolates one behavior
 * (each is commented).
 *
 * Usage: node tests/ui/keyboard.mjs
 *   QA_OUTPUT_DIR   where the report/screenshots go
 *                   (default .cache/ui-qa/keyboard)
 *
 * Exit 0 = every check passed; 1 = at least one failure (or a fixture
 * build that failed / emitted PHP diagnostics).
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const outdir = process.env.QA_OUTPUT_DIR
  ? path.join(process.env.QA_OUTPUT_DIR, 'keyboard')
  : path.join(root, '.cache', 'ui-qa', 'keyboard');
const shots = path.join(outdir, 'screenshots');
fs.mkdirSync(shots, { recursive: true });

/* ------------------------------------------------------------------ */
/* 1. Fixtures: real templates + real scripts through the offline mock  */
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

console.log(build('php', [path.join(here, 'keyboard-pages.php'), outdir], 'keyboard page fixtures').trimEnd());

/* ------------------------------------------------------------------ */
/* 2. Static server: fixtures + the theme's real assets (CSS *and* JS)  */
/* ------------------------------------------------------------------ */

const mime = { '.css': 'text/css', '.js': 'text/javascript', '.html': 'text/html', '.woff2': 'font/woff2', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer((req, res) => {
  const rel = decodeURIComponent(req.url.split('?')[0]);
  const mapping = rel.startsWith('/themes/flavor/')
    ? path.join(root, 'flavor', path.normalize(rel.slice('/themes/flavor/'.length)))
    : rel.startsWith('/assets/')
      ? path.join(root, 'flavor', 'assets', path.normalize(rel.slice('/assets/'.length)))
      : path.join(outdir, path.normalize(rel === '/' ? 'page-menu.html' : rel));
  if (!mapping.startsWith(root) && !mapping.startsWith(outdir)) { res.writeHead(403); res.end(); return; }
  fs.readFile(mapping, (err, data) => {
    if (err) { res.writeHead(404); res.end('not found'); return; }
    res.writeHead(200, { 'Content-Type': mime[path.extname(mapping).toLowerCase()] || 'application/octet-stream' });
    res.end(data);
  });
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}/`;

/* ------------------------------------------------------------------ */
/* 3. Browser + check runner                                            */
/* ------------------------------------------------------------------ */

const browser = await chromium.launch({ args: ['--no-sandbox'] });
/* reducedMotion keeps the motion layer out of the assertions entirely —
   the motion suite owns choreography; this suite owns focus. */
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const pageErrors = [];
page.on('pageerror', (e) => pageErrors.push(e.message));

let passed = 0;
const failures = [];
const report = { generated: new Date().toISOString(), mode: 'offline', checks: [] };

async function check(group, name, fn) {
  try {
    await fn();
    passed += 1;
    report.checks.push({ group, name, ok: true });
    console.log(`  ✓ ${name}`);
  } catch (e) {
    failures.push(`${group} :: ${name} — ${String(e.message).split('\n')[0]}`);
    report.checks.push({ group, name, ok: false, error: String(e.message).slice(0, 500) });
    console.log(`  ✗ ${name}\n      ${String(e.message).split('\n')[0]}`);
  }
}

function assert(cond, msg) { if (!cond) throw new Error(msg); }
function assertEqual(actual, expected, msg) {
  if (actual !== expected) throw new Error(`${msg} — expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
}

/* activeElement facts, in one round-trip. */
const active = () => page.evaluate(() => {
  const el = document.activeElement;
  if (!el) return null;
  const describe = (n) => (n ? (n.id ? `#${n.id}` : n.tagName.toLowerCase() + '.' + String(n.className || '').split(' ')[0]) : 'null');
  return {
    body: el === document.body,
    id: el.id || '',
    matches: (sel) => false,
    label: describe(el),
    inSheet: !!el.closest('#flavor-sheet'),
    inCartPanel: !!el.closest('#flavor-cart-panel'),
    inDrawer: !!el.closest('#flavor-mobile-drawer'),
    inWish: !!el.closest('#flavor-wishlist'),
    inHeaderNav: !!el.closest('.flavor-header__nav'),
    inMegaPanel: !!el.closest('.flavor-header__nav .flavor-mega > .sub-menu'),
    inSubPanel: !!el.closest('.flavor-header__nav .sub-menu'),
    inSearch: !!el.closest('#flavor-search'),
    disabled: el.hasAttribute('disabled'),
  };
});
const activeMatches = (sel) => page.evaluate((s) => !!document.activeElement && document.activeElement.matches(s), sel);
const attr = (sel, name) => page.locator(sel).first().getAttribute(name);
const box = (sel) => page.locator(sel).first().boundingBox();
const visible = async (sel) => (await box(sel)) !== null;

async function tabTo(sel, max = 30) {
  if (await activeMatches(sel)) return 0; // already there — pressing Tab would walk PAST it
  for (let i = 0; i < max; i += 1) {
    await page.keyboard.press('Tab');
    if (await activeMatches(sel)) return i + 1;
  }
  throw new Error(`Tab never reached ${sel} within ${max} presses (active: ${JSON.stringify(await active())})`);
}

/* The bottom mobile nav is the last tab stop of the document; walking
   backwards from a fresh load is how a keyboard shopper reaches it
   without crossing the whole menu grid. */
async function tabBackTo(sel, max = 25) {
  if (await activeMatches(sel)) return 0;
  for (let i = 0; i < max; i += 1) {
    await page.keyboard.press('Shift+Tab');
    if (await activeMatches(sel)) return i + 1;
  }
  throw new Error(`Shift+Tab never reached ${sel} within ${max} presses (active: ${JSON.stringify(await active())})`);
}

async function freeze() {
  await page.evaluate(() => {
    document.documentElement.classList.remove('flavor-anim-ready');
    const style = document.createElement('style');
    style.textContent = '*,*::before,*::after{transition:none!important;animation:none!important;}';
    document.head.appendChild(style);
  });
}

async function fresh(slug, viewport) {
  if (viewport) await page.setViewportSize(viewport);
  else await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(new URL(`page-${slug}.html`, base).href, { waitUntil: 'load' });
  await freeze();
  if (slug === 'menu') {
    await page.waitForFunction(() => document.querySelectorAll('#flavor-menu-grid [data-add]').length >= 8, null, { timeout: 8000 });
    await page.waitForSelector('#flavor-cart-toggle:not([hidden])', { timeout: 5000 });
  }
}

const MEGA_BRANCH = '.flavor-header__nav .flavor-mega > a[data-flavor-nav="branch"]';
const MEGA_PANEL = '.flavor-header__nav .flavor-mega > .sub-menu';
const TOP_LINKS = '.flavor-header__nav .flavor-nav > li > a';

/* Tab-loop helper for focus traps: presses Tab until the focus returns to
   the element focused at the start, asserting every stop satisfies
   insideSel. Returns the number of distinct stops visited. */
async function trapCycle(insideSel, max = 40) {
  const start = await page.evaluate(() => document.activeElement.outerHTML.slice(0, 120));
  const seen = [];
  for (let i = 0; i < max; i += 1) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate((sel) => !!document.activeElement.closest(sel), insideSel);
    assert(inside, `Tab escaped ${insideSel} at stop ${i + 1}: ${JSON.stringify(await active())}`);
    const now = await page.evaluate(() => document.activeElement.outerHTML.slice(0, 120));
    seen.push(now);
    if (now === start) return seen.length;
  }
  throw new Error(`focus never wrapped back inside ${insideSel} within ${max} Tab presses`);
}

/* ================================================================== */
console.log('\n== A. desktop: tab-order foundations (menu page) ==\n');
/* ================================================================== */

await fresh('menu');

await check('A', 'the first Tab stop of the document is the skip link', async () => {
  await page.keyboard.press('Tab');
  assert(await activeMatches('.flavor-skip'), `first tab stop is ${JSON.stringify(await active())}, not .flavor-skip`);
});

await check('A', 'the skip link targets the real main landmark', async () => {
  assertEqual(await attr('.flavor-skip', 'href'), '#main', 'skip link href');
  assert(await page.locator('main#main').count() === 1, 'a <main id="main"> exists');
});

await check('A', 'the header nav follows the skip link in DOM/tab order', async () => {
  await tabTo(`${TOP_LINKS}`, 10);
});

/* ================================================================== */
console.log('\n== B. desktop: mega menu + dropdown branches ==\n');
/* ================================================================== */

await fresh('menu');

await check('B', 'a closed mega panel is invisible and out of the tab order', async () => {
  assert(!(await visible(MEGA_PANEL)), 'the closed panel still paints a box (no hiding rule — invisible-focus trap)');
  assertEqual(await attr(MEGA_BRANCH, 'aria-expanded'), 'false', 'closed branch aria-expanded');
});

await check('B', 'Tabbing onto the branch opens the panel (aria-expanded + visible)', async () => {
  await tabTo(MEGA_BRANCH, 12);
  await page.waitForFunction(
    (sel) => document.querySelector(sel).getAttribute('aria-expanded') === 'true',
    MEGA_BRANCH, { timeout: 2000 },
  );
  assert(await visible(MEGA_PANEL), 'aria-expanded=true but the panel paints no box');
});

await check('B', 'the branch owns aria-controls pointing at its panel id', async () => {
  const wiring = await page.evaluate(({ branch, panel }) => {
    const link = document.querySelector(branch);
    const controls = link.getAttribute('aria-controls');
    if (!controls) return { controls: null };
    const target = document.getElementById(controls);
    return { controls, isPanel: !!target && target === document.querySelector(panel) };
  }, { branch: MEGA_BRANCH, panel: MEGA_PANEL });
  assert(wiring.controls, 'branch link carries no aria-controls');
  assert(wiring.isPanel, `aria-controls="${wiring.controls}" does not resolve to the panel`);
});

await check('B', 'Tab from the branch enters the panel (logical DOM order)', async () => {
  await page.keyboard.press('Tab');
  const a = await active();
  assert(a.inMegaPanel, `Tab from the branch went to ${a.label}, not into its panel`);
});

await check('B', 'Tabbing past the last panel link exits to the next top-level item and closes the panel', async () => {
  // The panel holds six links; the focus is on the first after the check above.
  for (let i = 0; i < 5; i += 1) {
    await page.keyboard.press('Tab');
    assert((await active()).inMegaPanel, `Tab left the panel early at step ${i + 2}`);
  }
  await page.keyboard.press('Tab');
  const a = await active();
  assert(!a.inSubPanel && a.inHeaderNav, `exit landed on ${a.label}, not the next top-level link`);
  assert(!(await visible(MEGA_PANEL)), 'the panel stayed open after focus left it');
  assertEqual(await attr(MEGA_BRANCH, 'aria-expanded'), 'false', 'aria-expanded after focusout');
});

await check('B', 'Escape inside the panel closes it and returns focus to the branch', async () => {
  await fresh('menu');
  await tabTo(MEGA_BRANCH, 12);
  await page.keyboard.press('Tab'); // first panel link
  assert((await active()).inMegaPanel, 'setup: Tab should enter the panel');
  await page.keyboard.press('Escape');
  const same = await page.evaluate((sel) => document.activeElement === document.querySelector(sel), MEGA_BRANCH);
  assert(same, `after Escape focus is ${JSON.stringify(await active())}, not the branch link`);
  assert(!(await visible(MEGA_PANEL)), 'Escape did not hide the panel');
  assertEqual(await attr(MEGA_BRANCH, 'aria-expanded'), 'false', 'aria-expanded after Escape');
});

await check('B', 'an Escape-closed panel stays closed while focus sits on the branch', async () => {
  // Directly after the previous check: focus is on the branch, panel closed.
  // Reopening on refocus would make Escape useless (focusin loop).
  assert(!(await visible(MEGA_PANEL)), 'the panel reopened on its own after Escape');
  await page.keyboard.press('Tab');
  const a = await active();
  assert(!a.inMegaPanel, `Tab after Escape re-entered the panel (${a.label})`);
  assert(!(await visible(MEGA_PANEL)), 'the panel reopened when Tab moved past the branch');
});

await check('B', 'Shift+Tab back onto the branch reopens the panel', async () => {
  await page.keyboard.press('Shift+Tab');
  assert(await activeMatches(MEGA_BRANCH), 'Shift+Tab should land back on the branch');
  await page.waitForFunction(
    (sel) => document.querySelector(sel).getAttribute('aria-expanded') === 'true',
    MEGA_BRANCH, { timeout: 2000 },
  );
  assert(await visible(MEGA_PANEL), 'refocus did not reopen the panel');
});

await check('B', 'ArrowDown opens the panel and lands on its first link; ArrowUp returns', async () => {
  await fresh('menu');
  await tabTo(MEGA_BRANCH, 12);
  await page.keyboard.press('ArrowDown');
  let a = await active();
  assert(a.inMegaPanel, `ArrowDown from the branch went to ${a.label}, not into the panel`);
  await page.keyboard.press('ArrowUp');
  assert(await activeMatches(MEGA_BRANCH), 'ArrowUp did not return focus to the branch');
});

await check('B', 'ArrowLeft/ArrowRight walk the top level (RTL: left = next), Home/End jump', async () => {
  await fresh('menu');
  await tabTo(MEGA_BRANCH, 12);
  const nth = async () => page.evaluate((sel) => {
    const links = Array.from(document.querySelectorAll(sel));
    return links.indexOf(document.activeElement);
  }, TOP_LINKS);
  const start = await nth();
  assert(start >= 0, 'setup: focus is on a top-level link');
  await page.keyboard.press('ArrowLeft'); // RTL next
  assertEqual(await nth(), start + 1, 'ArrowLeft (RTL next) index');
  assert(!(await visible(MEGA_PANEL)), 'walking away left the mega panel open');
  await page.keyboard.press('ArrowRight'); // RTL previous
  assertEqual(await nth(), start, 'ArrowRight (RTL previous) index');
  await page.keyboard.press('End');
  const count = await page.locator(TOP_LINKS).count();
  assertEqual(await nth(), count - 1, 'End index');
  await page.keyboard.press('Home');
  assertEqual(await nth(), 0, 'Home index');
});

await check('B', 'a plain dropdown branch (two children) opens on focus and Escapes closed', async () => {
  await fresh('menu');
  const branch = '.flavor-header__nav li:not(.flavor-mega) > a[data-flavor-nav="branch"]';
  await tabTo(branch, 14);
  await page.waitForFunction(
    (sel) => document.querySelector(sel).getAttribute('aria-expanded') === 'true',
    branch, { timeout: 2000 },
  );
  const panel = '.flavor-header__nav li:not(.flavor-mega) > .sub-menu';
  assert(await visible(panel), 'the plain dropdown paints no box when expanded');
  await page.keyboard.press('Tab');
  assert((await active()).inSubPanel, 'Tab from the plain branch did not enter its dropdown');
  await page.keyboard.press('Escape');
  assert(!(await visible(panel)), 'Escape did not close the plain dropdown');
  assert(await page.evaluate((sel) => document.activeElement === document.querySelector(sel), branch), 'Escape did not return focus to the plain branch');
});

await page.screenshot({ path: path.join(shots, 'mega-open.png') });

/* ================================================================== */
console.log('\n== C. desktop: smart-search combobox ==\n');
/* ================================================================== */

await fresh('menu');

await check('C', 'Ctrl+K focuses the search input', async () => {
  await page.keyboard.press('Control+k');
  assert(await activeMatches('#flavor-search-input'), `Ctrl+K focused ${JSON.stringify(await active())}`);
});

await check('C', 'typing opens the results listbox with role=option rows', async () => {
  await page.keyboard.type('کباب', { delay: 30 });
  await page.waitForSelector('#flavor-search-panel:not([hidden])', { timeout: 3000 });
  assertEqual(await attr('#flavor-search-input', 'aria-expanded'), 'true', 'combobox aria-expanded');
  assertEqual(await page.locator('#flavor-search-results [role="option"]').count(), 3, 'result rows');
});

await check('C', 'ArrowDown/ArrowUp move aria-activedescendant and wrap both ways', async () => {
  await page.keyboard.press('ArrowDown');
  assertEqual(await attr('#flavor-search-input', 'aria-activedescendant'), 'flavor-search-opt-0', 'first ArrowDown');
  assertEqual(await attr('#flavor-search-opt-0', 'aria-selected'), 'true', 'row 0 selected');
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('ArrowDown');
  assertEqual(await attr('#flavor-search-input', 'aria-activedescendant'), 'flavor-search-opt-2', 'third ArrowDown');
  await page.keyboard.press('ArrowDown');
  assertEqual(await attr('#flavor-search-input', 'aria-activedescendant'), 'flavor-search-opt-0', 'forward wrap');
  await page.keyboard.press('ArrowUp');
  assertEqual(await attr('#flavor-search-input', 'aria-activedescendant'), 'flavor-search-opt-2', 'backward wrap');
});

await check('C', 'Enter opens the item sheet for the active row and Escape returns focus to the input', async () => {
  await page.keyboard.press('ArrowDown'); // opt-0 → dish 101
  await page.keyboard.press('Enter');
  await page.waitForTimeout(600);
  await page.waitForSelector('#flavor-sheet:not([hidden])', { timeout: 3000 });
  assert((await active()).inSheet, `sheet opened but focus is ${JSON.stringify(await active())}`);
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-sheet[hidden]', { state: 'attached', timeout: 3000 });
  assert(await activeMatches('#flavor-search-input'), `after Escape focus is ${JSON.stringify(await active())}, not the search input`);
});

await check('C', 'retyping reopens the list and Tab walks past it — rows are arrow-navigated, not tab stops', async () => {
  // The Enter above closed the list (search.js close()s before openProduct);
  // retype to bring it back, then Tab forward while it is open.
  await page.fill('#flavor-search-input', '');
  await page.fill('#flavor-search-input', 'کباب');
  await page.waitForSelector('#flavor-search-panel:not([hidden])', { timeout: 2000 });
  let a;
  for (let i = 0; i < 4; i += 1) {
    await page.keyboard.press('Tab');
    a = await active();
    assert(!a.id.startsWith('flavor-search-opt'), `a result row is in the tab order (${a.id})`);
    if (!a.inSearch) break;
  }
  assert(!a.inSearch, `Tab from the input never left the search widget (${a.label})`);
  for (let i = 0; i < 4; i += 1) {
    await page.keyboard.press('Shift+Tab');
    if (await activeMatches('#flavor-search-input')) break;
  }
  assert(await activeMatches('#flavor-search-input'), `Shift+Tab did not return to the input: ${JSON.stringify(await active())}`);
});

await check('C', 'Escape with the list open closes it and keeps focus on the input', async () => {
  assert(await visible('#flavor-search-panel'), 'setup: the results panel should still be open');
  await page.keyboard.press('Escape');
  assert(!(await visible('#flavor-search-panel')), 'Escape did not close the results panel');
  assertEqual(await attr('#flavor-search-input', 'aria-expanded'), 'false', 'aria-expanded after Escape');
  assert(await activeMatches('#flavor-search-input'), 'Escape blurred the input');
  assert(!(await page.evaluate(() => document.activeElement.hasAttribute('aria-activedescendant'))), 'aria-activedescendant survived the close');
});

/* ================================================================== */
console.log('\n== D. desktop: item sheet dialog ==\n');
/* ================================================================== */

await fresh('menu');

await check('D', 'Enter on a card details button opens the sheet with focus on its close button', async () => {
  await tabTo('#flavor-menu-grid [data-detail]', 50);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-sheet:not([hidden])', { timeout: 3000 });
  assert(await activeMatches('.flavor-sheet__close'), `sheet focus is ${JSON.stringify(await active())}`);
});

await check('D', 'the background goes inert while the sheet is open', async () => {
  // ui.js inerts the siblings along the dialog's ancestry chain — never an
  // ancestor of the dialog itself: #main hosts the sheet, so main stays
  // interactive while everything beside the sheet (the grid section) and
  // beside #main (header, cart aside) goes inert.
  const inerted = await page.evaluate(() => ({
    header: !!document.querySelector('.flavor-header')?.closest('[inert]'),
    cartToggle: !!document.querySelector('#flavor-cart-toggle')?.closest('[inert]'),
    grid: !!document.querySelector('#flavor-menu-grid')?.closest('[inert]'),
    mainItself: !!document.querySelector('#main')?.hasAttribute('inert'),
  }));
  assert(inerted.header && inerted.cartToggle && inerted.grid, `background not inert: ${JSON.stringify(inerted)}`);
  assert(!inerted.mainItself, '#main hosts the open sheet — inerting it would inert the dialog itself');
});

await check('D', 'Tab is trapped inside the sheet and wraps to the first stop', async () => {
  const stops = await trapCycle('#flavor-sheet', 15);
  assert(stops >= 4, `the sheet trap visited only ${stops} stops (close/radios/note/qty/add expected)`);
});

await check('D', 'Shift+Tab from the first stop wraps to the last', async () => {
  // After trapCycle the focus is back on the first stop (the close button).
  assert(await activeMatches('.flavor-sheet__close'), 'setup: focus should be the sheet close button');
  await page.keyboard.press('Shift+Tab');
  const a = await active();
  assert(a.inSheet, `Shift+Tab escaped the sheet to ${a.label}`);
  assert(await activeMatches('#flavor-sheet-add'), `Shift+Tab from first should wrap to the add button, got ${a.label}`);
});

await check('D', 'Escape closes the sheet, restores focus to the opener and lifts inert', async () => {
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-sheet[hidden]', { state: 'attached', timeout: 3000 });
  assert(await page.evaluate(() => {
    const el = document.activeElement;
    return !!el && el.matches('#flavor-menu-grid [data-detail]');
  }), `focus did not return to the card button: ${JSON.stringify(await active())}`);
  const inerted = await page.evaluate(() => !!document.querySelector('#main')?.closest('[inert]'));
  assert(!inerted, 'the background stayed inert after the sheet closed');
});

await check('D', 'focus that lands on <body> while a dialog is open is recaptured by Tab and Shift+Tab', async () => {
  await tabTo('#flavor-menu-grid [data-detail]', 50);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-sheet:not([hidden])', { timeout: 3000 });
  // A click on non-focusable dialog text leaves focus on <body> in real
  // browsers; reproduce that state directly, then prove Tab cannot leave.
  await page.evaluate(() => document.activeElement.blur());
  assert((await active()).body, 'setup: focus should be on <body>');
  await page.keyboard.press('Tab');
  assert((await active()).inSheet, `Tab from <body> escaped the open sheet: ${JSON.stringify(await active())}`);
  await page.evaluate(() => document.activeElement.blur());
  await page.keyboard.press('Shift+Tab');
  assert((await active()).inSheet, `Shift+Tab from <body> escaped the open sheet: ${JSON.stringify(await active())}`);
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-sheet[hidden]', { state: 'attached', timeout: 3000 });
});

await check('D', 'a disabled add button (unavailable dish) is skipped by Tab', async () => {
  const reached = await page.evaluate(() => {
    const disabled = document.querySelector('#flavor-menu-grid [data-add][disabled]');
    if (!disabled) return 'missing';
    disabled.closest('.flavor-card').querySelector('[data-detail]').focus();
    return 'focused-detail';
  });
  assertEqual(reached, 'focused-detail', 'the unavailable dish should ship a disabled add button');
  await page.keyboard.press('Tab');
  const a = await active();
  assert(!a.disabled, `Tab landed on a disabled control (${a.label})`);
  assert(!(await activeMatches('#flavor-menu-grid [data-add][disabled]')), 'Tab landed on the disabled add button');
});

await page.screenshot({ path: path.join(shots, 'sheet-open.png') });

/* ================================================================== */
console.log('\n== E. desktop: cart dialog + checkout step ==\n');
/* ================================================================== */

await fresh('menu');

await check('E', 'Enter on a card button opens the sheet; Enter on its add button puts the dish in the cart', async () => {
  // Card buttons (data-add / data-detail) open the choice sheet; the actual
  // add-to-cart is the sheet's add button, which closes the sheet on success.
  await tabTo('#flavor-menu-grid [data-add]:not([disabled])', 55);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-sheet:not([hidden])', { timeout: 3000 });
  await tabTo('#flavor-sheet-add', 12);
  assert(await page.locator('#flavor-sheet-add').isEnabled(), 'add button enabled with the default modifier preselected');
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => document.getElementById('flavor-cart-count').textContent === '1', null, { timeout: 3000 });
  await page.waitForSelector('#flavor-sheet[hidden]', { state: 'attached', timeout: 3000 });
  assert(await activeMatches('#flavor-menu-grid [data-add]'), `sheet close returns focus to the card trigger: ${JSON.stringify(await active())}`);
});

await check('E', 'Enter on the cart toggle opens the dialog on its close button and flips aria-expanded', async () => {
  await tabTo('#flavor-cart-toggle', 40);
  assertEqual(await attr('#flavor-cart-toggle', 'aria-expanded'), 'false', 'aria-expanded before opening');
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-cart-panel:not([hidden])', { timeout: 3000 });
  assert(await activeMatches('#flavor-cart-close'), `cart focus is ${JSON.stringify(await active())}`);
  assertEqual(await attr('#flavor-cart-toggle', 'aria-expanded'), 'true', 'aria-expanded after opening');
});

await check('E', 'the cart line ships its keyboard affordances (edit, quantity, remove)', async () => {
  assertEqual(await page.locator('.flavor-cart-line[data-key]').count(), 1, 'cart lines');
  assertEqual(await attr('.flavor-cart-line__edit', 'aria-controls'), 'flavor-sheet', 'edit button targets the sheet');
  assert(await page.locator('.flavor-cart-line [data-ui-cart-qty="1"]').count() === 1, 'increase button');
  assert(await page.locator('.flavor-cart-line [data-rm]').count() === 1, 'remove button');
});

await check('E', 'Tab is trapped inside the cart dialog and wraps', async () => {
  const stops = await trapCycle('#flavor-cart-panel', 40);
  assert(stops >= 8, `the cart trap visited only ${stops} stops`);
});

await check('E', 'Enter on «continue» moves the dialog to checkout and focus to the first field', async () => {
  await page.evaluate(() => document.getElementById('flavor-cart-continue').focus());
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-checkout:not([hidden])', { timeout: 3000 });
  assert(await activeMatches('#flavor-name'), `checkout focus is ${JSON.stringify(await active())}, not #flavor-name`);
});

await check('E', 'Tab walks the checkout fields in form order (name → mobile)', async () => {
  await page.keyboard.press('Tab');
  assert(await activeMatches('#flavor-mobile'), `after #flavor-name focus is ${JSON.stringify(await active())}`);
});

await check('E', 'Escape closes the whole dialog, restores the toggle focus, expanded state and page scroll', async () => {
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-cart-panel[hidden]', { state: 'attached', timeout: 3000 });
  assert(await activeMatches('#flavor-cart-toggle'), `focus after Escape is ${JSON.stringify(await active())}`);
  assertEqual(await attr('#flavor-cart-toggle', 'aria-expanded'), 'false', 'aria-expanded after close');
  const overflow = await page.evaluate(() => document.body.style.overflow);
  assertEqual(overflow, '', 'body scroll lock survived the close');
});

await page.screenshot({ path: path.join(shots, 'cart-open.png') });

/* ================================================================== */
console.log('\n== F. home page: wishlist dialog + site-wide mega ==\n');
/* ================================================================== */

await fresh('home');

await check('F', 'Enter on the header wishlist button opens its dialog with focus inside', async () => {
  await tabTo('.flavor-header [data-wishlist-open]', 25);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-wishlist:not([hidden])', { timeout: 3000 });
  assert((await active()).inWish, `wishlist focus is ${JSON.stringify(await active())}`);
  assertEqual(await attr('.flavor-header [data-wishlist-open]', 'aria-expanded'), 'true', 'wishlist trigger aria-expanded');
});

await check('F', 'Tab stays inside the wishlist dialog', async () => {
  await page.keyboard.press('Tab');
  assert((await active()).inWish, `Tab escaped the wishlist dialog: ${JSON.stringify(await active())}`);
});

await check('F', 'Escape closes the wishlist and returns focus to its trigger', async () => {
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-wishlist[hidden]', { state: 'attached', timeout: 3000 });
  assert(await activeMatches('.flavor-header [data-wishlist-open]'), `focus after Escape is ${JSON.stringify(await active())}`);
  assertEqual(await attr('.flavor-header [data-wishlist-open]', 'aria-expanded'), 'false', 'aria-expanded after close');
});

await check('F', 'the mega menu behaves identically on a non-menu page', async () => {
  await fresh('home'); // previous check left focus in the header actions, past the nav
  await tabTo(MEGA_BRANCH, 12);
  await page.waitForFunction(
    (sel) => document.querySelector(sel).getAttribute('aria-expanded') === 'true',
    MEGA_BRANCH, { timeout: 2000 },
  );
  assert(await visible(MEGA_PANEL), 'the panel did not open on the home page');
  await page.keyboard.press('Escape');
  assert(!(await visible(MEGA_PANEL)), 'Escape did not close the panel on the home page');
});

/* ================================================================== */
console.log('\n== G. mobile (390px): drawer, nested dialogs, bottom-nav cart ==\n');
/* ================================================================== */

await fresh('menu', { width: 390, height: 844 });

await check('G', 'at phone width the desktop nav hides and the hamburger shows', async () => {
  assert(!(await visible('.flavor-header__nav')), 'the desktop nav still paints at 390px');
  assert(await visible('#flavor-drawer-toggle'), 'the drawer toggle is not reachable at 390px');
});

await check('G', 'Enter on the hamburger opens the drawer on its close button, inerting the page', async () => {
  await tabTo('#flavor-drawer-toggle', 12);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-mobile-drawer.is-active', { timeout: 3000 });
  assert(await activeMatches('#flavor-drawer-close'), `drawer focus is ${JSON.stringify(await active())}`);
  assertEqual(await attr('#flavor-mobile-drawer', 'aria-hidden'), 'false', 'drawer aria-hidden while open');
  assertEqual(await attr('#flavor-drawer-toggle', 'aria-expanded'), 'true', 'hamburger aria-expanded');
  const inerted = await page.evaluate(() => !!document.querySelector('#main')?.closest('[inert]'));
  assert(inerted, 'the page behind the drawer is not inert');
});

await check('G', 'Tab is trapped inside the drawer and wraps', async () => {
  const stops = await trapCycle('#flavor-mobile-drawer', 45);
  assert(stops >= 6, `the drawer trap visited only ${stops} stops`);
});

await check('G', 'drawer branches are plain nested lists — no disclosure semantics, submenus statically visible', async () => {
  const facts = await page.evaluate(() => ({
    branchAtts: document.querySelectorAll('#flavor-mobile-drawer [data-flavor-nav]').length,
    expanded: document.querySelectorAll('#flavor-mobile-drawer a[aria-expanded]').length,
    popup: document.querySelectorAll('#flavor-mobile-drawer a[aria-haspopup]').length,
  }));
  assertEqual(facts.branchAtts, 0, 'data-flavor-nav survived inside the drawer');
  assertEqual(facts.expanded, 0, 'aria-expanded survived inside the drawer (the submenu is statically open — the attribute would lie)');
  assertEqual(facts.popup, 0, 'aria-haspopup survived inside the drawer');
  const subVisible = await page.evaluate(() => {
    const sub = document.querySelector('#flavor-mobile-drawer .flavor-nav .sub-menu');
    return !!sub && sub.getBoundingClientRect().height > 0;
  });
  assert(subVisible, 'the drawer submenu should be a statically visible nested list');
});

await check('G', 'Escape closes the drawer, restores the hamburger focus and lifts inert', async () => {
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => !document.getElementById('flavor-mobile-drawer').classList.contains('is-active'), null, { timeout: 3000 });
  assert(await activeMatches('#flavor-drawer-toggle'), `focus after Escape is ${JSON.stringify(await active())}`);
  assertEqual(await attr('#flavor-mobile-drawer', 'aria-hidden'), 'true', 'drawer aria-hidden after close');
  const inerted = await page.evaluate(() => !!document.querySelector('#main')?.closest('[inert]'));
  assert(!inerted, 'the page stayed inert after the drawer closed');
});

await check('G', 'nested layers: Escape closes the wishlist first, the drawer survives; the second Escape closes the drawer', async () => {
  await tabTo('#flavor-drawer-toggle', 12);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-mobile-drawer.is-active', { timeout: 3000 });
  await tabTo('#flavor-mobile-drawer [data-wishlist-open]', 30);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-wishlist:not([hidden])', { timeout: 3000 });
  const bothOpen = await page.evaluate(() => ({
    drawer: document.getElementById('flavor-mobile-drawer').classList.contains('is-active'),
    wish: !document.getElementById('flavor-wishlist').hidden,
  }));
  assert(bothOpen.drawer && bothOpen.wish, `setup: both layers should be open — ${JSON.stringify(bothOpen)}`);
  await page.keyboard.press('Escape');
  const afterFirst = await page.evaluate(() => ({
    drawer: document.getElementById('flavor-mobile-drawer').classList.contains('is-active'),
    wish: !document.getElementById('flavor-wishlist').hidden,
  }));
  assert(!afterFirst.wish, 'the first Escape did not close the wishlist');
  assert(afterFirst.drawer, 'the first Escape closed the drawer too — the top layer must own Escape');
  assert(await activeMatches('#flavor-mobile-drawer [data-wishlist-open]'), `focus should return inside the drawer, got ${JSON.stringify(await active())}`);
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => !document.getElementById('flavor-mobile-drawer').classList.contains('is-active'), null, { timeout: 3000 });
  assert(await activeMatches('#flavor-drawer-toggle'), `the second Escape should land on the hamburger, got ${JSON.stringify(await active())}`);
});

await page.screenshot({ path: path.join(shots, 'drawer-open.png') });

await check('G', 'the bottom-nav cart button opens the cart dialog and Escape returns focus to it', async () => {
  // The bottom nav is the last tab stop of the document — reaching it by
  // walking backwards is the realistic keyboard path (forward would cross
  // the whole menu grid and overflow any sane press budget).
  await tabBackTo('#flavor-mobile-cart-btn', 20);
  await page.keyboard.press('Enter');
  await page.waitForSelector('#flavor-cart-panel:not([hidden])', { timeout: 3000 });
  assert((await active()).inCartPanel, `cart focus is ${JSON.stringify(await active())}`);
  await page.keyboard.press('Escape');
  await page.waitForSelector('#flavor-cart-panel[hidden]', { state: 'attached', timeout: 3000 });
  assert(await activeMatches('#flavor-mobile-cart-btn'), `focus after Escape is ${JSON.stringify(await active())}, not the bottom-nav cart button`);
});

/* ================================================================== */
/*  H. hygiene + report                                                 */
/* ================================================================== */

await check('H', 'no uncaught page errors during the whole run', async () => {
  assertEqual(pageErrors.length, 0, `page errors: ${pageErrors.slice(0, 3).join(' | ')}`);
});

report.passed = passed;
report.failed = failures.length;
report.failures = failures;
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 2));

console.log(`\n${'─'.repeat(58)}`);
console.log(`keyboard navigation: ${passed} passed, ${failures.length} failed`);
if (failures.length) {
  console.log('failures:');
  failures.forEach((f) => console.log('  ✗ ' + f));
}
console.log(`report: ${path.join(outdir, 'report.json')}`);

await browser.close();
server.close();
process.exit(failures.length ? 1 : 0);
