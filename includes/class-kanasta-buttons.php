<?php
/**
 * WhatsApp buttons on product and cart pages.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end buttons.
 */
class Kanasta_Buttons {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'product_button' ) );
		add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'cart_button' ), 30 );
	}

	/**
	 * Load assets where buttons can appear.
	 */
	public static function enqueue() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style( 'kanasta', KANASTA_URL . 'assets/css/kanasta.css', array(), KANASTA_VERSION );

		if ( is_product() && self::show( 'product' ) ) {
			wp_enqueue_script( 'kanasta-product', KANASTA_URL . 'assets/js/product.js', array(), KANASTA_VERSION, true );
			wp_localize_script(
				'kanasta-product',
				'kanastaProduct',
				array(
					'selectOptions' => __( 'Please choose the product options first.', 'kanasta' ),
				)
			);
		}

		if ( is_cart() && self::show( 'cart' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			// Block-based cart: the button is added in the order summary through a slot.
			wp_enqueue_script( 'kanasta-cart-block', KANASTA_URL . 'assets/js/cart-block.js', array( 'wp-plugins', 'wp-element', 'wc-blocks-checkout' ), KANASTA_VERSION, true );
			wp_localize_script(
				'kanasta-cart-block',
				'kanastaCart',
				array(
					'url'    => Kanasta_Redirect::url( 'cart' ),
					'text'   => Kanasta_Settings::get( 'cart_button_text' ),
					'notice' => self::notice( 'kanasta-cart-button' ),
				)
			);
		}
	}

	/**
	 * Whether a button type should be shown.
	 *
	 * @param string $context 'product' or 'cart'.
	 * @return bool
	 */
	private static function show( $context ) {
		return Kanasta_Settings::enabled( $context . '_button' ) && Kanasta_Plugin::is_available( $context );
	}

	/**
	 * Button on the single product page (after the add to cart form).
	 */
	public static function product_button() {
		global $product;

		if ( ! $product instanceof WC_Product || ! self::show( 'product' ) ) {
			return;
		}

		/**
		 * Filters whether the WhatsApp button shows for a product.
		 *
		 * @param bool       $show    Show the button.
		 * @param WC_Product $product Product.
		 */
		if ( ! apply_filters( 'kanasta_show_product_button', true, $product ) ) {
			return;
		}

		self::render_button(
			Kanasta_Redirect::url( 'product', array( 'product_id' => $product->get_id() ) ),
			Kanasta_Settings::get( 'product_button_text' ),
			'kanasta-product-button',
			array(
				'data-product-id' => $product->get_id(),
				'data-variable'   => $product->is_type( 'variable' ) ? '1' : '0',
			)
		);
	}

	/**
	 * Button on the classic (shortcode) cart page.
	 */
	public static function cart_button() {
		if ( ! self::show( 'cart' ) ) {
			return;
		}
		self::render_button( Kanasta_Redirect::url( 'cart' ), Kanasta_Settings::get( 'cart_button_text' ), 'kanasta-cart-button' );
	}

	/**
	 * Print a button.
	 *
	 * @param string $url   Link.
	 * @param string $text  Label.
	 * @param string $class Extra CSS class.
	 * @param array  $attrs Extra attributes.
	 */
	public static function render_button( $url, $text, $class, array $attrs = array() ) {
		$extra = '';
		foreach ( $attrs as $name => $value ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		printf(
			'<div class="kanasta-button-wrap"><a href="%1$s" class="kanasta-button %2$s" rel="nofollow noopener" target="_blank"%3$s>%4$s</a></div>',
			esc_url( $url ),
			esc_attr( $class ),
			$extra, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html( $text )
		);

		self::render_notice( $class );
	}

	/**
	 * Notice under a button (e.g. "we are closed right now" in Pro).
	 *
	 * @param string $class Button CSS class.
	 * @return string
	 */
	public static function notice( $class ) {
		/**
		 * Filters the short notice shown under the WhatsApp buttons.
		 *
		 * @param string $notice Notice text (empty for none).
		 * @param string $class  Button CSS class.
		 */
		return (string) apply_filters( 'kanasta_button_notice', '', $class );
	}

	/**
	 * Print the notice under a button, if any.
	 *
	 * @param string $class Button CSS class.
	 */
	public static function render_notice( $class ) {
		$notice = self::notice( $class );
		if ( '' !== $notice ) {
			echo '<p class="kanasta-notice">' . esc_html( $notice ) . '</p>';
		}
	}
}
