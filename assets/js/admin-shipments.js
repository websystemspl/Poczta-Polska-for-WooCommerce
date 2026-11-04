/**
 * Poczta Polska shipment box on the order screen.
 */
( function () {
	'use strict';

	const config = window.ppwcShipments || {};

	// Point type (PUNKT, AUTOMAT) required by the service chosen in the box, '' for home delivery.
	function pointTypeOf( box ) {
		const service = box.querySelector( '[name="ppwc_service"]' );
		let types = {};

		try {
			types = JSON.parse( box.dataset.pointTypes || '{}' );
		} catch ( error ) {
			types = {};
		}

		return ( service && types[ service.value ] ) || '';
	}

	function showPoint( box, pni, text, type ) {
		const input = box.querySelector( '[name="ppwc_pickup_pni"]' );
		const label = box.querySelector( '.ppwc-shipments__point-text' );

		input.value = pni ? String( pni ) : '';
		input.dataset.pointType = type || '';
		label.textContent = text || label.dataset.empty;
	}

	document.addEventListener( 'change', function ( event ) {
		const box = event.target.closest( '.ppwc-shipments' );

		if ( ! box || 'ppwc_service' !== event.target.name ) {
			return;
		}

		const type = pointTypeOf( box );
		const input = box.querySelector( '[name="ppwc_pickup_pni"]' );

		box.querySelector( '.ppwc-shipments__point' ).hidden = ! type;

		// A pickup point does not fit a parcel locker service and the other way round.
		if ( type && input.dataset.pointType && input.dataset.pointType !== type ) {
			showPoint( box, 0, '', '' );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		const choose = event.target.closest( '.ppwc-shipments__choose-point' );

		if ( ! choose || ! window.ppwcPickupMap ) {
			return;
		}

		event.preventDefault();

		const box = choose.closest( '.ppwc-shipments' );
		const type = pointTypeOf( box );
		const codAmount = parseFloat( box.dataset.codAmount || '0' );

		window.ppwcPickupMap.open( {
			pointType: type,
			cod: codAmount > 0,
			codAmount,
			address: box.dataset.address || '',
			onSelect: function ( pni ) {
				// The server checks the point again when the shipment is saved.
				showPoint( box, pni, config.i18n.pointChosen.replace( '%d', String( pni ) ), type );
			},
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-ppwc-operation]' );
		const box = button && button.closest( '.ppwc-shipments' );

		if ( ! box ) {
			return;
		}

		event.preventDefault();

		const operation = button.dataset.ppwcOperation;
		const row = button.closest( '[data-shipment-id]' );

		if ( 'delete' === operation && ! window.confirm( config.i18n.confirm ) ) {
			return;
		}

		const message = box.querySelector( '.ppwc-shipments__message' );
		const show = function ( text, type ) {
			const paragraph = document.createElement( 'p' );

			paragraph.textContent = text;
			message.className = 'ppwc-shipments__message notice inline notice-' + type;
			message.replaceChildren( paragraph );
		};
		const value = function ( name ) {
			const field = box.querySelector( '[name="' + name + '"]' );

			return field ? field.value : '';
		};

		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			operation,
			order_id: box.dataset.orderId,
			shipment_id: row ? row.dataset.shipmentId : '',
			service: value( 'ppwc_service' ),
			format: value( 'ppwc_format' ),
			parcels: value( 'ppwc_parcels' ),
			weight: value( 'ppwc_weight' ),
			mobile: value( 'ppwc_mobile' ),
			phone: value( 'ppwc_phone' ),
			pickup_pni: pointTypeOf( box ) ? value( 'ppwc_pickup_pni' ) : '',
			office_id: value( 'ppwc_office_id' ),
		} );

		box.querySelectorAll( 'button' ).forEach( function ( element ) {
			element.disabled = true;
		} );
		show( config.i18n.working, 'info' );

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
					return;
				}

				show( ( response && response.data && response.data.message ) || config.i18n.error, 'error' );
				box.querySelectorAll( 'button' ).forEach( function ( element ) {
					element.disabled = false;
				} );
			} )
			.catch( function () {
				show( config.i18n.error, 'error' );
				box.querySelectorAll( 'button' ).forEach( function ( element ) {
					element.disabled = false;
				} );
			} );
	} );
}() );
