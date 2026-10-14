/* Flavor offline shell (prompt 63) — minimal offline behaviour: keep the
 * menu page readable without a network. Served from PHP (class-pwa.php)
 * with the two placeholders replaced, so the menu URL and cache version
 * follow the live site; Service-Worker-Allowed: / gives it root scope.
 *
 * Navigations are network-first; a successful menu navigation refreshes
 * the cache, and any navigation that fails offline falls back to the
 * cached menu shell. Nothing else is intercepted.
 */
const CACHE = 'flavor-offline-v__CACHE_VERSION__';
const MENU_URL = '__MENU_URL__';

self.addEventListener( 'install', ( event ) => {
	event.waitUntil(
		caches.open( CACHE )
			.then( ( cache ) => cache.addAll( [ MENU_URL ] ) )
			.then( () => self.skipWaiting() )
	);
} );

self.addEventListener( 'activate', ( event ) => {
	event.waitUntil(
		caches.keys()
			.then( ( keys ) => Promise.all( keys.filter( ( key ) => key !== CACHE ).map( ( key ) => caches.delete( key ) ) ) )
			.then( () => self.clients.claim() )
	);
} );

self.addEventListener( 'fetch', ( event ) => {
	const request = event.request;
	if ( request.method !== 'GET' ) {
		return;
	}
	if ( new URL( request.url ).origin !== self.location.origin ) {
		return;
	}
	if ( request.mode !== 'navigate' ) {
		return;
	}
	event.respondWith(
		fetch( request )
			.then( ( response ) => {
				if ( response.ok && new URL( request.url ).pathname === new URL( MENU_URL ).pathname ) {
					const copy = response.clone();
					caches.open( CACHE ).then( ( cache ) => cache.put( MENU_URL, copy ) );
				}
				return response;
			} )
			.catch( () => caches.match( MENU_URL ).then( ( hit ) => hit || Response.error() ) )
	);
} );
