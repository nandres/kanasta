<?php
/**
 * "Order via WhatsApp" checkout method.
 *
 * @package Kanasta
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the order in WooCommerce, then sends the customer to WhatsApp with the order details.
 */
class Kanasta_Gateway extends WC_Payment_Gateway {

	/**
	 * Text shown on the order received page and in emails.
	 *
	 * @var string
	 */
	public $instructions = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'kanasta';
		$this->has_fields         = false;
		$this->method_title       = __( 'Order via WhatsApp (Kanasta)', 'kanasta' );
		$this->method_description = __( 'The order is created in WooCommerce and the customer is sent to WhatsApp with all the order details, so you can agree on payment and delivery in the chat.', 'kanasta' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Gateway settings (WooCommerce → Settings → Payments).
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'kanasta' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable "Order via WhatsApp" at checkout', 'kanasta' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __( 'Title', 'kanasta' ),
				'type'        => 'safe_text',
				'description' => __( 'Name of the option customers see at checkout.', 'kanasta' ),
				'default'     => __( 'Order via WhatsApp', 'kanasta' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'Description', 'kanasta' ),
				'type'        => 'textarea',
				'description' => __( 'Shown below the option at checkout.', 'kanasta' ),
				'default'     => __( 'Place your order and we will open WhatsApp so you can send it to us. We agree on payment and delivery in the chat.', 'kanasta' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Order received text', 'kanasta' ),
				'type'        => 'textarea',
				'description' => __( 'Shown on the order received page and in the order email, above the WhatsApp button.', 'kanasta' ),
				'default'     => __( 'Your order is reserved. Send it to us on WhatsApp to confirm payment and delivery.', 'kanasta' ),
				'desc_tip'    => true,
			),
			'order_status' => array(
				'title'       => __( 'Order status', 'kanasta' ),
				'type'        => 'select',
				'description' => __( '"On hold" reserves stock and waits for you to confirm payment. "Pending payment" does not reduce stock.', 'kanasta' ),
				'default'     => 'on-hold',
				'options'     => array(
					'on-hold'    => _x( 'On hold', 'Order status', 'kanasta' ),
					'pending'    => _x( 'Pending payment', 'Order status', 'kanasta' ),
					'processing' => _x( 'Processing', 'Order status', 'kanasta' ),
				),
				'desc_tip'    => true,
			),
			'auto_open'    => array(
				'title'   => __( 'Open WhatsApp automatically', 'kanasta' ),
				'type'    => 'checkbox',
				'label'   => __( 'Open WhatsApp automatically on the order received page (a button is always shown too)', 'kanasta' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Only offer the method when a WhatsApp number is configured.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && Kanasta_Plugin::is_available( 'order' );
	}

	/**
	 * Place the order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );
		$status = $this->get_option( 'order_status', 'on-hold' );

		if ( 'pending' === $status ) {
			$order->add_order_note( __( 'Order placed via WhatsApp. Waiting for the customer to confirm in the chat.', 'kanasta' ) );
			$order->save();
		} else {
			$order->update_status( $status, __( 'Order placed via WhatsApp. Waiting for the customer to confirm in the chat.', 'kanasta' ) );
		}

		/**
		 * Fires after an order is placed with "Order via WhatsApp" (before the customer is redirected).
		 *
		 * @param WC_Order $order Order.
		 */
		do_action( 'kanasta_order_placed', $order );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Order received page: instructions, WhatsApp button and optional auto-open.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$auto_open = 'yes' === $this->get_option( 'auto_open' ) && ! $order->get_meta( '_kanasta_opened' );
		if ( $auto_open ) {
			$order->update_meta_data( '_kanasta_opened', time() );
			$order->save();
			wp_enqueue_script( 'kanasta-thankyou', KANASTA_URL . 'assets/js/thankyou.js', array(), KANASTA_VERSION, true );
		}
		wp_enqueue_style( 'kanasta', KANASTA_URL . 'assets/css/kanasta.css', array(), KANASTA_VERSION );

		echo '<div class="kanasta-thankyou">';
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
		printf(
			'<a href="%1$s" class="kanasta-button kanasta-thankyou-button" data-order="%2$s" data-autoopen="%3$s" rel="nofollow noopener" target="_blank">%4$s</a>',
			Kanasta_Message::esc_url( Kanasta_Message::order_url( $order ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_attr() inside.
			esc_attr( $order->get_id() ),
			$auto_open ? '1' : '0',
			esc_html__( 'Send my order via WhatsApp', 'kanasta' )
		);
		Kanasta_Buttons::render_notice( 'kanasta-thankyou-button' );
		echo '</div>';
	}

	/**
	 * Add the WhatsApp link to customer emails while the order still waits for confirmation.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether the email goes to the admin.
	 * @param bool     $plain_text    Plain text email.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() || ! $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			return;
		}

		$url = Kanasta_Message::order_url( $order );

		if ( $plain_text ) {
			echo esc_html( wp_strip_all_tags( $this->instructions ) ) . "\n" . esc_html( Kanasta_Message::safe_url( $url ) ) . "\n\n";
			return;
		}

		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
		printf(
			'<p><a href="%1$s" style="display:inline-block;padding:12px 20px;background:#25d366;color:#ffffff;border-radius:6px;text-decoration:none;font-weight:bold;">%2$s</a></p>',
			Kanasta_Message::esc_url( $url ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_attr() inside.
			esc_html__( 'Send my order via WhatsApp', 'kanasta' )
		);
	}
}
