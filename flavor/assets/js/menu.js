/**
 * Menu + modifiers + cart drawer + checkout. Vanilla JS, RTL-first.
 */
(function () {
	'use strict';

	var cfg = window.flavorData || {};
	var ui = window.FlavorUI;
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
	var mode = cfg.defaultMode || 'takeaway';
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
		try {
			var token = sessionStorage.getItem('flavorCartToken');
			if (token) h['X-Cart-Token'] = token;
		} catch (ignore) { /* Cookies still support guests when storage is blocked. */ }
		return h;
	}

	function api(path, opt) {
		var options = Object.assign({ credentials: 'same-origin' }, opt || {});
		options.headers = Object.assign({}, headers(false), options.headers || {});
		return fetch(cfg.rest + path, options).then(function (r) {
			return r.json().then(function (j) {
				if (!r.ok || (j && j.success === false)) {
					var error = j && j.errors && j.errors[0];
					var msg = (error && error.message) || (j && (j.message || (j.data && j.data.message))) || r.statusText;
					throw new Error(msg);
				}
				// Core 1.4 uses an envelope; retained v1 store routes are raw.
				var data = j && j.success === true ? j.data : j;
				if (data && data.cart_token) {
					try { sessionStorage.setItem('flavorCartToken', data.cart_token); } catch (ignore) {}
				}
				return data;
			});
		});
	}

	function displayAmount(amount) {
		var money = cfg.currency || { storage: 'irr', display: 'irt' };
		var value = Number(amount) || 0;
		if (money.storage === 'irr' && money.display === 'irt') return Math.floor(value / 10);
		if (money.storage === 'irt' && money.display === 'irr') return value * 10;
		return value;
	}

	function normalizeDish(item, canonical) {
		var normalized = Object.assign({}, item);
		normalized.id = Number(item.id);
		normalized.short = item.short_desc || item.short || '';
		if (canonical) { normalized.storagePrice = Number(item.price) || 0; normalized.price = displayAmount(item.price); }
		if (Array.isArray(item.modifier_groups)) {
			normalized.groups = item.modifier_groups.map(function (group) { return Object.assign({}, group, { options: (group.options || []).map(function (option) { return Object.assign({}, option, { storagePrice: Number(option.price) || 0, price: displayAmount(option.price) }); }) }); });
			normalized.modifiers = [];
			item.modifier_groups.forEach(function (group) {
				(group.options || []).forEach(function (option) {
					normalized.modifiers.push(Object.assign({}, option, { type: group.type, price: displayAmount(option.price) }));
				});
			});
		}
		return normalized;
	}

	function showError(error) {
		if (window.flavorToast) window.flavorToast(error.message, 'error');
	}

	function fa(value) { return ui ? ui.digits(value) : String(value); }
	function safeImage(value) { try { var url = new URL(value, document.baseURI); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch (ignore) { return ''; } }
	function plain(value) { var el = document.createElement('textarea'); el.innerHTML = String(value || '').replace(/<[^>]*>/g, ''); return el.value; }
	function smallIcon(name) { var path = name === 'clock' ? '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>' : '<path d="M12 5v14M5 12h14"/>'; return '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">' + path + '</svg>'; }
	var dietLabels = { vegetarian: 'گیاهی', vegan: 'وگان', gluten_free: 'بدون گلوتن', spicy: 'تند', dairy_free: 'بدون لبنیات' };
	function card(item) {
		var disabled = item.available === false;
		var image = safeImage(item.image);
		var cat = item.categories && item.categories[0];
		var name = esc(item.name);
		var media = image ? '<img src="' + esc(image) + '" alt="' + name + '" width="600" height="400" loading="lazy" decoding="async" />' : '<span class="flavor-food-card__placeholder">بدون تصویر</span>';
		var metadata = (item.prep_time ? '<span class="flavor-food-card__meta-item">' + smallIcon('clock') + fa(item.prep_time) + ' دقیقه آماده‌سازی</span>' : '');
		(item.dietary || []).slice(0, 1).forEach(function (flag) { if (dietLabels[flag]) metadata += '<span class="flavor-food-card__meta-item">' + esc(dietLabels[flag]) + '</span>'; });
		return '<article class="flavor-card flavor-food-card' + (disabled ? ' is-unavailable' : '') + '" id="item-' + esc(item.id) + '" data-id="' + esc(item.id) + '" data-cats="' + esc((item.categories || []).map(function (c) { return c.id; }).join(',')) + '">' +
			'<div class="flavor-food-card__media"><button type="button" data-detail="' + esc(item.id) + '" aria-label="جزئیات ' + name + '">' + media + '</button>' + (cat ? '<span class="flavor-food-card__cat-badge">' + esc(cat.name) + '</span>' : '') + '</div>' +
			'<div class="flavor-food-card__body"><h2 class="flavor-food-card__title"><button type="button" data-detail="' + esc(item.id) + '">' + name + '</button>' + (disabled ? '<span class="flavor-food-card__unavailable">فعلاً ناموجود</span>' : '') + '</h2><p class="flavor-food-card__desc">' + esc(item.short) + '</p>' +
			'<div class="flavor-food-card__meta">' + metadata + '</div><div class="flavor-food-card__footer"><strong class="flavor-food-card__price">' + esc(plain(item.price_html)) + '</strong><button type="button" class="flavor-btn flavor-btn--primary flavor-btn--sm" data-add="' + esc(item.id) + '" aria-label="انتخاب ' + name + '"' + (disabled ? ' disabled' : '') + '>' + smallIcon('plus') + 'انتخاب غذا</button></div></div></article>';
	}

	function renderCats(cats) {
		if (!catsEl) return;
		var html = '<button type="button" class="is-active" data-cat="0" aria-pressed="true" aria-controls="flavor-menu-grid">همهٔ منو <span class="flavor-cats__count">' + fa(catalog.length) + '</span></button>';
		(cats || []).forEach(function (c) {
			var count = catalog.filter(function (dish) { return (dish.categories || []).some(function (category) { return Number(category.id) === Number(c.id); }); }).length;
			if (count) html += '<button type="button" data-cat="' + esc(c.id) + '" aria-pressed="false" aria-controls="flavor-menu-grid">' + esc(c.name) + '<span class="flavor-cats__count">' + fa(count) + '</span></button>';
		});
		catsEl.innerHTML = html;
		var nav = document.getElementById('flavor-cats-nav'); if (nav) nav.hidden = false;
	}
	function filterCat(id) {
		var count = 0;
		grid.querySelectorAll('.flavor-card').forEach(function (el) { var visible = !id || id === '0' || (el.getAttribute('data-cats') || '').split(',').includes(String(id)); el.hidden = !visible; if (visible && !el.classList.contains('is-search-hidden')) count++; });
		if (catsEl) catsEl.querySelectorAll('button').forEach(function (button) { var selected = String(button.dataset.cat) === String(id || 0); button.classList.toggle('is-active', selected); button.setAttribute('aria-pressed', String(selected)); });
		if (statusEl) statusEl.textContent = fa(count) + ' انتخاب نمایش داده می‌شود.';
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
		var canonical = current.storagePrice != null;
		var extra = 0;
		if (sheetBody) sheetBody.querySelectorAll('input:checked').forEach(function (input) { extra += Number(input.getAttribute(canonical ? 'data-storage-price' : 'data-price')) || 0; });
		var amount = ((canonical ? current.storagePrice : current.price) + extra) * qty;
		return canonical ? displayAmount(amount) : amount;
	}
	function paintSheetPrice() {
		if (!sheetAdd || !current) return;
		var n = livePrice();
		var label = Number(n).toLocaleString('fa-IR') + ' ' + ((cfg.currency && cfg.currency.label) || 'تومان');
		var price = document.getElementById('flavor-sheet-price'); if (price) price.textContent = label;
		var quantity = document.getElementById('flavor-qty'); if (quantity) quantity.textContent = fa(qty);
		if (sheet) sheet.querySelectorAll('[data-q]').forEach(function (button) { button.disabled = (button.dataset.q === '-1' && qty <= 1) || (button.dataset.q === '1' && qty >= 20); });
		sheetAdd.disabled = current.available === false;
		sheetAdd.textContent = current.available === false ? 'این انتخاب فعلاً ناموجود است' : 'افزودن به سبد · ' + fa(qty) + ' عدد';
	}
	function openSheet(item, trigger) {
		if (!sheet || !sheetBody || !sheetTitle) { showError(new Error('پنل جزئیات در دسترس نیست. صفحه را دوباره باز کنید.')); return; }
		current = item; qty = 1;
		sheetTitle.textContent = item.name;
		var visual = document.getElementById('flavor-sheet-visual');
		var image = safeImage(item.image);
		if (visual) visual.innerHTML = (image ? '<img src="' + esc(image) + '" alt="' + esc(item.name) + '" width="700" height="700" />' : '') + '<p>' + smallIcon('clock') + 'زمان آماده‌سازی برآورد است، نه زمان تضمین‌شدهٔ رسیدن سفارش.</p>';
		var html = '<p class="flavor-sheet__description">' + esc(plain(item.description || item.short)) + '</p><div class="flavor-sheet__metadata">' + (item.prep_time ? '<span class="flavor-ui-tag">' + smallIcon('clock') + fa(item.prep_time) + ' دقیقه آماده‌سازی</span>' : '') + (item.calories ? '<span class="flavor-ui-tag">' + fa(item.calories) + ' کالری</span>' : '');
		(item.dietary || []).forEach(function (flag) { if (dietLabels[flag]) html += '<span class="flavor-ui-tag">' + esc(dietLabels[flag]) + '</span>'; });
		html += '</div>';
		var labels = { size: 'اندازه و پرس', topping: 'افزودنی‌ها', cook: 'درجهٔ پخت', removal: 'حذف مخلفات' };
		var groups = item.groups;
		if (!groups) { var g = groupModifiers(item.modifiers); groups = Object.keys(g).map(function (type) { return { type: type, multi: type === 'topping' || type === 'removal', required: type === 'size', options: g[type] }; }); }
		groups.forEach(function (group) {
			var list = group.options || []; if (!list.length) return;
			var firstDefault = list.findIndex(function (option) { return option.is_default; }); if (firstDefault < 0 && !group.multi) firstDefault = 0;
			html += '<fieldset class="flavor-sheet__fieldset"><legend>' + esc(group.title || labels[group.type] || 'انتخاب‌ها') + (group.required ? '<span class="flavor-ui-required"> (انتخاب ضروری)</span>' : '') + '</legend><div class="flavor-sheet__options">';
			list.forEach(function (option, index) {
				var checked = group.multi ? !!option.is_default : index === firstDefault;
				html += '<label class="flavor-sheet__option"><input type="' + (group.multi ? 'checkbox' : 'radio') + '" name="mod-' + esc(group.type) + '" value="' + esc(option.id) + '" data-price="' + esc(option.price || 0) + '" data-storage-price="' + esc(option.storagePrice == null ? option.price || 0 : option.storagePrice) + '"' + (checked ? ' checked' : '') + ' /><span>' + esc(option.name) + '</span><span class="flavor-sheet__option-price">' + (option.price ? '+' + Number(option.price).toLocaleString('fa-IR') + ' ' + esc((cfg.currency && cfg.currency.label) || 'تومان') : 'بدون هزینهٔ اضافه') + '</span></label>';
			});
			html += '</div></fieldset>';
		});
		html += '<div class="flavor-ui-field"><label for="flavor-instr">یادداشت برای آشپزخانه <span class="flavor-ui-required">(اختیاری)</span></label><textarea id="flavor-instr" maxlength="200" rows="2" placeholder="مثلاً سس جداگانه یا کم‌نمک" aria-describedby="flavor-instr-help"></textarea><small id="flavor-instr-help">حساسیت غذایی دارید؟ پیش از ثبت سفارش دربارهٔ امکان آماده‌سازی و آلودگی متقاطع هماهنگ کنید.</small></div>';
		sheetBody.innerHTML = html;
		paintSheetPrice();
		if (ui) ui.openDialog(sheet, trigger); else { sheet.hidden = false; document.body.style.overflow = 'hidden'; }
	}
	function closeSheet() { if (ui) ui.closeDialog(sheet); else { if (sheet) sheet.hidden = true; document.body.style.overflow = ''; } current = null; }

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
		var allowed = cfg.orderModes || ['dine_in', 'takeaway', 'delivery'];
		mode = allowed.includes(next) ? next : (allowed.includes(cfg.defaultMode) ? cfg.defaultMode : (allowed.includes('takeaway') ? 'takeaway' : allowed[0]));
		document.querySelectorAll('#flavor-modes [data-mode]').forEach(function (b) {
			b.hidden = !allowed.includes(b.getAttribute('data-mode'));
			b.classList.toggle('is-active', b.getAttribute('data-mode') === mode);
			b.setAttribute('aria-pressed', String(b.getAttribute('data-mode') === mode));
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

	function dishDetail(item) {
		if (item.detailLoaded) return Promise.resolve(item);
		return api('dishes/' + item.id + '?branch_id=' + encodeURIComponent(cfg.branchId || 0)).then(function (detail) { var dish = normalizeDish(detail, true); dish.detailLoaded = true; return dish; });
	}

	var resolvedFragment = '';
	function openFragment() {
		// Opening a homepage product link must not silently add it to the cart.
		var match = /^#item-(\d+)$/.exec(window.location.hash);
		if (!match || resolvedFragment === window.location.hash) return;
		resolvedFragment = window.location.hash;
		var id = Number(match[1]);
		var item = catalog.find(function (dish) { return dish.id === id; });
		var detail = item ? dishDetail(item) : api('dishes/' + id).then(function (dish) { return normalizeDish(dish, true); });
		detail.then(function (dish) {
			if (dish.available === false) throw new Error('این انتخاب در حال حاضر موجود نیست.');
			var target = document.getElementById('item-' + id);
			if (target) { target.scrollIntoView({ block: 'center' }); var button = target.querySelector('[data-add]'); if (button) button.focus({ preventScroll: true }); }
			openSheet(dish, target && target.querySelector('[data-add]'));
		}).catch(showError);
	}
	window.addEventListener('hashchange', openFragment);

	function loadMenu() {
		if (statusEl) statusEl.textContent = (cfg.i18n && cfg.i18n.loading) || '';
		grid.setAttribute('aria-busy', 'true');
		var retry = document.getElementById('flavor-menu-retry'); if (retry) retry.hidden = true;
		api('menu?per_page=100&branch_id=' + encodeURIComponent(cfg.branchId || 0)).then(function (data) {
			var canonical = Array.isArray(data);
			catalog = (canonical ? data : data.items || []).map(function (dish) { return normalizeDish(dish, canonical); });
			if (statusEl) statusEl.textContent = catalog.length ? '' : (cfg.i18n && cfg.i18n.empty) || '';
			grid.innerHTML = catalog.map(card).join('');
			grid.removeAttribute('aria-busy');
			if (statusEl) statusEl.textContent = fa(catalog.length) + ' انتخاب در منو';
			document.dispatchEvent(new CustomEvent('flavor:menu-ready'));
			var cats = canonical ? api('categories').then(function (rows) { return rows.filter(function (cat) { return cat.count > 0; }); }) : Promise.resolve(data.categories || []);
			cats.then(function (rows) {
				renderCats(rows);
				var requested = new URL(window.location.href).searchParams.get('cat');
				if (requested && Array.from(catsEl.querySelectorAll('button')).some(function (button) { return button.dataset.cat === requested; })) filterCat(requested);
			}).catch(showError);
			openFragment();
		}).catch(function (error) {
			grid.removeAttribute('aria-busy'); if (retry) retry.hidden = false;
			if (statusEl) statusEl.textContent = 'بارگذاری موجودی زنده ناموفق بود؛ جزئیات منتشرشده را ببینید یا دوباره تلاش کنید.';
			showError(error);
		});
	}

	grid.addEventListener('click', function (e) {
		var button = e.target.closest('[data-add], [data-detail]');
		if (!button || button.disabled) return;
		var id = Number(button.getAttribute('data-add') || button.getAttribute('data-detail'));
		var item = catalog.find(function (dish) { return dish.id === id; });
		if (!item) return;
		button.disabled = true;
		dishDetail(item).then(function (dish) {
			openSheet(dish, button);
		}).catch(showError).finally(function () { button.disabled = false; });
	});

	if (sheet) {
		sheet.addEventListener('click', function (e) {
			if (e.target.closest('[data-close="sheet"]')) closeSheet();
			var q = e.target.getAttribute('data-q');
			if (q) {
				qty = Math.max(1, Math.min(20, qty + parseInt(q, 10)));
				var n = document.getElementById('flavor-qty');
				if (n) n.textContent = fa(qty);
				paintSheetPrice();
			}
		});
		sheet.addEventListener('change', paintSheetPrice);
	}
	if (sheetAdd) {
		sheetAdd.addEventListener('click', function () {
			if (!current) return;
			var instr = document.getElementById('flavor-instr');
			sheetAdd.disabled = true;
			addItem(current, selectedIds(), instr ? instr.value : '', qty).then(closeSheet).catch(showError).finally(function () { sheetAdd.disabled = false; });
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
		}).catch(showError);
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
			if (cfg.branchId) ctx.branch_id = Number(cfg.branchId);
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

	var reload = document.getElementById('flavor-menu-reload'); if (reload) reload.addEventListener('click', loadMenu);
	loadMenu();
})();
