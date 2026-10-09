/**
 * Flavor chrome builder — Customizer controls.
 *
 * One job: keep the ordered list of chrome elements in sync with its setting.
 * Drag-and-drop where jQuery UI is available, move buttons and Alt+arrow
 * always, and a reset that returns the region to the skin defaults without
 * publishing anything.
 *
 * Loaded in the Customizer frame only, never on the public site.
 */
/* global wp, jQuery */
(function () {
	'use strict';

	var api = window.wp && window.wp.customize;
	if (! api) { return; }

	var labels = (window.flavorChromeControls && window.flavorChromeControls.i18n) || {};
	var live = null;
	var ON = ['yes', '1', 'on', 'true', true, 1];

	/**
	 * Quiet status text for assistive tech.
	 *
	 * @param {string} message Text.
	 */
	function announce(message) {
		if (! live) {
			live = document.createElement('div');
			live.className = 'screen-reader-text';
			live.setAttribute('aria-live', 'polite');
			document.body.appendChild(live);
		}
		window.setTimeout(function () { live.textContent = message; }, 30);
	}

	/**
	 * @param {Element} list List element.
	 * @returns {Array<Element>} Rows.
	 */
	function rowsOf(list) {
		return Array.prototype.slice.call(list.querySelectorAll('[data-flavor-key]'));
	}

	/**
	 * @param {Element} list List element.
	 * @returns {string} Comma separated keys in DOM order.
	 */
	function keysOf(list) {
		return rowsOf(list).map(function (row) { return row.getAttribute('data-flavor-key'); }).join(',');
	}

	/**
	 * Reorder the rows so they match a stored value (or a server-side repair).
	 *
	 * @param {Element} list  List element.
	 * @param {string} order  Comma separated keys.
	 */
	function syncOrder(list, order) {
		if (! order) { return; }

		var rows = {};
		rowsOf(list).forEach(function (row) { rows[row.getAttribute('data-flavor-key')] = row; });

		String(order).split(',').forEach(function (key) {
			if (rows[key]) { list.appendChild(rows[key]); }
		});
	}

	/**
	 * Push the DOM order into the setting.
	 *
	 * @param {Element} list List element.
	 */
	function commit(list) {
		var id = list.getAttribute('data-flavor-order');
		if (! id) { return; }

		api(id, function (setting) {
			var next = keysOf(list);
			if (setting.get() !== next) { setting.set(next); }
		});
	}

	/**
	 * @param {Element} row       Row.
	 * @param {number}  direction -1 up, 1 down.
	 * @returns {boolean} Whether the row moved.
	 */
	function move(row, direction) {
		var list = row.parentNode;
		var rows = rowsOf(list);
		var index = rows.indexOf(row);
		var target = direction < 0 ? rows[index - 1] : rows[index + 1];

		if (! target) { return false; }

		if (direction < 0) {
			list.insertBefore(row, target);
		} else {
			list.insertBefore(row, target.nextSibling);
		}

		commit(list);
		announce(labels.moved || 'جابه‌جا شد.');
		return true;
	}

	/**
	 * @param {Element} list List element.
	 */
	function enhance(list) {
		if (list.getAttribute('data-flavor-ready')) { return; }
		list.setAttribute('data-flavor-ready', '1');

		api(list.getAttribute('data-flavor-order'), function (setting) {
			syncOrder(list, setting.get());
			setting.bind(function (value) { syncOrder(list, value); });
		});

		rowsOf(list).forEach(function (row) {
			var input = row.querySelector('[data-flavor-setting]');
			if (! input) { return; }

			api(input.getAttribute('data-flavor-setting'), function (setting) {
				input.checked = ON.indexOf(setting.get()) !== -1;
				row.classList.toggle('is-on', input.checked);
				setting.bind(function (value) {
					input.checked = ON.indexOf(value) !== -1;
					row.classList.toggle('is-on', input.checked);
				});
			});
		});

		if (window.jQuery && jQuery.fn && jQuery.fn.sortable) {
			jQuery(list).sortable({
				axis: 'y',
				items: '> [data-flavor-key]',
				handle: '.flavor-builder-item__handle',
				placeholder: 'flavor-builder-item flavor-builder-item--placeholder',
				forcePlaceholderSize: true,
				stop: function () {
					commit(list);
					announce(labels.moved || 'جابه‌جا شد.');
				}
			});
		}

		list.addEventListener('click', function (event) {
			var button = event.target.closest('[data-flavor-move]');
			if (! button) { return; }
			if (move(button.closest('[data-flavor-key]'), parseInt(button.getAttribute('data-flavor-move'), 10) < 0 ? -1 : 1)) {
				button.focus();
			}
		});

		list.addEventListener('change', function (event) {
			var input = event.target.closest('[data-flavor-setting]');
			if (! input) { return; }
			api(input.getAttribute('data-flavor-setting'), function (setting) {
				setting.set(input.checked ? 'yes' : 'no');
			});
		});

		list.addEventListener('keydown', function (event) {
			if (! event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) { return; }
			var row = event.target.closest('[data-flavor-key]');
			if (! row) { return; }
			event.preventDefault();
			move(row, event.key === 'ArrowUp' ? -1 : 1);
		});
	}

	/**
	 * Restore the region's defaults in the preview (nothing is published).
	 *
	 * @param {Element} list List element.
	 */
	function reset(list) {
		var defaults = {};

		try {
			defaults = JSON.parse(list.getAttribute('data-flavor-defaults') || '{}');
		} catch (error) {
			defaults = {};
		}

		var order = String(list.getAttribute('data-flavor-default-order') || '').split(',').filter(Boolean);

		api(list.getAttribute('data-flavor-order'), function (setting) {
			setting.set(order.join(','));
		});

		rowsOf(list).forEach(function (row) {
			var input = row.querySelector('[data-flavor-setting]');
			var key = row.getAttribute('data-flavor-key');
			if (! input || typeof defaults[key] === 'undefined') { return; }
			api(input.getAttribute('data-flavor-setting'), function (setting) {
				setting.set(defaults[key] ? 'yes' : 'no');
			});
		});
	}

	/**
	 * Wire every builder list currently in the pane.
	 */
	function bindPanels() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-flavor-sortable]'), enhance);

		Array.prototype.forEach.call(document.querySelectorAll('[data-flavor-reset]'), function (button) {
			if (button.getAttribute('data-flavor-bound')) { return; }
			button.setAttribute('data-flavor-bound', '1');
			button.addEventListener('click', function () {
				var control = button.closest('.customize-control');
				var list = control ? control.querySelector('[data-flavor-sortable]') : null;
				if (! list) { return; }
				if (window.confirm(labels.resetConfirm || 'بازنشانی شود؟')) {
					reset(list);
					announce(labels.resetDone || 'پیش‌فرض بازگردانده شد.');
				}
			});
		});
	}

	api.bind('ready', bindPanels);

	if (api.control && api.control.bind) {
		api.control.bind('add', bindPanels);
	}
})();
