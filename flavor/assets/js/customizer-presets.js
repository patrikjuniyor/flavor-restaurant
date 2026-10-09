/**
 * Visual preset picker behaviour.
 *
 * Two jobs:
 *  1. Keep the radio grid in sync with the real Customizer setting, so the
 *     preset behaves exactly like the radio it replaced.
 *  2. Clear manual colour overrides — but only inside the changeset. Writing
 *     '' through the JS API stages a draft; the database is untouched until
 *     the merchant publishes, and discarding the changeset restores
 *     everything. That is what makes the button safe to click.
 */
/* global wp, jQuery, flavorPresetData */
(function (api) {
 'use strict';
 if (!window.wp || !wp.customize) return;

 var data = window.flavorPresetData || {};
 var colourSettings = data.colourSettings || [];

 function syncGrid(container, value) {
  var cards = container.querySelectorAll('.flavor-preset__card');
  Array.prototype.forEach.call(cards, function (card) {
   var input = card.querySelector('input[type="radio"]');
   if (!input) return;
   var on = input.value === value;
   input.checked = on;
   card.classList.toggle('is-active', on);
   if (on && input.dataset.desc) {
    var desc = container.querySelector('.flavor-preset__desc');
    if (desc) desc.textContent = input.dataset.desc;
   }
  });
 }

 api.control('flavor_skin', function (control) {
  if (!control || !control.container) return;

  var container = control.container[0] || control.container;
  if (!container || !container.querySelector) return;

  var root = container.querySelector('.flavor-preset');
  if (!root) return;

  // Reflect the current value, including a value restored from a changeset.
  syncGrid(root, control.setting.get());

  control.setting.bind(function (value) {
   syncGrid(root, value);
  });

  root.addEventListener('change', function (event) {
   var input = event.target;
   if (!input || input.name !== 'flavor-preset-choice') return;
   control.setting.set(input.value);
  });

  var clear = root.querySelector('.flavor-preset__clear');
  if (clear) {
   clear.addEventListener('click', function () {
    colourSettings.forEach(function (id) {
     var setting = api(id);
     if (setting) setting.set('');
    });
    var notice = root.querySelector('.flavor-preset__notice');
    if (notice) notice.remove();
   });
  }
 });
})(wp.customize);
