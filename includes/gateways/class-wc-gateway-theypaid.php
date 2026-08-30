<?php

defined( 'ABSPATH' ) || exit;

class WC_Gateway_TheyPaid extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'theypaid';
		$this->method_title       = __( 'TheyPaid Crypto', 'theypaid-payments' );
		$this->method_description = __(
			'Accept cryptocurrency payments via the TheyPaid non-custodial gateway. Payments go directly to your own wallet.',
			'theypaid-payments'
		);
		$this->has_fields = true;
		$this->supports   = [ 'products' ];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Pay with Crypto', 'theypaid-payments' ) );
		$this->description = $this->get_option( 'description', __( 'Pay using Bitcoin, USDT, and other cryptocurrencies.', 'theypaid-payments' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_assets' ] );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'     => [
				'title'   => __( 'Enable / Disable', 'theypaid-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable TheyPaid Crypto Payments', 'theypaid-payments' ),
				'default' => 'no',
			],
			'title'       => [
				'title'       => __( 'Title', 'theypaid-payments' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown to the customer during checkout.', 'theypaid-payments' ),
				'default'     => __( 'Pay with Crypto', 'theypaid-payments' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'theypaid-payments' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown to the customer during checkout.', 'theypaid-payments' ),
				'default'     => __( 'Pay using Bitcoin, USDT, and other cryptocurrencies.', 'theypaid-payments' ),
				'desc_tip'    => true,
			],
			'api_key'     => [
				'title'       => __( 'API Key', 'theypaid-payments' ),
				'type'        => 'password',
				'description' => __(
					'Your TheyPaid merchant API key. Found in your dashboard at dashboard.theypaid.io.',
					'theypaid-payments'
				),
				'desc_tip'    => true,
			],
			'tolerance'   => [
				'title'             => __( 'Underpayment Tolerance (USD)', 'theypaid-payments' ),
				'type'              => 'text',
				'description'       => __(
					'Maximum underpayment amount (in USD) that will still be accepted. Maps to acceptToleranceInTether.',
					'theypaid-payments'
				),
				'default'           => '0.10',
				'desc_tip'          => true,
				'custom_attributes' => [
					'inputmode' => 'decimal',
					'pattern'   => '[0-9]*\.?[0-9]+',
				],
			],
			'expires_mins' => [
				'title'             => __( 'Invoice Expiry (minutes)', 'theypaid-payments' ),
				'type'              => 'number',
				'description'       => __(
					'Requested invoice lifetime. The payment page timer uses the expiry TheyPaid returns (expiresAt).',
					'theypaid-payments'
				),
				'default'           => '60',
				'desc_tip'          => true,
				'custom_attributes' => [
					'min'  => '5',
					'max'  => '1440',
					'step' => '1',
				],
			],
			'test_connection' => [
				'title'       => __( 'Test Connection', 'theypaid-payments' ),
				'type'        => 'title',
				'description' => sprintf(
					'<button type="button" id="theypaid-test-connection" class="button">%s</button> <span id="theypaid-test-result"></span>',
					esc_html__( 'Test Connection', 'theypaid-payments' )
				),
			],
			'info_note' => [
				'title'       => __( 'Note', 'theypaid-payments' ),
				'type'        => 'title',
				'description' => __(
					'Enabled cryptocurrencies are configured in your TheyPaid dashboard (dashboard.theypaid.io) — this plugin always fetches the live list.',
					'theypaid-payments'
				),
			],
			'webhook_note' => [
				'title'       => __( 'Payment detection', 'theypaid-payments' ),
				'type'        => 'title',
				'description' => __(
					'The callback URL is sent automatically when creating invoices. Your site must be publicly reachable for payment detection to work. Local setups (e.g. .local domains) need Live Link or ngrok.',
					'theypaid-payments'
				),
			],
		];
	}

	public function enqueue_checkout_assets(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'theypaid-checkout',
			THEYPAID_PLUGIN_URL . 'assets/css/checkout.css',
			[],
			THEYPAID_VERSION
		);

		wp_enqueue_script(
			'theypaid-checkout',
			THEYPAID_PLUGIN_URL . 'assets/js/checkout.js',
			[ 'jquery' ],
			THEYPAID_VERSION,
			true
		);
	}

	public function is_available(): bool {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return ! empty( $this->get_option( 'api_key' ) );
	}

	public function payment_fields(): void {
		if ( $this->description ) {
			echo '<p>' . wp_kses_post( $this->description ) . '</p>';
		}

		$assets = $this->get_cached_assets();

		if ( is_wp_error( $assets ) ) {
			echo '<p class="theypaid-error">'
				. esc_html__( 'Unable to load available cryptocurrencies. Please try again later.', 'theypaid-payments' )
				. '</p>';
			return;
		}

		if ( empty( $assets ) ) {
			echo '<p class="theypaid-error">'
				. esc_html__( 'No cryptocurrencies are currently enabled. Please contact the store owner.', 'theypaid-payments' )
				. '</p>';
			return;
		}

		echo '<div class="theypaid-coin-selector">';
		echo '<p class="theypaid-coin-selector__label">'
			. esc_html__( 'Select cryptocurrency:', 'theypaid-payments' )
			. '</p>';
		echo '<div class="theypaid-coin-selector__grid">';

		foreach ( $assets as $index => $asset ) {
			$asset_id = esc_attr( $asset['assetId'] ?? '' );
			$symbol   = esc_html( $asset['symbol'] ?? '' );
			$network  = esc_html( $asset['network'] ?? '' );
			$logo_url = esc_url( $asset['logo']['medium']['url'] ?? $asset['logo']['original']['url'] ?? '' );
			$checked  = 0 === $index ? 'checked' : '';

			echo '<label class="theypaid-coin-selector__item">';
			printf(
				'<input type="radio" name="theypaid_asset_id" value="%s" %s />',
				$asset_id,
				$checked
			);
			echo '<span class="theypaid-coin-selector__card">';
			if ( $logo_url ) {
				printf(
					'<img src="%s" alt="%s" class="theypaid-coin-selector__logo" width="32" height="32" />',
					$logo_url,
					$symbol
				);
			}
			echo '<span class="theypaid-coin-selector__info">';
			printf( '<span class="theypaid-coin-selector__symbol">%s</span>', $symbol );
			printf( '<span class="theypaid-coin-selector__network">%s</span>', $network );
			echo '</span>';
			echo '</span>';
			echo '</label>';
		}

		echo '</div>';
		echo '</div>';
	}

	public function validate_fields(): bool {
		$asset_id = sanitize_text_field( wp_unslash( $_POST['theypaid_asset_id'] ?? '' ) );

		if ( empty( $asset_id ) ) {
			wc_add_notice(
				__( 'Please select a cryptocurrency to pay with.', 'theypaid-payments' ),
				'error'
			);
			return false;
		}

		return true;
	}

	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'theypaid-payments' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$api = theypaid_get_api();
		if ( ! $api ) {
			wc_add_notice( __( 'Payment gateway is not configured.', 'theypaid-payments' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$asset_id     = sanitize_text_field( wp_unslash( $_POST['theypaid_asset_id'] ?? '' ) );
		$tolerance    = floatval( $this->get_option( 'tolerance', '0.10' ) );
		$expires_mins = absint( $this->get_option( 'expires_mins', '60' ) );
		$webhook_url  = get_rest_url( null, 'theypaid/v1/webhook' );

		$result = $api->create_invoice( [
			'assetId'                 => $asset_id,
			'amountInTether'          => floatval( $order->get_total() ),
			'acceptToleranceInTether' => $tolerance,
			'expiresMins'             => $expires_mins ?: 60,
			'callbackUrl'             => $webhook_url,
			'description'             => sprintf( 'WooCommerce order #%s', $order->get_order_number() ),
			'redirectUrl'             => $order->get_checkout_order_received_url(),
		] );

		if ( is_wp_error( $result ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: error message */
					__( 'Payment error: %s', 'theypaid-payments' ),
					$result->get_error_message()
				),
				'error'
			);
			return [ 'result' => 'failure' ];
		}

		$order->update_meta_data( '_theypaid_invoice_id', sanitize_text_field( $result['id'] ?? '' ) );
		$order->update_meta_data( '_theypaid_asset_symbol', sanitize_text_field( $result['asset']['symbol'] ?? '' ) );
		$order->update_meta_data( '_theypaid_asset_network', sanitize_text_field( $result['asset']['network'] ?? '' ) );
		$order->update_meta_data( '_theypaid_crypto_amount', sanitize_text_field( $result['amount'] ?? '' ) );
		$order->update_meta_data( '_theypaid_crypto_address', sanitize_text_field( $result['address'] ?? '' ) );
		$order->update_meta_data( '_theypaid_crypto_tag', sanitize_text_field( $result['tag'] ?? '' ) );

		$asset_logo = $result['asset']['logo']['medium']['url'] ?? $result['asset']['logo']['original']['url'] ?? '';
		$order->update_meta_data( '_theypaid_asset_logo', esc_url_raw( $asset_logo ) );
		$order->update_meta_data( '_theypaid_amount_usd', floatval( $order->get_total() ) );
		$order->update_meta_data( '_theypaid_expires_at', theypaid_invoice_expires_at( $result, $expires_mins ?: 60 ) );

		if ( ! empty( $result['paymentLink'] ) ) {
			$order->update_meta_data( '_theypaid_payment_link', esc_url_raw( (string) $result['paymentLink'] ) );
		}

		$order->set_status( 'pending', __( 'Awaiting TheyPaid crypto payment.', 'theypaid-payments' ) );
		$order->save();

		WC()->cart->empty_cart();

		return [
			'result'   => 'success',
			'redirect' => $this->get_pending_payment_url( $order ),
		];
	}

	public function can_refund_order( $order ): bool {
		return false;
	}

	public function get_pending_payment_url( WC_Order $order ): string {
		return add_query_arg( [
			'theypaid_pay' => $order->get_id(),
			'key'          => $order->get_order_key(),
		], wc_get_checkout_url() );
	}

	private function get_cached_assets(): array|WP_Error {
		$cached = get_transient( 'theypaid_store_assets' );

		if ( false !== $cached ) {
			return $cached;
		}

		$api = theypaid_get_api();
		if ( ! $api ) {
			return new WP_Error( 'theypaid_no_api', __( 'Gateway not configured.', 'theypaid-payments' ) );
		}

		$assets = $api->get_store_assets();

		if ( ! is_wp_error( $assets ) ) {
			set_transient( 'theypaid_store_assets', $assets, 5 * MINUTE_IN_SECONDS );
		}

		return $assets;
	}
}
