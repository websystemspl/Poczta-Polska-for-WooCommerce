/**
 * Poczta Polska pickup point map.
 *
 * Opens the official Poczta Polska map widget (PPWidgetApp). When the widget is
 * not available, falls back to a search of the plugin's copy of the points.
 *
 * window.ppwcPickupMap.open( {
 *     pointType: 'PUNKT' | 'AUTOMAT',
 *     cod: boolean,          // cash on delivery chosen
 *     codAmount: number,     // order total in PLN
 *     address: string,       // customer address, centres the map
 *     onSelect: function ( pni ) {}
 * } )
 */
( function () {
	'use strict';

	const config = window.ppwcPickupMapConfig || {};
	const i18n = config.i18n || {};

	function openWidget( options ) {
		const params = {
			callback: function ( point ) {
				const pni = parseInt( point && point.pni, 10 );

				if ( pni > 0 ) {
					options.onSelect( pni );
				}
			},
			type: ( config.widgetTypes && config.widgetTypes[ options.pointType ] ) || [],
		};

		if ( options.address ) {
			params.address = options.address;
		}

		// The widget filters points by COD limit, the server validates the chosen point again.
		if ( options.cod ) {
			params.payOnPickup = true;

			if ( options.codAmount > 0 ) {
				params.payOnPickupMax = Math.ceil( options.codAmount );
			}
		}

		window.PPWidgetApp.toggleMap( params );
	}

	function openFallback( options ) {
		const dialog = document.createElement( 'dialog' );
		const form = document.createElement( 'form' );
		const label = document.createElement( 'label' );
		const input = document.createElement( 'input' );
		const search = document.createElement( 'button' );
		const close = document.createElement( 'button' );
		const status = document.createElement( 'p' );
		const list = document.createElement( 'ul' );

		dialog.className = 'ppwc-fallback';
		dialog.setAttribute( 'aria-label', i18n.fallbackTitle || '' );
		label.textContent = i18n.fallbackLabel || '';
		input.type = 'search';
		input.required = true;
		input.minLength = 2;
		input.value = options.postcode || '';
		label.appendChild( input );
		search.type = 'submit';
		search.className = 'button';
		search.textContent = i18n.search || '';
		close.type = 'button';
		close.className = 'button ppwc-fallback__close';
		close.textContent = i18n.close || '';
		status.setAttribute( 'role', 'status' );
		status.textContent = i18n.fallbackIntro || '';
		list.className = 'ppwc-fallback__results';

		form.append( label, search, close );
		dialog.append( form, status, list );
		document.body.appendChild( dialog );

		close.addEventListener( 'click', function () {
			dialog.close();
		} );
		dialog.addEventListener( 'close', function () {
			dialog.remove();
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			status.textContent = i18n.searching || '';
			list.replaceChildren();

			const url = new URL( config.restUrl );
			url.searchParams.set( 'search', input.value );
			url.searchParams.set( 'type', options.pointType );
			url.searchParams.set( 'cod', options.cod ? String( options.codAmount || 0 ) : '0' );

			fetch( url.toString(), { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.ok ? response.json() : [];
				} )
				.then( function ( points ) {
					status.textContent = points.length ? '' : i18n.noResults || '';

					points.forEach( function ( point ) {
						const item = document.createElement( 'li' );
						const button = document.createElement( 'button' );
						const name = document.createElement( 'strong' );

						button.type = 'button';
						button.dataset.pni = String( point.pni );
						name.textContent = point.name;
						button.append( name, document.createElement( 'br' ), document.createTextNode( point.address ) );
						button.addEventListener( 'click', function () {
							options.onSelect( point.pni );
							dialog.close();
						} );
						item.appendChild( button );
						list.appendChild( item );
					} );
				} )
				.catch( function () {
					status.textContent = i18n.error || '';
				} );
		} );

		dialog.showModal();
		input.focus();
	}

	window.ppwcPickupMap = {
		open: function ( options ) {
			if ( window.PPWidgetApp && 'function' === typeof window.PPWidgetApp.toggleMap ) {
				openWidget( options );
			} else {
				openFallback( options );
			}
		},
	};
}() );
