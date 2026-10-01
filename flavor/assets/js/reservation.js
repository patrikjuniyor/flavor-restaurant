/**
 * Jalali reservation picker. Talks to flavor/v1.
 */
(function () {
	'use strict';
	var root = document.getElementById('flavor-res');
	var cfg = window.flavorData || {};
	if (!root || !cfg.hasCore) return;

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
		var q = 'calendar?jy=' + jy + '&jm=' + jm;
		api(q).then(function (cal) {
			jy = cal.jy;
			jm = cal.jm;
			var title = document.getElementById('flavor-cal-title');
			if (title) title.textContent = cal.month + ' ' + cal.jy;
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
					d.jd +
					'</button>';
			});
			grid.innerHTML = html;
			grid.querySelectorAll('[data-g]').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.g === selectedDate)); });
		}).catch(reportError);
	}

	function loadSlots() {
		var branch = document.getElementById('flavor-res-branch');
		var party = document.getElementById('flavor-res-party');
		var section = document.getElementById('flavor-res-section');
		var host = document.getElementById('flavor-res-slots');
		if (!selectedDate || !host) return;
		selectedTime = '';
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

	api('branches').then(function (list) {
		var sel = document.getElementById('flavor-res-branch');
		if (!sel) return;
		sel.innerHTML = (list || [])
			.map(function (b) {
				return '<option value="' + esc(b.id) + '">' + esc(b.name) + '</option>';
			})
			.join('');
		if (cfg.branchId) sel.value = String(cfg.branchId);
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
			slots.querySelectorAll('.flavor-slot').forEach(function (x) {
				x.classList.toggle('is-active', x === b);
				x.setAttribute('aria-pressed', String(x === b));
			});
		});
	}

	['flavor-res-party', 'flavor-res-section', 'flavor-res-branch'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) el.addEventListener('change', loadSlots);
	});

	var form = document.getElementById('flavor-res-form');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = document.getElementById('flavor-res-err');
			var ok = document.getElementById('flavor-res-ok');
			if (err) err.hidden = true;
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
						ok.textContent = 'رزرو ثبت شد (' + (res.jalali_label || '') + ' ساعت ' + res.time + '). وضعیت: ' + res.status;
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
