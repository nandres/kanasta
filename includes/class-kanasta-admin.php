<?php
/**
 * Admin helpers: plugin links, setup notice and Pro box.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI glue.
 */
class Kanasta_Admin {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'plugin_action_links_' . plugin_basename( KANASTA_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_action( 'kanasta_settings_page_after', array( __CLASS__, 'pro_box' ) );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . Kanasta_Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'kanasta' ) . '</a>' );
		return $links;
	}

	/**
	 * Ask for the WhatsApp number until it is set (Plugins screen only).
	 */
	public static function setup_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'manage_woocommerce' ) || '' !== Kanasta_Settings::get( 'phone' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Kanasta is almost ready: add your store WhatsApp number to start receiving orders.', 'kanasta' ),
			esc_url( admin_url( 'admin.php?page=' . Kanasta_Settings::PAGE ) ),
			esc_html__( 'Add number', 'kanasta' )
		);
	}

	/**
	 * Pro features box under the settings form (free version only).
	 */
	public static function pro_box() {
		if ( Kanasta_Plugin::is_pro() ) {
			return;
		}

		$features = array(
			__( 'Several WhatsApp numbers (sales team) with automatic rotation', 'kanasta' ),
			__( 'Business hours: hide the buttons or show a notice when you are closed', 'kanasta' ),
			__( 'One-click WhatsApp messages to the customer from the order screen (order confirmed, shipped, ready for pickup, payment reminder)', 'kanasta' ),
		);
		?>
		<div class="card" style="max-width:640px;margin-top:2em;">
			<h2><?php esc_html_e( 'Kanasta Pro', 'kanasta' ); ?></h2>
			<ul style="list-style:disc;padding-left:1.5em;">
				<?php foreach ( $features as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( Kanasta_Plugin::upgrade_url() ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( Kanasta_Plugin::upgrade_url() ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'kanasta' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
