/**
 * Contact-page message form (prompt 53). Submits to flavor/v1/contact via
 * Contact_Form. Every rendered string comes from Enqueue::contact_strings()
 * through flavorData.i18n.contact; validation states the fix, not just the
 * failure, and lands on the field through aria-invalid + aria-describedby.
 */
(function () {
	'use strict';
	var form = document.getElementById('flavor-contact-form');
	var cfg = window.flavorData || {};
	if (!form || !cfg.rest) return;
	var ui = window.FlavorUI;
	/* flavor-i18n-map: flavorData.i18n.contact */
	var STR = (cfg.i18n || {}).contact || {};
	function t(key) {
		return typeof STR[key] === 'string' && STR[key] !== '' ? STR[key] : key;
	}

	var fields = {
		name: document.getElementById('flavor-contact-name'),
		mobile: document.getElementById('flavor-contact-mobile'),
		email: document.getElementById('flavor-contact-email'),
		message: document.getElementById('flavor-contact-message'),
		website: document.getElementById('flavor-contact-website'),
	};
	var err = document.getElementById('flavor-contact-err');
	var ok = document.getElementById('flavor-contact-ok');
	var submit = form.querySelector('[type="submit"]');

	Object.keys(fields).forEach(function (key) {
		var input = fields[key];
		if (input && ui) input.addEventListener('input', function () { ui.fieldClear(input); });
	});

	function invalid(input, message) {
		if (ui) { ui.fieldError(input, message); }
		input.focus();
		return true;
	}

	form.hidden = false;
	form.addEventListener('submit', function (event) {
		event.preventDefault();
		if (err) { err.hidden = true; err.textContent = ''; }
		if (ok) { ok.hidden = true; ok.textContent = ''; }

		var name = fields.name.value.trim();
		var message = fields.message.value.trim();
		var mobile = ui ? ui.latin(fields.mobile.value.trim()) : fields.mobile.value.trim();
		var email = fields.email.value.trim();

		if (!name) { invalid(fields.name, t('name_required')); return; }
		if (message.length < 10) { invalid(fields.message, t('message_short')); return; }
		if (!mobile && !email) { invalid(fields.mobile, t('reply_required')); return; }
		if (mobile && !/^09\d{9}$/.test(mobile)) { invalid(fields.mobile, t('mobile_invalid')); return; }
		if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { invalid(fields.email, t('email_invalid')); return; }

		submit.disabled = true;
		/* prompt 60: the sending button carries aria-busy alongside disabled. */
		submit.setAttribute('aria-busy', 'true');
		form.setAttribute('aria-busy', 'true');
		if (ui) { ui.announce(t('sending')); }
		ui.request('contact', {
			method: 'POST',
			body: JSON.stringify({
				name: name,
				message: message,
				mobile: mobile,
				email: email,
				website: fields.website.value,
			}),
		}).then(function (data) {
			ok.hidden = false;
			ok.textContent = (data && data.message) || t('sent');
			ok.focus({ preventScroll: true });
			form.reset();
		}).catch(function (error) {
			err.hidden = false;
			err.textContent = error.message || t('send_failed');
			err.focus({ preventScroll: true });
		}).finally(function () {
			submit.disabled = false;
			submit.removeAttribute('aria-busy');
			form.removeAttribute('aria-busy');
		});
	});
})();
