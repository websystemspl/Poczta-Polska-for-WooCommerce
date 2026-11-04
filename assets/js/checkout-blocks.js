/**
 * Pickup point picker in the Checkout block.
 *
 * Plain JavaScript on the WordPress and WooCommerce globals, no build step.
 */
( function () {
	'use strict';

	const { registerPlugin } = window.wp.plugins;
	const { createElement: el, useEffect } = window.wp.element;
	const { useSelect, useDispatch } = window.wp.data;
	const { __, sprintf } = window.wp.i18n;
	const { ExperimentalOrderShippingPackages, extensionCartUpdate } = window.wc.blocksCheckout;
	const settings = window.wc.wcSettings.getSetting( 'ppwc_data', {} );

	const VALIDATION_KEY = 'ppwc-pickup-point';

	function formatPrice( amount ) {
		return new Intl.NumberFormat( settings.locale || 'pl-PL', { style: 'currency', currency: 'PLN' } ).format( amount );
	}

	function PickupPoint() {
		const data = useSelect( function ( select ) {
			const cart = select( 'wc/store/cart' ).getCartData();
			const payment = select( 'wc/store/payment' );

			return {
				rates: cart.shippingRates || [],
				points: ( cart.extensions && cart.extensions.ppwc && cart.extensions.ppwc.points ) || {},
				totals: cart.totals || {},
				address: cart.shippingAddress || {},
				paymentMethod: payment.getActivePaymentMethod(),
			};
		}, [] );
		const { setValidationErrors, clearValidationError } = useDispatch( 'wc/store/validation' );

		const selectedRate = data.rates.length ? ( data.rates[ 0 ].shipping_rates || [] ).find( function ( rate ) {
			return rate.selected;
		} ) : null;
		const pointType = selectedRate ? settings.pointTypes[ selectedRate.method_id ] : null;
		const point = pointType ? data.points[ pointType ] : null;
		const cod = 'cod' === data.paymentMethod;
		const total = data.totals.total_price ? parseInt( data.totals.total_price, 10 ) / Math.pow( 10, data.totals.currency_minor_unit || 2 ) : 0;
		const isLocker = 'AUTOMAT' === pointType;

		let problem = '';

		if ( pointType && ! point ) {
			problem = isLocker ? __( 'Please choose a parcel locker on the map.', 'poczta-polska-for-woocommerce' ) : __( 'Please choose a pickup point on the map.', 'poczta-polska-for-woocommerce' );
		} else if ( point && cod && ! ( point.cod_limit > 0 ) ) {
			problem = __( 'The chosen point does not accept cash on delivery. Choose another point or another payment method.', 'poczta-polska-for-woocommerce' );
		} else if ( point && cod && total > point.cod_limit ) {
			/* translators: %s: maximum cash on delivery amount. */
			problem = sprintf( __( 'The chosen point accepts cash on delivery up to %s. Choose another point or another payment method.', 'poczta-polska-for-woocommerce' ), formatPrice( point.cod_limit ) );
		}

		// Blocks placing the order in the browser, the server checks the same rules again.
		useEffect( function () {
			if ( problem ) {
				setValidationErrors( { [ VALIDATION_KEY ]: { message: problem, hidden: true } } );
			} else {
				clearValidationError( VALIDATION_KEY );
			}

			return function () {
				clearValidationError( VALIDATION_KEY );
			};
		}, [ problem ] );

		if ( ! pointType ) {
			return null;
		}

		function choose() {
			window.ppwcPickupMap.open( {
				pointType,
				cod,
				codAmount: total,
				address: [ data.address.address_1, ( data.address.postcode || '' ) + ' ' + ( data.address.city || '' ) ].map( function ( part ) {
					return ( part || '' ).trim();
				} ).filter( Boolean ).join( ', ' ),
				postcode: data.address.postcode || '',
				onSelect( pni ) {
					extensionCartUpdate( { namespace: 'ppwc', data: { type: pointType, pni } } );
				},
			} );
		}

		const summary = point
			? el( 'div', { className: 'ppwc-pickup__summary' },
				el( 'strong', { className: 'ppwc-pickup__name' }, point.name ),
				el( 'br' ),
				el( 'span', { className: 'ppwc-pickup__address' }, point.address ),
				point.cod_limit > 0 ? el( 'span', { className: 'ppwc-pickup__cod' },
					/* translators: %s: maximum cash on delivery amount. */
					sprintf( __( 'Cash on delivery available (up to %s).', 'poczta-polska-for-woocommerce' ), formatPrice( point.cod_limit ) )
				) : null
			)
			: el( 'div', { className: 'ppwc-pickup__summary ppwc-pickup__empty' },
				isLocker ? __( 'No parcel locker chosen.', 'poczta-polska-for-woocommerce' ) : __( 'No pickup point chosen.', 'poczta-polska-for-woocommerce' )
			);

		return el( 'div', { className: 'ppwc-pickup ppwc-pickup--blocks' },
			summary,
			problem ? el( 'p', { className: 'ppwc-pickup__problem', role: 'alert' }, problem ) : null,
			el( 'button', { type: 'button', className: 'wc-block-components-button wp-element-button ppwc-pickup__choose contained', onClick: choose },
				el( 'span', { className: 'wc-block-components-button__text' },
					point ? __( 'Change', 'poczta-polska-for-woocommerce' ) : ( isLocker ? __( 'Choose a parcel locker', 'poczta-polska-for-woocommerce' ) : __( 'Choose a pickup point', 'poczta-polska-for-woocommerce' ) )
				)
			)
		);
	}

	registerPlugin( 'ppwc-pickup-point', {
		scope: 'woocommerce-checkout',
		render: function () {
			return el( ExperimentalOrderShippingPackages, null, el( PickupPoint ) );
		},
	} );
}() );
