/**
 * Jalali reservation picker. Talks to flavor/v1.
 */
(function () {
	'use strict';
	var root = document.getElementById('flavor-res');
	var cfg = window.flavorData || {};
	if (!root || !cfg.hasCore || !document.getElementById('flavor-res-form')) return;
	var ui = window.FlavorUI;
	function digits(value) { return ui ? ui.digits(value) : String(value); }
	/* flavor-i18n-map: flavorData.i18n.reservation */
	// Every label comes from Enqueue::reservation_strings(). A fallback here
	// used to be Persian, which is copy no site could translate; a key absent
	// from the map now surfaces as the key itself and fails
	// tests/theme/test-js-i18n.php.
	var STR = (cfg.i18n || {}).reservation || {};
	function t(key) {
		return typeof STR[key] === 'string' && STR[key] !== '' ? STR[key] : key;
	}
	function fmt(key, value) { return t(key).replace('%s', value); }
	function fmt3(key, first, second, third) { return t(key).replace('%1$s', first).replace('%2$s', second).replace('%3$s', third); }
	var calendarRequest = 0;

	var jy = 0;
	var jm = 0;
	var selectedDate = '';
	var selectedTime = '';

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/"/g, '&quot;');
	}

	function api(path, opt) {
		return fetch(cfg.rest + path, Object.assign({ credentials: 'same-origin' }, opt || {})).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok || (j && j.success === false)) {
					var error = j && j.errors && j.errors[0];
					throw new Error((error && error.message) || j.message || r.statusText);
				}
				return j && j.success === true ? j.data : j;
			});
		});
	}

	function reportError(error) {
		var host = document.getElementById('flavor-res-err');
		if (host) { host.hidden = false; host.textContent = error.message; host.focus({ preventScroll: true }); }
		/* prompt 59: every reportError caller is a load path (branches,
		   sections, slots, calendar), so a failed load always offers one
		   press that repeats the whole availability boot. */
		var retry = document.getElementById('flavor-res-retry');
		if (retry) { retry.hidden = false; }
	}

	/* Field-level errors (prompt 53): aria-invalid + aria-describedby through
	   the shared FlavorUI helper, cleared as soon as the visitor edits. */
	function bindFieldClear(id) {
		var input = document.getElementById(id);
		if (input && ui) input.addEventListener('input', function () { ui.fieldClear(input); });
	}
	['flavor-res-party', 'flavor-res-name', 'flavor-res-mobile'].forEach(bindFieldClear);

	function invalid(inputId, message) {
		var input = document.getElementById(inputId);
		if (ui) { ui.fieldError(input, message); }
		if (input) { input.focus(); }
		return true;
	}

	var slotsRequest = 0;
	function loadSlots() {
		var branch = document.getElementById('flavor-res-branch');
		var party = document.getElementById('flavor-res-party');
		var section = document.getElementById('flavor-res-section');
		var host = document.getElementById('flavor-res-slots');
		if (!selectedDate || !host) return;
		selectedTime = '';
		var selected = document.getElementById('flavor-res-selection'); if (selected) selected.textContent = t('day_selected');
		var requestId = ++slotsRequest;
		host.setAttribute('aria-busy', 'true');
		host.textContent = t('checking_capacity');
		var q =
			'reservations/slots?branch_id=' +
			encodeURIComponent(branch ? branch.value : 0) +
			'&date=' +
			encodeURIComponent(selectedDate) +
			'&party=' +
			encodeURIComponent(party ? party.value : 2) +
			'&section=' +
			encodeURIComponent(section ? section.value : '');
		api(q).then(function (d) {
			if (requestId !== slotsRequest) return;
			host.removeAttribute('aria-busy');
			host.innerHTML = (d.slots || [])
				.map(function (s) {
					return (
						'<button type="button" class="flavor-slot' +
						(s.available ? '' : ' is-off') +
						(s.time === selectedTime ? ' is-active' : '') +
						'" data-t="' +
						esc(s.time) +
						'" ' +
						(s.available ? '' : 'disabled') +
						'>' +
						esc(s.time) +
						'</button>'
					);
				})
				.join('');
			host.querySelectorAll('[data-t]').forEach(function (button) { button.setAttribute('aria-pressed', 'false'); });
			if (!d.slots || !d.slots.length) host.textContent = t('no_capacity');
		}).catch(function (error) {
			if (requestId !== slotsRequest) return;
			host.removeAttribute('aria-busy'); host.textContent = t('capacity_failed'); reportError(error);
		});
	}

	function loadSections() {
		var branch = document.getElementById('flavor-res-branch');
		return api('branches/' + encodeURIComponent(branch.value) + '/tables').then(function (tables) {
			var select = document.getElementById('flavor-res-section'); var previous = select.value;
			var sections = Array.from(new Set((tables || []).map(function (table) { return table.section; }).filter(Boolean)));
			var labels = { indoor: t('section_indoor'), outdoor: t('section_outdoor'), window: t('section_window'), bar: t('section_bar') };
			select.innerHTML = '<option value="">' + esc(t('section_any')) + '</option>' + sections.map(function (section) { return '<option value="' + esc(section) + '">' + esc(labels[section] || section) + '</option>'; }).join('');
			if (sections.includes(previous)) select.value = previous;
		});
	}
	function bootData() {
		api('branches').then(function (list) {
			var select = document.getElementById('flavor-res-branch'); if (!select) return;
			var eligible = (list || []).filter(function (branch) { return (cfg.reservationBranchIds || []).includes(Number(branch.id)); });
			select.innerHTML = eligible.map(function (branch) { return '<option value="' + esc(branch.id) + '">' + esc(branch.name) + '</option>'; }).join('');
			if (eligible.some(function (branch) { return Number(branch.id) === Number(cfg.branchId); })) select.value = String(cfg.branchId);
			loadSections().catch(reportError);
		}).catch(reportError);

		api('calendar').then(function (cal) {
			jy = cal.jy;
			jm = cal.jm;
			loadCal();
		}).catch(reportError);
	}
	bootData();

	var resRetry = document.getElementById('flavor-res-retry');
	if (resRetry) {
		resRetry.addEventListener('click', function () {
			resRetry.hidden = true;
			var host = document.getElementById('flavor-res-err');
			if (host) { host.hidden = true; host.textContent = ''; }
			bootData();
		});
	}

	function loadCal() {
		var requestId = ++calendarRequest;
		var q = 'calendar?jy=' + jy + '&jm=' + jm;
		api(q).then(function (cal) {
			if (requestId !== calendarRequest) return;
			jy = cal.jy;
			jm = cal.jm;
			var title = document.getElementById('flavor-cal-title');
			if (title) title.textContent = cal.month + ' ' + digits(cal.jy);
			var week = document.getElementById('flavor-cal-week');
			if (week) week.innerHTML = (cal.weekdays || []).map(function (w) { return '<span>' + esc(w) + '</span>'; }).join('');
			var grid = document.getElementById('flavor-cal-grid');
			if (!grid) return;
			var html = '';
			var firstDow = (cal.days[0] && cal.days[0].dow) || 0;
			for (var i = 0; i < firstDow; i++) html += '<span></span>';
			cal.days.forEach(function (d) {
				html +=
					'<button type="button" class="flavor-cal__day' +
					(d.past ? ' is-past' : '') +
					(d.gregorian === selectedDate ? ' is-active' : '') +
					'" data-g="' +
					esc(d.gregorian) +
					'" ' +
					(d.past ? 'disabled' : '') +
					'>' +
					digits(d.jd) +
					'</button>';
			});
			grid.innerHTML = html;
			grid.querySelectorAll('[data-g]').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.g === selectedDate)); button.setAttribute('aria-label', button.textContent + ' ' + cal.month + ' ' + digits(cal.jy)); });
		}).catch(reportError);
	}

	var prev = document.getElementById('flavor-cal-prev');
	var next = document.getElementById('flavor-cal-next');
	if (prev) {
		prev.addEventListener('click', function () {
			jm -= 1;
			if (jm < 1) {
				jm = 12;
				jy -= 1;
			}
			selectedDate = ''; selectedTime = ''; document.getElementById('flavor-res-slots').textContent = '';
			loadCal();
		});
	}
	if (next) {
		next.addEventListener('click', function () {
			jm += 1;
			if (jm > 12) {
				jm = 1;
				jy += 1;
			}
			selectedDate = ''; selectedTime = ''; document.getElementById('flavor-res-slots').textContent = '';
			loadCal();
		});
	}

	var grid = document.getElementById('flavor-cal-grid');
	if (grid) {
		grid.addEventListener('click', function (e) {
			var b = e.target.closest('[data-g]');
			if (!b || b.disabled) return;
			selectedDate = b.getAttribute('data-g');
			selectedTime = '';
			grid.querySelectorAll('.flavor-cal__day').forEach(function (x) {
				x.classList.toggle('is-active', x === b);
				x.setAttribute('aria-pressed', String(x === b));
			});
			loadSlots();
		});
	}

	var slots = document.getElementById('flavor-res-slots');
	if (slots) {
		slots.addEventListener('click', function (e) {
			var b = e.target.closest('[data-t]');
			if (!b || b.disabled) return;
			selectedTime = b.getAttribute('data-t');
			var chosen = document.getElementById('flavor-res-selection'); if (chosen) chosen.textContent = fmt('time_selected', digits(selectedTime));
			slots.querySelectorAll('.flavor-slot').forEach(function (x) {
				x.classList.toggle('is-active', x === b);
				x.setAttribute('aria-pressed', String(x === b));
			});
		});
	}

	['flavor-res-party', 'flavor-res-section'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) el.addEventListener('change', loadSlots);
	});

	var branchControl = document.getElementById('flavor-res-branch'); if (branchControl) branchControl.addEventListener('change', function () { selectedTime = ''; loadSections().then(loadSlots).catch(reportError); });

	var form = document.getElementById('flavor-res-form');
	if (form) {
		form.hidden = false;
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = document.getElementById('flavor-res-err');
			var ok = document.getElementById('flavor-res-ok');
			var retry = document.getElementById('flavor-res-retry');
			if (err) { err.hidden = true; err.textContent = ''; }
			if (ok) { ok.hidden = true; ok.textContent = ''; }
			if (retry) { retry.hidden = true; }

			/* Validate in reading order; every message states its solution and
			   lands on the field through aria-describedby + aria-invalid. */
			var party = document.getElementById('flavor-res-party');
			var partySize = party ? Number(party.value) : 0;
			if (!partySize || partySize < 1 || partySize > 20) { invalid('flavor-res-party', t('party_range')); return; }
			var name = document.getElementById('flavor-res-name');
			if (!name || !name.value.trim()) { invalid('flavor-res-name', t('name_required')); return; }
			var mobile = document.getElementById('flavor-res-mobile');
			var mobileValue = mobile ? (ui ? ui.latin(mobile.value.trim()) : mobile.value.trim()) : '';
			if (!/^09\d{9}$/.test(mobileValue)) { invalid('flavor-res-mobile', t('mobile_invalid')); return; }
			if (!selectedDate || !selectedTime) {
				if (err) {
					err.hidden = false;
					err.textContent = t('pick_datetime');
					err.focus({ preventScroll: true });
				}
				return;
			}

			var branch = document.getElementById('flavor-res-branch');
			var submit = form.querySelector('[type="submit"]');
			/* prompt 60: the sending button carries aria-busy alongside its
			   disabled state, so the in-flight submit is announced on the
			   button itself, not only on the form. */
			if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
			form.setAttribute('aria-busy', 'true');
			if (ui) ui.announce(t('sending'));
			api('reservations', {
				method: 'POST',
				headers: { 'X-WP-Nonce': cfg.nonce || '', 'Content-Type': 'application/json' },
				body: JSON.stringify({
					branch_id: branch ? branch.value : 0,
					date: selectedDate,
					time: selectedTime,
					party_size: partySize,
					section: (document.getElementById('flavor-res-section') || {}).value,
					name: name.value,
					mobile: mobileValue,
					requests: (document.getElementById('flavor-res-note') || {}).value,
				}),
			})
				.then(function (res) {
					if (ok) {
						ok.hidden = false;
						var statuses = { confirmed: t('status_confirmed'), pending: t('status_pending'), cancelled: t('status_cancelled') };
						ok.textContent = fmt3('request_recorded', res.jalali_label || '', digits(res.time), statuses[res.status] || res.status);
						ok.focus({ preventScroll: true });
					}
				})
				.catch(function (ex) {
					if (err) {
						err.hidden = false;
						err.textContent = ex.message;
						err.focus({ preventScroll: true });
					}
				}).finally(function () { if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); } form.removeAttribute('aria-busy'); });
		});
	}
})();
