<?php
/**
 * Front-end redirect endpoint for product and cart buttons.
 *
 * Buttons point to ?kanasta=product|cart so the number and message are chosen on the server
 * (needed for agent rotation, business hours, etc.).
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles ?kanasta=… requests and redirects to WhatsApp.
 */
class Kanasta_Redirect {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 5 );
	}

	/**
	 * URL of the redirect endpoint.
	 *
	 * @param string $context 'product' or 'cart'.
	 * @param array  $args    Extra query args.
	 * @return string
	 */
	public static function url( $context, array $args = array() ) {
		return add_query_arg( array_merge( array( 'kanasta' => $context ), $args ), home_url( '/' ) );
	}

	/**
	 * Redirect to WhatsApp when the endpoint is requested.
	 */
	public static function maybe_redirect() {
		/*
		 * No nonce on purpose: this endpoint does not create, change or delete anything. It only reads a
		 * published product (or the visitor's own cart) and redirects to a wa.me link that the visitor
		 * still has to send. The buttons that point here are printed on public pages that are usually
		 * served from a page cache, where a nonce would expire and break the buttons for every visitor.
		 * Only the expected keys are read, and each one is sanitized.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['kanasta'] ) ) {
			return;
		}

		$context = sanitize_key( wp_unslash( $_GET['kanasta'] ) );
		$url     = '';

		if ( 'product' === $context ) {
			$variation_id = isset( $_GET['variation_id'] ) ? absint( $_GET['variation_id'] ) : 0;
			$url          = self::product_url(
				isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
				$variation_id,
				isset( $_GET['quantity'] ) ? max( 1, absint( $_GET['quantity'] ) ) : 1,
				self::attributes_from_request( $variation_id )
			);
		} elseif ( 'cart' === $context ) {
			$url = self::cart_url();
		} elseif ( 'chat' === $context ) {
			$url = self::chat_url( isset( $_GET['ref'] ) ? esc_url_raw( wp_unslash( $_GET['ref'] ) ) : '' );
		}
		// phpcs:enable

		$url = Kanasta_Message::safe_url( $url );
		if ( '' === $url ) {
			return;
		}

		/**
		 * Fires right before a visitor is sent to WhatsApp from a button.
		 *
		 * @param string $context 'product', 'cart' or 'chat'.
		 * @param string $url     WhatsApp link.
		 */
		do_action( 'kanasta_before_redirect', $context, $url );

		// wp_safe_redirect() would strip the encoded line breaks of the message; safe_url() has
		// already validated that the link only contains URL-safe characters (no CR/LF).
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Location: ' . $url, true, 302 );
		exit;
	}

	/**
	 * Chosen values of the variation's attributes ("Any size" and similar) from the request.
	 *
	 * Only the attribute keys of that variation are read; the rest of the request is ignored.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array attribute_* => value.
	 */
	private static function attributes_from_request( $variation_id ) {
		$attributes = array();
		$variation  = $variation_id ? wc_get_product( $variation_id ) : null;
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return $attributes;
		}

		foreach ( array_keys( $variation->get_variation_attributes() ) as $key ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect, see maybe_redirect().
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
				$attributes[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
			// phpcs:enable
		}
		return $attributes;
	}

	/**
	 * WhatsApp link for a product.
	 *
	 * @param int   $product_id   Product ID.
	 * @param int   $variation_id Variation ID.
	 * @param int   $quantity     Quantity.
	 * @param array $attributes   Variation attributes.
	 * @return string Empty when not allowed.
	 */
	public static function product_url( $product_id, $variation_id, $quantity, array $attributes ) {
		if ( ! Kanasta_Settings::enabled( 'product_button' ) || ! Kanasta_Plugin::is_available( 'product' ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return '';
		}

		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->get_parent_id() === $product->get_id() ) {
				$product = $variation;
			}
		}

		$text = Kanasta_Message::render( Kanasta_Settings::get( 'product_template' ), Kanasta_Message::product_vars( $product, $quantity, $attributes ) );
		return Kanasta_Message::whatsapp_url( Kanasta_Plugin::get_phone( 'product', $product ), $text );
	}

	/**
	 * WhatsApp link for the floating chat button.
	 *
	 * @param string $ref Page the visitor was on. Only pages of this site are used in the message.
	 * @return string Empty when not allowed.
	 */
	public static function chat_url( $ref ) {
		if ( ! Kanasta_Settings::enabled( 'float_button' ) || ! Kanasta_Plugin::is_available( 'chat' ) ) {
			return '';
		}

		$home = wp_parse_url( home_url( '/' ) );
		$page = wp_parse_url( $ref );
		if ( empty( $page['host'] ) || empty( $home['host'] ) || strtolower( $page['host'] ) !== strtolower( $home['host'] ) ) {
			$ref = '';
		}

		$text = Kanasta_Message::render( Kanasta_Settings::get( 'float_template' ), Kanasta_Message::page_vars( $ref ) );
		return Kanasta_Message::whatsapp_url( Kanasta_Plugin::get_phone( 'chat' ), $text );
	}

	/**
	 * WhatsApp link for the current cart.
	 *
	 * @return string Empty when not allowed.
	 */
	public static function cart_url() {
		if ( ! Kanasta_Settings::enabled( 'cart_button' ) || ! Kanasta_Plugin::is_available( 'cart' ) ) {
			return '';
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return '';
		}

		$cart->calculate_totals();
		$text = Kanasta_Message::render( Kanasta_Settings::get( 'cart_template' ), Kanasta_Message::cart_vars( $cart ) );
		return Kanasta_Message::whatsapp_url( Kanasta_Plugin::get_phone( 'cart', $cart ), $text );
	}
}
