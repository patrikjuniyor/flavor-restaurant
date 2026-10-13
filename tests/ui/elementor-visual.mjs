/*
 * Elementor template visual guard (R3-02).
 *
 * Renders the three shipped templates — flavor-home, flavor-about,
 * flavor-menu — at phone (430px), tablet (960px) and desktop (1440px)
 * widths, in light and dark scheme, and checks what a reviewer sees:
 *
 *   1. Section overlap: getBoundingClientRect pairwise intersection of
 *      top-level Elementor sections (a fixed-position or negative-margin
 *      widget that slides under its neighbour).
 *   2. Text contrast: every visible text run inside a rendered section is
 *      measured against its composited background with the same math as
 *      flavor/inc/class-contrast.php — relative luminance per WCAG 2.x
 *      (channel <= 0.04045 ? /12.92 : ((c+0.055)/1.055)^2.4) and
 *      (Lmax+0.05)/(Lmin+0.05). Minimum 4.5:1, or 3.0:1 for large text
 *      (>=24px, or >=18.66px at weight >=700), mirroring AA_NORMAL/AA_LARGE.
 *   3. Horizontal overflow: no page may scroll sideways at any width.
 *
 * Screenshots for manual review go to <outdir>/screenshots/.
 * The fixture pages come from `php tests/ui/elementor-pages.php <outdir>`.
 *
 * Twin direction sweep (prompt 57): every main surface — the templates
 * above, the data-state pages, a dedicated direction probe page and the six
 * real page surfaces built by a11y-pages.php — also renders once per
 * direction (rtl = as shipped, ltr = twin). Each twin must keep the
 * document's dir, stay free of sideways overflow, mirror directional arrow
 * icons, and resolve every direction-bound animation, entrance and offset
 * to the correct side.
 *
 * Usage: node tests/ui/elementor-visual.mjs [--regen]
 *   --regen  run the PHP fixture builder first (CI convenience).
 * Exit 0 = everything visible and readable; 1 = violations reported.
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const assetsRoot = path.join(root, 'flavor', 'assets');
const outdir = process.env.QA_OUTPUT_DIR
  ? path.join(process.env.QA_OUTPUT_DIR, 'elementor')
  : path.join(root, '.cache', 'ui-qa', 'elementor');
const a11yOutdir = process.env.QA_OUTPUT_DIR
  ? path.join(process.env.QA_OUTPUT_DIR, 'a11y')
  : path.join(root, '.cache', 'ui-qa', 'a11y');

const slugs = ['home', 'about', 'menu', 'states-filled', 'states-empty'];
const schemes = ['light', 'dark'];
const widths = [430, 960, 1440];

{
  // The builder runs on every guard execution, not just --regen: its stderr is
  // part of the test (R3-03 — a widget must not warn or notice in any state).
  const built = spawnSync('php', [path.join(here, 'elementor-pages.php'), outdir], { encoding: 'utf8' });
  if (built.status !== 0) {
    process.stderr.write(built.stderr || 'fixture builder failed');
    process.exit(1);
  }
  const noise = /Fatal error|Warning:|Notice:|Deprecated:|Undefined (index|variable|array key)/;
  const output = `${built.stdout || ''}\n${built.stderr || ''}`;
  if (noise.test(output)) {
    console.error('✗ the fixture build emitted PHP diagnostics:');
    for (const line of output.split('\n').filter((l) => noise.test(l)).slice(0, 5)) console.error('  ' + line.trim());
    process.exit(1);
  }
  if (process.argv.includes('--regen')) process.stdout.write((built.stdout || '').replace(/^/gm, '  '));
}

{
  // The twin sweep (prompt 57) drives the a11y fixtures as well: the six
  // real page surfaces, each rendered in both directions. Same noise
  // contract as the elementor builder above.
  const builtA11y = spawnSync('php', [path.join(here, 'a11y-pages.php'), a11yOutdir], { encoding: 'utf8' });
  if (builtA11y.status !== 0) {
    process.stderr.write(builtA11y.stderr || 'a11y fixture builder failed');
    process.exit(1);
  }
  const noiseA11y = /Fatal error|Warning:|Notice:|Deprecated:|Undefined (index|variable|array key)/;
  const outputA11y = [builtA11y.stdout || '', builtA11y.stderr || ''].join(String.fromCharCode(10));
  if (noiseA11y.test(outputA11y)) {
    console.error('✗ the a11y fixture build emitted PHP diagnostics:');
    for (const line of outputA11y.split(String.fromCharCode(10)).filter((l) => noiseA11y.test(l)).slice(0, 5)) console.error('  ' + line.trim());
    process.exit(1);
  }
}

fs.mkdirSync(path.join(outdir, 'screenshots'), { recursive: true });

/* Static server: fixture pages from outdir, theme assets under /assets/**. */
const mime = { '.css': 'text/css', '.html': 'text/html', '.woff2': 'font/woff2', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer((req, res) => {
  const rel = decodeURIComponent(req.url.split('?')[0]);
  const mapping = rel.startsWith('/themes/flavor/')
    ? [path.join(root, 'flavor', path.normalize(rel.slice('/themes/flavor/'.length))), path.join(root, 'flavor')]
    : rel.startsWith('/assets/')
      ? [path.join(assetsRoot, path.normalize(rel.slice('/assets/'.length))), assetsRoot]
      : rel.startsWith('/a11y/')
        ? [path.join(a11yOutdir, path.normalize(rel.slice('/a11y/'.length))), a11yOutdir]
        : [path.join(outdir, path.normalize(rel)), outdir];
  const [file, base] = mapping;
  if (!file.startsWith(base + path.sep) && file !== base) { res.writeHead(404); res.end('nope'); return; }
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); res.end('missing ' + rel); return; }
  res.writeHead(200, { 'content-type': mime[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;

const browser = await chromium.launch();

const checks = `(() => {
  const parse = (s) => {
    const m = /rgba?\\(\\s*([\\d.]+)[,\\s]+([\\d.]+)[,\\s]+([\\d.]+)(?:[,/\\s]+([\\d.]+))?\\s*\\)/.exec(s || '');
    return m ? { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] } : null;
  };
  const lum = (c) => {
    const ch = [c.r, c.g, c.b].map((v) => {
      const s = v / 255;
      return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
  };
  const ratio = (a, b) => {
    const la = lum(a), lb = lum(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  };
  const compositeOver = (fg, bg) => ({
    r: Math.round(fg.r * fg.a + bg.r * (1 - fg.a)),
    g: Math.round(fg.g * fg.a + bg.g * (1 - fg.a)),
    b: Math.round(fg.b * fg.a + bg.b * (1 - fg.a)),
    a: 1,
  });

  /* 1. top-level section overlap (pairwise rect intersection). */
  const sections = [...document.querySelectorAll('.elementor-top-section')].filter((el) => el.offsetHeight > 0);
  const overlap = [];
  for (let i = 0; i < sections.length; i++) {
    for (let j = i + 1; j < sections.length; j++) {
      const a = sections[i].getBoundingClientRect(), b = sections[j].getBoundingClientRect();
      const ix = Math.min(a.right, b.right) - Math.max(a.left, b.left);
      const iy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
      if (ix > 1 && iy > 1) {
        overlap.push({ a: sections[i].dataset.id, b: sections[j].dataset.id, x: +ix.toFixed(1), y: +iy.toFixed(1) });
      }
    }
  }

  /* 2. text contrast for every visible text run inside rendered sections. */
  const rootFallback = parse(getComputedStyle(document.documentElement).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 };
  const contrast = [];
  let textRuns = 0;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let node;
  while ((node = walker.nextNode())) {
    const el = node.parentElement;
    if (!el || !node.textContent.trim() || !el.closest('.flavor-section')) continue;
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || +cs.opacity < 0.05 || cs.display === 'none') continue;
    const rect = el.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2) continue;
    textRuns++;
    const fg = parse(cs.color);
    if (!fg || fg.a < 0.05) continue; // invisible-by-design text (clipped labels etc.)
    let bg = null;
    for (let p = el; p && !(bg && bg.a === 1); p = p.parentElement) {
      const pcs = getComputedStyle(p);
      const c = parse(pcs.backgroundColor);
      if (c && c.a > 0) { bg = c; if (c.a === 1) break; continue; }
      if (pcs.backgroundImage && pcs.backgroundImage !== 'none') { bg = null; break; } // image/gradient underlay: not measurable, skip run
    }
    if (bg === null) continue;
    let base = bg.a === 1 ? bg : compositeOver(bg, rootFallback);
    /* translucent fg over base */
    const text = compositeOver({ ...fg, a: fg.a === 1 ? 1 : fg.a }, base);
    const value = ratio(text, base.a === 1 ? base : compositeOver(base, rootFallback));
    const px = parseFloat(cs.fontSize);
    const bold = +cs.fontWeight >= 700;
    const min = px >= 24 || (px >= 18.66 && bold) ? 3.0 : 4.5;
    if (value < min) {
      contrast.push({ text: node.textContent.trim().slice(0, 32), ratio: +value.toFixed(2), min, px: +px.toFixed(1), el: el.className.toString().slice(0, 48) });
    }
  }

  /* 3. horizontal overflow — main.css sets overflow-x:hidden, so scrollWidth
     can lie; also probe real element rects against the viewport edges. */
  let worst = 0;
  for (const el of document.querySelectorAll('.elementor *')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0) continue;
    worst = Math.max(worst, r.right - window.innerWidth, 0 - r.left);
  }
  const overflow = Math.round(Math.max(document.documentElement.scrollWidth - window.innerWidth, worst));

  return { sections: sections.length, textRuns, overlap, contrast: contrast.sort((a, b) => a.ratio - b.ratio), overflow };
})()`;

const violations = [];
const pages = [];
for (const slug of slugs) {
  for (const scheme of schemes) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(`${origin}/flavor-${slug}-${scheme}.html`, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.waitForTimeout(60);
      const shot = path.join(outdir, 'screenshots', `flavor-${slug}-${scheme}-${width}.png`);
      await page.screenshot({ path: shot, fullPage: true });
      const result = await page.evaluate(checks);
      pages.push({ page: `flavor-${slug}-${scheme}`, width, ...result });
      const tag = `${slug}-${scheme}@${width}`;
      if (slug.startsWith('states-') && width === 1440) {
        const shape = await page.evaluate(`(() => ({
          cards: document.querySelectorAll('.flavor-food-card').length,
          notes: document.querySelectorAll('.flavor-widget-note').length,
          banner: document.querySelectorAll('.flavor-offer-banner').length,
          gallery: document.querySelectorAll('.flavor-gallery__item').length,
          text: document.body.textContent,
        }))()`);
        const want = slug === 'states-filled'
          ? { cards: 3, notes: 0, banner: 1, gallery: 2 }
          : { cards: 0, notes: 3, banner: 0, gallery: 0 };
        for (const key of Object.keys(want)) {
          if (shape[key] !== want[key]) violations.push(`${tag}: data-state shape ${key}=${shape[key]}, expected ${want[key]}`);
        }
        if (slug === 'states-filled') {
          for (const needle of ['GUARD15', 'چلو کباب کوبیده']) {
            if (!shape.text.includes(needle)) violations.push(`${tag}: filled page missing "${needle}"`);
          }
        }
      }
      if (!result.sections) violations.push(`${tag}: no top-level sections rendered`);
      for (const o of result.overlap) violations.push(`${tag}: sections ${o.a}/${o.b} overlap ${o.x}x${o.y}px`);
      for (const c of result.contrast) violations.push(`${tag}: contrast ${c.ratio}:1 < ${c.min} on "${c.text}" (${c.el})`);
      if (result.overflow > 1) violations.push(`${tag}: horizontal overflow ${result.overflow}px`);
      const bytes = fs.statSync(shot).size;
      if (bytes < 2048) violations.push(`${tag}: screenshot too small (${bytes} bytes)`);
    }
    await page.close();
  }
}
/* ------------------------------------------------------------------ */
/* Twin direction sweep (prompt 57).                                    */
/*                                                                      */
/* Every main surface — the Elementor templates, the data-state pages,  */
/* the direction probe page and the six real a11y surfaces — renders    */
/* once per direction. The document's dir must win everywhere: no       */
/* sideways overflow, arrow icons mirrored in LTR only, and every       */
/* direction-bound animation, entrance and offset resolving to the      */
/* correct side. Pre-reveal parked states (enter transforms, the        */
/* fixed drawer, the travelling marquee) are layout-neutral on a live   */
/* page — JS clears them — so the overflow probe skips exactly those.   */
/* ------------------------------------------------------------------ */
const twinWidths = [1440, 430];
const twinDirs = ['rtl', 'ltr'];
const twinPages = [];
for (const slug of slugs) {
  for (const scheme of schemes) {
    twinPages.push({ name: `flavor-${slug}-${scheme}`, url: (d) => `${origin}/${d === 'rtl' ? '' : 'ltr/'}flavor-${slug}-${scheme}.html` });
  }
}
twinPages.push({
  name: 'flavor-states-direction-light',
  url: (d) => `${origin}/${d === 'rtl' ? '' : 'ltr/'}flavor-states-direction-light.html`,
  probePage: true,
});
for (const slug of ['home', 'menu', 'reservation', 'contact', 'about', 'tracking']) {
  twinPages.push({ name: `page-${slug}`, url: (d) => `${origin}/a11y/${d === 'rtl' ? '' : 'ltr/'}page-${slug}.html` });
}

const twinProbes = `(() => {
  const dir = document.documentElement.dir.toLowerCase();
  const cs = (sel, pseudo) => { const el = document.querySelector(sel); return el ? getComputedStyle(el, pseudo || null) : null; };
  const anim = (sel, pseudo) => { const s = cs(sel, pseudo); return s ? s.animationName : null; };
  const xf = (sel) => { const s = cs(sel); return s ? s.transform : null; };
  /* Only rendered arrows count: inside a display:none subtree Chromium
     reports the string transform as 'none' even though the mirror rule
     applies (the typed OM still shows scale(-1,1)). */
  const arrows = [...document.querySelectorAll('svg[data-icon="arrow"]')]
    .filter((el) => el.checkVisibility())
    .slice(0, 4)
    .map((el) => getComputedStyle(el).transform);
  const badge = document.querySelector('.flavor-about__exp-badge');
  let badgeGap = null;
  if (badge && badge.parentElement) {
    const b = badge.getBoundingClientRect();
    const p = badge.parentElement.getBoundingClientRect();
    badgeGap = dir === 'rtl' ? b.left - p.left : p.right - b.right;
  }
  /* Fixed subtrees never contribute to the document's scrollable overflow —
     the parked drawer and its children are viewport-relative by design — so
     the walk-up memo skips them wholesale instead of per element. */
  const fixedMemo = new Map();
  const inFixed = (el) => {
    if (fixedMemo.has(el)) return fixedMemo.get(el);
    let v = getComputedStyle(el).position === 'fixed';
    if (!v && el.parentElement && el.parentElement !== document.documentElement) v = inFixed(el.parentElement);
    fixedMemo.set(el, v);
    return v;
  };
  let overflow = 0;
  for (const el of document.querySelectorAll('body *')) {
    if (el.closest('.flavor-anim-marquee')) continue;
    if (el.classList.contains('fd-anim-enter') || el.classList.contains('flavor-anim-enter')) continue;
    if (inFixed(el)) continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0) continue;
    overflow = Math.max(overflow, r.right - window.innerWidth, 0 - r.left);
  }
  /* Parked-drawer geometry: fully off-screen on the direction's own side. */
  const dr = document.querySelector('.flavor-drawer');
  let drawerParked = null;
  if (dr && !dr.classList.contains('is-active') && dr.getClientRects().length) {
    const rr = dr.getBoundingClientRect();
    drawerParked = { left: Math.round(rr.left), right: Math.round(rr.right), vw: window.innerWidth };
  }
  return {
    dir,
    overflow: Math.round(overflow),
    arrows,
    marquee: anim('.flavor-anim-marquee > span'),
    drawer: xf('.flavor-drawer'),
    startEnter: xf('[data-flavor-anim="start"]'),
    endEnter: xf('[data-flavor-anim="end"]'),
    heroArt: anim('.flavor-hero__media'),
    megaItem: anim('.flavor-mega > .sub-menu > li'),
    /* Live walk: the probe page loads premium.js, whose top-level handler
       flips which arrow advances the nav; measure where focus lands after
       ArrowRight on the first link (rtl wraps backward, ltr steps forward). */
    walk: (() => {
      const links = [...document.querySelectorAll('.flavor-header__nav .flavor-nav > li > a')];
      if (links.length < 2 || !links[0].checkVisibility()) return { count: links.length, index: null };
      links[0].focus();
      links[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
      return { count: links.length, index: links.indexOf(document.activeElement) };
    })(),
    wishRow: anim('.flavor-wish__item'),
    cartLine: anim('.flavor-cart-line'),
    shimmer: anim('.flavor-anim-shimmer', '::before'),
    fdCopy: xf('.fd-hero__copy.fd-anim-enter'),
    fdArt: xf('.fd-hero__art.fd-anim-enter'),
    fdFaq: xf('.fd-faq details.fd-anim-enter'),
    fdVisit: anim('.fd-visit__card', '::after'),
    badgeGap,
    drawerParked,
  };
})()`;

const norm = (t) => (t || '').split(' ').join('');
const matrixX = (t) => {
  const s = norm(t);
  if (!s.startsWith('matrix(')) return 0;
  return parseFloat(s.slice(7, -1).split(',')[4]);
};
const mirrored = (t) => norm(t) === 'matrix(-1,0,0,1,0,0)';
const identity = (t) => !t || t === 'none' || norm(t) === 'matrix(1,0,0,1,0,0)';

const twin = [];
for (const entry of twinPages) {
  for (const dir of twinDirs) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.goto(entry.url(dir), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    for (const width of twinWidths) {
      await page.setViewportSize({ width, height: 900 });
      await page.waitForTimeout(50);
      const r = await page.evaluate(twinProbes);
      const tag = `twin ${entry.name}@${dir}${width}`;
      twin.push({ page: entry.name, dir, width, overflow: r.overflow, arrows: r.arrows.length });
      if (r.dir !== dir) violations.push(`${tag}: document dir is "${r.dir}", expected "${dir}" — the fixture lost the twin`);
      if (r.overflow > 1) violations.push(`${tag}: horizontal overflow ${r.overflow}px in ${dir}`);
      for (const t of r.arrows) {
        if (dir === 'ltr' ? !mirrored(t) : !identity(t)) violations.push(`${tag}: arrow icon transform "${t}" is not the ${dir} expectation`);
      }
      const wantAnim = (label, got, base) => {
        if (got === null || got === 'none' || got === '') return;
        const want = dir === 'ltr' ? `${base}-ltr` : base;
        if (got !== want) violations.push(`${tag}: ${label} animation is "${got}", expected "${want}"`);
      };
      wantAnim('marquee', r.marquee, 'flavor-m-marquee');
      wantAnim('hero art', r.heroArt, 'flavor-m-hero-art');
      wantAnim('mega item', r.megaItem, 'flavor-m-mega-item');
      if (entry.probePage) {
        if (!r.megaItem) violations.push(`${tag}: the probe page measured no mega-item animation — coverage lost`);
        if (!r.walk || r.walk.count < 2) violations.push(`${tag}: the probe page has no walkable top-level nav links`);
      }
      if (r.walk && r.walk.index !== null && r.walk.index !== 0) {
        const expected = dir === 'rtl' ? r.walk.count - 1 : 1;
        if (r.walk.index !== expected) violations.push(`${tag}: ArrowRight on the first nav link moved focus to index ${r.walk.index} — a ${dir} document expects index ${expected}`);
      }
      wantAnim('wishlist row', r.wishRow, 'flavor-m-wish-row');
      wantAnim('cart line', r.cartLine, 'flavor-m-line-in');
      wantAnim('skeleton gloss', r.shimmer, 'flavor-m-shimmer');
      wantAnim('visit shimmer', r.fdVisit, 'fd-shimmer');
      const wantSign = (label, t, rtlSign) => {
        if (t === null) return;
        const x = matrixX(t);
        if (Math.abs(x) < 1) return;
        const want = dir === 'rtl' ? rtlSign : -rtlSign;
        if (Math.sign(x) !== want) violations.push(`${tag}: ${label} sits at x=${x}px, expected the ${dir} side`);
      };
      wantSign('drawer', r.drawer, 1);
      if (r.drawerParked) {
        const dp = r.drawerParked;
        const off = dir === 'rtl' ? dp.left >= dp.vw - 1 : dp.right <= 1;
        if (!off) violations.push(`${tag}: parked drawer occupies [${dp.left},${dp.right}] of a ${dp.vw}px viewport — it must sit fully off-screen on the ${dir === 'rtl' ? 'right' : 'left'}`);
      }
      if (entry.probePage && r.arrows.length === 0) violations.push(`${tag}: the probe page rendered no visible arrow icon — the mirror check lost its subject`);
      wantSign('start entrance', r.startEnter, 1);
      wantSign('end entrance', r.endEnter, -1);
      wantSign('demo hero copy', r.fdCopy, 1);
      wantSign('demo hero art', r.fdArt, -1);
      wantSign('demo FAQ item', r.fdFaq, 1);
      if (entry.probePage && r.badgeGap !== null && Math.abs(r.badgeGap - 20) > 2) {
        violations.push(`${tag}: experience badge gap is ${r.badgeGap.toFixed(1)}px from the trailing edge, expected 20px`);
      }
      if (entry.probePage && width === 1440) {
        const shot = path.join(outdir, 'screenshots', `twin-states-direction-${dir}.png`);
        await page.screenshot({ path: shot, fullPage: true });
        if (fs.statSync(shot).size < 2048) violations.push(`${tag}: twin screenshot too small`);
      }
    }
    await page.close();
  }
}


/* ---------------------------------------------------------------- */
/* Scheme mechanism, live (prompt 58): the toggle, localStorage, the */
/* pre-paint boot script and the prefers-color-scheme auto path.     */
/* ---------------------------------------------------------------- */
const schemeViolations = [];
{
  const schemePage = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  await schemePage.goto(origin + '/flavor-states-direction-light.html', { waitUntil: 'load' });
  const fail = (msg) => { schemeViolations.push(msg); violations.push('scheme: ' + msg); };

  const hasToggle = await schemePage.evaluate(() => !!document.querySelector('[data-flavor-scheme-toggle]'));
  if (!hasToggle) {
    fail('the probe page rendered no [data-flavor-scheme-toggle] button');
  } else {
    const dayBg = await schemePage.evaluate(() => getComputedStyle(document.body).backgroundColor);

    /* 1 — one click paints night, persists the JSON-encoded choice, flips aria-pressed. */
    await schemePage.click('[data-flavor-scheme-toggle]');
    await schemePage.waitForTimeout(420); /* let the cross-fade land */
    const clicked = await schemePage.evaluate(() => ({
      attr: document.documentElement.getAttribute('data-flavor-scheme'),
      stored: window.localStorage.getItem('flavorScheme'),
      bg: getComputedStyle(document.body).backgroundColor,
      pressed: document.querySelector('[data-flavor-scheme-toggle]').getAttribute('aria-pressed'),
    }));
    if (clicked.attr !== 'dark') fail(`toggle click left data-flavor-scheme="${clicked.attr}", expected "dark"`);
    if (clicked.stored !== '"dark"') fail(`toggle click stored ${JSON.stringify(clicked.stored)} — premium.js must persist the JSON-encoded choice`);
    if (clicked.bg === dayBg) fail(`toggle click did not repaint the page (body background stayed ${clicked.bg}) — the night palette never applied`);
    if (clicked.pressed !== 'true') fail(`toggle click left aria-pressed="${clicked.pressed}", expected "true" under night`);

    /* 2 — reload: the pre-paint boot script must restore the stored choice. */
    await schemePage.reload({ waitUntil: 'load' });
    const booted = await schemePage.evaluate(() => ({
      attr: document.documentElement.getAttribute('data-flavor-scheme'),
      bg: getComputedStyle(document.body).backgroundColor,
    }));
    if (booted.attr !== 'dark') fail(`after reload the boot script did not restore the stored night choice (data-flavor-scheme="${booted.attr}")`);
    if (booted.bg === dayBg) fail('after reload the page painted the day background despite the stored night choice — the white-flash bug is back');

    /* 3 — click back to day. */
    await schemePage.click('[data-flavor-scheme-toggle]');
    await schemePage.waitForTimeout(420);
    const back = await schemePage.evaluate(() => ({
      attr: document.documentElement.getAttribute('data-flavor-scheme'),
      stored: window.localStorage.getItem('flavorScheme'),
      bg: getComputedStyle(document.body).backgroundColor,
    }));
    if (back.attr !== 'light' || back.stored !== '"light"') fail(`toggle back produced attr="${back.attr}" stored=${JSON.stringify(back.stored)}, expected light and "light"`);
    if (back.bg !== dayBg) fail(`toggle back painted ${back.bg}, expected the day background ${dayBg}`);

    /* 4 — auto follows the device, with no stored choice at all. */
    await schemePage.evaluate(() => {
      window.localStorage.removeItem('flavorScheme');
      document.documentElement.setAttribute('data-flavor-scheme', 'auto');
    });
    await schemePage.emulateMedia({ colorScheme: 'dark' });
    await schemePage.waitForTimeout(420);
    const autoDark = await schemePage.evaluate(() => ({
      bg: getComputedStyle(document.body).backgroundColor,
      pressed: document.querySelector('[data-flavor-scheme-toggle]').getAttribute('aria-pressed'),
    }));
    if (autoDark.bg === dayBg) fail('auto mode: prefers-color-scheme dark did not paint the night palette');
    if (autoDark.pressed !== 'true') fail(`auto mode: the toggle reports aria-pressed="${autoDark.pressed}" while the device prefers dark`);

    /* 5 — the device flips back to light: auto follows. */
    await schemePage.emulateMedia({ colorScheme: 'light' });
    await schemePage.waitForTimeout(420);
    const autoLight = await schemePage.evaluate(() => ({
      bg: getComputedStyle(document.body).backgroundColor,
      pressed: document.querySelector('[data-flavor-scheme-toggle]').getAttribute('aria-pressed'),
    }));
    if (autoLight.bg !== dayBg) fail(`auto mode: flipping the device back to light painted ${autoLight.bg}, expected ${dayBg}`);
    if (autoLight.pressed !== 'false') fail(`auto mode: aria-pressed stayed "${autoLight.pressed}" after the device flipped to light`);
  }
  await schemePage.close();
}

await browser.close();
server.close();

const report = {
  generatedBy: 'tests/ui/elementor-visual.mjs',
  templates: slugs.map((s) => `flavor-${s}`),
  widths,
  schemes,
  pages,
  twin: { dirs: twinDirs, widths: twinWidths, renders: twin },
  scheme: { violations: schemeViolations.length },
  violations,
};
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 2) + '\n');

const totalSections = pages.reduce((n, p) => n + p.sections, 0);
const totalRuns = pages.reduce((n, p) => n + p.textRuns, 0);
const shotCount = fs.readdirSync(path.join(outdir, 'screenshots')).filter((f) => f.endsWith('.png')).length;
console.log(`صفحات: ${pages.length} · بخش‌ها: ${totalSections} · تکه‌متن‌های سنجیده‌شده: ${totalRuns} · اسکرین‌شات: ${shotCount}`);
console.log(`گشتِ دوقلو: ${twin.length} رندر (${twinDirs.join(' + ')}) در ${twinWidths.join(' + ')} پیکسل — dir، سرریز، آیکون‌ها، انیمیشن‌ها و آفست‌ها در هر دو جهت سنجیده شد`);
console.log('مکانیسم شب/روز: کلیکِ تاگل، ذخیرهٔ localStorage، بازیابی اسکریپتِ بوت پس از رفرش و حالتِ خودکارِ prefers-color-scheme — زنده سنجیده شد');

if (violations.length) {
  for (const v of violations.slice(0, 40)) console.error('✗ ' + v);
  console.error(`violation: ${violations.length} visual fault(s) (report: ${path.join(outdir, 'report.json')})`);
  process.exit(1);
}
console.log(`✅ elementor-visual: ${pages.length} page×width checks + ${twin.length} twin renders + live scheme mechanism passed (overlap 0 · contrast ≥ AA · overflow 0 · mirrored both ways · night persists)`);
