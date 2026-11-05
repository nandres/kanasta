/**
 * Kanasta – product page button: sends the chosen quantity and variation.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.kanasta-product-button' ) : null;
		if ( ! button ) {
			return;
		}

		var scope = button.closest( '.product' ) || document;
		var form = scope.querySelector( 'form.cart' ) || document.querySelector( 'form.cart' );
		if ( ! form ) {
			return;
		}

		var url = new URL( button.href );
		var quantity = form.querySelector( 'input[name="quantity"]' );
		if ( quantity && parseInt( quantity.value, 10 ) > 0 ) {
			url.searchParams.set( 'quantity', parseInt( quantity.value, 10 ) );
		}

		if ( '1' === button.getAttribute( 'data-variable' ) ) {
			var variation = form.querySelector( 'input[name="variation_id"]' );
			if ( ! variation || ! parseInt( variation.value, 10 ) ) {
				event.preventDefault();
				if ( window.kanastaProduct && window.kanastaProduct.selectOptions ) {
					window.alert( window.kanastaProduct.selectOptions );
				}
				return;
			}
			url.searchParams.set( 'variation_id', variation.value );
			Array.prototype.forEach.call( form.querySelectorAll( '[name^="attribute_"]' ), function ( field ) {
				if ( field.value ) {
					url.searchParams.set( field.name, field.value );
				}
			} );
		}

		// The browser follows the updated link once this handler returns.
		button.href = url.toString();
	} );
} )();
