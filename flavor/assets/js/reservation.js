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
		if (host) { host.hidden = false; host.textContent = error.message; }
	}

	var slotsRequest = 0;
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

	function loadSlots() {
		var branch = document.getElementById('flavor-res-branch');
		var party = document.getElementById('flavor-res-party');
		var section = document.getElementById('flavor-res-section');
		var host = document.getElementById('flavor-res-slots');
		if (!selectedDate || !host) return;
		selectedTime = '';
		var selected = document.getElementById('flavor-res-selection'); if (selected) selected.textContent = 'روز انتخاب شد؛ یک ساعت دارای ظرفیت انتخاب کنید.';
		var requestId = ++slotsRequest;
		host.setAttribute('aria-busy', 'true');
		host.textContent = 'در حال بررسی ظرفیت…';
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
			if (!d.slots || !d.slots.length) host.textContent = 'برای این تاریخ و انتخاب، ظرفیت قابل رزرو وجود ندارد. تاریخ یا بخش دیگری را بررسی کنید.';
		}).catch(function (error) {
			if (requestId !== slotsRequest) return;
			host.removeAttribute('aria-busy'); host.textContent = 'بررسی ظرفیت ناموفق بود.'; reportError(error);
		});
	}

	function loadSections() {
		var branch = document.getElementById('flavor-res-branch');
		return api('branches/' + encodeURIComponent(branch.value) + '/tables').then(function (tables) {
			var select = document.getElementById('flavor-res-section'); var previous = select.value;
			var sections = Array.from(new Set((tables || []).map(function (table) { return table.section; }).filter(Boolean)));
			var labels = { indoor:'سالن', outdoor:'فضای باز', window:'کنار پنجره', bar:'بار' };
			select.innerHTML = '<option value="">هر بخشِ دارای ظرفیت</option>' + sections.map(function (section) { return '<option value="' + esc(section) + '">' + esc(labels[section] || section) + '</option>'; }).join('');
			if (sections.includes(previous)) select.value = previous;
		});
	}
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
			var chosen = document.getElementById('flavor-res-selection'); if (chosen) chosen.textContent = 'ساعت انتخاب‌شده: ' + digits(selectedTime) + '؛ هنوز رزروی ارسال نشده است.';
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
			if (err) err.hidden = true; if (ok) ok.hidden = true;
			if (!selectedDate || !selectedTime) {
				if (err) {
					err.hidden = false;
					err.textContent = 'تاریخ و ساعت را انتخاب کنید.';
				}
				return;
			}
			var branch = document.getElementById('flavor-res-branch');
			var submit = form.querySelector('[type="submit"]');
			if (submit) submit.disabled = true;
			api('reservations', {
				method: 'POST',
				headers: { 'X-WP-Nonce': cfg.nonce || '', 'Content-Type': 'application/json' },
				body: JSON.stringify({
					branch_id: branch ? branch.value : 0,
					date: selectedDate,
					time: selectedTime,
					party_size: (document.getElementById('flavor-res-party') || {}).value,
					section: (document.getElementById('flavor-res-section') || {}).value,
					name: (document.getElementById('flavor-res-name') || {}).value,
					mobile: (document.getElementById('flavor-res-mobile') || {}).value,
					requests: (document.getElementById('flavor-res-note') || {}).value,
				}),
			})
				.then(function (res) {
					if (ok) {
						ok.hidden = false;
						var statuses = { confirmed:'تأیید شده', pending:'در انتظار تأیید', cancelled:'لغوشده' };
						ok.textContent = 'درخواست ثبت شد (' + (res.jalali_label || '') + ' ساعت ' + digits(res.time) + '). وضعیت: ' + (statuses[res.status] || res.status);
						ok.focus({ preventScroll:true });
					}
				})
				.catch(function (ex) {
					if (err) {
						err.hidden = false;
						err.textContent = ex.message;
					}
				}).finally(function () { if (submit) submit.disabled = false; });
		});
	}
})();
