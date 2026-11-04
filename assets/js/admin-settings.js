/**
 * Poczta Polska settings screen: "Test connection" buttons.
 */
( function () {
	'use strict';

	const settings = window.ppwcSettings;

	if ( ! settings ) {
		return;
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.ppwc-test-connection' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		const card = button.closest( '.ppwc-environment' );
		const result = card ? card.querySelector( '.ppwc-test-connection-result' ) : null;

		if ( ! result ) {
			return;
		}
		const show = function ( message, type ) {
			const paragraph = document.createElement( 'p' );

			paragraph.textContent = message;
			result.className = 'notice inline notice-' + type;
			result.replaceChildren( paragraph );
		};

		show( settings.i18n.testing, 'info' );
		button.disabled = true;

		const body = new URLSearchParams( {
			action: settings.action,
			nonce: settings.nonce,
			environment: button.dataset.environment,
		} );

		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				const message = response && response.data && response.data.message ? response.data.message : settings.i18n.error;

				show( message, response && response.success ? 'success' : 'error' );
			} )
			.catch( function () {
				show( settings.i18n.error, 'error' );
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );
}() );
