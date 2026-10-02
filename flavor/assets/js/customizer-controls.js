/** Navigate the actual preview while WordPress retains the unpublished changeset. */
(function () {
 'use strict';
 document.addEventListener('click', function (event) {
  var link = event.target.closest('[data-flavor-ui-preview-url]');
  if (!link || !window.wp || !wp.customize || !wp.customize.previewer) return;
  event.preventDefault();
  wp.customize.previewer.previewUrl.set(link.href);
 });
})();
