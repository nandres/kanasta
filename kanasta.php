<?php
/**
 * Plugin Name:       Kanasta – Chat Orders for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/kanasta/
 * Description:       Let customers place real WooCommerce orders and send them to you on WhatsApp. Adds an "Order via WhatsApp" checkout method plus WhatsApp buttons on product and cart pages.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Marcos Arellano
 * Author URI:        https://marcos-arellano.vercel.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kanasta
 * Domain Path:       /languages
 * WC requires at least: 8.6
 * WC tested up to:   11.2
 *
 * @fs_premium_only /includes/pro/
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

define( 'KANASTA_VERSION', '1.2.0' );
define( 'KANASTA_FILE', __FILE__ );
define( 'KANASTA_DIR', plugin_dir_path( __FILE__ ) );
define( 'KANASTA_URL', plugin_dir_url( __FILE__ ) );

/*
 * Freemius product credentials (Freemius Developer Dashboard → your product → Settings → Keys).
 * The public key is meant to be public; never put the secret key here.
 * While these are empty the plugin runs without Freemius (free features only).
 */
define( 'KANASTA_FS_ID', '43589' );
define( 'KANASTA_FS_PUBLIC_KEY', 'pk_fff102d7265cafd411358aca81198' );

/**
 * Whether the Freemius credentials have been filled in.
 *
 * @return bool
 */
function kanasta_fs_is_configured() {
	return '' !== KANASTA_FS_ID && '' !== KANASTA_FS_PUBLIC_KEY;
}

if ( kanasta_fs_is_configured() && ! function_exists( 'kanasta_fs' ) ) {
	/**
	 * Freemius SDK instance (licensing, checkout and updates of the Pro version).
	 *
	 * @return Freemius
	 */
	function kanasta_fs() {
		global $kanasta_fs;

		if ( ! isset( $kanasta_fs ) ) {
			require_once KANASTA_DIR . 'vendor/freemius/start.php';

			$kanasta_fs = fs_dynamic_init(
				array(
					'id'                  => KANASTA_FS_ID,
					'slug'                => 'kanasta',
					'premium_slug'        => 'kanasta-pro',
					'type'                => 'plugin',
					'public_key'          => KANASTA_FS_PUBLIC_KEY,
					'is_premium'          => false,
					'premium_suffix'      => 'Pro',
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					'menu'                => array(
						'slug'       => 'kanasta',
						'first-path' => 'admin.php?page=kanasta',
						'contact'    => false,
						'support'    => false,
						'parent'     => array(
							'slug' => 'woocommerce',
						),
					),
				)
			);
		}

		return $kanasta_fs;
	}

	/**
	 * Keep Freemius messages (opt-in, trial offer, license notices) on the Plugins screen and on the
	 * plugin's own pages instead of the whole dashboard.
	 *
	 * @param bool $show Whether Freemius wants to show the notice.
	 * @return bool
	 */
	function kanasta_fs_show_admin_notice( $show ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $show || ! $screen ) {
			return $show;
		}
		return 'plugins' === $screen->id || false !== strpos( $screen->id, 'kanasta' );
	}

	kanasta_fs();
	kanasta_fs()->add_filter( 'show_admin_notice', 'kanasta_fs_show_admin_notice' );
	kanasta_fs()->add_filter( 'show_affiliate_program_notice', '__return_false' );
	do_action( 'kanasta_fs_loaded' );
}

/**
 * Delete Kanasta options when the plugin is deleted.
 *
 * Runs from Freemius' uninstall hook (Freemius needs to own uninstall, so there is no uninstall.php).
 */
function kanasta_uninstall_cleanup() {
	delete_option( 'kanasta_settings' );
	delete_option( 'woocommerce_kanasta_settings' );
	delete_option( 'kanasta_rotation_index' );
}

if ( function_exists( 'kanasta_fs' ) ) {
	kanasta_fs()->add_action( 'after_uninstall', 'kanasta_uninstall_cleanup' );
} else {
	register_uninstall_hook( KANASTA_FILE, 'kanasta_uninstall_cleanup' );
}

require_once KANASTA_DIR . 'includes/class-kanasta-plugin.php';

// Pro features: Freemius removes this block, and the Pro folder, from the free build.
if ( function_exists( 'kanasta_fs' ) ) {
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', KANASTA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', KANASTA_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'Kanasta_Plugin', 'instance' ) );
