<?php
/**
 * Floating WhatsApp chat button.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Round button fixed in a corner of every front-end page.
 */
class Kanasta_Float {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
	}

	/**
	 * Whether the button shows on the current page.
	 *
	 * @return bool
	 */
	private static function show() {
		if ( is_admin() || ! Kanasta_Settings::enabled( 'float_button' ) || ! Kanasta_Plugin::is_available( 'chat' ) ) {
			return false;
		}
		$show = ! ( Kanasta_Settings::enabled( 'float_hide_checkout' ) && ( is_cart() || is_checkout() ) );

		/**
		 * Filters whether the floating WhatsApp button shows on the current page.
		 *
		 * @param bool $show Show the button.
		 */
		return (bool) apply_filters( 'kanasta_show_float_button', $show );
	}

	/**
	 * Load the button styles.
	 */
	public static function enqueue() {
		if ( self::show() ) {
			wp_enqueue_style( 'kanasta', KANASTA_URL . 'assets/css/kanasta.css', array(), KANASTA_VERSION );
		}
	}

	/**
	 * Address of the page being viewed, without query strings.
	 *
	 * @return string
	 */
	private static function current_url() {
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return (string) get_permalink( wc_get_page_id( 'shop' ) );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? home_url( '/' ) : $link;
		}

		global $wp;
		return home_url( $wp->request ? trailingslashit( $wp->request ) : '/' );
	}

	/**
	 * Print the button.
	 */
	public static function render() {
		if ( ! self::show() ) {
			return;
		}

		$text     = trim( (string) Kanasta_Settings::get( 'float_text' ) );
		$position = 'left' === Kanasta_Settings::get( 'float_position' ) ? 'left' : 'right';
		$classes  = 'kanasta-float kanasta-float--' . $position . ( '' === $text ? ' kanasta-float--icon' : '' );
		$notice   = Kanasta_Buttons::notice( 'kanasta-float' );

		printf(
			'<a href="%1$s" class="%2$s" target="_blank" rel="nofollow noopener" aria-label="%3$s"%4$s>%5$s</a>',
			esc_url( Kanasta_Redirect::url( 'chat', array( 'ref' => self::current_url() ) ) ),
			esc_attr( $classes ),
			esc_attr( '' === $text ? __( 'Chat on WhatsApp', 'kanasta' ) : $text ),
			'' === $notice ? '' : ' title="' . esc_attr( $notice ) . '"',
			'' === $text ? '' : '<span class="kanasta-float__text">' . esc_html( $text ) . '</span>'
		);
	}
}
