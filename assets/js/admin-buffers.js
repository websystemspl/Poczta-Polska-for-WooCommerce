/**
 * Poczta Polska buffers screen: confirmations and "select all".
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-ppwc-confirm]' );

		if ( button && ! window.confirm( button.dataset.ppwcConfirm ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( ! event.target.classList.contains( 'ppwc-buffer__all' ) ) {
			return;
		}

		event.target.closest( 'table' ).querySelectorAll( 'tbody input[type="checkbox"]' ).forEach( function ( checkbox ) {
			checkbox.checked = event.target.checked;
		} );
	} );
}() );
