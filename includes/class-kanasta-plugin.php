<?php
/**
 * Plugin bootstrap.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class: loads modules once WooCommerce is available.
 */
final class Kanasta_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Kanasta_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and boot on first call) the plugin instance.
	 *
	 * @return Kanasta_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_translations' ), 0 );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_woocommerce' ) );
			return;
		}

		require_once KANASTA_DIR . 'includes/class-kanasta-settings.php';
		require_once KANASTA_DIR . 'includes/class-kanasta-message.php';
		require_once KANASTA_DIR . 'includes/class-kanasta-redirect.php';
		require_once KANASTA_DIR . 'includes/class-kanasta-buttons.php';
		require_once KANASTA_DIR . 'includes/class-kanasta-float.php';
		require_once KANASTA_DIR . 'includes/class-kanasta-admin.php';

		Kanasta_Settings::init();
		Kanasta_Redirect::init();
		Kanasta_Buttons::init();
		Kanasta_Float::init();
		Kanasta_Admin::init();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_blocks_integration' ) );

		do_action( 'kanasta_loaded' );
	}

	/**
	 * Load bundled translations. Any regional Spanish/Portuguese locale falls back to es_ES/pt_BR.
	 */
	public function load_translations() {
		$locale = determine_locale();
		$file   = KANASTA_DIR . 'languages/kanasta-' . $locale . '.mo';

		if ( ! file_exists( $file ) ) {
			$fallbacks = array(
				'es' => 'es_ES',
				'pt' => 'pt_BR',
			);
			$language  = substr( $locale, 0, 2 );
			if ( isset( $fallbacks[ $language ] ) ) {
				$file = KANASTA_DIR . 'languages/kanasta-' . $fallbacks[ $language ] . '.mo';
			}
		}

		if ( file_exists( $file ) ) {
			load_textdomain( 'kanasta', $file );
		}
	}

	/**
	 * Add the WhatsApp checkout method to WooCommerce.
	 *
	 * @param string[] $gateways Gateway class names.
	 * @return string[]
	 */
	public function register_gateway( $gateways ) {
		require_once KANASTA_DIR . 'includes/class-kanasta-gateway.php';
		$gateways[] = 'Kanasta_Gateway';
		return $gateways;
	}

	/**
	 * Make the checkout method available in the block-based checkout.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Registry.
	 */
	public function register_blocks_integration( $registry ) {
		require_once KANASTA_DIR . 'includes/class-kanasta-blocks.php';
		$registry->register( new Kanasta_Blocks() );
	}

	/**
	 * The store's WhatsApp number for a given context, digits only.
	 *
	 * @param string $context  'order', 'product', 'cart' or 'chat'.
	 * @param mixed  $object   Order, product or null.
	 * @return string
	 */
	public static function get_phone( $context, $object = null ) {
		/**
		 * Filters the WhatsApp number used for a message.
		 *
		 * @param string $phone   Number from the settings, digits only.
		 * @param string $context 'order', 'product', 'cart' or 'chat'.
		 * @param mixed  $object  WC_Order, WC_Product or null.
		 */
		$phone = apply_filters( 'kanasta_phone', Kanasta_Settings::get( 'phone' ), $context, $object );
		return Kanasta_Message::sanitize_phone( $phone );
	}

	/**
	 * Whether WhatsApp ordering is available right now for a context.
	 *
	 * @param string $context 'order', 'product', 'cart' or 'chat'.
	 * @return bool
	 */
	public static function is_available( $context ) {
		/**
		 * Filters whether Kanasta buttons/checkout method are offered.
		 *
		 * @param bool   $available Whether a number is configured.
		 * @param string $context   'order', 'product', 'cart' or 'chat'.
		 */
		return (bool) apply_filters( 'kanasta_is_available', '' !== Kanasta_Settings::get( 'phone' ), $context );
	}

	/**
	 * Whether the Pro features are unlocked.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return class_exists( 'Kanasta_Pro' ) && Kanasta_Pro::is_active();
	}

	/**
	 * Upgrade URL for the Pro version, or empty string when Freemius is not configured.
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		return function_exists( 'kanasta_fs' ) ? kanasta_fs()->get_upgrade_url() : '';
	}

	/**
	 * Admin notice shown when WooCommerce is not active.
	 */
	public function notice_missing_woocommerce() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Kanasta needs WooCommerce to be installed and active.', 'kanasta' ) . '</p></div>';
	}
}
