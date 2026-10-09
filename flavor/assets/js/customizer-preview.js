/**
 * Customizer-only postMessage listeners.
 *
 * Every rule this script touches is a CSS custom property on :root or a <body>
 * class already read by the theme's stylesheet, so a changed setting produces
 * the same visuals that a page refresh would. Appearance changes never mutate
 * cart, order, or business state.
 */
/* global wp, flavorPreviewData, flavorChromePreview */
(function () {
 'use strict';
 if (!window.wp || !wp.customize) return;
 var api = wp.customize;
 var root = document.documentElement;
 var data = window.flavorPreviewData || {};

 function replaceClass(prefix, value, choices) {
  choices.forEach(function (choice) { document.body.classList.remove(prefix + choice); });
  document.body.classList.add(prefix + value);
 }

 /* -------- UI choices (menu / ordering UX) -------- */
 Object.keys(data.ui || {}).forEach(function (key) {
  api('flavor_ui_' + key, function (setting) {
   setting.bind(function (value) {
    if ((data.ui[key] || []).indexOf(value) === -1) return;
    if (document.body.classList.contains('flavor-ui')) replaceClass('flavor-ui-' + key.replace(/_/g, '-') + '-', value, data.ui[key]);
    var cfg = window.flavorData;
    if (cfg) { cfg.ui = cfg.ui || {}; cfg.ui[key] = value; }
    if (key === 'mobile_nav') {
     var nav = document.querySelector('.flavor-mobile-nav');
     if (nav) nav.classList.toggle('flavor-mobile-nav--minimal', value === 'minimal');
    }
    var change = {}; change[key] = value;
    document.dispatchEvent(new CustomEvent('flavor:ui-settings-changed', { detail: change }));
   });
  });
 });

 /* -------- Typography (font family) -------- */
 ['heading', 'body'].forEach(function (key) {
  api('flavor_font_' + key, function (setting) {
   setting.bind(function (value) {
    if (!(data.fonts || []).indexOf) return;
    if ((data.fonts || []).indexOf(value) === -1 && value !== '') return;
    var font = value || (data[key] || 'Vazirmatn');
    root.style.setProperty('--flavor-font-' + key, "'" + font + "', 'Vazirmatn', Tahoma, sans-serif");
   });
  });
 });

 /* -------- Header layout / sticky / transparent -------- */
 api('flavor_header_layout', function (setting) {
  setting.bind(function (value) {
   var choices = ['default', 'centered', 'minimal', 'transparent'];
   if (choices.indexOf(value) === -1) return;
   replaceClass('flavor-header-', value, choices);
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });
 api('flavor_header_sticky', function (setting) {
  setting.bind(function (value) {
   var on = [true, 1, '1', 'yes', 'on'].indexOf(value) !== -1;
   document.body.classList.toggle('flavor-header-not-sticky', !on);
   document.body.classList.toggle('flavor-header-sticky', on);
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });

 /* -------- Pure CSS variable tokens (responsive spacing, geometry) -------- */
 var cssVarTokens = {
  flavor_container_width:        '--flavor-container-max',
  flavor_radius:                 '--flavor-radius',
  flavor_btn_radius:             '--flavor-btn-radius',
  flavor_gutter_mobile:          '--flavor-container-gutter',
  flavor_gutter_tablet:          '--flavor-container-gutter-tablet',
  flavor_gutter_desktop:         '--flavor-container-gutter-desktop',
  flavor_body_size:              '--flavor-body-size',
  flavor_heading_size_mobile:    '--flavor-heading-size',
  flavor_heading_size_tablet:    '--flavor-heading-size-tablet',
  flavor_heading_size_desktop:   '--flavor-heading-size-desktop',
  flavor_section_space_mobile:   '--flavor-section-space',
  flavor_section_space_tablet:    '--flavor-section-space-tablet',
  flavor_section_space_desktop:  '--flavor-section-space-desktop'
 };
 Object.keys(cssVarTokens).forEach(function (id) {
  api(id, function (setting) {
   setting.bind(function (value) {
    if (value === '' || value === null || typeof value === 'undefined') {
     root.style.removeProperty(cssVarTokens[id]);
     return;
    }
    root.style.setProperty(cssVarTokens[id], String(value));
   });
  });
 });

 /* -------- Logo box (proportional scaling) -------- */
 api('flavor_logo_height', function (setting) {
  setting.bind(function (value) {
   var height = parseInt(value, 10);
   if (!height || height < 24 || height > 160) height = 52;
   var s = root.style;
   s.setProperty('--flavor-logo-height', height + 'px');
   s.setProperty('--flavor-logo-height-mobile', Math.max(24, Math.round(height * 0.72)) + 'px');
   s.setProperty('--flavor-logo-max-width', Math.min(420, Math.max(120, height * 4)) + 'px');
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });

 /* -------- Brand color overrides (without requiring a refresh) --------
  * Each token drives a CSS variable AND an --…-rgb companion so shadows and
  * translucent overlays keep working. The skin change still needs a refresh
  * because swapping demo markup/skins can re-render templates. */
 var COLOR_TO_VAR = {
  flavor_primary:    { color: '--flavor-primary',    rgb: '--flavor-primary-rgb' },
  flavor_secondary:  { color: '--flavor-secondary',  rgb: '--flavor-secondary-rgb' },
  flavor_accent:     { color: '--flavor-accent',     rgb: '--flavor-accent-rgb' },
  flavor_bg:         { color: '--flavor-bg',         rgb: null },
  flavor_surface:    { color: '--flavor-surface',    rgb: '--flavor-surface-rgb' },
  flavor_surface_alt:{ color: '--flavor-surface-alt',rgb: null },
  flavor_ink:        { color: '--flavor-ink',        rgb: '--flavor-ink-rgb' },
  flavor_muted:      { color: '--flavor-muted',      rgb: null },
  flavor_line:       { color: '--flavor-line',       rgb: null }
 };
 var HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
 function hexToRgb(hex) {
  hex = (hex || '').replace('#', '');
  if (hex.length === 3) hex = hex.split('').map(function (c) { return c + c; }).join('');
  if (hex.length !== 6) return null;
  return [
   parseInt(hex.slice(0, 2), 16),
   parseInt(hex.slice(2, 4), 16),
   parseInt(hex.slice(4, 6), 16)
  ].join(', ');
 }
 Object.keys(COLOR_TO_VAR).forEach(function (id) {
  api(id, function (setting) {
   setting.bind(function (value) {
    if (!value || typeof value !== 'string' || !HEX.test(value.trim())) {
     root.style.removeProperty(COLOR_TO_VAR[id].color);
     if (COLOR_TO_VAR[id].rgb) root.style.removeProperty(COLOR_TO_VAR[id].rgb);
     return;
    }
    var v = value.trim();
    root.style.setProperty(COLOR_TO_VAR[id].color, v);
    if (COLOR_TO_VAR[id].rgb) {
     var rgb = hexToRgb(v);
     if (rgb) root.style.setProperty(COLOR_TO_VAR[id].rgb, rgb);
    }
   });
  });
 });

 /* -------- Skin switch (demo palette) requires selective refresh of the
  * style block because it rewrites the design tokens and markup classes. We
  * request a refresh of the preview frame — clean, reliable, no divergence. */
 api('flavor_skin', function (setting) {
  setting.bind(function () {
   if (api.previewer && api.previewer.refresh) api.previewer.refresh();
  });
 });
})();
