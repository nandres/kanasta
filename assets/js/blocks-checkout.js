/**
 * Kanasta – registers "Order via WhatsApp" in the block-based checkout.
 */
( function ( wc, wp ) {
	'use strict';

	if ( ! wc || ! wc.wcBlocksRegistry || ! wc.wcSettings || ! wp || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var decode = wp.htmlEntities ? wp.htmlEntities.decodeEntities : function ( text ) {
		return text;
	};
	var settings = wc.wcSettings.getSetting( 'kanasta_data', {} );
	var title = decode( settings.title || 'WhatsApp' );

	var Content = function () {
		return el( 'div', { className: 'kanasta-checkout-description' }, decode( settings.description || '' ) );
	};

	var Label = function ( props ) {
		var PaymentMethodLabel = props.components.PaymentMethodLabel;
		return el( PaymentMethodLabel, { text: title } );
	};

	wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'kanasta',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )( window.wc, window.wp );
