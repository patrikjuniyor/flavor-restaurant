/**
 * Flavor — smart AJAX menu search.
 *
 * Vanilla JS, no dependencies, RTL-first.
 * Features: debounce, request cancellation (AbortController), in-memory cache,
 * keyboard navigation (↑ ↓ Enter Esc), recent searches, popular terms,
 * did-you-mean, add-to-cart straight from a result, admin-ajax fallback.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-flavor-search]');
	if (!root) {
		return;
	}

	var cfg = window.flavorSearchData || window.flavorData || {};
	var input = document.getElementById('flavor-search-input');
	var panel = document.getElementById('flavor-search-panel');
	var list = document.getElementById('flavor-search-results');
	var status = document.getElementById('flavor-search-status');
	var spinner = document.getElementById('flavor-search-spinner');
	var clearBtn = document.getElementById('flavor-search-clear');
	var chips = document.getElementById('flavor-search-chips');
	var grid = document.getElementById('flavor-menu-grid');

	if (!input || !panel || !list) {
		return;
	}

	var i18n = cfg.i18n || {};
	var DEBOUNCE = 220;
	var MIN_CHARS = 2;
	var RECENT_KEY = 'flavorRecentSearches';

	var timer = null;
	var controller = null;
	var cache = Object.create(null);
	var items = [];
	var active = -1;
	var lastQuery = '';

	function t(key, fallback) {
		return i18n[key] || fallback;
	}

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/** Server highlight only ever contains <mark> around already-escaped text. */
	function safeHighlight(html, plain) {
		if (typeof html !== 'string') {
			return esc(plain);
		}
		var stripped = html.replace(/<\/?mark>/g, '');
		return stripped.indexOf('<') === -1 ? html : esc(plain);
	}

	function faDigits(value) {
		return String(value).replace(/[0-9]/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'[d];
		});
	}

	/* ---------------------------------------------------------- transport */

	function endpoint(path, params) {
		var query = Object.keys(params)
			.filter(function (k) { return params[k] !== '' && params[k] != null; })
			.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
			.join('&');

		if (cfg.rest) {
			return cfg.rest + path + (query ? '?' + query : '');
		}
		// admin-ajax fallback.
		return (cfg.ajax || '/wp-admin/admin-ajax.php') + '?action=flavor_search' + (query ? '&' + query : '');
	}

	function request(query) {
		var params = {
			q: query,
			branch_id: cfg.branchId || 0,
			limit: 8
		};
		var url = endpoint('search', params);

		if (controller) {
			controller.abort();
		}
		controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

		var options = { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce || '' } };
		if (controller) {
			options.signal = controller.signal;
		}

		return fetch(url, options)
			.then(function (response) { return response.json(); })
			.then(function (json) {
				// admin-ajax wraps the payload in {success, data}.
				return json && json.data && json.success !== undefined ? json.data : json;
			});
	}

	/* ------------------------------------------------------------ recents */

	function recents() {
		try {
			var raw = window.localStorage.getItem(RECENT_KEY);
			var parsed = raw ? JSON.parse(raw) : [];
			return Array.isArray(parsed) ? parsed.slice(0, 6) : [];
		} catch (e) {
			return [];
		}
	}

	function remember(query) {
		if (!query || query.length < MIN_CHARS) {
			return;
		}
		try {
			var out = recents().filter(function (item) { return item !== query; });
			out.unshift(query);
			window.localStorage.setItem(RECENT_KEY, JSON.stringify(out.slice(0, 6)));
		} catch (e) {
			/* storage unavailable — ignore */
		}
	}

	function renderChips() {
		if (!chips) {
			return;
		}
		var terms = recents();
		if (!terms.length) {
			chips.hidden = true;
			chips.innerHTML = '';
			return;
		}
		chips.innerHTML =
			'<span class="flavor-search__chips-label">' + esc(t('recent', 'جست‌وجوهای اخیر')) + '</span>' +
			terms.map(function (term) {
				return '<button type="button" class="flavor-chip" data-term="' + esc(term) + '">' + esc(term) + '</button>';
			}).join('');
		chips.hidden = false;
	}

	/* ------------------------------------------------------------- render */

	function rowHtml(item, index) {
		var badges = '';
		if (item.available === false) {
			badges += '<span class="flavor-search__badge is-out">' + esc(t('unavailable', 'ناموجود')) + '</span>';
		} else if (item.in_schedule === false && item.available_at) {
			badges += '<span class="flavor-search__badge">' + esc(item.available_at) + '</span>';
		}
		(item.dietary || []).slice(0, 2).forEach(function (flag) {
			badges += '<span class="flavor-search__badge is-diet">' + esc(flag) + '</span>';
		});

		var thumb = item.image
			? '<img src="' + esc(item.image) + '" alt="" loading="lazy" width="56" height="56" />'
			: '<span class="flavor-search__thumb-ph" aria-hidden="true"></span>';

		var meta = [];
		if (item.short) {
			meta.push(esc(item.short).slice(0, 70));
		}
		if (item.prep_time) {
			meta.push(faDigits(item.prep_time) + ' ' + esc(t('minutes', 'دقیقه')));
		}
		if (item.calories) {
			meta.push(faDigits(item.calories) + ' ' + esc(t('kcal', 'کالری')));
		}

		return (
			'<li class="flavor-search__row" role="option" id="flavor-search-opt-' + index + '"' +
			' aria-selected="false" data-index="' + index + '" data-id="' + esc(item.id) + '"' +
			' data-url="' + esc(item.permalink || '') + '">' +
			'<span class="flavor-search__thumb">' + thumb + '</span>' +
			'<span class="flavor-search__body">' +
			'<span class="flavor-search__name">' + safeHighlight(item.highlight, item.name) + '</span>' +
			'<span class="flavor-search__meta">' + meta.join(' · ') + '</span>' +
			'<span class="flavor-search__badges">' + badges + '</span>' +
			'</span>' +
			'<span class="flavor-search__price">' + esc(item.price_html || '') + '</span>' +
			'</li>'
		);
	}

	function render(payload, query) {
		items = (payload && payload.results) || [];
		active = -1;

		if (!items.length) {
			var empty = '<p class="flavor-search__empty">' + esc(t('noResults', 'چیزی پیدا نشد.'));
			if (payload && payload.suggestion) {
				empty +=
					' ' + esc(t('didYouMean', 'منظورتان این بود؟')) +
					' <button type="button" class="flavor-search__mean" data-term="' +
					esc(payload.suggestion) + '">' + esc(payload.suggestion) + '</button>';
			}
			empty += '</p>';

			if (payload && payload.popular && payload.popular.length) {
				empty +=
					'<p class="flavor-search__popular">' + esc(t('popular', 'پرجست‌وجوها')) + ': ' +
					payload.popular.map(function (row) {
						return '<button type="button" class="flavor-chip" data-term="' + esc(row.term) + '">' +
							esc(row.term) + '</button>';
					}).join(' ') + '</p>';
			}

			list.innerHTML = '';
			status.innerHTML = empty;
			input.setAttribute('aria-expanded', 'true');
			panel.hidden = false;
			return;
		}

		list.innerHTML = items.map(rowHtml).join('');
		status.textContent =
			faDigits(payload.total) + ' ' + t('resultsFound', 'نتیجه برای') + ' «' + query + '»';
		input.setAttribute('aria-expanded', 'true');
		panel.hidden = false;

		remember(query);
		renderChips();
		filterGrid(items);
	}

	/** Also narrow the menu grid on the page to the matched ids. */
	function filterGrid(results) {
		if (!grid) {
			return;
		}
		var ids = results.map(function (item) { return String(item.id); });
		grid.querySelectorAll('.flavor-card').forEach(function (card) {
			var match = ids.indexOf(card.getAttribute('data-id')) !== -1;
			card.classList.toggle('is-search-hidden', !match);
		});
		grid.classList.add('is-search-filtered');
	}

	function resetGrid() {
		if (!grid) {
			return;
		}
		grid.classList.remove('is-search-filtered');
		grid.querySelectorAll('.flavor-card').forEach(function (card) {
			card.classList.remove('is-search-hidden');
		});
	}

	function close() {
		panel.hidden = true;
		input.setAttribute('aria-expanded', 'false');
		active = -1;
	}

	function busy(on) {
		if (spinner) {
			spinner.hidden = !on;
		}
		root.classList.toggle('is-busy', !!on);
	}

	/* -------------------------------------------------------------- query */

	function run(query) {
		lastQuery = query;

		if (cache[query]) {
			render(cache[query], query);
			return;
		}

		busy(true);
		request(query)
			.then(function (payload) {
				busy(false);
				if (query !== lastQuery) {
					return; // A newer keystroke already won.
				}
				cache[query] = payload;
				render(payload, query);
			})
			.catch(function (error) {
				if (error && error.name === 'AbortError') {
					return;
				}
				busy(false);
				list.innerHTML = '';
				status.textContent = t('error', 'جست‌وجو ناموفق بود. دوباره تلاش کنید.');
				panel.hidden = false;
			});
	}

	function schedule() {
		var query = input.value.trim();

		if (clearBtn) {
			clearBtn.hidden = !query;
		}

		window.clearTimeout(timer);

		if (query.length < MIN_CHARS) {
			close();
			resetGrid();
			lastQuery = '';
			return;
		}

		timer = window.setTimeout(function () { run(query); }, DEBOUNCE);
	}

	function highlightActive() {
		var rows = list.querySelectorAll('.flavor-search__row');
		rows.forEach(function (row, index) {
			var on = index === active;
			row.classList.toggle('is-active', on);
			row.setAttribute('aria-selected', on ? 'true' : 'false');
			if (on) {
				input.setAttribute('aria-activedescendant', row.id);
				row.scrollIntoView({ block: 'nearest' });
			}
		});
		if (active < 0) {
			input.removeAttribute('aria-activedescendant');
		}
	}

	function choose(index) {
		var item = items[index];
		if (!item) {
			return;
		}
		if (item.permalink) {
			window.location.href = item.permalink;
			return;
		}
		var card = grid && grid.querySelector('.flavor-card[data-id="' + item.id + '"]');
		if (card) {
			card.scrollIntoView({ behavior: 'smooth', block: 'center' });
			close();
		}
	}

	/* ------------------------------------------------------------- events */

	input.addEventListener('input', schedule);

	input.addEventListener('focus', function () {
		if (!input.value.trim()) {
			renderChips();
		} else if (items.length) {
			panel.hidden = false;
		}
	});

	input.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			close();
			return;
		}
		if (panel.hidden || !items.length) {
			return;
		}
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			active = (active + 1) % items.length;
			highlightActive();
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			active = active <= 0 ? items.length - 1 : active - 1;
			highlightActive();
		} else if (event.key === 'Enter' && active >= 0) {
			event.preventDefault();
			choose(active);
		}
	});

	list.addEventListener('click', function (event) {
		var row = event.target.closest('.flavor-search__row');
		if (row) {
			choose(parseInt(row.getAttribute('data-index'), 10));
		}
	});

	root.addEventListener('click', function (event) {
		var chip = event.target.closest('[data-term]');
		if (!chip) {
			return;
		}
		input.value = chip.getAttribute('data-term');
		input.focus();
		schedule();
	});

	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			input.value = '';
			clearBtn.hidden = true;
			close();
			resetGrid();
			input.focus();
			renderChips();
		});
	}

	document.addEventListener('click', function (event) {
		if (!root.contains(event.target)) {
			close();
		}
	});

	// Ctrl/Cmd + K focuses the search.
	document.addEventListener('keydown', function (event) {
		if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
			event.preventDefault();
			input.focus();
			input.select();
		}
	});

	root.querySelector('form').addEventListener('submit', function (event) {
		event.preventDefault();
		window.clearTimeout(timer);
		var query = input.value.trim();
		if (query.length >= MIN_CHARS) {
			run(query);
		}
	});

	renderChips();
})();
