/*
 * Motion layer regression guard.
 *
 * The twelve demos each ship an art-direction block, and two animation scripts
 * sit underneath them. That is a lot of surface for faults that are invisible
 * in a screenshot of a page that happens to work:
 *
 *   - a skin block gated on the wrong script's ready class, so it never runs;
 *   - an `animation:` naming a @keyframes that was renamed or never landed;
 *   - a stylesheet carrying motion with no prefers-reduced-motion guard, the
 *     standard the 1.4.0 release set and test-premium-features.php enforces
 *     for the premium layer;
 *   - a rule that hides content ungated, so a blocked script leaves a shop
 *     with an invisible menu. This one already shipped once:
 *     demo-animations.css set `opacity: 0` on every demo image while its own
 *     script returned early for reduced-motion visitors, so all five bespoke
 *     demos rendered with no hero, no product photography and no story image;
 *   - two effects writing `transform` to the same element, where whichever ran
 *     last silently cancelled the other.
 *
 * No WordPress and no browser needed — it reads the real stylesheets.
 *
 * Usage: node motion.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const theme = path.resolve(here, '../../flavor');
const cssDir = path.join(theme, 'assets/css');
const jsDir = path.join(theme, 'assets/js');

const SKINS = [
  'modern-restaurant', 'luxury-dining', 'persian-traditional',
  'pizza-italian', 'fast-food', 'cafe-bistro', 'bakery-pastry',
  'juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering',
];
const BESPOKE = ['juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering'];

/* Admin and print sheets are out of scope, matching responsive.mjs. */
const SKIP_FILES = new Set(['builder-admin.css', 'builder.css', 'print-receipt.css', 'customizer-modern.css']);
const MIN_FONT = 12;

let failed = 0;
let checks = 0;
let sectionFails = 0;
let sectionLabel = '';

const fail = (msg) => { console.error(`  FAIL ${msg}`); failed++; checks++; sectionFails++; };
const ok = (msg) => { console.log(`  ok   ${msg}`); checks++; };

function section(title, done) {
  sectionFails = 0;
  sectionLabel = title;
  console.log(`\n${title}`);
  const before = failed;
  done();
  if (failed === before) ok(`${sectionLabel.replace(/^\d+\.\s*/, '')} — clean`);
}

const read = (p) => fs.readFileSync(p, 'utf8');
const strip = (text) => text.replace(/\/\*[\s\S]*?\*\//g, '');

/** Brace-match every `@media ... { }` whose header satisfies `predicate`. */
function mediaBlocks(text, predicate) {
  const out = [];
  const re = /@media[^{}]*\{/g;
  let m;
  while ((m = re.exec(text))) {
    if (!predicate(m[0])) continue;
    let depth = 1;
    let i = re.lastIndex;
    while (i < text.length && depth > 0) {
      if (text[i] === '{') depth++;
      else if (text[i] === '}') depth--;
      i++;
    }
    out.push(text.slice(re.lastIndex, i - 1));
    re.lastIndex = i;
  }
  return out;
}

/** Flat rule blocks. Rules nested inside an @media are returned individually.
    Keyframe stops (`from`, `to`, `0%`) are not rules and must be skipped, or
    every opacity inside a keyframe looks like an ungated hidden-content rule. */
function rules(text) {
  const out = [];
  for (const m of text.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
    const selector = m[1].trim();
    if (!selector || selector.startsWith('@')) continue;
    if (/^(from|to|\d+(?:\.\d+)?%)(\s*,\s*(from|to|\d+(?:\.\d+)?%))*$/.test(selector)) continue;
    out.push({ selector, body: m[2], index: m.index });
  }
  return out;
}

function walkCss(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) { out.push(...walkCss(full)); continue; }
    if (entry.name.endsWith('.css') && !SKIP_FILES.has(entry.name)) out.push(full);
  }
  return out;
}

const sheets = walkCss(cssDir).map((file) => ({
  file,
  name: path.relative(cssDir, file),
  raw: read(file),
}));
for (const s of sheets) s.text = strip(s.raw);
const byName = new Map(sheets.map((s) => [s.name, s]));

console.log(`\n=== Flavor motion layer: ${sheets.length} stylesheets, ${SKINS.length} demos ===`);

/* ============================================================ 1. braces */
section('1. Every stylesheet parses to balanced blocks', () => {
  for (const sheet of sheets) {
    const open = (sheet.text.match(/\{/g) || []).length;
    const close = (sheet.text.match(/\}/g) || []).length;
    if (open !== close) fail(`${sheet.name}: ${open} '{' vs ${close} '}'`);
  }
});

/* ==================================================== 2. skin coverage */
section('2. All twelve demos carry their own motion art direction', () => {
  for (const skin of SKINS) {
    const sheet = byName.get(`skins/${skin}.css`);
    if (!sheet) { fail(`skins/${skin}.css is missing`); continue; }
    if (!/Motion art direction/.test(sheet.raw)) { fail(`${skin}: no motion art-direction block`); continue; }

    const frames = (sheet.text.match(/@keyframes\s+[A-Za-z0-9_-]+/g) || []).length;
    if (frames < 3) { fail(`${skin}: only ${frames} keyframes; a signature needs more than that`); continue; }
    if (!mediaBlocks(sheet.text, (h) => /prefers-reduced-motion/.test(h)).length) {
      fail(`${skin}: motion block has no reduced-motion guard`);
      continue;
    }

    /* The gate has to be the script that owns the vocabulary being animated. */
    const gate = BESPOKE.includes(skin) ? 'fd-anim-ready' : 'flavor-anim-ready';
    if (!sheet.text.includes(`html.${gate}`)) {
      fail(`${skin}: motion block is not gated on html.${gate}`);
      continue;
    }
    /* A bespoke block that gates fd-* rules on the classic class never fires,
       because demo-animations.js is what sets fd-anim-ready. */
    const wrongGate = new RegExp(`html\\.flavor-anim-ready[^{}]*\\.flavor-skin-${skin}[^{}]*\\.fd-`);
    if (BESPOKE.includes(skin) && wrongGate.test(sheet.text)) {
      fail(`${skin}: bespoke fd-* rules gated on the classic flavor-anim-ready class`);
      continue;
    }
    console.log(`  ·    ${skin}: ${frames} keyframes, gated on html.${gate}, guarded`);
  }
});

/* ==================================================== 3. keyframe refs */
section('3. Every animation names a @keyframes that actually exists', () => {
  const allFrames = new Set();
  for (const sheet of sheets) {
    for (const m of sheet.text.matchAll(/@keyframes\s+([A-Za-z0-9_-]+)/g)) allFrames.add(m[1]);
  }
  /* Words that can legally occupy the animation-name slot's neighbourhood. */
  const NOT_NAMES = /^(none|inherit|initial|unset|revert|revert-layer|linear|ease|ease-in|ease-out|ease-in-out|step-start|step-end|normal|reverse|alternate|alternate-reverse|running|paused|infinite|forwards|backwards|both|steps|cubic-bezier|var|calc)$/;
  let refs = 0;
  for (const sheet of sheets) {
    for (const rule of rules(sheet.text)) {
      for (const decl of rule.body.split(';')) {
        /* The `?` matters: without it this only matches `animation-name:`,
           and the check silently validates a single declaration. */
        const m = decl.match(/^\s*animation(?:-name)?\s*:\s*(.+)$/);
        if (!m) continue;
        for (const part of m[1].split(',')) {
          const name = part.trim().split(/\s+/).find((t) => /^[A-Za-z_-][A-Za-z0-9_-]*$/.test(t) && !NOT_NAMES.test(t));
          if (!name) continue;
          refs++;
          if (!allFrames.has(name)) fail(`${sheet.name} { ${rule.selector.slice(0, 48)} } names unknown @keyframes "${name}"`);
        }
      }
    }
  }
  console.log(`  ·    ${refs} animation references checked against ${allFrames.size} keyframes`);
});

/* ============================================ 4. reduced-motion guard */
section('4. Every stylesheet carrying motion carries a reduced-motion guard', () => {
  for (const sheet of sheets) {
    const moves = /animation\s*:|transition\s*:|@keyframes/.test(sheet.text);
    if (!moves) continue;
    if (!mediaBlocks(sheet.text, (h) => /prefers-reduced-motion\s*:\s*reduce/.test(h)).length) {
      fail(`${sheet.name}: declares motion with no prefers-reduced-motion block`);
    }
  }
});

/* ============================================ 5. guard lists are live */
section('5. Reduced-motion guards name selectors the file actually styles', () => {
  for (const sheet of sheets) {
    const guards = mediaBlocks(sheet.text, (h) => /prefers-reduced-motion/.test(h));
    if (!guards.length) continue;
    /* Everything outside the guards, so a class mentioned only in a guard is
       caught. Comments are already stripped. */
    let outside = sheet.text;
    for (const g of guards) outside = outside.split(g).join(' ');
    for (const g of guards) {
      for (const m of g.matchAll(/\.(?:flavor|fd)-[A-Za-z0-9_-]+/g)) {
        if (!outside.includes(m[0])) fail(`${sheet.name}: guard names "${m[0]}", which the file never styles`);
      }
    }
  }
});

/* ================================================ 6. type size floor */
section(`6. No stylesheet drops below the ${MIN_FONT}px readability floor`, () => {
  const exempt = [/\.fd-fresh-seal\b/];
  for (const sheet of sheets) {
    for (const m of sheet.raw.matchAll(/font-size:\s*(\d+(?:\.\d+)?)px/g)) {
      if (parseFloat(m[1]) >= MIN_FONT) continue;
      const rule = sheet.raw.slice(sheet.raw.lastIndexOf('}', m.index) + 1, m.index);
      if (exempt.some((rx) => rx.test(rule))) continue;
      const line = sheet.raw.slice(0, m.index).split('\n').length;
      fail(`${sheet.name}:${line} declares ${m[1]}px (floor is ${MIN_FONT}px)`);
    }
  }
});

/* ======================================= 7. no ungated hidden content */
section('7. Nothing hides content unless a script has said motion is live', () => {
  for (const sheet of sheets) {
    /* Classes the file elsewhere gives a non-zero opacity: an overlay or dock
       that starts hidden and is shown by a state class is the standard
       pattern, not a fault. */
    const restored = new Set();
    for (const rule of rules(sheet.text)) {
      const decl = rule.body.match(/(?:^|;)\s*opacity\s*:\s*([^;]+)/);
      if (!decl) continue;
      const value = parseFloat(decl[1]);
      if (Number.isNaN(value) || value === 0) continue;
      for (const c of rule.selector.matchAll(/\.[A-Za-z][A-Za-z0-9_-]*/g)) restored.add(c[0]);
    }

    for (const rule of rules(sheet.text)) {
      const decl = rule.body.match(/(?:^|;)\s*opacity\s*:\s*([^;]+)/);
      if (!decl || parseFloat(decl[1]) !== 0) continue;

      const sel = rule.selector;
      const gated = /html\.(flavor-anim-ready|fd-anim-ready)/.test(sel)
        || /\.flavor-anim-(enter|in|injected|out|ripple|shimmer)\b/.test(sel)
        || /\.fd-anim-(enter|ripple|shapes?|typewriter)\b/.test(sel)
        || /::?(before|after)/.test(sel);
      if (gated) continue;

      /* Accepted when the same file restores opacity on one of these classes. */
      const classes = [...sel.matchAll(/\.[A-Za-z][A-Za-z0-9_-]*/g)].map((m) => m[0]);
      if (classes.some((c) => restored.has(c))) continue;

      const line = sheet.text.slice(0, rule.index).split('\n').length;
      fail(`${sheet.name}:${line} hides content ungated -> ${sel.slice(0, 70)}`);
    }
  }
});

/* ================================================== 8. script contract */
section('8. Both animation scripts parse and honour their contract', () => {
  for (const name of ['motion.js', 'demo-animations.js']) {
    const file = path.join(jsDir, name);
    if (!fs.existsSync(file)) { fail(`${name} is missing`); continue; }
    try {
      execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
    } catch (error) {
      fail(`${name} does not parse: ${(error.stderr || error.message).toString().split('\n')[0]}`);
      continue;
    }
    const text = read(file);
    if (!/prefers-reduced-motion/.test(text)) fail(`${name}: never checks prefers-reduced-motion`);
    if (!/@version\s+\d+\.\d+\.\d+/.test(text)) fail(`${name}: no @version header`);
    if (/\bjQuery\b/.test(text)) fail(`${name}: pulls in jQuery`);
    /* No third-party host: a motion layer has nothing to phone home to. */
    if (/https?:\/\/(?!www\.w3\.org|localhost)/.test(text.replace(/@license[^\n]*|GPL-2\.0-or-later/g, ''))) {
      fail(`${name}: references an external URL`);
    }
    console.log(`  ·    ${name} parses, reduced-motion aware, dependency-free`);
  }

  const motionJs = read(path.join(jsDir, 'motion.js'));
  const demoJs = read(path.join(jsDir, 'demo-animations.js'));
  const demoCss = byName.get('demo-animations.css').text;

  /* Each script must set the gate its own stylesheet reads. */
  if (!/flavor-anim-ready/.test(motionJs)) fail('motion.js never sets html.flavor-anim-ready');
  if (!/fd-anim-ready/.test(demoJs)) fail('demo-animations.js never sets html.fd-anim-ready');

  /* motion.js must yield anchors to demo-animations.js on bespoke pages. */
  if (!/flavor-bespoke/.test(motionJs)) {
    fail('motion.js handles anchors on bespoke pages too, where demo-animations.js already owns them');
  }

  /* Regression guards for the two shipped defects this pass fixed. */
  if (/history\.pushState/.test(demoJs) || /history\.pushState/.test(motionJs)) {
    fail('an anchor handler uses pushState: an in-page jump must not add a history entry');
  }
  if (/img\.style\.transform\s*=/.test(demoJs)) {
    fail('demo-animations.js assigns transform directly on the hero image, fighting the parallax');
  }
  if (!/fd-anim-ready \.fd-hero__image/.test(demoCss)) {
    fail('demo-animations.css no longer gates the image fade on html.fd-anim-ready');
  }
  const ungatedImage = /(?:^|\n)\s*\.fd-hero__image\s*,[^{]*\{[^}]*opacity\s*:\s*0/.test(demoCss);
  if (ungatedImage) {
    fail('demo-animations.css hides .fd-hero__image without the ready gate — reduced-motion visitors lose the hero');
  }
  const smooth = mediaBlocks(demoCss, (h) => /prefers-reduced-motion/.test(h)).join('\n');
  if (!/scroll-behavior/.test(smooth)) {
    fail('demo-animations.css forces scroll-behavior:smooth with no reduced-motion override');
  }
});

/* ============================================== 9. enqueue wiring */
section('9. The motion layer is enqueued for all twelve demos', () => {
  const enqueue = read(path.join(theme, 'inc/class-enqueue.php'));
  for (const needle of ['assets/css/motion.css', 'assets/js/motion.js', "'flavor-motion'", "'flavor-motion-js'", "'motion'"]) {
    if (!enqueue.includes(needle)) fail(`class-enqueue.php does not wire ${needle}`);
  }
  /* The whole point: motion() must not be limited to the bespoke five. */
  const start = enqueue.indexOf('public static function motion');
  if (start < 0) {
    fail('class-enqueue.php has no motion() method');
  } else {
    let depth = 0;
    let end = enqueue.indexOf('{', start);
    const from = end;
    for (let i = from; i < enqueue.length; i++) {
      if (enqueue[i] === '{') depth++;
      else if (enqueue[i] === '}') { depth--; if (depth === 0) { end = i; break; } }
    }
    const body = enqueue.slice(from, end);
    if (/Bespoke_Demos::active\(\)/.test(body)) {
      fail('motion() is gated on Bespoke_Demos::active(), so seven demos would get nothing');
    }
    /* It has to load after premium.css, which shares selectors with it. */
    if (!/'flavor-premium'/.test(body)) fail('motion.css does not declare flavor-premium as a dependency, so premium.css can win shared selectors');
  }
  if (!enqueue.includes("'flavor-demo-animations'") || !enqueue.includes("'flavor-demo-animations-js'")) {
    fail('the bespoke demo-animations pair stopped being enqueued');
  }
});

/* ============================== 10. compositor owns the hero image */
section('10. One rule composes the hero transform', () => {
  const demoCss = byName.get('demo-animations.css').text;
  /* Parse once; comparing objects across two rules() calls never matches. */
  const demoRules = rules(demoCss);
  const hitters = demoRules.filter((r) => /\.fd-hero__image/.test(r.selector) && /transform\s*:/.test(r.body));
  const composers = hitters.filter((r) => /--fd-anim-parallax/.test(r.body) && /--fd-anim-tilt/.test(r.body));

  if (composers.length !== 1) {
    fail(`expected exactly one parallax+tilt compositor for .fd-hero__image, found ${composers.length}`);
  }
  /* No other rule may write a literal transform onto the same element. A
     `transform: none` inside a reduced-motion guard is the correction, not a
     second writer, so it is allowed. */
  for (const r of hitters) {
    if (composers.includes(r)) continue;
    if (/transform\s*:\s*none\b/.test(r.body)) continue;
    fail(`demo-animations.css writes a second transform on .fd-hero__image -> ${r.selector.slice(0, 60)}`);
  }
  /* And no skin may either, or the parallax and the skin cancel each other. */
  for (const skin of BESPOKE) {
    const sheet = byName.get(`skins/${skin}.css`);
    for (const r of rules(sheet.text)) {
      if (/\.fd-hero__image/.test(r.selector) && /transform\s*:/.test(r.body)) {
        fail(`skins/${skin}.css writes transform on .fd-hero__image, cancelling the compositor`);
      }
    }
  }
});

/* ==================================== 11. cross-file event wiring */
section('11. Every document-level flavor: event can reach its listener', () => {
  /* A listener on `document` hears an event dispatched on the document itself
     or one that bubbles — nothing else. ui.js dispatched its dialog events on
     the dialog host with no `bubbles`, so the cart-line stagger the motion
     layer choreographs for them never ran: the listener, the CSS and the
     markup were each correct, and the event never arrived. Every unit test
     passed. This check exists because that failure is invisible to all of
     them. */
  const sources = fs.readdirSync(jsDir)
    .filter((f) => f.endsWith('.js'))
    .map((f) => ({ name: f, text: read(path.join(jsDir, f)) }));

  const coreDir = path.join(path.dirname(theme), 'flavor-core/assets/js');
  if (fs.existsSync(coreDir)) {
    for (const f of fs.readdirSync(coreDir).filter((n) => n.endsWith('.js'))) {
      sources.push({ name: `flavor-core/${f}`, text: read(path.join(coreDir, f)) });
    }
  }

  const waited = new Set();
  for (const src of sources) {
    for (const m of src.text.matchAll(/\bdoc(?:ument)?\s*\.\s*addEventListener\(\s*'(flavor:[a-z:-]+)'/g)) {
      waited.add(m[1]);
    }
  }
  if (!waited.size) fail('the motion layer consumes no flavor: events at all');

  for (const name of waited) {
    let seen = 0;
    let reachable = false;
    let offender = '';

    for (const src of sources) {
      const marker = `new CustomEvent('${name}'`;
      let index = src.text.indexOf(marker);
      while (index >= 0) {
        seen++;
        const before = src.text.slice(Math.max(0, index - 140), index);
        const after = src.text.slice(index, index + 180);
        const onDocument = /document\s*\.\s*dispatchEvent\(\s*$/.test(before);
        const bubbles = /bubbles\s*:\s*true/.test(after);
        if (onDocument || bubbles) reachable = true;
        else if (!offender) offender = src.name;
        index = src.text.indexOf(marker, index + 1);
      }
    }

    if (!seen) fail(`${name} is waited for on the document, but nothing dispatches it`);
    else if (!reachable) fail(`${name} is dispatched on an element without bubbling (${offender}) — a document-level listener never receives it`);
    else console.log(`  ·    ${name} reaches its document-level listener`);
  }
});

/* ================================================ summary */
console.log('\n=======================================================');
console.log(failed
  ? `Motion layer: ${checks - failed} passed, ${failed} FAILED`
  : `Motion layer: all ${checks} checks passed`);
console.log('=======================================================\n');
process.exit(failed ? 1 : 0);
