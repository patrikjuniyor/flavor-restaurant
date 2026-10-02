/*
 * Regression guard for the site logo box.
 *
 * `custom-logo` is registered with flex-width/flex-height, so WordPress prints
 * whatever file the restaurant owner uploaded at its intrinsic size. A
 * 1024x1024 logo used to render 1024px tall and, because the header is
 * sticky, cover the whole viewport. The only thing standing between a raw
 * upload and a broken header is the CSS box in `assets/css/main.css`
 * (and `bespoke-demos.css` for the landing family).
 *
 * This check needs no WordPress: it serves the real theme stylesheets against
 * the exact markup `the_custom_logo()` emits, for the logo shapes owners
 * actually upload, and fails if the header leaves its budget.
 *
 * Usage: node logo-box.mjs
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
const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'flavor-logo-'));

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

/* Shapes restaurant owners really hand over: a square app icon, a wide
   signboard lockup and a tall stacked mark. */
const logos = [
  { file: 'square.png', w: 1024, h: 1024, label: 'square 1024x1024' },
  { file: 'wide.png', w: 1600, h: 300, label: 'wide 1600x300' },
  { file: 'tall.png', w: 400, h: 1200, label: 'tall 400x1200' },
];
logos.forEach(l => png(l.file, l.w, l.h));

const tokens = '<style id="flavor-tokens">:root{--flavor-logo-height:52px;--flavor-logo-height-mobile:37px;--flavor-logo-max-width:208px}</style>';

/* Exactly what the_custom_logo() prints. */
const logoMarkup = (logo) =>
  `<a href="#" class="custom-logo-link" rel="home"><img width="${logo.w}" height="${logo.h}" src="/img/${logo.file}" class="custom-logo" alt="رستوران" decoding="async" /></a>`;

const classicPage = (logo) => `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/css/main.css" /><link rel="stylesheet" href="/css/marketing.css" />
<link rel="stylesheet" href="/css/rtl.css" /><link rel="stylesheet" href="/css/ui-navigation.css" />${tokens}</head>
<body class="flavor-theme flavor-skin-modern-restaurant flavor-header-default">
<header class="flavor-header" id="flavor-site-header"><div class="flavor-container flavor-header__inner">
<div class="flavor-brand">${logoMarkup(logo)}</div>
<nav class="flavor-header__nav"><ul class="flavor-nav"><li><a href="#">صفحه اصلی</a></li><li><a href="#">منو</a></li><li><a href="#">رزرو</a></li></ul></nav>
<div class="flavor-header__actions"><a href="tel:0" class="flavor-header__phone"><span>۰۲۱۸۸۰۰۱۲۳۴</span></a>
<a href="#" class="flavor-btn flavor-btn--primary flavor-btn--sm">سفارش آنلاین</a>
<button type="button" class="flavor-hamburger"><span></span><span></span><span></span></button></div></div></header>
<main id="main" class="flavor-main"><footer class="flavor-footer"><div class="flavor-container flavor-footer__inner">
<div class="flavor-footer__grid"><div class="flavor-footer__col flavor-footer__col--brand">
<div class="flavor-footer__brand">${logoMarkup(logo)}</div><p class="flavor-footer__bio">تجربه طعم اصیل.</p>
</div></div></div></footer></main></body></html>`;

const bespokePage = (logo) => `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/css/main.css" /><link rel="stylesheet" href="/css/marketing.css" />
<link rel="stylesheet" href="/css/rtl.css" /><link rel="stylesheet" href="/css/ui-navigation.css" />
<link rel="stylesheet" href="/css/bespoke-demos.css" />${tokens}</head>
<body class="flavor-theme flavor-bespoke flavor-skin-minimal-clean flavor-header-default">
<header class="flavor-header fd-header" id="flavor-site-header"><div class="flavor-container flavor-header__inner">
<div class="fd-brand">${logoMarkup(logo)}</div>
<nav class="flavor-header__nav"><ul class="flavor-nav"><li><a href="#">منو</a></li><li><a href="#">داستان ما</a></li><li><a href="#">رزرو</a></li></ul></nav>
<div class="flavor-header__actions"><a class="fd-header__phone" href="tel:0"><bdi>۰۲۱۸۸۰۰۱۲۳۴</bdi></a>
<a class="fd-button fd-button--primary fd-header__cta" href="#">مشاهده منو</a>
<button type="button" class="flavor-hamburger"><span></span><span></span><span></span></button></div></div></header>
<main id="main" class="flavor-main"></main></body></html>`;

const pages = new Map();
for (const logo of logos) {
  pages.set(`/classic-${logo.file}`, classicPage(logo));
  pages.set(`/bespoke-${logo.file}`, bespokePage(logo));
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

/* A logo may never own more than this slice of the header row. */
const budgets = [
  { width: 320, header: 110 },
  { width: 390, header: 110 },
  { width: 768, header: 140 },
  { width: 1024, header: 140 },
  { width: 1440, header: 140 },
];

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const results = [];
let failed = 0;

try {
  for (const family of ['classic', 'bespoke']) {
    for (const logo of logos) {
      for (const budget of budgets) {
        const page = await browser.newPage({ viewport: { width: budget.width, height: 900 } });
        await page.goto(`${base}/${family}-${logo.file}`, { waitUntil: 'networkidle' });

        const measured = await page.evaluate(() => {
          const box = el => {
            if (!el) return null;
            const rect = el.getBoundingClientRect();
            return { w: Math.round(rect.width), h: Math.round(rect.height) };
          };
          return {
            header: box(document.querySelector('.flavor-header')),
            logo: box(document.querySelector('.flavor-brand .custom-logo, .fd-brand .custom-logo')),
            footerLogo: box(document.querySelector('.flavor-footer__brand .custom-logo')),
            scrollWidth: document.documentElement.scrollWidth,
            viewport: window.innerWidth,
          };
        });
        await page.close();

        const row = {
          family,
          shape: logo.label,
          width: budget.width,
          header: measured.header,
          logoBox: `${measured.logo.w}x${measured.logo.h}`,
          footerLogo: measured.footerLogo,
          scrollWidth: measured.scrollWidth,
        };
        results.push(row);

        try {
          assert.ok(
            measured.header.h <= budget.header,
            `${family} / ${logo.label} @${budget.width}px: header is ${measured.header.h}px, budget ${budget.header}px`
          );
          assert.ok(
            measured.logo.h <= 60 && measured.logo.w <= Math.max(210, budget.width * 0.46),
            `${family} / ${logo.label} @${budget.width}px: logo box ${measured.logo.w}x${measured.logo.h} left its cap`
          );
          assert.ok(
            measured.scrollWidth <= measured.viewport + 1,
            `${family} / ${logo.label} @${budget.width}px: horizontal overflow (${measured.scrollWidth} > ${measured.viewport})`
          );
          if (measured.footerLogo) {
            assert.ok(
              measured.footerLogo.h <= 80,
              `${family} / ${logo.label} @${budget.width}px: footer logo is ${measured.footerLogo.h}px tall`
            );
          }
          row.pass = true;
        } catch (error) {
          row.pass = false;
          failed++;
          console.error('FAIL ' + error.message);
        }
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
    `${row.pass ? 'pass' : 'FAIL'}  ${row.family.padEnd(8)} ${row.shape.padEnd(18)} @${String(row.width).padStart(4)}px  ` +
    `header=${String(row.header.h).padStart(3)}px  logo=${row.logoBox}` +
    `${row.footerLogo ? `  footer=${row.footerLogo.w}x${row.footerLogo.h}` : ''}`
  );
}

console.log(`\n${results.length - failed}/${results.length} logo-box checks passed.`);
if (failed) process.exit(1);
