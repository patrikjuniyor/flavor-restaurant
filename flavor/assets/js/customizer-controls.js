/**
 * Customizer control-pane polish for Flavor:
 *  - Adds a settings search box that filters controls in real time.
 *  - Adds a "بازنشانی بخش" (Reset section) button to each section the theme
 *    registers, so an owner can revert one group of settings to its default
 *    without touching other sections.
 *  - Shows an unsaved-changes banner in the pane header.
 *  - Previews navigated URLs inside the Customizer frame.
 *
 * Loaded in the Customizer controls frame only.
 */
/* global wp, jQuery */
(function () {
 'use strict';
 if (!window.wp || !wp.customize) return;
 var api = wp.customize;
 var $ = window.jQuery;

 /* ------------------------------------------------------------------ search */
 function buildSearch() {
  if (document.getElementById('flavor-customizer-search')) return;
  var root = document.querySelector('.customize-pane-parent > .accordion-section, #customize-theme-controls') || document.querySelector('.wp-full-overlay-sidebar-content');
  if (!root) return;
  var wrap = document.createElement('div');
  wrap.id = 'flavor-customizer-search-wrap';
  wrap.setAttribute('role', 'search');
  var input = document.createElement('input');
  input.type = 'search';
  input.id = 'flavor-customizer-search';
  input.placeholder = 'جست‌وجو در تنظیمات…';
  input.setAttribute('aria-label', 'جست‌وجو در تنظیمات سفارشی‌ساز');
  input.autocomplete = 'off';
  wrap.appendChild(input);
  var container = document.querySelector('#customize-info') || document.querySelector('.customize-info');
  if (container) container.parentNode.insertBefore(wrap, container.nextSibling);

  input.addEventListener('input', function () {
   var q = input.value.trim().toLowerCase();
   var sections = document.querySelectorAll('#customize-theme-controls .control-section');
   sections.forEach(function (section) {
    if (section.classList.contains('control-panel-themes')) return;
    var title = section.querySelector('.accordion-section-title');
    var titleText = title ? title.textContent.toLowerCase() : '';
    var any = false;
    var controls = section.querySelectorAll('.customize-control');
    controls.forEach(function (control) {
     var text = (control.textContent || '').toLowerCase();
     var match = !q || text.indexOf(q) !== -1 || titleText.indexOf(q) !== -1;
     control.style.display = match ? '' : 'none';
     if (match) any = true;
    });
    var body = section.querySelector('.accordion-section-content');
    if (q && any) {
     section.classList.add('open', 'expanded');
     if (body) body.style.display = '';
    }
    if (q && !any) {
     section.style.display = 'none';
    } else {
     section.style.display = '';
    }
   });
  });
 }

 /* ------------------------------------------------------ unsaved indicator */
 function buildUnsavedBadge() {
  if (document.getElementById('flavor-unsaved-badge')) return;
  var save = document.querySelector('#customize-header-actions .customize-save-button-wrapper, #customize-save-button-wrapper');
  if (!save) return;
  var badge = document.createElement('span');
  badge.id = 'flavor-unsaved-badge';
  badge.className = 'flavor-unsaved-badge';
  badge.textContent = 'تغییرات ذخیره نشده';
  badge.hidden = true;
  save.appendChild(badge);

  function refresh() {
   var dirty = false;
   api.each(function (setting) {
    if (setting._dirty) dirty = true;
   });
   badge.hidden = !dirty;
  }
  api.bind('change', refresh);
  api.bind('saved', function () {
   badge.hidden = true;
  });
  refresh();
 }

 /* ------------------------------------------------- per-section reset tool */
 var SECTION_WHITELIST = /^flavor_section_(header|footer|layout|colors|hero|preset|ui|mega|shopping|engagement|scheme|dock|locations)$/;

 function resetSection(sectionId) {
  if (!window.confirm('همهٔ تنظیمات این بخش به پیش‌فرض بازمی‌گردد. ادامه می‌دهید؟')) return;
  var section = api.section(sectionId);
  if (!section) return;
  var controls = section.controls();
  controls.forEach(function (control) {
   var setting = control.setting;
   if (!setting || typeof setting.id !== 'string' || 0 !== setting.id.indexOf('flavor_')) return;
   if (setting.id === 'flavor_skin') return; // never silently reset the active demo
   setting.set(setting.default);
  });
  // Announce for assistive tech.
  var live = document.getElementById('flavor-reset-live');
  if (!live) {
   live = document.createElement('div');
   live.id = 'flavor-reset-live';
   live.className = 'screen-reader-text';
   live.setAttribute('aria-live', 'polite');
   document.body.appendChild(live);
  }
  live.textContent = 'تنظیمات این بخش بازنشانی شد.';
 }

 function addSectionResetButton(section) {
  if (!section || !section.id || !SECTION_WHITELIST.test(section.id)) return;
  var container = section.container && section.container[0];
  if (!container) return;
  if (container.querySelector('.flavor-section-reset')) return;
  var header = container.querySelector('.accordion-section-title');
  if (!header) return;
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'flavor-section-reset';
  btn.setAttribute('aria-label', 'بازنشانی تنظیمات این بخش به پیش‌فرض');
  btn.textContent = 'بازنشانی بخش';
  btn.addEventListener('click', function (event) {
   event.preventDefault();
   event.stopPropagation();
   resetSection(section.id);
  });
  header.appendChild(btn);
 }

 function wireSections() {
  api.section.each(function (section) { addSectionResetButton(section); });
 }

 /* -------------------------------------- preview URL navigation (existing) */
 document.addEventListener('click', function (event) {
  var link = event.target.closest('[data-flavor-ui-preview-url]');
  if (!link || !wp.customize || !wp.customize.previewer) return;
  event.preventDefault();
  wp.customize.previewer.previewUrl.set(link.href);
 });

 api.bind('ready', function () {
  buildSearch();
  buildUnsavedBadge();
  wireSections();
  if ($ && $.expr && $.expr[':']) {
   // Make sure new sections added later (panels that lazy-load) also get reset.
   setInterval(wireSections, 1500);
  }
 });
})();
