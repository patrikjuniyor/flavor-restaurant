/**
 * Mobile-first menu loader. Talks to flavor/v1 when Flavor Core is active.
 */
(function () {
	'use strict';

	var cfg = window.flavorData || {};
	var grid = document.getElementById('flavor-menu-grid');
	var status = document.getElementById('flavor-menu-status');
	var cats = document.getElementById('flavor-cats');
	if (!grid) {
		return;
	}

	if (!cfg.hasCore) {
		if (status) {
			status.textContent = (cfg.i18n && cfg.i18n.offline) || '';
		}
		return;
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/"/g, '&quot;');
	}

	function card(item) {
		var img = item.image
			? '<img src="' + esc(item.image) + '" alt="" loading="lazy" width="600" height="400" />'
			: '<div class="flavor-card__ph"></div>';
		return (
			'<article class="flavor-card" data-id="' +
			esc(item.id) +
			'">' +
			img +
			'<div class="flavor-card__body">' +
			'<h2 class="flavor-card__name">' +
			esc(item.name) +
			'</h2>' +
			'<p class="flavor-card__meta">' +
			esc(item.short || '') +
			'</p>' +
			'<strong>' +
			esc(item.price_html || '') +
			'</strong>' +
			'<button type="button" class="flavor-btn flavor-btn--primary" data-add="' +
			esc(item.id) +
			'">' +
			esc((cfg.i18n && cfg.i18n.add) || '') +
			'</button>' +
			'</div></article>'
		);
	}

	function load() {
		if (status) {
			status.textContent = (cfg.i18n && cfg.i18n.loading) || '';
		}
		fetch(cfg.rest + 'menu', { credentials: 'same-origin' })
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				var items = (data && data.items) || [];
				if (!items.length) {
					if (status) {
						status.textContent = (cfg.i18n && cfg.i18n.empty) || '';
					}
					return;
				}
				if (status) {
					status.textContent = '';
				}
				grid.innerHTML = items.map(card).join('');
				if (cats && !cats.childElementCount) {
					var all = document.createElement('button');
					all.type = 'button';
					all.className = 'is-active';
					all.textContent = 'همه';
					cats.appendChild(all);
				}
			})
			.catch(function () {
				if (status) {
					status.textContent = (cfg.i18n && cfg.i18n.empty) || '';
				}
			});
	}

	load();
})();
