/** Small, dependency-free enhancements for the bespoke demo family only. */
(function () {
	'use strict';
	// The copy in this file comes from Bespoke_Demos::strings(), served on flavorData.i18n; nothing
	// here carries Persian text of its own (prompt 21).
	/* flavor-i18n-map: flavorData.i18n.bespoke */
	var STR=((window.flavorData||{}).i18n||{}).bespoke||{};
	function t(key){return STR[key]||key;}
	function fmt(key,v){return String(t(key)).replace('%s',v);}
	function fmt3(key,a,b,c){return String(t(key)).replace('%1$s',a).replace('%2$s',b).replace('%3$s',c);}
	if (!document.body.classList.contains('flavor-bespoke')) return;

	// No hidden content or dead filtering controls when JavaScript is disabled.
	document.querySelectorAll('[data-fd-filters]').forEach(function (filters) {
		var section = filters.closest('.fd-menu');
		var cards = Array.from(section.querySelectorAll('[data-fd-categories]'));
		var status = section.querySelector('[data-fd-filter-status]');
		filters.addEventListener('click', function (event) {
			var button = event.target.closest('[data-fd-filter]');
			if (!button) return;
			var selected = button.dataset.fdFilter;
			var count = 0;
			filters.querySelectorAll('button').forEach(function (item) {
				item.setAttribute('aria-pressed', String(item === button));
			});
			cards.forEach(function (card) {
				var visible = selected === 'all' || card.dataset.fdCategories.split(' ').includes(selected);
				card.hidden = !visible;
				if (visible) count++;
			});
			if (status) status.textContent = fmt('selection_shown', String(count).replace(/\d/g, function (n) { return '۰۱۲۳۴۵۶۷۸۹'[n]; }));
		});
		filters.hidden = false;
	});

	// Public eligibility only: never creates a cart, order, booking or SMS.
	var coverage = document.querySelector('[data-fd-coverage]');
	var cfg = window.flavorData || {};
	if (coverage && cfg.hasCore) {
		coverage.hidden = false;
		coverage.addEventListener('submit', function (event) {
			event.preventDefault();
			var button = coverage.querySelector('[type="submit"]');
			var result = coverage.querySelector('[data-fd-coverage-result]');
			button.disabled = true; result.textContent = t('zone_checking'); result.dataset.available = 'pending';
			fetch(cfg.rest + 'zones/check', {
				method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
				body: JSON.stringify({ branch_id: Number(cfg.branchId) || 0, neighborhood: coverage.elements.neighborhood.value, city: coverage.elements.city.value })
			}).then(function (response) {
				return response.json().then(function (json) {
					if (!response.ok || json.success === false) throw new Error(json.message || t('zone_failed'));
					return json.success === true ? json.data : json;
				});
			}).then(function (zone) {
				result.dataset.available = zone.ok ? 'yes' : 'no';
				result.textContent = zone.ok ? fmt3('zone_summary', zone.name, String(zone.delivery_fee_html || '').replace(/<[^>]*>/g, ''), zone.estimated_minutes) : (zone.message || t('zone_out'));
			}).catch(function (error) { result.dataset.available = 'error'; result.textContent = error.message; })
			.finally(function () { button.disabled = false; });
		});
	}

})();
