/**
 * Kitchen kanban: poll, advance, item-ready, audio, filters.
 *
 * Premium KDS extras layered on the same REST contract:
 * - per-lane ticket counts and empty states
 * - live clock and connection indicator in the header bar
 * - elapsed-time chip with warn/late urgency buckets
 * - session sound toggle and instant branch switching
 */
(function () {
	'use strict';

	var root = document.getElementById('flavor-kitchen');
	if (!root) return;

	var rest = root.getAttribute('data-rest');
	var nonce = root.getAttribute('data-nonce');
	var poll = parseInt(root.getAttribute('data-poll') || '15', 10) * 1000;
	var soundOn = root.getAttribute('data-sound') === '1';
	var home = root.getAttribute('data-home') || '/';
	var known = {};
	var filter = 'all';
	var audioCtx = null;

	var LANES = ['new', 'preparing', 'ready'];
	var EMPTY_LABEL = {
		new: 'سفارشی در صف جدید نیست',
		preparing: 'سفارشی در حال آماده‌سازی نیست',
		ready: 'سفارشی آمادهٔ تحویل نیست'
	};

	function beep() {
		if (!soundOn) return;
		try {
			audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
			var o = audioCtx.createOscillator();
			var g = audioCtx.createGain();
			o.type = 'sine';
			o.frequency.value = 880;
			g.gain.value = 0.08;
			o.connect(g);
			g.connect(audioCtx.destination);
			o.start();
			setTimeout(function () {
				o.stop();
			}, 180);
		} catch (e) {}
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/"/g, '&quot;');
	}

	function pad2(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function modeLabel(m) {
		return { dine_in: 'سالن', takeaway: 'بیرون‌بر', delivery: 'ارسال' }[m] || m;
	}

	function nextStatus(s) {
		return { new: 'preparing', preparing: 'ready', ready: 'completed' }[s] || '';
	}

	function nextLabel(s) {
		return { new: 'شروع آماده‌سازی', preparing: 'آماده شد', ready: 'تحویل شد' }[s] || '';
	}

	function age(sec) {
		var m = Math.floor(sec / 60);
		return m + ' دقیقه';
	}

	/* Elapsed buckets: neutral under 10 min, amber 10–20, red beyond. */
	function ageClass(sec) {
		if (sec >= 1200) return 'is-late';
		if (sec >= 600) return 'is-warn';
		return '';
	}

	function lane(status) {
		return root.querySelector('[data-lane="' + status + '"] .flavor-kitchen__cards');
	}

	function cardHtml(t) {
		var items = (t.items || [])
			.map(function (it) {
				var mods = (it.modifiers || []).map(function (m) { return m.name; }).join('، ');
				var cls = it.item_status === 'ready' ? ' is-ready' : '';
				return (
					'<li class="' +
					cls +
					'" data-item="' +
					esc(it.id) +
					'"><strong>' +
					esc(it.quantity) +
					'× ' +
					esc(it.item_name) +
					'</strong>' +
					(mods ? '<div class="mod">' + esc(mods) + '</div>' : '') +
					(it.special_instructions ? '<div class="note">' + esc(it.special_instructions) + '</div>' : '') +
					'</li>'
				);
			})
			.join('');
		var nxt = nextStatus(t.kitchen_status);
		var printK = home.replace(/\/?$/, '/') + 'kitchen-receipt/' + t.id + '/kitchen/';
		var printC = home.replace(/\/?$/, '/') + 'kitchen-receipt/' + t.id + '/cashier/';
		var ageSec = t.age_seconds || 0;
		return (
			'<article class="flavor-kcard" data-id="' +
			esc(t.id) +
			'" data-mode="' +
			esc(t.order_mode) +
			'" data-urgency="' +
			esc(t.urgency || 'ok') +
			'">' +
			'<div class="flavor-kcard__top"><span>#' +
			esc(t.order_number) +
			'</span><span>' +
			esc(modeLabel(t.order_mode)) +
			(t.table_number ? ' · میز ' + esc(t.table_number) : '') +
			'</span></div>' +
			'<div class="flavor-kcard__meta"><span>' +
			esc(t.customer_name || '—') +
			'</span><span class="flavor-kcard__age ' +
			ageClass(ageSec) +
			'">' +
			esc(age(ageSec)) +
			'</span></div>' +
			'<ul>' +
			items +
			'</ul>' +
			'<div class="flavor-kcard__actions">' +
			(nxt
				? '<button type="button" data-next="' +
				  esc(nxt) +
				  '">' +
				  esc(nextLabel(t.kitchen_status)) +
				  '</button>'
				: '') +
			'<a href="' +
			esc(printK) +
			'" target="_blank" rel="noopener">رسید آشپزخانه</a>' +
			'<a href="' +
			esc(printC) +
			'" target="_blank" rel="noopener">رسید صندوق</a>' +
			'</div></article>'
		);
	}

	function applyFilter() {
		root.querySelectorAll('.flavor-kcard').forEach(function (el) {
			var mode = el.getAttribute('data-mode');
			el.style.display = filter === 'all' || filter === mode ? '' : 'none';
		});
	}

	function updateLaneChrome() {
		LANES.forEach(function (s) {
			var section = root.querySelector('[data-lane="' + s + '"]');
			if (!section) return;
			var cards = section.querySelectorAll('.flavor-kcard');
			var count = section.querySelector('.flavor-kitchen__count');
			if (count) count.textContent = cards.length;
			var empty = section.querySelector('.flavor-kitchen__empty');
			if (!cards.length) {
				if (!empty) {
					empty = document.createElement('div');
					empty.className = 'flavor-kitchen__empty';
					empty.textContent = EMPTY_LABEL[s];
					section.querySelector('.flavor-kitchen__cards').appendChild(empty);
				}
			} else if (empty) {
				empty.remove();
			}
		});
	}

	function render(payload) {
		var tickets = payload.tickets || [];
		var fresh = {};
		LANES.forEach(function (s) {
			var el = lane(s);
			if (el) el.innerHTML = '';
		});
		tickets.forEach(function (t) {
			fresh[t.id] = true;
			if (!known[t.id] && t.kitchen_status === 'new') {
				beep();
			}
			var host = lane(t.kitchen_status);
			if (host) host.insertAdjacentHTML('beforeend', cardHtml(t));
		});
		known = fresh;
		applyFilter();
		updateLaneChrome();
	}

	function setConn(online) {
		var dot = root.querySelector('.flavor-kitchen__conn');
		if (dot) dot.classList.toggle('is-offline', !online);
	}

	function tick() {
		var branch = document.getElementById('flavor-kitchen-branch');
		var id = branch ? branch.value : root.getAttribute('data-branch');
		fetch(rest + '?branch_id=' + encodeURIComponent(id), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': nonce }
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (payload) {
				setConn(true);
				render(payload);
			})
			.catch(function () {
				setConn(false);
			});
	}

	/* --- Header chrome: counts, clock, connection dot, sound toggle --- */
	LANES.forEach(function (s) {
		var h2 = root.querySelector('[data-lane="' + s + '"] h2');
		if (!h2) return;
		var count = document.createElement('span');
		count.className = 'flavor-kitchen__count';
		count.setAttribute('aria-label', 'تعداد سفارش‌ها');
		count.textContent = '0';
		h2.appendChild(count);
	});

	var bar = root.querySelector('.flavor-kitchen__bar');
	if (bar) {
		var conn = document.createElement('span');
		conn.className = 'flavor-kitchen__conn';
		conn.setAttribute('aria-hidden', 'true');
		bar.appendChild(conn);

		var clock = document.createElement('span');
		clock.className = 'flavor-kitchen__clock';
		bar.appendChild(clock);
		var renderClock = function () {
			var d = new Date();
			clock.textContent = pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
		};
		renderClock();
		setInterval(renderClock, 1000);

		var soundBtn = document.createElement('button');
		soundBtn.type = 'button';
		soundBtn.id = 'flavor-kitchen-sound';
		soundBtn.textContent = 'صدای اعلان';
		soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
		soundBtn.addEventListener('click', function () {
			soundOn = !soundOn;
			soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
		});
		bar.insertBefore(soundBtn, document.getElementById('flavor-kitchen-fs'));
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-next]');
		if (btn) {
			var card = btn.closest('.flavor-kcard');
			var id = card && card.getAttribute('data-id');
			if (!id) return;
			fetch(rest + '/' + id + '/status', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': nonce,
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({ status: btn.getAttribute('data-next') })
			}).then(tick);
			return;
		}
		var li = e.target.closest('li[data-item]');
		if (li) {
			var card2 = li.closest('.flavor-kcard');
			var tid = card2 && card2.getAttribute('data-id');
			if (!tid) return;
			fetch(rest + '/' + tid + '/item', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': nonce,
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					item_id: li.getAttribute('data-item'),
					status: li.classList.contains('is-ready') ? 'pending' : 'ready'
				})
			}).then(tick);
		}
	});

	root.querySelectorAll('[data-filter]').forEach(function (b) {
		b.addEventListener('click', function () {
			filter = b.getAttribute('data-filter');
			root.querySelectorAll('[data-filter]').forEach(function (x) {
				x.classList.toggle('is-active', x === b);
			});
			applyFilter();
		});
	});

	/* Switching branch re-fetches immediately instead of waiting for the poll. */
	var branchSelect = document.getElementById('flavor-kitchen-branch');
	if (branchSelect) {
		branchSelect.addEventListener('change', tick);
	}

	var fs = document.getElementById('flavor-kitchen-fs');
	if (fs) {
		fs.addEventListener('click', function () {
			if (!document.fullscreenElement) {
				document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
			} else {
				document.exitFullscreen && document.exitFullscreen();
			}
		});
	}

	tick();
	setInterval(tick, poll);
})();
