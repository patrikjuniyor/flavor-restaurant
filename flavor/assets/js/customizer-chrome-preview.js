/**
 * Flavor chrome builder — live preview.
 *
 * The preview never re-implements the theme: PHP prints the chrome as CSS
 * custom properties plus a `flavor-chrome-*` body class per changed setting, and
 * this script writes exactly the same two things when a value moves. Defaults
 * are removed again, so the stylesheet decides once the owner reverts a change.
 *
 * Values that change structure (which element is where, which column is on) are
 * handled by WordPress selective refresh, not here.
 */
/* global wp */
(function () {
	'use strict';

	var api = window.wp && window.wp.customize;
	if (! api) { return; }

	var schema = window.flavorChromePreview || { tokens: {}, colors: {}, switches: {} };
	var root = document.documentElement;
	var applied = {};
	var HEX = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

	/**
	 * Drop whatever a setting last wrote.
	 *
	 * @param {string} id Setting id.
	 */
	function clear(id) {
		(applied[id] || []).forEach(function (name) {
			if (name.indexOf('--') === 0) {
				root.style.removeProperty(name);
			} else {
				document.body.classList.remove(name);
			}
		});
		applied[id] = [];
	}

	/**
	 * @param {string} name Custom property name.
	 * @param {string} value Token value.
	 */
	function setVar(name, value) {
		root.style.setProperty(name, value);
	}

	/**
	 * @param {Array<string>} choices Allowed tokens.
	 * @param {string} value Value.
	 * @returns {boolean} Whether the value is one of the control's own choices.
	 */
	function allowed(choices, value) {
		return Array.isArray(choices) && choices.map(String).indexOf(String(value)) !== -1;
	}

	/**
	 * Apply one select-backed chrome token.
	 *
	 * @param {string} id Setting id.
	 * @param {Object} config Schema entry.
	 * @param {string} value Value.
	 */
	function applyToken(id, config, value) {
		clear(id);

		var isDefault = value === '' || value === null || value === undefined || String(value) === String(config.default);

		if (isDefault || ! allowed(config.choices, value)) {
			document.body.classList.remove(config.gate);
			return;
		}

		var props = config.extra && config.extra[String(value)] ? config.extra[String(value)] : null;

		if (! props) {
			props = {};
			props[config.var] = config.grid
				? 'repeat(' + (parseInt(value, 10) || 1) + ', minmax(0, 1fr))'
				: String(value);
		}

		Object.keys(props).forEach(function (name) {
			setVar(name, props[name]);
			applied[id].push(name);
		});

		document.body.classList.add(config.gate);
		applied[id].push(config.gate);
	}

	/**
	 * Apply one colour override.
	 *
	 * @param {string} id Setting id.
	 * @param {Object} config Schema entry.
	 * @param {string} value Value.
	 */
	function applyColor(id, config, value) {
		clear(id);

		var valid = typeof value === 'string' && HEX.test(value.trim());

		document.body.classList.toggle(config.gate, valid);
		if (! valid) { return; }

		setVar(config.var, value.trim());
		applied[id].push(config.var, config.gate);
	}

	/**
	 * Toggle a class-only switch (sticky on phones, mobile drawer, …).
	 *
	 * @param {string} id Setting id.
	 * @param {Object} config Schema entry.
	 * @param {string} value Value.
	 */
	function applySwitch(id, config, value) {
		var on = ['yes', '1', 'on', 'true', true, 1].indexOf(value) !== -1;
		if (config.off) {
			document.body.classList.toggle(config.off, ! on);
		}
		if (config.on) {
			document.body.classList.toggle(config.on, on);
		}
	}

	Object.keys(schema.tokens || {}).forEach(function (id) {
		var config = schema.tokens[id];
		api(id, function (setting) {
			applyToken(id, config, setting.get());
			setting.bind(function (value) { applyToken(id, config, value); });
		});
	});

	Object.keys(schema.colors || {}).forEach(function (id) {
		var config = schema.colors[id];
		api(id, function (setting) {
			applyColor(id, config, setting.get());
			setting.bind(function (value) { applyColor(id, config, value); });
		});
	});

	Object.keys(schema.switches || {}).forEach(function (id) {
		var config = schema.switches[id];
		api(id, function (setting) {
			applySwitch(id, config, setting.get());
			setting.bind(function (value) { applySwitch(id, config, value); });
		});
	});
})();
