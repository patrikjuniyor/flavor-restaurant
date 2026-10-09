/**
 * Live readability report for the colour section.
 *
 * The PHP renders the saved state; this recomputes it on every keystroke so
 * the merchant sees a ratio drop the moment they drag a colour picker past
 * the point where the text stops being readable. Recomputing locally avoids
 * a preview refresh per change, which would make the picker unusable.
 *
 * The WCAG formula is reimplemented here because the check has to run in the
 * browser. It is the same formula as flavor/inc/class-contrast.php and
 * flavor/inc/class-ui.php; tests/theme/test-contrast.php asserts the two
 * implementations agree on a shared fixture, so they cannot silently drift.
 */
/* global wp, flavorContrastData */
(function (api) {
 'use strict';
 if (!window.wp || !wp.customize) return;

 var data = window.flavorContrastData || {};
 var pairs = data.pairs || [];
 var settings = data.settings || [];

 function srgb(value) {
  return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
 }

 function luminance(hex) {
  hex = String(hex || '').replace('#', '');
  if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
  if (!/^[0-9a-f]{6}$/i.test(hex)) return 0;
  var r = srgb(parseInt(hex.substring(0, 2), 16) / 255);
  var g = srgb(parseInt(hex.substring(2, 4), 16) / 255);
  var b = srgb(parseInt(hex.substring(4, 6), 16) / 255);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
 }

 function ratio(a, b) {
  var la = luminance(a);
  var lb = luminance(b);
  if (la <= 0 && lb <= 0) return 1;
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
 }

 function buttonInk(primary) {
  return ratio(primary, '#ffffff') >= ratio(primary, '#000000') ? '#ffffff' : '#000000';
 }

 function collect() {
  var tokens = {};
  settings.forEach(function (key) {
   var setting = api('flavor_' + key);
   if (setting) tokens[key] = setting.get() || '';
  });
  // Anything the merchant left empty falls back to the preset's own value,
  // which is what the front end would resolve it to.
  Object.keys(data.current || {}).forEach(function (key) {
   if (!tokens[key]) tokens[key] = data.current[key];
  });
  return tokens;
 }

 function render(wrap, tokens) {
  var ink = buttonInk(tokens.primary || '#000000');
  var anyFail = false;

  pairs.forEach(function (pair) {
   var row = wrap.querySelector('[data-pair="' + pair.key + '"]');
   if (!row) return;

   var fg = pair.fg === '@button' ? ink : tokens[pair.fg];
   var bg = tokens[pair.bg];

   // Not judgeable yet — the merchant has not filled both sides in.
   if (!fg || !bg || !/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(fg) || !/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(bg)) {
    row.hidden = true;
    return;
   }

   row.hidden = false;
   var value = ratio(fg, bg);
   var pass = value >= pair.min;
   if (!pass) anyFail = true;

   row.classList.toggle('flavor-contrast__row--pass', pass);
   row.classList.toggle('flavor-contrast__row--fail', !pass);

   var out = row.querySelector('.flavor-contrast__ratio');
   if (out) out.textContent = value.toFixed(2);

   var sample = row.querySelector('.flavor-contrast__sample');
   if (sample) {
    sample.style.color = fg;
    sample.style.background = bg;
   }
  });

  wrap.classList.toggle('has-failures', anyFail);

  var warn = wrap.querySelector('.flavor-contrast__warn');
  var ok = wrap.querySelector('.flavor-contrast__ok');
  if (warn) warn.hidden = !anyFail;
  if (ok) ok.hidden = anyFail;
 }

 api.bind('ready', function () {
  var wrap = document.querySelector('[data-contrast]');
  if (!wrap) return;

  render(wrap, collect());

  settings.forEach(function (key) {
   var setting = api('flavor_' + key);
   if (setting) setting.bind(function () { render(wrap, collect()); });
  });

  // A preset switch changes every token at once.
  var skin = api('flavor_skin');
  if (skin) skin.bind(function () { render(wrap, collect()); });
 });
})(wp.customize);
