/**
 * Kanasta – adds the WhatsApp button to the block-based cart (order summary area).
 */
( function ( wp, wc, data ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout || ! wc.blocksCheckout.ExperimentalOrderMeta || ! data ) {
		return;
	}

	var el = wp.element.createElement;

	wp.plugins.registerPlugin( 'kanasta-cart', {
		scope: 'woocommerce-checkout',
		render: function () {
			return el(
				wc.blocksCheckout.ExperimentalOrderMeta,
				null,
				el(
					'div',
					{ className: 'kanasta-button-wrap' },
					el( 'a', { href: data.url, className: 'kanasta-button kanasta-cart-button', rel: 'nofollow noopener', target: '_blank' }, data.text ),
					data.notice ? el( 'p', { className: 'kanasta-notice' }, data.notice ) : null
				)
			);
		},
	} );
} )( window.wp, window.wc, window.kanastaCart );
