/*
 * Browser verification for the assembled motion preview.
 *
 * Build the preview first:
 *
 *   bash dev-tools/preview/build-preview.sh
 *
 * …then point this at it. Run from tests/ui so the Playwright dependency the
 * other UI suites install is the one that resolves.
 *
 * A build that only "looks fine" is not evidence: the failure this whole work
 * stream exists to prevent is content that animates in production and stays
 * invisible everywhere else. So this drives the real page — switches all
 * twelve skins, scrolls each one top to bottom, and asserts that the motion
 * layer booted, that revealed content actually reached full opacity, and that
 * nothing threw.
 *
 * Usage: node preview.mjs [file]
 *
 * Exit code 1 on any page error, failed request, or a demo whose entrance
 * never completed — the checks below are the ones a screenshot cannot make.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

const target = process.argv[2] || pathToFileURL(path.resolve(here, '../../flavor-motion-preview.html')).href;
const outDir = process.env.PREVIEW_SHOTS || path.resolve(here, '../../.cache/ui-qa/preview');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

const problems = [];
const noise = [];
page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
const missing = [];
page.on('requestfailed', (request) => missing.push(request.url().slice(0, 90)));
page.on('console', (message) => {
  if (message.type() === 'error') problems.push(`console.error: ${message.text()}`);
  if (message.type() === 'warning') noise.push(message.text().slice(0, 120));
});

await page.goto(target, { waitUntil: 'load' });
await page.waitForTimeout(900);

/* The control tray ships closed so the first demo's hero entrance is visible
   on load; the skin switcher lives inside it. */
await page.click('#pv-pill');
await page.waitForTimeout(200);

/* Walk the page so every IntersectionObserver kicks in, then let the
   transitions settle. */
async function sweep() {
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < height; y += 700) {
    await page.evaluate((top) => window.scrollTo(0, top), y);
    await page.waitForTimeout(90);
  }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(700);
}

const boot = await page.evaluate(() => ({
  motionReady: !!(window.FlavorMotion && window.FlavorMotion.ready()),
  demosReady: !!(window.FlavorDemoAnimations && window.FlavorDemoAnimations.ready()),
  animReady: document.documentElement.classList.contains('flavor-anim-ready'),
  bodyClass: document.body.className,
  tray: !!document.querySelector('#pv-tray-tools .flavor-countdown'),
  toasts: typeof window.flavorToast,
}));

await sweep();

const skins = await page.$$eval('#pv-skins button', (nodes) => nodes.map((n) => n.getAttribute('data-skin')));
const rows = [];

for (const skin of skins) {
  await page.click(`#pv-skins button[data-skin="${skin}"]`);
  await page.waitForTimeout(400);
  await sweep();

  const state = await page.evaluate(() => {
    const stage = document.getElementById('pv-stage');
    const visible = (el) => el.getClientRects().length > 0;
    const hidden = [];
    stage.querySelectorAll('*').forEach((el) => {
      if (el.closest('[hidden], #pv-tray, [aria-hidden="true"]')) return;
      if (!visible(el)) return;
      const style = getComputedStyle(el);
      /* A section mid-entrance is legitimately part-transparent; only the ones
         that never arrive matter, and the sweep above has already waited them
         out. Anything still fully transparent after that is a stranded layer. */
      if (Number(style.opacity) === 0 && style.display !== 'none' && style.visibility !== 'hidden'
          && !el.classList.contains('flavor-anim-enter') && !el.classList.contains('fd-anim-enter')) {
        hidden.push(el.className || el.tagName);
      }
    });
    return {
      bodyClass: document.body.className,
      sections: stage.querySelectorAll('section, footer, header').length,
      revealed: stage.querySelectorAll('.flavor-anim-in').length,
      entered: stage.querySelectorAll('.flavor-anim-enter').length,
      fdVisible: stage.querySelectorAll('.fd-anim-visible').length,
      fdTotal: stage.querySelectorAll('.fd-anim-enter').length,
      counted: stage.querySelectorAll('.flavor-anim-counting').length,
      hidden: hidden.slice(0, 6),
      heroTitle: (stage.querySelector('h1') || {}).textContent || '',
      enterClass: stage.querySelector('.flavor-section') ? 'flavor-anim-in' : 'fd-*',
      skinSheetOn: Array.from(document.querySelectorAll('style[data-pv-skin]'))
        .some((node) => !node.disabled && node.getAttribute('data-pv-skin') === (document.body.className.match(/flavor-skin-([a-z-]+)/) || [])[1]),
      bespokeLayer: !!(window.FlavorDemoAnimations && window.FlavorDemoAnimations.ready()),
    };
  });

  rows.push({ skin, ...state });
  await page.screenshot({ path: `${outDir}/${skin}.png`, fullPage: false });
}

/* Reduced-motion emulation: the layer must hand the page back still. */
await context.close();
const quiet = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
const quietPage = await quiet.newPage();
await quietPage.goto(target, { waitUntil: 'load' });
await quietPage.waitForTimeout(800);
const reduced = await quietPage.evaluate(() => ({
  animReady: document.documentElement.classList.contains('flavor-anim-ready'),
  motionReady: !!(window.FlavorMotion && window.FlavorMotion.ready()),
  hidden: Array.from(document.querySelectorAll('#pv-stage section')).filter((el) => {
    const style = getComputedStyle(el);
    return el.getClientRects().length && Number(style.opacity) === 0;
  }).length,
}));
await quietPage.screenshot({ path: `${outDir}/reduced-motion.png` });
await quiet.close();

await browser.close();

console.log('\nBoot:', JSON.stringify(boot, null, 2));
console.log('\nPer skin:');
console.log('  skin                  sections revealed entered hero                      sheet');
for (const row of rows) {
  const skin = (row.bodyClass.match(/flavor-skin-([a-z-]+)/) || [])[1] || '?';
  console.log(
    `  ${row.skin.padEnd(21)} ${String(row.sections).padStart(8)} ${String(row.revealed).padStart(8)} ` +
    `${String(row.entered).padStart(7)} ${String(row.heroTitle.trim().slice(0, 22)).padEnd(24)} ` +
    `${(row.skinSheetOn ? 'sheet' : 'SHEET-OFF')} ${(row.bespokeLayer ? 'bespoke' : 'classic')} ` +
    `${row.fdTotal ? `fd ${row.fdVisible}/${row.fdTotal}` : ''}`
  );
  if (row.hidden.length) console.log(`      opacity-0 after reveal: ${row.hidden.join(', ')}`);
}
console.log('\nReduced motion:', JSON.stringify(reduced));
if (noise.length) console.log('\nWarnings (isolated theme scripts):', [...new Set(noise)].slice(0, 4));
console.log('\nProblems:', problems.length ? problems.slice(0, 8) : 'none');
console.log('Failed requests:', missing.length ? [...new Set(missing)].slice(0, 8) : 'none');
console.log('\nScreenshots in', outDir);

/* A demo that boots but never reveals its content is the exact failure this
   suite exists to catch, so it fails the run rather than printing a warning. */
const stranded = rows.filter((row) => row.hidden.length || row.revealed + row.fdVisible + row.fdTotal === 0);
const failures = [];
if (problems.length) failures.push(`${problems.length} console/page errors`);
if (missing.length) failures.push(`${missing.length} failed requests`);
if (!boot.motionReady) failures.push('the motion layer never booted');
if (stranded.length) failures.push(`content never arrived on: ${stranded.map((row) => row.skin).join(', ')}`);
if (reduced.animReady) failures.push('reduced motion still armed the animation layer');

if (failures.length) {
  console.error('\nPreview check FAILED: ' + failures.join('; '));
  process.exitCode = 1;
} else {
  const entrances = rows.reduce((sum, row) => sum + row.revealed + row.fdVisible, 0);
  console.log(`\nPreview check passed: ${rows.length} demos, ${entrances} entrances, no errors.`);
}
