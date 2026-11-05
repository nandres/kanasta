<?php
/**
 * Builds WhatsApp messages and links.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Message rendering helpers.
 */
class Kanasta_Message {

	/**
	 * Keep only digits from a phone number.
	 *
	 * @param mixed $phone Raw number.
	 * @return string
	 */
	public static function sanitize_phone( $phone ) {
		return preg_replace( '/\D+/', '', (string) $phone );
	}

	/**
	 * Build a wa.me link.
	 *
	 * @param string $phone Number with country code.
	 * @param string $text  Message text.
	 * @return string
	 */
	public static function whatsapp_url( $phone, $text = '' ) {
		$phone = self::sanitize_phone( $phone );
		$url   = 'https://wa.me/' . $phone;

		if ( '' !== $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}

		/**
		 * Filters the WhatsApp link.
		 *
		 * @param string $url   Link.
		 * @param string $phone Number, digits only.
		 * @param string $text  Message.
		 */
		return apply_filters( 'kanasta_whatsapp_url', $url, $phone, $text );
	}

	/**
	 * Validate a WhatsApp link for output.
	 *
	 * esc_url() and wp_safe_redirect() strip encoded line breaks (%0A), which WhatsApp needs for
	 * multi-line messages. Links built by whatsapp_url() only contain rawurlencode()d text, so we
	 * accept any https URL made of URL-safe characters and reject everything else.
	 *
	 * @param string $url Link.
	 * @return string The link, or empty string when it is not safe.
	 */
	public static function safe_url( $url ) {
		$url = (string) $url;
		if ( 0 !== strpos( $url, 'https://' ) || ! preg_match( '#^[A-Za-z0-9\-._~:/?\#\[\]@!$&\'()*+,;=%]+$#', $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Escape a WhatsApp link for an HTML attribute, keeping line breaks in the message.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	public static function esc_url( $url ) {
		return esc_attr( self::safe_url( $url ) );
	}

	/**
	 * Replace {tags} in a template. Lines whose tags all resolve to empty values are dropped.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Tag => value.
	 * @return string
	 */
	public static function render( $template, array $vars ) {
		$lines = preg_split( '/\R/u', (string) $template );
		$out   = array();

		foreach ( $lines as $line ) {
			$has_tag   = false;
			$all_empty = true;

			$line = preg_replace_callback(
				'/\{([a-z0-9_]+)\}/',
				static function ( $match ) use ( $vars, &$has_tag, &$all_empty ) {
					if ( ! array_key_exists( $match[1], $vars ) ) {
						return $match[0];
					}
					$has_tag = true;
					$value   = (string) $vars[ $match[1] ];
					if ( '' !== trim( $value ) ) {
						$all_empty = false;
					}
					return $value;
				},
				$line
			);

			if ( $has_tag && $all_empty ) {
				continue;
			}
			$out[] = rtrim( $line );
		}

		$text = preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $out ) );
		return trim( $text );
	}

	/**
	 * Convert WooCommerce price/HTML output to plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain( $html ) {
		$html = preg_replace( '#<br\s*/?>#i', ', ', (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Format an amount as plain text in the store (or order) currency.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function price( $amount, $currency = '' ) {
		return self::plain( wc_price( $amount, $currency ? array( 'currency' => $currency ) : array() ) );
	}

	/**
	 * One-line address without the name, company, email and phone (those have their own tags).
	 *
	 * @param array $address WooCommerce address fields.
	 * @return string
	 */
	public static function address( array $address ) {
		$address = array_diff_key( $address, array_flip( array( 'first_name', 'last_name', 'company', 'email', 'phone' ) ) );
		if ( '' === implode( '', array_map( 'trim', array_diff_key( $address, array( 'country' => '' ) ) ) ) ) {
			return '';
		}
		return self::plain( WC()->countries->get_formatted_address( $address, ', ' ) );
	}

	/**
	 * Common tags.
	 *
	 * @return array
	 */
	private static function site_vars() {
		return array(
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	/**
	 * Tags for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_vars( WC_Order $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$meta = array();
			foreach ( $item->get_formatted_meta_data( '_', true ) as $entry ) {
				$value = self::plain( $entry->display_value );
				// Variation names already include their attributes ("T-shirt - Red").
				if ( '' === $value || false !== stripos( $item->get_name(), $value ) ) {
					continue;
				}
				$meta[] = self::plain( $entry->display_key ) . ': ' . $value;
			}

			$line = '• ' . $item->get_quantity() . ' x ' . $item->get_name();
			if ( $meta ) {
				$line .= ' (' . implode( ', ', $meta ) . ')';
			}
			$line   .= ' – ' . self::plain( $order->get_formatted_line_subtotal( $item ) );
			$items[] = $line;
		}

		$discount = (float) $order->get_total_discount() > 0 ? '-' . self::plain( $order->get_discount_to_display() ) : '';
		$shipping = $order->get_shipping_method() ? self::plain( $order->get_shipping_to_display() ) : '';

		$vars = array(
			'order_number'     => $order->get_order_number(),
			'order_date'       => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
			'items'            => implode( "\n", $items ),
			'subtotal'         => self::plain( $order->get_subtotal_to_display() ),
			'shipping'         => $shipping,
			'discount'         => $discount,
			'total'            => self::plain( $order->get_formatted_order_total() ),
			'customer_name'    => trim( $order->get_formatted_billing_full_name() ),
			'customer_phone'   => $order->get_billing_phone(),
			'customer_email'   => $order->get_billing_email(),
			'billing_address'  => self::address( $order->get_address( 'billing' ) ),
			'shipping_address' => $order->has_shipping_address() ? self::address( $order->get_address( 'shipping' ) ) : '',
			'customer_note'    => self::plain( $order->get_customer_note() ),
			'order_url'        => $order->get_checkout_order_received_url(),
		) + self::site_vars();

		/**
		 * Filters the tags available in order messages.
		 *
		 * @param array    $vars  Tags.
		 * @param WC_Order $order Order.
		 */
		return apply_filters( 'kanasta_order_vars', $vars, $order );
	}

	/**
	 * Tags for the current cart.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array
	 */
	public static function cart_vars( WC_Cart $cart ) {
		$items = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$line    = '• ' . $cart_item['quantity'] . ' x ' . $product->get_name();
			$details = self::plain( str_replace( "\n", ', ', trim( wc_get_formatted_cart_item_data( $cart_item, true ) ) ) );
			if ( '' !== $details ) {
				$line .= ' (' . $details . ')';
			}
			$line   .= ' – ' . self::plain( $cart->get_product_subtotal( $product, $cart_item['quantity'] ) );
			$items[] = $line;
		}

		$vars = array(
			'items'    => implode( "\n", $items ),
			'subtotal' => self::plain( $cart->get_cart_subtotal() ),
			'total'    => self::plain( $cart->get_total() ),
			'cart_url' => wc_get_cart_url(),
		) + self::site_vars();

		/**
		 * Filters the tags available in cart messages.
		 *
		 * @param array   $vars Tags.
		 * @param WC_Cart $cart Cart.
		 */
		return apply_filters( 'kanasta_cart_vars', $vars, $cart );
	}

	/**
	 * Tags for a single product.
	 *
	 * @param WC_Product $product    Product or variation.
	 * @param int        $quantity   Quantity.
	 * @param array      $attributes Chosen variation attributes (attribute_* => value).
	 * @return array
	 */
	public static function product_vars( WC_Product $product, $quantity = 1, array $attributes = array() ) {
		$variation = '';
		$name      = $product->get_name();
		$url       = $product->get_permalink();

		if ( $product->is_type( 'variation' ) ) {
			$parent     = wc_get_product( $product->get_parent_id() );
			$name       = $parent ? $parent->get_name() : $name;
			$attributes = array_merge( $product->get_variation_attributes(), array_filter( $attributes ) );
			$variation  = self::plain( wc_get_formatted_variation( $attributes, true, false ) );
		}

		$vars = array(
			'product_name' => $name,
			'variation'    => $variation,
			'quantity'     => max( 1, (int) $quantity ),
			'price'        => '' === $product->get_price() ? '' : self::price( wc_get_price_to_display( $product ) ),
			'sku'          => $product->get_sku(),
			'product_url'  => $url,
		) + self::site_vars();

		/**
		 * Filters the tags available in product messages.
		 *
		 * @param array      $vars    Tags.
		 * @param WC_Product $product Product.
		 */
		return apply_filters( 'kanasta_product_vars', $vars, $product );
	}

	/**
	 * Tags for the page a visitor is on (floating chat button).
	 *
	 * @param string $url Page URL on this site, or empty.
	 * @return array
	 */
	public static function page_vars( $url ) {
		$title = '';
		if ( '' !== $url ) {
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				$title = wp_specialchars_decode( get_the_title( $post_id ), ENT_QUOTES );
			} elseif ( untrailingslashit( $url ) === untrailingslashit( home_url() ) ) {
				$title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			}
		}

		$vars = array(
			'page_title' => $title,
			'page_url'   => $url,
		) + self::site_vars();

		/**
		 * Filters the tags available in floating button messages.
		 *
		 * @param array  $vars Tags.
		 * @param string $url  Page URL.
		 */
		return apply_filters( 'kanasta_page_vars', $vars, $url );
	}

	/**
	 * Full WhatsApp link for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function order_url( WC_Order $order ) {
		$text = self::render( Kanasta_Settings::get( 'order_template' ), self::order_vars( $order ) );
		return self::whatsapp_url( Kanasta_Plugin::get_phone( 'order', $order ), $text );
	}
}
