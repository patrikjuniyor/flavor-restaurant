/**
 * Menu + modifiers + cart drawer + checkout. Vanilla JS, RTL-first.
 */
(function () {
	'use strict';

	var cfg = window.flavorData || {};
	var grid = document.getElementById('flavor-menu-grid');
	if (!grid || !cfg.hasCore) {
		var st = document.getElementById('flavor-menu-status');
		if (st && !cfg.hasCore) st.textContent = (cfg.i18n && cfg.i18n.offline) || '';
		return;
	}

	var statusEl = document.getElementById('flavor-menu-status');
	var catsEl = document.getElementById('flavor-cats');
	var sheet = document.getElementById('flavor-sheet');
	var sheetTitle = document.getElementById('flavor-sheet-title');
	var sheetBody = document.getElementById('flavor-sheet-body');
	var sheetAdd = document.getElementById('flavor-sheet-add');
	var cartCount = document.getElementById('flavor-cart-count');
	var mobileCartCount = document.getElementById('flavor-mobile-cart-count');
	var cartLines = document.getElementById('flavor-cart-lines');
	var cartTotal = document.getElementById('flavor-cart-total');
	var cartPanel = document.getElementById('flavor-cart-panel');
	var catalog = [];
	var current = null;
	var qty = 1;
	var mode = 'takeaway';
	var ctx = {};

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/"/g, '&quot;');
	}

	function headers(json) {
		var h = { 'X-WP-Nonce': cfg.nonce || '' };
		if (json) h['Content-Type'] = 'application/json';
		return h;
	}

	function api(path, opt) {
		return fetch(cfg.rest + path, Object.assign({ credentials: 'same-origin' }, opt || {})).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok) {
					var msg = (j && (j.message || (j.data && j.data.message))) || r.statusText;
					throw new Error(msg);
				}
				return j;
			});
		});
	}

	function card(item) {
		var disabled = item.available === false;
		var img = item.image
			? '<img src="' + esc(item.image) + '" alt="' + esc(item.name) + '" loading="lazy" width="600" height="400" />'
			: '<div class="flavor-card__ph"></div>';

		var catName = (item.categories && item.categories[0] && item.categories[0].name) || '';

		return (
			'<article class="flavor-card flavor-food-card' +
			(disabled ? ' is-unavailable' : '') +
			'" data-id="' +
			esc(item.id) +
			'" data-cats="' +
			esc((item.categories || []).map(function (c) { return c.id; }).join(',')) +
			'"><div class="flavor-food-card__media">' +
			img +
			(catName ? '<span class="flavor-food-card__cat-badge">' + esc(catName) + '</span>' : '') +
			'</div><div class="flavor-food-card__body">' +
			'<h2 class="flavor-food-card__title">' +
			esc(item.name) +
			(disabled ? ' <span class="flavor-badge">ناموجود</span>' : '') +
			'</h2>' +
			'<p class="flavor-food-card__desc">' +
			esc(item.short || '') +
			'</p>' +
			'<div class="flavor-food-card__meta">' +
			(item.prep_time ? '<span class="flavor-food-card__meta-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' + item.prep_time + ' دقیقه</span>' : '') +
			(item.calories ? '<span class="flavor-food-card__meta-item">' + item.calories + ' کالری</span>' : '') +
			(item.available_at && item.in_schedule === false ? '<span class="flavor-food-card__meta-item">سرو از ' + esc(item.available_at) + '</span>' : '') +
			'</div>' +
			'<div class="flavor-food-card__footer"><strong class="flavor-food-card__price">' +
			(item.price_html || '') +
			'</strong>' +
			'<button type="button" class="flavor-btn flavor-btn--primary flavor-btn--sm" data-add="' +
			esc(item.id) +
			'" ' +
			(disabled ? 'disabled' : '') +
			'><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> ' +
			esc((cfg.i18n && cfg.i18n.add) || 'افزودن') +
			'</button></div></div></article>'
		);
	}

	function renderCats(cats) {
		if (!catsEl) return;
		var html = '<button type="button" class="is-active" data-cat="0">همه</button>';
		(cats || []).forEach(function (c) {
			html += '<button type="button" data-cat="' + esc(c.id) + '">' + esc(c.name) + '</button>';
		});
		catsEl.innerHTML = html;
	}

	function filterCat(id) {
		grid.querySelectorAll('.flavor-card').forEach(function (el) {
			if (!id || id === '0') {
				el.style.display = '';
				return;
			}
			var list = (el.getAttribute('data-cats') || '').split(',');
			el.style.display = list.indexOf(String(id)) !== -1 ? '' : 'none';
		});
	}

	function groupModifiers(mods) {
		var g = { size: [], topping: [], cook: [], removal: [] };
		(mods || []).forEach(function (m) {
			if (g[m.type]) g[m.type].push(m);
		});
		return g;
	}

	function livePrice() {
		if (!current) return 0;
		var extra = 0;
		if (sheetBody) {
			sheetBody.querySelectorAll('input:checked').forEach(function (inp) {
				extra += parseInt(inp.getAttribute('data-price') || '0', 10);
			});
		}
		return (current.price || 0) * qty + extra * qty;
	}

	function paintSheetPrice() {
		if (!sheetAdd || !current) return;
		var n = livePrice();
		sheetAdd.textContent = 'افزودن به سبد · ' + n.toLocaleString('fa-IR') + ' تومان';
	}

	function openSheet(item) {
		current = item;
		qty = 1;
		if (!sheet || !sheetBody || !sheetTitle) {
			addItem(item, [], '', 1);
			return;
		}
		sheetTitle.textContent = item.name;
		var g = groupModifiers(item.modifiers);
		var html = '';
		function radios(list, name, label) {
			if (!list.length) return;
			html += '<fieldset><legend style="font-weight:700; margin-bottom:8px;">' + esc(label) + '</legend>';
			list.forEach(function (m, i) {
				html +=
					'<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><input type="radio" name="' +
					name +
					'" value="' +
					esc(m.id) +
					'" data-price="' +
					esc(m.price) +
					'" ' +
					(m.is_default || i === 0 ? 'checked' : '') +
					'/> ' +
					esc(m.name) +
					(m.price ? ' (+' + m.price.toLocaleString('fa-IR') + ' تومان)' : '') +
					'</label>';
			});
			html += '</fieldset>';
		}
		function checks(list, label) {
			if (!list.length) return;
			html += '<fieldset><legend style="font-weight:700; margin-bottom:8px;">' + esc(label) + '</legend>';
			list.forEach(function (m) {
				html +=
					'<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><input type="checkbox" value="' +
					esc(m.id) +
					'" data-price="' +
					esc(m.price) +
					'"/> ' +
					esc(m.name) +
					(m.price ? ' (+' + m.price.toLocaleString('fa-IR') + ' تومان)' : '') +
					'</label>';
			});
			html += '</fieldset>';
		}
		radios(g.size, 'size', 'اندازه و پرس');
		checks(g.topping, 'افزودنی‌ها و تاپینگ');
		radios(g.cook, 'cook', 'درجه پخت');
		checks(g.removal, 'حذف مخلفات');
		html +=
			'<label style="display:block; margin-top:14px; font-weight:700;">یادداشت سرآشپز: <input type="text" id="flavor-instr" maxlength="200" style="width:100%; padding:10px; border:1px solid var(--flavor-line); border-radius:8px; margin-top:4px;" placeholder="مثال: کم‌نمک، سس جداگانه" /></label>' +
			'<div class="flavor-qty" style="display:flex; align-items:center; gap:16px; margin:16px 0;"><button type="button" class="flavor-btn flavor-btn--outline flavor-btn--sm" data-q="-1">−</button><span id="flavor-qty" style="font-weight:800; font-size:18px;">1</span><button type="button" class="flavor-btn flavor-btn--outline flavor-btn--sm" data-q="1">+</button></div>';
		sheetBody.innerHTML = html;
		sheet.hidden = false;
		document.body.style.overflow = 'hidden';
		paintSheetPrice();
	}

	function closeSheet() {
		if (sheet) sheet.hidden = true;
		document.body.style.overflow = '';
		current = null;
	}

	function selectedIds() {
		var ids = [];
		if (!sheetBody) return ids;
		sheetBody.querySelectorAll('input:checked').forEach(function (inp) {
			ids.push(inp.value);
		});
		return ids;
	}

	function addItem(item, ids, instr, q) {
		return api('cart/add', {
			method: 'POST',
			headers: headers(true),
			body: JSON.stringify({
				product_id: item.id,
				quantity: q || 1,
				modifier_ids: ids || [],
				instructions: instr || '',
			}),
		}).then(function (cart) {
			drawCart(cart);
			if (window.flavorToast) {
				window.flavorToast('«' + item.name + '» به سبد خرید اضافه شد.', 'success');
			}
			return cart;
		});
	}

	function drawCart(cart) {
		if (!cart) return;
		var countStr = String(cart.count || 0);
		if (cartCount) cartCount.textContent = countStr;
		if (mobileCartCount) mobileCartCount.textContent = countStr;
		if (cartLines) {
			cartLines.innerHTML = (cart.items || [])
				.map(function (it) {
					var mods = (it.modifiers || []).map(function (m) { return m.name; }).join('، ');
					return (
						'<div class="flavor-line" data-key="' +
						esc(it.key) +
						'"><div><strong>' +
						esc(it.name) +
						'</strong> × ' +
						esc(it.quantity) +
						(mods ? '<div class="flavor-card__meta">' + esc(mods) + '</div>' : '') +
						'</div><div>' +
						(it.line_html || '') +
						' <button type="button" class="flavor-btn flavor-btn--sm" style="padding:2px 8px; margin-right:6px;" data-rm="' +
						esc(it.key) +
						'">×</button></div></div>'
					);
				})
				.join('');
		}
		if (cartTotal) cartTotal.innerHTML = cart.total_html || '';
	}

	function setMode(next) {
		mode = next;
		document.querySelectorAll('#flavor-modes [data-mode]').forEach(function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-mode') === mode);
		});
		var tbox = document.getElementById('flavor-table-box');
		var abox = document.getElementById('flavor-address-box');
		if (tbox) tbox.hidden = mode !== 'dine_in';
		if (abox) abox.hidden = mode !== 'delivery';
		api('context', {
			method: 'POST',
			headers: headers(true),
			body: JSON.stringify({ order_mode: mode, branch_id: ctx.branch_id || 0 }),
		}).catch(function () {});
		loadPay();
		if (mode === 'dine_in') loadTables();
	}

	function loadPay() {
		api('checkout/options?mode=' + encodeURIComponent(mode)).then(function (d) {
			var box = document.getElementById('flavor-pay-box');
			if (!box) return;
			var html = '<legend style="font-weight:700; margin-bottom:8px;">روش پرداخت</legend>';
			(d.methods || []).forEach(function (m, i) {
				html +=
					'<label style="display:block; margin-bottom:6px;"><input type="radio" name="pay" value="' +
					esc(m.id) +
					'" ' +
					(i === 0 ? 'checked' : '') +
					'/> ' +
					esc(m.title) +
					'</label>';
			});
			box.innerHTML = html;
			if (d.tables && d.tables.length) {
				var sel = document.getElementById('flavor-table');
				if (sel) {
					sel.innerHTML = d.tables
						.map(function (t) {
							var selc = String(t.id) === String(ctx.table_id) ? ' selected' : '';
							return '<option value="' + esc(t.id) + '" data-num="' + esc(t.table_number) + '"' + selc + '>میز ' + esc(t.table_number) + '</option>';
						})
						.join('');
				}
			}
		});
	}

	function loadTables() {
		api('tables?branch_id=' + encodeURIComponent(ctx.branch_id || 0)).then(function (rows) {
			var sel = document.getElementById('flavor-table');
			if (!sel) return;
			sel.innerHTML = (rows || [])
				.map(function (t) {
					return '<option value="' + esc(t.id) + '" data-num="' + esc(t.table_number) + '">میز ' + esc(t.table_number) + '</option>';
				})
				.join('');
		});
	}

	function loadMenu() {
		if (statusEl) statusEl.textContent = (cfg.i18n && cfg.i18n.loading) || '';
		api('menu').then(function (data) {
			catalog = data.items || [];
			if (statusEl) statusEl.textContent = catalog.length ? '' : (cfg.i18n && cfg.i18n.empty) || '';
			grid.innerHTML = catalog.map(card).join('');
			renderCats(data.categories || []);
		});
	}

	grid.addEventListener('click', function (e) {
		var b = e.target.closest('[data-add]');
		if (!b) return;
		var id = parseInt(b.getAttribute('data-add'), 10);
		var item = catalog.filter(function (x) { return x.id === id; })[0];
		if (!item) return;
		if (item.modifiers && item.modifiers.length) openSheet(item);
		else addItem(item, [], '', 1);
	});

	if (sheet) {
		sheet.addEventListener('click', function (e) {
			if (e.target.getAttribute('data-close') === 'sheet') closeSheet();
			var q = e.target.getAttribute('data-q');
			if (q) {
				qty = Math.max(1, Math.min(20, qty + parseInt(q, 10)));
				var n = document.getElementById('flavor-qty');
				if (n) n.textContent = String(qty);
				paintSheetPrice();
			}
		});
		sheet.addEventListener('change', paintSheetPrice);
	}
	if (sheetAdd) {
		sheetAdd.addEventListener('click', function () {
			if (!current) return;
			var instr = document.getElementById('flavor-instr');
			addItem(current, selectedIds(), instr ? instr.value : '', qty).then(closeSheet);
		});
	}

	if (catsEl) {
		catsEl.addEventListener('click', function (e) {
			var b = e.target.closest('[data-cat]');
			if (!b) return;
			catsEl.querySelectorAll('button').forEach(function (x) {
				x.classList.toggle('is-active', x === b);
			});
			filterCat(b.getAttribute('data-cat'));
		});
	}

	var toggle = document.getElementById('flavor-cart-toggle');
	if (toggle && cartPanel) {
		toggle.addEventListener('click', function () {
			cartPanel.hidden = !cartPanel.hidden;
		});
	}

	if (cartLines) {
		cartLines.addEventListener('click', function (e) {
			var rm = e.target.getAttribute('data-rm');
			if (!rm) return;
			api('cart/item', {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify({ key: rm, quantity: 0 }),
			}).then(drawCart);
		});
	}

	document.querySelectorAll('#flavor-modes [data-mode]').forEach(function (b) {
		b.addEventListener('click', function () {
			setMode(b.getAttribute('data-mode'));
		});
	});

	var otpSend = document.getElementById('flavor-otp-send');
	var otpCode = document.getElementById('flavor-otp-code');
	if (otpSend) {
		otpSend.addEventListener('click', function () {
			var mobile = (document.getElementById('flavor-mobile') || {}).value || '';
			api('auth/otp/request', {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify({ mobile: mobile }),
			})
				.then(function () {
					if (otpCode) {
						otpCode.hidden = false;
						otpCode.focus();
					}
					if (window.flavorToast) window.flavorToast('کد تایید پیامک شد.', 'info');
				})
				.catch(function (err) {
					if (window.flavorToast) window.flavorToast(err.message, 'error');
					else alert(err.message);
				});
		});
	}
	if (otpCode) {
		otpCode.addEventListener('change', function () {
			var mobile = (document.getElementById('flavor-mobile') || {}).value || '';
			api('auth/otp/verify', {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify({
					mobile: mobile,
					code: otpCode.value,
					name: (document.getElementById('flavor-name') || {}).value || '',
				}),
			})
				.then(function () {
					if (window.flavorToast) window.flavorToast('ورود با موفقیت انجام شد.', 'success');
				})
				.catch(function (err) {
					if (window.flavorToast) window.flavorToast(err.message, 'error');
					else alert(err.message);
				});
		});
	}

	var hood = document.getElementById('flavor-hood');
	var city = document.getElementById('flavor-city');
	function checkZone() {
		if (mode !== 'delivery') return;
		api('zones/check', {
			method: 'POST',
			headers: headers(true),
			body: JSON.stringify({
				branch_id: ctx.branch_id || 0,
				neighborhood: hood ? hood.value : '',
				city: city ? city.value : '',
			}),
		}).then(function (z) {
			var msg = document.getElementById('flavor-zone-msg');
			if (!msg) return;
			msg.textContent = z.ok
				? z.name + ' · ارسال ' + (z.delivery_fee_html || '') + ' · حدود ' + z.estimated_minutes + ' دقیقه'
				: z.message || 'خارج از محدوده';
		});
	}
	if (hood) hood.addEventListener('change', checkZone);
	if (city) city.addEventListener('change', checkZone);

	var couponBtn = document.getElementById('flavor-coupon-btn');
	if (couponBtn) {
		couponBtn.addEventListener('click', function () {
			var code = (document.getElementById('flavor-coupon') || {}).value || '';
			api('coupon', {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify({ code: code }),
			})
				.then(function (c) {
					drawCart(c);
					if (window.flavorToast) window.flavorToast('کد تخفیف اعمال گردید.', 'success');
				})
				.catch(function (err) {
					if (window.flavorToast) window.flavorToast(err.message, 'error');
					else alert(err.message);
				});
		});
	}

	var form = document.getElementById('flavor-checkout');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = document.getElementById('flavor-checkout-err');
			if (err) {
				err.hidden = true;
				err.textContent = '';
			}
			var pay = form.querySelector('input[name="pay"]:checked');
			var tableSel = document.getElementById('flavor-table');
			var opt = tableSel && tableSel.options[tableSel.selectedIndex];
			api('checkout', {
				method: 'POST',
				headers: headers(true),
				body: JSON.stringify({
					order_mode: mode,
					branch_id: ctx.branch_id || 0,
					name: (document.getElementById('flavor-name') || {}).value || '',
					mobile: (document.getElementById('flavor-mobile') || {}).value || '',
					payment_method: pay ? pay.value : '',
					table_id: tableSel ? tableSel.value : 0,
					table_number: opt ? opt.getAttribute('data-num') : '',
					address: {
						city: city ? city.value : '',
						neighborhood: hood ? hood.value : '',
						line: (document.getElementById('flavor-line') || {}).value || '',
					},
				}),
			})
				.then(function (res) {
					if (res.redirect) {
						window.location.href = res.redirect;
						return;
					}
					if (window.flavorToast) window.flavorToast('سفارش #' + (res.order_number || res.order_id) + ' ثبت شد', 'success');
					else alert('سفارش #' + (res.order_number || res.order_id) + ' ثبت شد');
					drawCart({ items: [], count: 0, total_html: '' });
				})
				.catch(function (ex) {
					if (err) {
						err.hidden = false;
						err.textContent = ex.message;
					}
					if (window.flavorToast) window.flavorToast(ex.message, 'error');
				});
		});
	}

	Promise.all([api('context'), api('cart'), api('me')])
		.then(function (pair) {
			ctx = pair[0] || {};
			if (ctx.order_mode) mode = ctx.order_mode;
			drawCart(pair[1]);
			var me = pair[2] || {};
			if (me.logged_in) {
				var n = document.getElementById('flavor-name');
				var m = document.getElementById('flavor-mobile');
				if (n && me.name) n.value = me.name;
				if (m && me.mobile) m.value = me.mobile;
			}
			setMode(mode);
		})
		.catch(function () {
			setMode(mode);
		});

	loadMenu();
})();
