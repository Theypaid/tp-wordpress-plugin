<?php

defined( 'ABSPATH' ) || exit;

class EDD_Gateway_TheyPaid {

	public function __construct() {
		add_filter( 'edd_payment_gateways', [ $this, 'register_gateway' ] );
		add_action( 'edd_theypaid_cc_form', [ $this, 'payment_form' ] );
		add_action( 'edd_gateway_theypaid', [ $this, 'process_payment' ] );
		add_filter( 'edd_settings_gateways', [ $this, 'gateway_settings' ] );
		add_action( 'edd_payment_receipt_after', [ $this, 'payment_receipt' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_gateway( array $gateways ): array {
		$gateways['theypaid'] = [
			'admin_label'    => __( 'TheyPaid Crypto', 'theypaid-payments' ),
			'checkout_label' => __( 'Pay with Crypto', 'theypaid-payments' ),
			'supports'       => [ 'buy_now' ],
		];
		return $gateways;
	}

	public function gateway_settings( array $settings ): array {
		$theypaid_settings = [
			'theypaid_header' => [
				'id'   => 'theypaid_header',
				'name' => '<strong>' . __( 'TheyPaid Crypto Payments', 'theypaid-payments' ) . '</strong>',
				'type' => 'header',
			],
			'theypaid_api_key' => [
				'id'   => 'theypaid_api_key',
				'name' => __( 'API Key', 'theypaid-payments' ),
				'desc' => __( 'Your TheyPaid merchant API key from merchant.theypaid.io.', 'theypaid-payments' ),
				'type' => 'password',
				'size' => 'regular',
			],
			'theypaid_tolerance' => [
				'id'   => 'theypaid_tolerance',
				'name' => __( 'Underpayment Tolerance (USD)', 'theypaid-payments' ),
				'desc' => __( 'Maximum underpayment (in USD) that will still be accepted.', 'theypaid-payments' ),
				'type' => 'text',
				'size' => 'small',
				'std'  => '0.10',
			],
			'theypaid_expires_mins' => [
				'id'   => 'theypaid_expires_mins',
				'name' => __( 'Invoice Expiry (minutes)', 'theypaid-payments' ),
				'desc' => __( 'How long an invoice stays active before it expires.', 'theypaid-payments' ),
				'type' => 'number',
				'size' => 'small',
				'std'  => '60',
				'min'  => 5,
				'max'  => 1440,
				'step' => 1,
			],
			'theypaid_note' => [
				'id'   => 'theypaid_note',
				'name' => __( 'Note', 'theypaid-payments' ),
				'desc' => __( 'Enabled coins are configured in your TheyPaid merchant dashboard.', 'theypaid-payments' ),
				'type' => 'descriptive_text',
			],
			'theypaid_webhook_note' => [
				'id'   => 'theypaid_webhook_note',
				'name' => __( 'Payment detection', 'theypaid-payments' ),
				'desc' => __( 'The callback URL is sent automatically when creating invoices. Your site must be publicly reachable for payment detection to work. Local setups (e.g. .local domains) need Live Link or ngrok.', 'theypaid-payments' ),
				'type' => 'descriptive_text',
			],
		];

		return array_merge( $settings, $theypaid_settings );
	}

	public function enqueue_assets(): void {
		if ( ! edd_is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'theypaid-checkout',
			THEYPAID_PLUGIN_URL . 'assets/css/checkout.css',
			[],
			THEYPAID_VERSION
		);
	}

	public function payment_form(): void {
		$assets = $this->get_cached_assets();

		if ( is_wp_error( $assets ) || empty( $assets ) ) {
			echo '<p class="theypaid-error">'
				. esc_html__( 'Unable to load available cryptocurrencies. Please try again later.', 'theypaid-payments' )
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

	public function process_payment( array $purchase_data ): void {
		$asset_id = sanitize_text_field( wp_unslash( $_POST['theypaid_asset_id'] ?? '' ) );
		if ( empty( $asset_id ) ) {
			edd_set_error( 'theypaid_no_asset', __( 'Please select a cryptocurrency to pay with.', 'theypaid-payments' ) );
			edd_send_back_to_checkout( '?payment-mode=theypaid' );
			return;
		}

		$payment_data = [
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending',
			'gateway'      => 'theypaid',
		];

		$payment_id = edd_insert_payment( $payment_data );
		if ( ! $payment_id ) {
			edd_set_error( 'theypaid_payment_error', __( 'Could not create payment record.', 'theypaid-payments' ) );
			edd_send_back_to_checkout( '?payment-mode=theypaid' );
			return;
		}

		$api = $this->get_api();
		if ( ! $api ) {
			edd_set_error( 'theypaid_no_api', __( 'Payment gateway is not configured.', 'theypaid-payments' ) );
			edd_send_back_to_checkout( '?payment-mode=theypaid' );
			return;
		}

		$tolerance    = floatval( edd_get_option( 'theypaid_tolerance', '0.10' ) );
		$expires_mins = absint( edd_get_option( 'theypaid_expires_mins', '60' ) );
		$webhook_url  = get_rest_url( null, 'theypaid/v1/webhook' );

		$result = $api->create_invoice( [
			'assetId'                 => $asset_id,
			'amountInTether'          => floatval( $purchase_data['price'] ),
			'acceptToleranceInTether' => $tolerance,
			'expiresMins'             => $expires_mins ?: 60,
			'callbackUrl'             => $webhook_url,
		] );

		if ( is_wp_error( $result ) ) {
			edd_set_error( 'theypaid_api_error', $result->get_error_message() );
			edd_send_back_to_checkout( '?payment-mode=theypaid' );
			return;
		}

		edd_update_payment_meta( $payment_id, '_theypaid_invoice_id', sanitize_text_field( $result['id'] ?? '' ) );
		edd_update_payment_meta( $payment_id, '_theypaid_asset_symbol', sanitize_text_field( $result['asset']['symbol'] ?? '' ) );
		edd_update_payment_meta( $payment_id, '_theypaid_asset_network', sanitize_text_field( $result['asset']['network'] ?? '' ) );
		edd_update_payment_meta( $payment_id, '_theypaid_crypto_amount', sanitize_text_field( $result['amount'] ?? '' ) );
		edd_update_payment_meta( $payment_id, '_theypaid_crypto_address', sanitize_text_field( $result['address'] ?? '' ) );
		edd_update_payment_meta( $payment_id, '_theypaid_crypto_tag', sanitize_text_field( $result['tag'] ?? '' ) );

		$asset_logo = $result['asset']['logo']['medium']['url'] ?? $result['asset']['logo']['original']['url'] ?? '';
		edd_update_payment_meta( $payment_id, '_theypaid_asset_logo', esc_url_raw( $asset_logo ) );
		edd_update_payment_meta( $payment_id, '_theypaid_amount_usd', floatval( $purchase_data['price'] ) );

		$expires_at = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( $expires_mins * 60 ) );
		edd_update_payment_meta( $payment_id, '_theypaid_expires_at', $expires_at );

		edd_insert_payment_note(
			$payment_id,
			sprintf(
				/* translators: %s: invoice ID */
				__( 'TheyPaid invoice created: %s', 'theypaid-payments' ),
				$result['id'] ?? ''
			)
		);

		edd_send_to_success_page();
	}

	public function payment_receipt( $payment ): void {
		$payment_id = is_object( $payment ) ? $payment->ID : $payment;
		$payment_obj = edd_get_payment( $payment_id );

		if ( ! $payment_obj || 'theypaid' !== $payment_obj->gateway ) {
			return;
		}

		if ( 'pending' !== $payment_obj->status ) {
			return;
		}

		$invoice_id    = edd_get_payment_meta( $payment_id, '_theypaid_invoice_id', true );
		$symbol        = edd_get_payment_meta( $payment_id, '_theypaid_asset_symbol', true );
		$network       = edd_get_payment_meta( $payment_id, '_theypaid_asset_network', true );
		$crypto_amount = edd_get_payment_meta( $payment_id, '_theypaid_crypto_amount', true );
		$address       = edd_get_payment_meta( $payment_id, '_theypaid_crypto_address', true );
		$tag           = edd_get_payment_meta( $payment_id, '_theypaid_crypto_tag', true );
		$logo_url      = edd_get_payment_meta( $payment_id, '_theypaid_asset_logo', true );
		$amount_usd    = edd_get_payment_meta( $payment_id, '_theypaid_amount_usd', true );
		$expires_at    = edd_get_payment_meta( $payment_id, '_theypaid_expires_at', true );

		if ( empty( $invoice_id ) || empty( $address ) ) {
			return;
		}

		$has_tag   = ! empty( $tag );
		$qr_data   = theypaid_build_payment_uri( $symbol, $address, $crypto_amount, $tag );
		$qr_url    = 'https://api.qrserver.com/v1/create-qr-code/?data=' . rawurlencode( $qr_data ) . '&size=250x250&margin=8&ecc=H';

		wp_enqueue_style( 'theypaid-checkout', THEYPAID_PLUGIN_URL . 'assets/css/checkout.css', [], THEYPAID_VERSION );
		wp_enqueue_script( 'theypaid-checkout', THEYPAID_PLUGIN_URL . 'assets/js/checkout.js', [ 'jquery' ], THEYPAID_VERSION, true );

		wp_localize_script( 'theypaid-checkout', 'theypaidCheckout', [
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'theypaid_check_edd_status' ),
			'orderId'          => $payment_id,
			'orderReceivedUrl' => edd_get_success_page_uri(),
			'pollInterval'     => 15000,
			'isEdd'            => true,
			'cartUrl'          => edd_get_checkout_uri(),
			'i18n'             => [
				'paid'         => __( 'Payment confirmed!', 'theypaid-payments' ),
				'expired'      => __( 'This invoice has expired. Please place a new order.', 'theypaid-payments' ),
				'insufficient' => __( 'Underpayment detected. The store has been notified — please contact support.', 'theypaid-payments' ),
				'waiting'      => __( 'Waiting for payment…', 'theypaid-payments' ),
				'copied'       => __( 'Copied!', 'theypaid-payments' ),
			],
		] );
		?>
		<div id="theypaid-pending-page" class="theypaid-pending">
			<div class="theypaid-pending__card">

				<!-- Step indicator -->
				<div class="theypaid-pending__steps">
					<div class="theypaid-pending__step theypaid-pending__step--done">
						<span class="theypaid-pending__step-icon">&#10003;</span>
						<span class="theypaid-pending__step-text"><?php esc_html_e( 'Order placed', 'theypaid-payments' ); ?></span>
					</div>
					<div class="theypaid-pending__step-line"></div>
					<div class="theypaid-pending__step theypaid-pending__step--active" id="theypaid-step-pay">
						<span class="theypaid-pending__step-icon">2</span>
						<span class="theypaid-pending__step-text"><?php esc_html_e( 'Send payment', 'theypaid-payments' ); ?></span>
					</div>
					<div class="theypaid-pending__step-line"></div>
					<div class="theypaid-pending__step" id="theypaid-step-done">
						<span class="theypaid-pending__step-icon">3</span>
						<span class="theypaid-pending__step-text"><?php esc_html_e( 'Confirmed', 'theypaid-payments' ); ?></span>
					</div>
				</div>

				<!-- Header -->
				<div class="theypaid-pending__header">
					<div class="theypaid-pending__asset">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $symbol ); ?>" class="theypaid-pending__asset-logo" width="44" height="44" />
						<?php endif; ?>
						<div>
							<div class="theypaid-pending__asset-name"><?php echo esc_html( $symbol ); ?></div>
							<div class="theypaid-pending__asset-network"><?php echo esc_html( $network ); ?></div>
						</div>
					</div>
				</div>

				<!-- Status badge -->
				<div class="theypaid-pending__status">
					<span id="theypaid-status-text" class="theypaid-pending__status-text theypaid-pending__status-text--waiting">
						<span class="theypaid-pending__status-dot"></span>
						<span class="theypaid-pending__status-label"><?php esc_html_e( 'Waiting for payment…', 'theypaid-payments' ); ?></span>
					</span>
				</div>

				<!-- Do not close notice -->
				<p class="theypaid-pending__notice" id="theypaid-notice">
					<?php esc_html_e( 'Please do not close this page. It will update automatically once your payment is detected.', 'theypaid-payments' ); ?>
				</p>

				<!-- Payment details wrapper -->
				<div id="theypaid-payment-details" class="theypaid-pending__details">

					<!-- QR code -->
					<div class="theypaid-pending__qr">
						<div class="theypaid-pending__qr-frame">
							<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'Payment QR Code', 'theypaid-payments' ); ?>" width="250" height="250" class="theypaid-pending__qr-img" />
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="theypaid-pending__qr-logo" width="56" height="56" aria-hidden="true" />
							<?php endif; ?>
						</div>
						<p class="theypaid-pending__qr-hint"><?php esc_html_e( 'Scan with your wallet app or copy the address below', 'theypaid-payments' ); ?></p>
					</div>

					<!-- Address -->
					<div class="theypaid-pending__field">
						<span class="theypaid-pending__field-label"><?php esc_html_e( 'Send to address', 'theypaid-payments' ); ?></span>
						<div class="theypaid-pending__field-value">
							<span><?php echo esc_html( $address ); ?></span>
							<button type="button" class="theypaid-pending__copy-btn"><?php esc_html_e( 'Copy', 'theypaid-payments' ); ?></button>
						</div>
					</div>

					<?php if ( $has_tag ) : ?>
						<div class="theypaid-pending__tag-warning">
							<strong><?php esc_html_e( 'Memo / Tag (Required)', 'theypaid-payments' ); ?></strong>
							<?php esc_html_e( 'You MUST include this memo/tag with your transaction. Omitting it may result in permanently lost funds.', 'theypaid-payments' ); ?>
						</div>
						<div class="theypaid-pending__field">
							<span class="theypaid-pending__field-label"><?php esc_html_e( 'Memo / Tag', 'theypaid-payments' ); ?></span>
							<div class="theypaid-pending__field-value">
								<span><?php echo esc_html( $tag ); ?></span>
								<button type="button" class="theypaid-pending__copy-btn"><?php esc_html_e( 'Copy', 'theypaid-payments' ); ?></button>
							</div>
						</div>
					<?php endif; ?>

					<!-- Amounts -->
					<div class="theypaid-pending__amounts">
						<div class="theypaid-pending__field">
							<span class="theypaid-pending__field-label"><?php echo esc_html( sprintf(
								/* translators: %s: crypto symbol */
								__( 'Amount (%s)', 'theypaid-payments' ),
								$symbol
							) ); ?></span>
							<div class="theypaid-pending__field-value">
								<span><?php echo esc_html( $crypto_amount ); ?></span>
								<button type="button" class="theypaid-pending__copy-btn"><?php esc_html_e( 'Copy', 'theypaid-payments' ); ?></button>
							</div>
						</div>
						<div class="theypaid-pending__field">
							<span class="theypaid-pending__field-label"><?php esc_html_e( 'Amount (USD)', 'theypaid-payments' ); ?></span>
							<div class="theypaid-pending__field-value">
								<span>$<?php echo esc_html( number_format( (float) $amount_usd, 2 ) ); ?></span>
							</div>
						</div>
					</div>

				</div><!-- /.theypaid-pending__details -->

				<!-- Countdown -->
				<div class="theypaid-pending__countdown" id="theypaid-countdown">
					<div class="theypaid-pending__countdown-label"><?php esc_html_e( 'Time remaining', 'theypaid-payments' ); ?></div>
					<div id="theypaid-countdown-timer" class="theypaid-pending__countdown-timer" data-expires="<?php echo esc_attr( $expires_at ); ?>">--:--</div>
				</div>

				<!-- Success overlay -->
				<div id="theypaid-success-overlay" class="theypaid-pending__overlay theypaid-pending__overlay--hidden">
					<div class="theypaid-pending__overlay-icon theypaid-pending__overlay-icon--success">&#10003;</div>
					<p class="theypaid-pending__overlay-text"><?php esc_html_e( 'Payment confirmed! Redirecting…', 'theypaid-payments' ); ?></p>
				</div>

				<!-- Expired overlay -->
				<div id="theypaid-expired-overlay" class="theypaid-pending__overlay theypaid-pending__overlay--hidden">
					<div class="theypaid-pending__overlay-icon theypaid-pending__overlay-icon--expired">&#10005;</div>
					<p class="theypaid-pending__overlay-text"><?php esc_html_e( 'This invoice has expired.', 'theypaid-payments' ); ?></p>
					<a href="<?php echo esc_url( edd_get_checkout_uri() ); ?>" class="theypaid-pending__overlay-btn">
						<?php esc_html_e( 'Return to checkout', 'theypaid-payments' ); ?>
					</a>
				</div>

				<!-- Footer -->
				<div class="theypaid-pending__footer">
					<span><?php esc_html_e( 'Secured by', 'theypaid-payments' ); ?></span>
					<a href="https://theypaid.io" target="_blank" rel="noopener noreferrer">TheyPaid</a>
				</div>

			</div><!-- /.theypaid-pending__card -->
		</div>
		<?php
	}

	private function get_api(): ?TheyPaid_API {
		$api_key = edd_get_option( 'theypaid_api_key', '' );

		if ( empty( $api_key ) ) {
			return theypaid_get_api();
		}

		return new TheyPaid_API( $api_key );
	}

	private function get_cached_assets(): array|WP_Error {
		$cached = get_transient( 'theypaid_store_assets' );
		if ( false !== $cached ) {
			return $cached;
		}

		$api = $this->get_api();
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
