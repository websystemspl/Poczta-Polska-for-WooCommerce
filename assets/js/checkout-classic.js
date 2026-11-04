/**
 * Pickup point picker in the classic checkout.
 */
jQuery( function ( $ ) {
	'use strict';

	function field( name ) {
		const shipToDifferent = $( '#ship-to-different-address-checkbox' ).is( ':checked' );
		const value = $( '#' + ( shipToDifferent ? 'shipping_' : 'billing_' ) + name ).val();

		return value ? String( value ).trim() : '';
	}

	$( document.body ).on( 'click', '.ppwc-pickup__choose', function ( event ) {
		event.preventDefault();

		const box = $( this ).closest( '.ppwc-pickup' );

		window.ppwcPickupMap.open( {
			pointType: String( box.data( 'point-type' ) ),
			cod: 'cod' === $( 'input[name="payment_method"]:checked' ).val(),
			codAmount: parseFloat( box.data( 'order-total' ) ) || 0,
			address: [ field( 'address_1' ), field( 'postcode' ) + ' ' + field( 'city' ) ].map( function ( part ) {
				return part.trim();
			} ).filter( Boolean ).join( ', ' ),
			postcode: field( 'postcode' ),
			onSelect: function ( pni ) {
				box.find( 'input[type="hidden"]' ).val( pni );
				$( document.body ).trigger( 'update_checkout' );
			},
		} );
	} );

	// The point summary warns when the point does not accept cash on delivery.
	$( document.body ).on( 'change', 'input[name="payment_method"]', function () {
		if ( $( '.ppwc-pickup' ).length ) {
			$( document.body ).trigger( 'update_checkout' );
		}
	} );
} );
