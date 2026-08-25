/**
 * Keyboard helpers for the phone-order desk (form is server-rendered).
 */
(function () {
	var form = document.getElementById('flavor-phone-form');
	if (!form) return;
	form.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target && e.target.tagName === 'INPUT' && e.target.type === 'number') {
			e.preventDefault();
		}
	});
})();
