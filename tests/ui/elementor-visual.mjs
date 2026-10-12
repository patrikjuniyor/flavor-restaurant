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

fs.mkdirSync(path.join(outdir, 'screenshots'), { recursive: true });

/* Static server: fixture pages from outdir, theme assets under /assets/**. */
const mime = { '.css': 'text/css', '.html': 'text/html', '.woff2': 'font/woff2', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
const server = http.createServer((req, res) => {
  const rel = decodeURIComponent(req.url.split('?')[0]);
  const mapping = rel.startsWith('/themes/flavor/')
    ? [path.join(root, 'flavor', path.normalize(rel.slice('/themes/flavor/'.length))), path.join(root, 'flavor')]
    : rel.startsWith('/assets/')
      ? [path.join(assetsRoot, path.normalize(rel.slice('/assets/'.length))), assetsRoot]
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
await browser.close();
server.close();

const report = {
  generatedBy: 'tests/ui/elementor-visual.mjs',
  templates: slugs.map((s) => `flavor-${s}`),
  widths,
  schemes,
  pages,
  violations,
};
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 2) + '\n');

const totalSections = pages.reduce((n, p) => n + p.sections, 0);
const totalRuns = pages.reduce((n, p) => n + p.textRuns, 0);
const shotCount = fs.readdirSync(path.join(outdir, 'screenshots')).filter((f) => f.endsWith('.png')).length;
console.log(`صفحات: ${pages.length} · بخش‌ها: ${totalSections} · تکه‌متن‌های سنجیده‌شده: ${totalRuns} · اسکرین‌شات: ${shotCount}`);

if (violations.length) {
  for (const v of violations.slice(0, 40)) console.error('✗ ' + v);
  console.error(`violation: ${violations.length} visual fault(s) (report: ${path.join(outdir, 'report.json')})`);
  process.exit(1);
}
console.log(`✅ elementor-visual: ${pages.length} page×width checks passed (overlap 0 · contrast ≥ AA · overflow 0)`);
