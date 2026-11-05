/**
 * Kanasta – opens WhatsApp automatically once on the order received page.
 */
( function () {
	'use strict';

	var button = document.querySelector( '.kanasta-thankyou-button[data-autoopen="1"]' );
	if ( ! button ) {
		return;
	}

	var key = 'kanasta_opened_' + button.getAttribute( 'data-order' );
	try {
		if ( window.sessionStorage.getItem( key ) ) {
			return;
		}
		window.sessionStorage.setItem( key, '1' );
	} catch ( e ) {
		// Storage unavailable: still open once.
	}

	window.setTimeout( function () {
		window.location.href = button.href;
	}, 1500 );
} )();
