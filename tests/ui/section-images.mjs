/*
 * Regression guard for the homepage section images.
 *
 * The About, Reservation and Gallery blocks now take their picture from a
 * Customizer setting, so whatever the restaurant owner uploads lands straight
 * in the page: a portrait phone photo, a panorama, a square crop. Before the
 * fix `.flavor-about__img-wrapper img` carried nothing but `width: 100%`,
 * and a 900x2700 upload painted ~2160px tall, pushing the copy beside it off
 * the screen. The same applied to `.fb-image img` in the page builder.
 *
 * Like logo-box.mjs this needs no WordPress: it serves the real theme
 * stylesheets against the markup the templates emit, for every skin, and
 * fails if an image leaves its box.
 *
 * Usage: node section-images.mjs
 */
import { chromium } from 'playwright';
import http from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import zlib from 'node:zlib';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const cssDir = path.resolve(here, '../../flavor/assets/css');
const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'flavor-sections-'));

/* Minimal solid-colour PNG, so the fixture needs no binary assets in git. */
function png(file, width, height) {
  const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(width * 3, 0x7a)]);
  const chunk = (type, data) => {
    const body = Buffer.concat([Buffer.from(type), data]);
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(zlib.crc32 ? zlib.crc32(body) >>> 0 : crc32(body));
    return Buffer.concat([len, body, crc]);
  };
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8;
  ihdr[9] = 2;
  const data = Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(Buffer.concat(Array.from({ length: height }, () => row)))),
    chunk('IEND', Buffer.alloc(0)),
  ]);
  fs.writeFileSync(path.join(workDir, file), data);
}

function crc32(buf) {
  let c = ~0;
  for (const b of buf) {
    c ^= b;
    for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return ~c >>> 0;
}

/* Shapes that come straight out of a phone or a stock library. */
const shapes = [
  { file: 'tall.png', w: 900, h: 2700, label: 'portrait 900x2700' },
  { file: 'wide.png', w: 3000, h: 700, label: 'panorama 3000x700' },
  { file: 'square.png', w: 1500, h: 1500, label: 'square 1500x1500' },
];
shapes.forEach(s => png(s.file, s.w, s.h));

const classicSkins = [
  'modern-restaurant', 'luxury-dining', 'persian-traditional',
  'pizza-italian', 'fast-food', 'cafe-bistro', 'bakery-pastry',
];
const bespokeSkins = ['juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering'];
const skins = [...classicSkins, ...bespokeSkins];

const tokens = '<style id="flavor-tokens">:root{--flavor-logo-height:52px;--flavor-logo-height-mobile:37px;--flavor-logo-max-width:208px}</style>';

const img = (shape, extra = '') =>
  `<img src="/img/${shape.file}" width="${shape.w}" height="${shape.h}" alt="تصویر رستوران" loading="lazy" decoding="async"${extra} />`;

/* about.php, reservation-cta.php and gallery.php, parent chain intact. */
const sectionsPage = (skin, shape) => {
  const bespoke = bespokeSkins.includes(skin);
  return `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/css/main.css" /><link rel="stylesheet" href="/css/marketing.css" />
<link rel="stylesheet" href="/css/rtl.css" />${bespoke ? '<link rel="stylesheet" href="/css/bespoke-demos.css" />' : ''}
<link rel="stylesheet" href="/css/skins/${skin}.css" />${tokens}</head>
<body class="flavor-theme${bespoke ? ' flavor-bespoke' : ''} flavor-skin-${skin} flavor-header-default">
<main id="main" class="flavor-main">

<section class="flavor-section flavor-about" aria-label="درباره ما">
 <div class="flavor-container"><div class="flavor-about__inner">
  <div class="flavor-about__media"><div class="flavor-about__img-wrapper">
   ${img(shape)}
   <div class="flavor-about__exp-badge"><strong>۱۰+</strong><span>سال تجربه و افتخار میزبانی</span></div>
  </div></div>
  <div class="flavor-about__content"><span class="flavor-section-header__tag">درباره ما</span>
   <h2 class="flavor-about__title">داستان و فلسفه آشپزی ما</h2>
   <div class="flavor-about__text"><p>مواد اولیه روزانه و با وسواس از برترین مزارع تهیه می‌شوند.</p></div>
   <div class="flavor-about__stats">
    <div class="flavor-about__stat"><span class="flavor-about__stat-num">۱۰۰٪</span><span class="flavor-about__stat-lbl">مواد اولیه تازه</span></div>
    <div class="flavor-about__stat"><span class="flavor-about__stat-num">۴.۹ ★</span><span class="flavor-about__stat-lbl">رضایت مهمان‌ها</span></div>
    <div class="flavor-about__stat"><span class="flavor-about__stat-num">۳۵+</span><span class="flavor-about__stat-lbl">تنوع آیتم‌ها</span></div>
   </div>
  </div>
 </div></div>
</section>

<section class="flavor-section flavor-res-cta" aria-label="رزرو میز">
 <div class="flavor-container"><div class="flavor-res-banner">
  <div class="flavor-res-banner__content"><span class="flavor-res-banner__tag">پذیرایی اختصاصی</span>
   <h2 class="flavor-res-banner__title">میز خود را رزرو کنید</h2>
   <p class="flavor-res-banner__desc">برای دورهمی‌های خانوادگی و جشن‌های خاطره‌انگیز.</p>
   <div class="flavor-res-banner__action"><a href="#" class="flavor-btn flavor-btn--primary flavor-btn--lg">رزرو میز</a></div>
  </div>
  <div class="flavor-res-banner__media">${img(shape)}</div>
 </div></div>
</section>

<section class="flavor-section flavor-gallery" aria-label="گالری">
 <div class="flavor-container">
  <div class="flavor-section-header"><h2 class="flavor-section-header__title">گالری تصاویر رستوران</h2></div>
  <div class="flavor-gallery__grid">
   ${[1, 2, 3, 4, 5, 6].map(n => `<figure class="flavor-gallery__item flavor-gallery__item--${n}">${img(shape)}</figure>`).join('\n   ')}
  </div>
 </div>
</section>

</main></body></html>`;
};

/* A page-builder image block, as the builder renders it. */
const builderPage = (shape) => `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/css/main.css" /><link rel="stylesheet" href="/css/marketing.css" />
<link rel="stylesheet" href="/css/rtl.css" /><link rel="stylesheet" href="/css/builder.css" />${tokens}</head>
<body class="flavor-theme flavor-skin-modern-restaurant">
<main id="main" class="flavor-main"><div class="flavor-container">
<div class="fb-block fb-image">${img(shape)}</div>
</div></main></body></html>`;

const pages = new Map();
for (const shape of shapes) {
  for (const skin of skins) pages.set(`/sections-${skin}-${shape.file}`, sectionsPage(skin, shape));
  pages.set(`/builder-${shape.file}`, builderPage(shape));
}

const mime = { '.css': 'text/css', '.png': 'image/png' };
const server = http.createServer((req, res) => {
  const url = decodeURIComponent(req.url.split('?')[0]);
  if (pages.has(url)) {
    res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
    return res.end(pages.get(url));
  }
  const file = url.startsWith('/css/')
    ? path.join(cssDir, url.slice(5))
    : path.join(workDir, url.slice(5));
  fs.readFile(file, (err, body) => {
    if (err) {
      res.writeHead(404);
      return res.end('not found');
    }
    res.writeHead(200, { 'content-type': mime[path.extname(file)] || 'application/octet-stream' });
    res.end(body);
  });
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;

/*
 * Height ceilings per viewport. Generous enough for the skins that
 * deliberately set a tall `min-height` on the about photo (luxury-dining
 * asks for 600px), tight enough that an unboxed upload cannot pass.
 */
const viewportHeight = 900;

/* `.fb-image img` is capped at 80vh, so the budget has to track the viewport. */
const builderBudget = Math.round(viewportHeight * 0.8) + 2;

const budgets = [
  { width: 390, about: 470, res: 470, tile: 420 },
  { width: 768, about: 640, res: 640, tile: 520 },
  { width: 1440, about: 680, res: 640, tile: 460 },
].map(b => ({ ...b, builder: builderBudget }));

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const results = [];
let failed = 0;

const box = (page, selector) => page.evaluate((sel) => {
  const el = document.querySelector(sel);
  if (!el) return null;
  const rect = el.getBoundingClientRect();
  return { w: Math.round(rect.width), h: Math.round(rect.height) };
}, selector);

try {
  for (const shape of shapes) {
    for (const budget of budgets) {
      for (const skin of skins) {
        const page = await browser.newPage({ viewport: { width: budget.width, height: viewportHeight } });
        await page.goto(`${base}/sections-${skin}-${shape.file}`, { waitUntil: 'networkidle' });

        const measured = {
          about: await box(page, '.flavor-about__img-wrapper img'),
          res: await box(page, '.flavor-res-banner__media img'),
          tile: await box(page, '.flavor-gallery__item img'),
          ...(await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            viewport: window.innerWidth,
          }))),
        };
        await page.close();

        const row = {
          target: skin,
          shape: shape.label,
          width: budget.width,
          note: `about=${measured.about.w}x${measured.about.h} res=${measured.res.w}x${measured.res.h} tile=${measured.tile.w}x${measured.tile.h}`,
        };
        results.push(row);

        try {
          for (const [key, label] of [['about', 'about photo'], ['res', 'reservation photo'], ['tile', 'gallery tile']]) {
            assert.ok(
              measured[key].h <= budget[key],
              `${skin} / ${shape.label} @${budget.width}px: ${label} is ${measured[key].h}px tall, budget ${budget[key]}px`
            );
            assert.ok(
              measured[key].h > 0 && measured[key].w > 0,
              `${skin} / ${shape.label} @${budget.width}px: ${label} collapsed to ${measured[key].w}x${measured[key].h}`
            );
          }
          assert.ok(
            measured.scrollWidth <= measured.viewport + 1,
            `${skin} / ${shape.label} @${budget.width}px: horizontal overflow (${measured.scrollWidth} > ${measured.viewport})`
          );
          row.pass = true;
        } catch (error) {
          row.pass = false;
          failed++;
          console.error('FAIL ' + error.message);
        }
      }

      /* The builder block is skin-independent, so it runs once per viewport. */
      const page = await browser.newPage({ viewport: { width: budget.width, height: viewportHeight } });
      await page.goto(`${base}/builder-${shape.file}`, { waitUntil: 'networkidle' });
      const builder = await box(page, '.fb-image img');
      const overflow = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
      }));
      await page.close();

      const row = {
        target: 'builder block',
        shape: shape.label,
        width: budget.width,
        note: `image=${builder.w}x${builder.h}`,
      };
      results.push(row);

      try {
        assert.ok(
          builder.h <= budget.builder,
          `builder / ${shape.label} @${budget.width}px: image is ${builder.h}px tall, budget ${budget.builder}px`
        );
        assert.ok(
          builder.w <= budget.width,
          `builder / ${shape.label} @${budget.width}px: image is ${builder.w}px wide, viewport ${budget.width}px`
        );
        assert.ok(
          overflow.scrollWidth <= overflow.viewport + 1,
          `builder / ${shape.label} @${budget.width}px: horizontal overflow (${overflow.scrollWidth} > ${overflow.viewport})`
        );
        row.pass = true;
      } catch (error) {
        row.pass = false;
        failed++;
        console.error('FAIL ' + error.message);
      }
    }
  }
} finally {
  await browser.close();
  server.close();
  fs.rmSync(workDir, { recursive: true, force: true });
}

for (const row of results) {
  console.log(
    `${row.pass ? 'pass' : 'FAIL'}  ${row.target.padEnd(20)} ${row.shape.padEnd(18)} @${String(row.width).padStart(4)}px  ${row.note}`
  );
}

console.log(`\n${results.length - failed}/${results.length} section-image checks passed.`);
if (failed) process.exit(1);
