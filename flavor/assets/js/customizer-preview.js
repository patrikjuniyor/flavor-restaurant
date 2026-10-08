/** Customizer-only postMessage listeners. Appearance changes never mutate a cart/order. */
(function () {
 'use strict';
 if (!window.wp || !wp.customize) return;
 var data = window.flavorPreviewData || {};
 function replaceClass(prefix, value, choices) {
  choices.forEach(function (choice) { document.body.classList.remove(prefix + choice); });
  document.body.classList.add(prefix + value);
 }
 Object.keys(data.ui || {}).forEach(function (key) {
  wp.customize('flavor_ui_' + key, function (setting) {
   setting.bind(function (value) {
    if (!data.ui[key].includes(value)) return;
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
 ['heading', 'body'].forEach(function (key) {
  wp.customize('flavor_font_' + key, function (setting) {
   setting.bind(function (value) {
    if (!(data.fonts || []).includes(value)) return;
    var font = value || data[key];
    document.documentElement.style.setProperty('--flavor-font-' + key, "'" + font + "', 'Vazirmatn', Tahoma, sans-serif");
   });
  });
 });
 wp.customize('flavor_header_layout', function (setting) {
  setting.bind(function (value) {
   var choices = ['default', 'centered', 'minimal', 'transparent'];
   if (!choices.includes(value)) return;
   replaceClass('flavor-header-', value, choices);
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });
 wp.customize('flavor_header_sticky', function (setting) {
  setting.bind(function (value) {
   document.body.classList.toggle('flavor-header-not-sticky', ![true, 1, '1', 'yes', 'on'].includes(value));
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });
 /* Responsive design tokens are pure CSS variables, so desktop/mobile controls update without a reload. */
 var responsiveTokens = {
  flavor_gutter_mobile: '--flavor-container-gutter',
  flavor_gutter_desktop: '--flavor-container-gutter-desktop',
  flavor_body_size: '--flavor-body-size',
  flavor_heading_size_mobile: '--flavor-heading-size',
  flavor_heading_size_desktop: '--flavor-heading-size-desktop',
  flavor_section_space_mobile: '--flavor-section-space',
  flavor_section_space_desktop: '--flavor-section-space-desktop'
 };
 Object.keys(responsiveTokens).forEach(function (id) {
  wp.customize(id, function (setting) {
   setting.bind(function (value) {
    document.documentElement.style.setProperty(responsiveTokens[id], value);
   });
  });
 });
 /* The logo box is pure CSS tokens, so live preview is a variable write. */
 wp.customize('flavor_logo_height', function (setting) {
  setting.bind(function (value) {
   var height = parseInt(value, 10);
   if (!height || height < 24 || height > 160) height = 52;
   var root = document.documentElement.style;
   root.setProperty('--flavor-logo-height', height + 'px');
   root.setProperty('--flavor-logo-height-mobile', Math.max(24, Math.round(height * 0.72)) + 'px');
   root.setProperty('--flavor-logo-max-width', Math.min(420, Math.max(120, height * 4)) + 'px');
   document.dispatchEvent(new CustomEvent('flavor:ui-header-changed'));
  });
 });
})();
