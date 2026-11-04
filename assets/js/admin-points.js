/**
 * Poczta Polska map in the admin: changing the pickup point of an order and
 * choosing the parcel locker for posting in the settings.
 */
( function () {
	'use strict';

	const config = window.ppwcAdminPoints || {};

	document.addEventListener( 'click', function ( event ) {
		const orderButton = event.target.closest( '.ppwc-admin-point__change' );
		const lockerButton = event.target.closest( '.ppwc-settings-locker' );

		if ( orderButton ) {
			event.preventDefault();
			changeOrderPoint( orderButton.closest( '.ppwc-admin-point' ) );
		} else if ( lockerButton ) {
			event.preventDefault();
			chooseLocker( lockerButton );
		}
	} );

	function changeOrderPoint( box ) {
		const text = box.querySelector( '.ppwc-admin-point__text' );

		window.ppwcPickupMap.open( {
			pointType: box.dataset.pointType,
			cod: false,
			codAmount: 0,
			address: '',
			onSelect: function ( pni ) {
				text.textContent = config.i18n.saving;

				const body = new URLSearchParams( {
					action: config.action,
					nonce: config.nonce,
					order_id: box.dataset.orderId,
					pni: String( pni ),
				} );

				fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( response ) {
						text.textContent = response && response.success
							? response.data.text + ' (PNI ' + response.data.pni + ')'
							: ( response && response.data && response.data.message ) || config.i18n.error;
					} )
					.catch( function () {
						text.textContent = config.i18n.error;
					} );
			},
		} );
	}

	function chooseLocker( button ) {
		const input = document.getElementById( button.dataset.target );

		window.ppwcPickupMap.open( {
			pointType: 'AUTOMAT',
			cod: false,
			codAmount: 0,
			address: button.dataset.address || '',
			onSelect: function ( pni ) {
				input.value = String( pni );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			},
		} );
	}
}() );
