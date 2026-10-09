/**
 * Repeating section behaviour: add, duplicate, reorder, delete.
 *
 * The whole list is held in one hidden input as JSON and pushed through the
 * Customizer setting on every edit, so the preview refreshes and the value
 * lands in the changeset like any other setting. Repeater::sanitize()
 * revalidates it server-side; nothing here is trusted on arrival.
 *
 * Reordering is done by moving DOM nodes and re-serialising, rather than by
 * keeping an index in the data. An index column is the classic way to end
 * up with two items claiming position 3 after a delete.
 */
/* global wp, jQuery */
(function (api, $) {
 'use strict';
 if (!window.wp || !wp.customize) return;

 function serialise(root) {
  var items = [];
  root.querySelectorAll('.flavor-repeater__item').forEach(function (li) {
   var item = {};
   li.querySelectorAll('.flavor-repeater__input').forEach(function (input) {
    item[input.dataset.field] = input.value;
   });
   items.push(item);
  });
  return JSON.stringify(items);
 }

 function renumber(root) {
  root.querySelectorAll('.flavor-repeater__item').forEach(function (li, i) {
   li.dataset.position = i + 1;
   var index = li.querySelector('.flavor-repeater__index');
   if (index) index.textContent = i + 1;
  });
  var empty = root.querySelector('.flavor-repeater__empty');
  if (empty) empty.hidden = root.querySelectorAll('.flavor-repeater__item').length > 0;
 }

 function bind(root, setting) {
  var list = root.querySelector('.flavor-repeater__list');
  var add = root.querySelector('.flavor-repeater__add');
  var template = root.querySelector('.flavor-repeater__template');
  var max = parseInt(root.dataset.max, 10) || 24;

  function commit() {
   renumber(root);
   setting.set(serialise(root));
  }

  if (!list || !template) return;

  list.addEventListener('input', commit);
  list.addEventListener('change', commit);

  list.addEventListener('click', function (event) {
   var button = event.target.closest('button');
   if (!button) return;
   var item = button.closest('.flavor-repeater__item');
   if (!item) return;
   event.preventDefault();

   if (button.classList.contains('flavor-repeater__remove')) {
    // Removing is the one destructive action here, so it asks first.
    if (!window.confirm(wp.i18n ? wp.i18n.__('این مورد حذف شود؟', 'flavor') : 'این مورد حذف شود؟')) return;
    item.remove();
    commit();
    return;
   }

   if (button.classList.contains('flavor-repeater__duplicate')) {
    if (list.querySelectorAll('.flavor-repeater__item').length >= max) return;
    var copy = item.cloneNode(true);
    list.insertBefore(copy, item.nextSibling);
    commit();
    return;
   }

   if (button.classList.contains('flavor-repeater__up')) {
    if (item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
    commit();
    return;
   }

   if (button.classList.contains('flavor-repeater__down')) {
    if (item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
    commit();
   }
  });

  // Media picker for image fields, using the core media frame.
  list.addEventListener('click', function (event) {
   var pick = event.target.closest('.flavor-repeater__pick');
   if (!pick || !window.wp.media) return;
   event.preventDefault();

   var field = pick.dataset.field;
   var input = pick.parentNode.querySelector('input[data-field="' + field + '"]');
   if (!input) return;

   var frame = window.wp.media({ title: 'انتخاب تصویر', button: { text: 'استفاده از این تصویر' }, multiple: false });

   frame.on('select', function () {
    var attachment = frame.state().get('selection').first().toJSON();
    input.value = attachment.url || '';
    commit();
   });

   frame.open();
  });

  if (add) {
   add.addEventListener('click', function () {
    if (list.querySelectorAll('.flavor-repeater__item').length >= max) return;
    var blank = document.createElement('li');
    blank.innerHTML = template.innerHTML;
    list.appendChild(blank);
    commit();
    var first = blank.querySelector('.flavor-repeater__input');
    if (first) first.focus();
   });
  }
 }

 api.control('flavor_gallery_items', function (control) {
  var root = (control.container[0] || control.container).querySelector('.flavor-repeater');
  if (root) bind(root, control.setting);
 });

 api.control('flavor_testimonials_items', function (control) {
  var root = (control.container[0] || control.container).querySelector('.flavor-repeater');
  if (root) bind(root, control.setting);
 });
})(wp.customize, jQuery);
