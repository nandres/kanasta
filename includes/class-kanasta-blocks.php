<?php
/**
 * Block-based checkout support for the "Order via WhatsApp" method.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers the method with the Cart & Checkout blocks.
 */
final class Kanasta_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name (matches the gateway ID).
	 *
	 * @var string
	 */
	protected $name = 'kanasta';

	/**
	 * Load the gateway settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_kanasta_settings', array() );
	}

	/**
	 * Whether the method is offered.
	 *
	 * @return bool
	 */
	public function is_active() {
		$enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
		return 'yes' === $enabled && Kanasta_Plugin::is_available( 'order' );
	}

	/**
	 * Script that registers the method in the checkout block.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'kanasta-blocks-checkout',
			KANASTA_URL . 'assets/js/blocks-checkout.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			KANASTA_VERSION,
			true
		);
		return array( 'kanasta-blocks-checkout' );
	}

	/**
	 * Data passed to the script (available through getSetting( 'kanasta_data' )).
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gateway  = isset( $gateways['kanasta'] ) ? $gateways['kanasta'] : null;

		return array(
			'title'       => $gateway ? $gateway->get_title() : $this->get_setting( 'title' ),
			'description' => $gateway ? $gateway->get_description() : $this->get_setting( 'description' ),
			'supports'    => $gateway ? array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ) : array( 'products' ),
		);
	}
}
