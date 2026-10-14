/* Service-worker registration for the Flavor PWA (prompt 63).
 * Secure contexts only; the URL and scope arrive via window.flavorPwa. */
( function () {
	if ( ! ( 'serviceWorker' in navigator ) ) {
		return;
	}
	if ( ! window.isSecureContext ) {
		return;
	}
	var cfg = window.flavorPwa || {};
	if ( ! cfg.sw ) {
		return;
	}
	window.addEventListener( 'load', function () {
		navigator.serviceWorker.register( cfg.sw, { scope: cfg.scope || '/' } ).catch( function () {
			/* Offline shell is progressive enhancement; never break the page. */
		} );
	} );
}() );
