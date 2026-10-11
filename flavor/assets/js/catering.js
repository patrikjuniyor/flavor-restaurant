/**
 * Mizan's local-only catering brief. No fetch, storage, booking or submission.
 * Progressive enhancement: the form stays hidden until the handlers are ready.
 */
(function () {
	'use strict';
	// The copy in this file comes from Enqueue::catering_strings(), served on flavorData.i18n; nothing
	// here carries Persian text of its own (prompt 21).
	/* flavor-i18n-map: flavorData.i18n.catering */
	var STR=((window.flavorData||{}).i18n||{}).catering||{};
	function t(key){return STR[key]||key;}
	function fmt(key,v){return String(t(key)).replace('%s',v);}
	var form = document.querySelector('[data-fd-proposal]');
	if (!form) return;
	var result = document.querySelector('[data-fd-proposal-result]');
	var summary = document.querySelector('[data-fd-summary]');
	var copyButton = document.querySelector('[data-fd-copy]');
	var copyStatus = document.querySelector('[data-fd-copy-status]');
	var section = document.getElementById('proposal');
	if (!result || !summary || !copyButton || !copyStatus || !section) return;

	function latinDigits(text) {
		return text.replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); })
			.replace(/[٠-٩]/g, function (digit) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)); });
	}
	function persianDigits(text) {
		return String(text).replace(/\d/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'[digit]; });
	}
	function clearResult() {
		result.hidden = true;
		summary.value = '';
		copyStatus.textContent = '';
	}

	form.addEventListener('input', function (event) {
		if (typeof event.target.setCustomValidity === 'function') event.target.setCustomValidity('');
		clearResult();
	});
	form.addEventListener('change', clearResult);
	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var guests = Number(latinDigits(form.elements.guests.value.trim()));
		form.elements.guests.setCustomValidity(Number.isInteger(guests) && guests >= 1 && guests <= 1000 ? '' : t('guests_range'));
		['date', 'location'].forEach(function (name) {
			form.elements[name].setCustomValidity(form.elements[name].value.trim() ? '' : t('field_required'));
		});
		if (!form.reportValidity()) { clearResult(); return; }
		var service = form.elements.service.options[form.elements.service.selectedIndex].textContent.trim();
		var notes = form.elements.notes.value.trim();
		var lines = [
			t('summary_title'),
			fmt('service_line', service),
			fmt('guests_line', persianDigits(guests)),
			fmt('date_line', form.elements.date.value.trim()),
			fmt('location_line', form.elements.location.value.trim()),
			fmt('notes_line', notes || t('notes_none')),
			'',
			t('summary_notice')
		];
		// User input is plain text, never inserted as HTML or sent anywhere.
		summary.value = lines.join('\n');
		copyStatus.textContent = '';
		result.hidden = false;
		result.focus({ preventScroll: true });
		result.scrollIntoView({ block: 'nearest', behavior: 'instant' });
	});

	document.querySelectorAll('[data-fd-service]').forEach(function (link) {
		link.addEventListener('click', function (event) {
			var selected = link.dataset.fdService;
			if (!Array.from(form.elements.service.options).some(function (option) { return option.value === selected; })) return;
			event.preventDefault();
			form.elements.service.value = selected;
			clearResult();
			section.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
			form.elements.service.focus({ preventScroll: true });
		});
	});

	function manualCopy() {
		summary.focus({ preventScroll: true });
		summary.select();
		var copied = false;
		try { copied = document.execCommand('copy'); } catch (error) { /* Keep the text selected for manual copying. */ }
		copyStatus.textContent = copied ? t('summary_copied') : t('copy_manual');
	}
	copyButton.addEventListener('click', function () {
		if (result.hidden || !summary.value) return;
		if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') { manualCopy(); return; }
		navigator.clipboard.writeText(summary.value).then(function () {
			copyStatus.textContent = t('summary_copied');
		}).catch(manualCopy);
	});
	form.hidden = false;
})();
