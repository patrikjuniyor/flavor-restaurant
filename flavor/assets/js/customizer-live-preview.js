/**
 * Live preview for the design-token settings.
 *
 * These settings change no markup, only CSS custom properties, so the
 * preview writes the variable directly instead of reloading the frame. A
 * colour picker that repaints instantly is the difference between choosing
 * a colour and guessing at one.
 *
 * Two server functions are mirrored here because they have to run in the
 * browser: Design::format_px() and Design::hex2rgb(). tests/theme/
 * test-live-preview.php runs both implementations over a shared fixture and
 * asserts they agree, so a change to either side is caught rather than
 * silently producing a preview that disagrees with the published page.
 */
/* global wp, flavorLivePreview */
(function (api) {
 'use strict';
 if (!window.wp || !wp.customize) return;

 var root = document.documentElement;
 var data = window.flavorLivePreview || {};
 var tokens = data.tokens || {};
 var defaults = data.defaults || {};

 function expand(hex) {
  hex = String(hex || '').replace('#', '');
  if (hex.length === 3) return hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
  return hex;
 }

 // Mirrors Design::hex2rgb(), including the "0, 0, 0" fallback spacing.
 function hex2rgb(hex) {
  hex = expand(hex);
  if (!/^[0-9a-f]{6}$/i.test(hex)) return '0, 0, 0';
  return parseInt(hex.substring(0, 2), 16) + ', ' + parseInt(hex.substring(2, 4), 16) + ', ' + parseInt(hex.substring(4, 6), 16);
 }

 // Mirrors Design::format_px(): two decimals, trailing zeros trimmed.
 function formatPx(size) {
  var s = (Math.round(size * 100) / 100).toFixed(2);
  s = s.replace(/0+$/, '').replace(/\.$/, '');
  return s + 'px';
 }

 function setVar(name, value) {
  if (!name) return;
  root.style.setProperty(name, value);
 }

 /** The type scale derives h2–h6 from the body size, exactly as the server does. */
 function applyScale() {
  var bodySetting = api('flavor_body_size');
  var scaleSetting = api('flavor_type_scale');
  if (!scaleSetting) return;

  var body = bodySetting ? parseFloat(String(bodySetting.get() || '').replace('px', '')) : NaN;
  if (!body || body <= 0) body = parseFloat(String(defaults.bodySize || '15').replace('px', '')) || 15;

  var scale = parseFloat(String(scaleSetting.get() || '').replace('px', ''));
  if (!scale || scale <= 0) scale = parseFloat(defaults.typeScale) || 1.25;

  for (var level = 2; level <= 6; level++) {
   setVar('--flavor-h' + level, formatPx(body * Math.pow(scale, 6 - level)));
  }
 }

 function apply(id, value) {
  var token = tokens[id];
  if (!token) return;

  if (token.kind === 'color') {
   var hex = String(value || '');
   if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) return;
   setVar(token.var, hex);
   // The -rgb twin feeds rgba() rules. Only some colours have one; updating
   // it where the server never emits it would be a no-op, and updating it
   // where the server does emit it is the difference between the colour
   // applying and half-applying.
   if ((defaults.rgbVars || []).indexOf(id) !== -1) {
    setVar(token.var + '-rgb', hex2rgb(hex));
   }
   return;
  }

  if (token.kind === 'scale') {
   applyScale();
   return;
  }

  if ('' === String(value || '')) return;
  setVar(token.var, value);
 }

 Object.keys(tokens).forEach(function (id) {
  api(id, function (setting) {
   setting.bind(function (value) { apply(id, value); });
  });
 });

 // The scale depends on the body size too, so changing either recomputes it.
 api('flavor_body_size', function (setting) {
  setting.bind(applyScale);
 });
})(wp.customize);
