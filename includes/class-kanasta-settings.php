<?php
/**
 * Settings storage and settings screen (WooCommerce → Kanasta).
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores all options in a single `kanasta_settings` array.
 */
class Kanasta_Settings {

	const OPTION = 'kanasta_settings';
	const PAGE   = 'kanasta';

	/**
	 * Cached settings for the request.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'option_page_capability_' . self::PAGE, array( __CLASS__, 'capability' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Settings sections, filterable so Pro can add its own.
	 *
	 * @return array<string,array{title:string,description?:string}>
	 */
	public static function sections() {
		$sections = array(
			'general' => array(
				'title' => __( 'WhatsApp number', 'kanasta' ),
			),
			'order'   => array(
				'title'       => __( 'Checkout: "Order via WhatsApp"', 'kanasta' ),
				'description' => sprintf(
					/* translators: %s: link to the WooCommerce payments settings. */
					__( 'Customers choose "Order via WhatsApp" at checkout: the order is created in WooCommerce and they are sent to WhatsApp with this message. Enable it and change its title in %s.', 'kanasta' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kanasta' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Payments', 'kanasta' ) . '</a>'
				),
			),
			'product' => array(
				'title' => __( 'Product page button', 'kanasta' ),
			),
			'cart'    => array(
				'title' => __( 'Cart page button', 'kanasta' ),
			),
			'float'   => array(
				'title'       => __( 'Floating chat button', 'kanasta' ),
				'description' => __( 'A round WhatsApp button fixed in a corner of every page, so visitors can ask you anything.', 'kanasta' ),
			),
		);

		/**
		 * Filters the settings sections.
		 *
		 * @param array $sections Sections keyed by id.
		 */
		return apply_filters( 'kanasta_settings_sections', $sections );
	}

	/**
	 * Settings fields, filterable so Pro can add its own.
	 *
	 * @return array<string,array> Fields keyed by option key.
	 */
	public static function fields() {
		$order_tags   = '{order_number} {order_date} {items} {subtotal} {shipping} {discount} {total} {customer_name} {customer_phone} {customer_email} {billing_address} {shipping_address} {customer_note} {order_url} {site_name}';
		$product_tags = '{product_name} {variation} {quantity} {price} {sku} {product_url} {site_name}';
		$cart_tags    = '{items} {subtotal} {total} {cart_url} {site_name}';
		$float_tags   = '{page_title} {page_url} {site_name}';

		$fields = array(
			'phone'               => array(
				'section'     => 'general',
				'type'        => 'phone',
				'label'       => __( 'Store WhatsApp number', 'kanasta' ),
				'description' => __( 'Full international number with country code, e.g. 595981123456. Spaces, "+" and dashes are removed automatically.', 'kanasta' ),
				'default'     => '',
				'placeholder' => '595981123456',
			),
			'order_template'      => array(
				'section'     => 'order',
				'type'        => 'textarea',
				'label'       => __( 'Order message', 'kanasta' ),
				/* translators: %s: list of placeholders. */
				'description' => sprintf( __( 'Available tags: %s. Lines whose tags are all empty are removed. Use *asterisks* for bold text in WhatsApp.', 'kanasta' ), '<code>' . $order_tags . '</code>' ),
				'default'     => __( "Hello! I just placed order *#{order_number}* on {site_name}.\n\n{items}\n\nSubtotal: {subtotal}\nShipping: {shipping}\nDiscount: {discount}\n*Total: {total}*\n\nName: {customer_name}\nPhone: {customer_phone}\nAddress: {shipping_address}\nNote: {customer_note}", 'kanasta' ),
			),
			'product_button'      => array(
				'section' => 'product',
				'type'    => 'checkbox',
				'label'   => __( 'Show the button on product pages', 'kanasta' ),
				'default' => 'yes',
			),
			'product_button_text' => array(
				'section' => 'product',
				'type'    => 'text',
				'label'   => __( 'Button text', 'kanasta' ),
				'default' => __( 'Order via WhatsApp', 'kanasta' ),
			),
			'product_template'    => array(
				'section'     => 'product',
				'type'        => 'textarea',
				'label'       => __( 'Message', 'kanasta' ),
				/* translators: %s: list of placeholders. */
				'description' => sprintf( __( 'Available tags: %s', 'kanasta' ), '<code>' . $product_tags . '</code>' ),
				'default'     => __( "Hello! I'd like to order:\n*{product_name}*\nOptions: {variation}\nQuantity: {quantity}\nPrice: {price}\n{product_url}", 'kanasta' ),
			),
			'cart_button'         => array(
				'section' => 'cart',
				'type'    => 'checkbox',
				'label'   => __( 'Show the button on the cart page', 'kanasta' ),
				'default' => 'yes',
			),
			'cart_button_text'    => array(
				'section' => 'cart',
				'type'    => 'text',
				'label'   => __( 'Button text', 'kanasta' ),
				'default' => __( 'Send cart via WhatsApp', 'kanasta' ),
			),
			'cart_template'       => array(
				'section'     => 'cart',
				'type'        => 'textarea',
				'label'       => __( 'Message', 'kanasta' ),
				/* translators: %s: list of placeholders. */
				'description' => sprintf( __( 'Available tags: %s', 'kanasta' ), '<code>' . $cart_tags . '</code>' ),
				'default'     => __( "Hello! I'd like to order these products:\n\n{items}\n\n*Total: {total}*", 'kanasta' ),
			),
			'float_button'        => array(
				'section' => 'float',
				'type'    => 'checkbox',
				'label'   => __( 'Show the floating button', 'kanasta' ),
				'default' => 'no',
			),
			'float_position'      => array(
				'section' => 'float',
				'type'    => 'select',
				'label'   => __( 'Position', 'kanasta' ),
				'default' => 'right',
				'options' => array(
					'right' => __( 'Bottom right', 'kanasta' ),
					'left'  => __( 'Bottom left', 'kanasta' ),
				),
			),
			'float_text'          => array(
				'section'     => 'float',
				'type'        => 'text',
				'label'       => __( 'Button text', 'kanasta' ),
				'description' => __( 'Shown next to the icon on computers. Leave empty for an icon-only button.', 'kanasta' ),
				'default'     => __( 'Chat with us', 'kanasta' ),
			),
			'float_template'      => array(
				'section'     => 'float',
				'type'        => 'textarea',
				'rows'        => 4,
				'label'       => __( 'Message', 'kanasta' ),
				/* translators: %s: list of placeholders. */
				'description' => sprintf( __( 'Available tags: %s', 'kanasta' ), '<code>' . $float_tags . '</code>' ),
				'default'     => __( "Hello! I have a question.\n{page_title}\n{page_url}", 'kanasta' ),
			),
			'float_hide_checkout' => array(
				'section'        => 'float',
				'type'           => 'checkbox',
				'label'          => __( 'Cart and checkout', 'kanasta' ),
				'checkbox_label' => __( 'Hide on the cart and checkout pages', 'kanasta' ),
				'default'        => 'yes',
			),
		);

		/**
		 * Filters the settings fields.
		 *
		 * @param array $fields Fields keyed by option key.
		 */
		return apply_filters( 'kanasta_settings_fields', $fields );
	}

	/**
	 * Get a setting value (falls back to the field default).
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public static function get( $key ) {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = is_array( $saved ) ? $saved : array();
		}

		if ( array_key_exists( $key, self::$settings ) ) {
			return self::$settings[ $key ];
		}

		$fields = self::fields();
		return isset( $fields[ $key ]['default'] ) ? $fields[ $key ]['default'] : '';
	}

	/**
	 * Whether a checkbox setting is on.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Drop the request cache after the option changes.
	 */
	public static function flush_cache() {
		self::$settings = null;
	}

	/**
	 * Shop managers can edit these settings too.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Add WooCommerce → Kanasta.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Kanasta – WhatsApp orders', 'kanasta' ),
			__( 'Kanasta', 'kanasta' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the option, sections and fields with the Settings API.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		foreach ( self::sections() as $id => $section ) {
			add_settings_section(
				'kanasta_' . $id,
				$section['title'],
				static function () use ( $section ) {
					if ( ! empty( $section['description'] ) ) {
						echo '<p>' . wp_kses_post( $section['description'] ) . '</p>';
					}
				},
				self::PAGE
			);
		}

		foreach ( self::fields() as $key => $field ) {
			add_settings_field(
				'kanasta_' . $key,
				esc_html( $field['label'] ),
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'kanasta_' . $field['section'],
				array(
					'key'       => $key,
					'field'     => $field,
					'label_for' => 'checkbox' === $field['type'] ? '' : 'kanasta-' . $key,
				)
			);
		}
	}

	/**
	 * Sanitize the submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		// Keep values of fields that are not registered right now (e.g. Pro settings while Pro is inactive).
		$saved = get_option( self::OPTION, array() );
		$clean = is_array( $saved ) ? array_diff_key( $saved, self::fields() ) : array();

		foreach ( self::fields() as $key => $field ) {
			$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';

			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = 'yes' === $value ? 'yes' : 'no';
					break;
				case 'phone':
					$clean[ $key ] = Kanasta_Message::sanitize_phone( $value );
					if ( '' !== $clean[ $key ] && strlen( $clean[ $key ] ) < 8 ) {
						add_settings_error( self::OPTION, 'kanasta_phone', __( 'The WhatsApp number looks too short. Remember to include the country code.', 'kanasta' ) );
					}
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'select':
					$clean[ $key ] = array_key_exists( $value, $field['options'] ) ? $value : $field['default'];
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}

			if ( isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
				$clean[ $key ] = call_user_func( $field['sanitize'], $clean[ $key ], $value );
			}
		}

		return $clean;
	}

	/**
	 * Render a single field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( $args ) {
		$key   = $args['key'];
		$field = $args['field'];
		$id    = 'kanasta-' . $key;
		$name  = self::OPTION . '[' . $key . ']';
		$value = self::get( $key );

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="yes" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 'yes', $value, false ),
					esc_html( isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : __( 'Enabled', 'kanasta' ) )
				);
				break;
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text code">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					isset( $field['rows'] ) ? (int) $field['rows'] : 8,
					esc_textarea( $value )
				);
				break;
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option_value ), selected( $option_value, $value, false ), esc_html( $option_label ) );
				}
				echo '</select>';
				break;
			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" placeholder="%5$s" />',
					'phone' === $field['type'] ? 'tel' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' )
				);
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field['description'] ) . '</p>';
		}
	}

	/**
	 * Render the settings screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap kanasta-settings">
			<h1><?php esc_html_e( 'Kanasta – WhatsApp orders', 'kanasta' ); ?></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
			<?php
			/**
			 * Fires after the settings form (used for the Pro upgrade box).
			 */
			do_action( 'kanasta_settings_page_after' );
			?>
		</div>
		<?php
	}
}
