/*
 * FlavorDigits — the theme's only number formatter.
 *
 * Why one file: the storefront used to shape digits in eight places. Each copy
 * disagreed with the others — menu.js and ui.js ran Number.toLocaleString('fa-IR')
 * (separator depends on the browser's ICU build), while navigation.js, pages.js,
 * search.js, catering.js, bespoke-demos.js and demo-animations.js hand-mapped
 * digits through an inline table and so printed "1234 شعبه" style prices without a
 * separator, or "۱۲۳۴" where the server had written "۱٬۲۳۴". The table itself was
 * the bug class: a copy could drift from PHP's strtr() mapping and nothing noticed.
 *
 * So the spec is PHP's and the algorithm is here. UI::digits_config() publishes
 * { zero, arabicZero, thousands, mode, label } on flavorData.digits; this module
 * maps by codepoint offset instead of carrying a table, and takes its grouping
 * character from PHP's number_format_i18n() rather than from the locale database.
 * tests/ui/digits.mjs runs this file against the real PHP function over a list of
 * amounts, and tests/theme/test-digit-parity.php keeps every other script off the
 * formatting and out of the table business.
 *
 * No dependency on jQuery, on Flavor Core, or on the payload being present: with
 * no flavorData the fallbacks below reproduce exactly what PHP sends today, and
 * the parity test asserts that the fallback and the server config stay equal.
 */
(function (root) {
	'use strict';

	/* Fallback = the shipped Persian setup, spelled in escapes so no literal digit
	   table lands in a JS file. U+06F0 is ۰ and U+0660 is ٠; both are BMP, so
	   charCodeAt is enough and the parity test asserts the codepoints. */
	var FALLBACK = { zero: 0x06f0, arabicZero: 0x0660, thousands: ',', mode: 'persian' };

	function spec() {
		var d = (root.flavorData && root.flavorData.digits) || {};
		var out = {
			zero: FALLBACK.zero,
			arabicZero: FALLBACK.arabicZero,
			thousands: FALLBACK.thousands,
			mode: d.mode === 'latin' ? 'latin' : 'persian',
			/* The currency word has one home already — flavorData.currency.label, which
			   PHP fills from Currency::display_label(). Repeating it in the digits spec
			   would give a shop two labels to disagree about. */
			label: (root.flavorData && root.flavorData.currency && typeof root.flavorData.currency.label === 'string')
				? root.flavorData.currency.label : '',
		};
		/* PHP sends the characters; a one-character string is all we need. A
		   multi-character or empty value is treated as absent, never trusted. */
		if (typeof d.zero === 'string' && d.zero.length === 1) { out.zero = d.zero.charCodeAt(0); }
		if (typeof d.arabicZero === 'string' && d.arabicZero.length === 1) { out.arabicZero = d.arabicZero.charCodeAt(0); }
		if (typeof d.thousands === 'string') { out.thousands = d.thousands; }
		return out;
	}

	/**
	 * Latin digits to the shop's display script. Identity when the store chose
	 * latin digits, so a "۱٬۲۳۴" price from the server and a "1,234" one render the
	 * same way instead of fighting each other.
	 */
	function digits(value) {
		var c = spec();
		if (c.mode !== 'persian') { return String(value == null ? '' : value); }
		return String(value == null ? '' : value).replace(/[0-9]/g, function (d) {
			return String.fromCharCode(c.zero + Number(d));
		});
	}

	/**
	 * Persian or Arabic-Indic digits back to Latin. This one always runs: it reads
	 * what the visitor typed or what the page already shows, and a shop in latin
	 * digit mode simply has nothing to convert.
	 */
	function latin(value) {
		var c = spec();
		return String(value == null ? '' : value).replace(/[\u06f0-\u06f9\u0660-\u0669]/g, function (d) {
			var code = d.charCodeAt(0);
			var p = code - c.zero;
			if (p >= 0 && p <= 9) { return String(p); }
			var a = code - c.arabicZero;
			return a >= 0 && a <= 9 ? String(a) : d;
		});
	}

	/**
	 * Thousands grouping with the server's separator. Deliberately not
	 * toLocaleString(): a browser (or a node build with small-icu) that lacks fa
	 * data silently returns "1,234" and the price on screen stops matching the
	 * price in the confirmation e-mail.
	 */
	function group(value, decimals) {
		var n = Number(value);
		if (!isFinite(n)) { n = 0; }
		var dp = decimals == null ? 0 : Math.max(0, Number(decimals) || 0);
		var fixed = Math.abs(n).toFixed(dp);
		var cut = fixed.length - dp;
		var whole = dp > 0 ? fixed.slice(0, cut - 1) : fixed;
		var rest = dp > 0 ? '.' + fixed.slice(cut) : '';
		var sep = spec().thousands || '';
		return (n < 0 ? '-' : '') + whole.replace(/\B(?=(\d{3})+(?!\d))/g, sep) + rest;
	}

	/** Number plus currency label, the one call every price display should use. */
	function money(amount, label) {
		var unit = typeof label === 'string' ? label : spec().label;
		var value = digits(group(amount));
		return unit ? value + ' ' + unit : value;
	}

	root.FlavorDigits = {
		config: spec,
		digits: digits,
		latin: latin,
		group: group,
		money: money,
	};
})(typeof window !== 'undefined' ? window : this);
