<?php
/**
 * Plugin Name: TheyPaid Crypto Payments
 * Plugin URI:  https://theypaid.io
 * Description: Accept cryptocurrency payments via the TheyPaid non-custodial payment gateway. Payments go directly to your own wallet — no KYC required.
 * Version:     1.1.0
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * Author:      TheyPaid
 * Author URI:  https://theypaid.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: theypaid-payments
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'THEYPAID_VERSION', '1.1.0' );
define( 'THEYPAID_PLUGIN_FILE', __FILE__ );
define( 'THEYPAID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'THEYPAID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'THEYPAID_API_BASE', 'https://merchant.theypaid.io' );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
} );

function theypaid_missing_platform_notice() {
	echo '<div class="error"><p>';
	echo esc_html__(
		'TheyPaid Crypto Payments requires WooCommerce 8.0+ or Easy Digital Downloads to be installed and active.',
		'theypaid-payments'
	);
	echo '</p></div>';
}

function theypaid_init() {
	$has_wc  = class_exists( 'WooCommerce' );
	$has_edd = class_exists( 'Easy_Digital_Downloads' );

	if ( ! $has_wc && ! $has_edd ) {
		add_action( 'admin_notices', 'theypaid_missing_platform_notice' );
		return;
	}

	require_once THEYPAID_PLUGIN_DIR . 'includes/class-theypaid-api.php';
	require_once THEYPAID_PLUGIN_DIR . 'includes/class-theypaid-webhook.php';

	if ( $has_wc ) {
		require_once THEYPAID_PLUGIN_DIR . 'includes/class-theypaid-settings.php';
		require_once THEYPAID_PLUGIN_DIR . 'includes/gateways/class-wc-gateway-theypaid.php';

		add_filter( 'woocommerce_payment_gateways', function ( array $gateways ): array {
			$gateways[] = WC_Gateway_TheyPaid::class;
			return $gateways;
		} );

		new TheyPaid_Settings();
	}

	if ( $has_edd ) {
		require_once THEYPAID_PLUGIN_DIR . 'includes/gateways/class-edd-gateway-theypaid.php';
		new EDD_Gateway_TheyPaid();
	}
}
add_action( 'plugins_loaded', 'theypaid_init' );

add_action( 'woocommerce_blocks_loaded', function () {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once THEYPAID_PLUGIN_DIR . 'includes/gateways/class-wc-blocks-theypaid.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new WC_Blocks_TheyPaid() );
			}
		);
	}
} );

function theypaid_get_api(): ?TheyPaid_API {
	$settings = get_option( 'woocommerce_theypaid_settings', [] );
	$api_key  = $settings['api_key'] ?? '';

	if ( empty( $api_key ) && function_exists( 'edd_get_option' ) ) {
		$api_key = edd_get_option( 'theypaid_api_key', '' );
	}

	if ( empty( $api_key ) ) {
		return null;
	}

	return new TheyPaid_API( $api_key );
}

/**
 * Expiry timestamp from an invoice payload. Create returns expiresAt; GET returns expiredAt.
 */
function theypaid_invoice_expires_at( array $invoice, int $fallback_mins = 60 ): string {
	if ( ! empty( $invoice['expiresAt'] ) ) {
		return (string) $invoice['expiresAt'];
	}
	if ( ! empty( $invoice['expiredAt'] ) ) {
		return (string) $invoice['expiredAt'];
	}
	$mins = isset( $invoice['expiresMin'] ) ? (int) $invoice['expiresMin'] : $fallback_mins;
	return gmdate( 'Y-m-d\TH:i:s\Z', time() + ( $mins * 60 ) );
}

/**
 * Invoice status, or CONFIRMING when WAITING with in-flight txs.
 */
function theypaid_resolve_status( array $invoice ): string {
	$status = strtoupper( (string) ( $invoice['status'] ?? 'WAITING' ) );
	if ( 'WAITING' !== $status ) {
		return $status;
	}

	$txs = isset( $invoice['txs'] ) && is_array( $invoice['txs'] ) ? $invoice['txs'] : [];
	foreach ( $txs as $tx ) {
		if ( ! is_array( $tx ) || empty( $tx['status'] ) ) {
			continue;
		}
		$tx_status = strtoupper( (string) $tx['status'] );
		if ( 'CONFIRMING' === $tx_status || 'PENDING' === $tx_status ) {
			return 'CONFIRMING';
		}
	}

	return $status;
}

/**
 * Recheck + GET invoice, then apply PAID/EXPIRED/INSUFFICIENT to matching orders.
 *
 * @return array|null Latest invoice payload, or null on failure.
 */
function theypaid_sync_remote_invoice( string $invoice_id ): ?array {
	$api = theypaid_get_api();
	if ( ! $api || '' === $invoice_id ) {
		return null;
	}

	$api->recheck_invoice( $invoice_id );
	$verified = $api->get_invoice( $invoice_id );
	if ( is_wp_error( $verified ) || empty( $verified['id'] ) ) {
		return null;
	}

	if ( class_exists( 'TheyPaid_Webhook' ) ) {
		TheyPaid_Webhook::apply_invoice( $verified );
	}

	return $verified;
}

/**
 * Build a crypto payment URI for QR code generation.
 *
 * @param string $symbol        Asset symbol (e.g. BTC, XRP).
 * @param string $address       Crypto address.
 * @param string $crypto_amount Amount string (e.g. "0.00023451" or "0.00023451 BTC").
 * @param string $tag           Memo/tag for XRP/XLM (empty for others).
 * @return string Payment URI for wallet apps.
 */
function theypaid_build_payment_uri( string $symbol, string $address, string $crypto_amount, string $tag = '' ): string {
	$amount_num = floatval( preg_replace( '/[^0-9.]/', '', $crypto_amount ) );
	$symbol_uc  = strtoupper( $symbol );

	switch ( $symbol_uc ) {
		case 'BTC':
			return 'bitcoin:' . $address . '?amount=' . $amount_num;
		case 'LTC':
			return 'litecoin:' . $address . '?amount=' . $amount_num;
		case 'BCH':
			return 'bitcoincash:' . $address . '?amount=' . $amount_num;
		case 'DOGE':
			return 'dogecoin:' . $address . '?amount=' . $amount_num;
		case 'DASH':
			return 'dash:' . $address . '?amount=' . $amount_num;
		case 'XRP':
			return 'xrpl:' . $address . '?amount=' . $amount_num . ( $tag ? '&dt=' . rawurlencode( $tag ) : '' );
		case 'XLM':
			return 'web+stellar:pay?destination=' . rawurlencode( $address ) . '&amount=' . $amount_num . ( $tag ? '&memo=' . rawurlencode( $tag ) : '' );
		case 'TON':
			return 'ton://transfer/' . $address . '?amount=' . $amount_num;
		default:
			return $address;
	}
}

/**
 * Intercept checkout page to show payment-pending template when
 * the `theypaid_pay` query param is present.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['theypaid_pay'] ) ) {
		return;
	}

	$order_id  = absint( $_GET['theypaid_pay'] );
	$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
	$order     = wc_get_order( $order_id );

	if ( ! $order || $order->get_order_key() !== $order_key ) {
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	if ( ! in_array( $order->get_status(), [ 'pending', 'on-hold' ], true ) ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	$invoice_id = $order->get_meta( '_theypaid_invoice_id' );
	if ( empty( $invoice_id ) ) {
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
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

	wp_localize_script( 'theypaid-checkout', 'theypaidCheckout', [
		'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'theypaid_check_status' ),
		'orderId'          => $order_id,
		'orderKey'         => $order->get_order_key(),
		'orderReceivedUrl' => $order->get_checkout_order_received_url(),
		'pollInterval'     => 15000,
		'i18n'             => [
			'paid'         => __( 'Payment confirmed! Redirecting…', 'theypaid-payments' ),
			'expired'      => __( 'This invoice has expired. Please place a new order.', 'theypaid-payments' ),
			'insufficient' => __( 'Underpayment detected. The store has been notified — please contact support.', 'theypaid-payments' ),
			'waiting'      => __( 'Waiting for payment…', 'theypaid-payments' ),
			'confirming'   => __( 'Payment detected — waiting for confirmations…', 'theypaid-payments' ),
			'copied'       => __( 'Copied!', 'theypaid-payments' ),
		],
	] );

	include THEYPAID_PLUGIN_DIR . 'templates/payment-pending.php';
	exit;
} );

/**
 * AJAX handler: check order payment status (logged-in users).
 */
add_action( 'wp_ajax_theypaid_check_status', 'theypaid_ajax_check_status' );

/**
 * AJAX handler: check order payment status (guest users).
 */
add_action( 'wp_ajax_nopriv_theypaid_check_status', 'theypaid_ajax_check_status' );

function theypaid_ajax_check_status(): void {
	check_ajax_referer( 'theypaid_check_status', 'nonce' );

	$order_id  = absint( $_POST['order_id'] ?? 0 );
	$order_key = sanitize_text_field( wp_unslash( $_POST['order_key'] ?? '' ) );
	$order     = wc_get_order( $order_id );

	if ( ! $order || $order->get_order_key() !== $order_key ) {
		wp_send_json_error( 'invalid_order', 400 );
	}

	$invoice_id = $order->get_meta( '_theypaid_invoice_id' );
	$remote     = $invoice_id ? theypaid_sync_remote_invoice( (string) $invoice_id ) : null;
	$order      = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( 'invalid_order', 400 );
	}

	$wc_status = $order->get_status();

	$status_map = [
		'processing' => 'paid',
		'completed'  => 'paid',
		'cancelled'  => 'expired',
		'on-hold'    => 'insufficient',
		'pending'    => 'waiting',
	];

	$status = $status_map[ $wc_status ] ?? 'waiting';
	if ( 'waiting' === $status && $remote && 'CONFIRMING' === theypaid_resolve_status( $remote ) ) {
		$status = 'confirming';
	}

	wp_send_json_success( [
		'status' => $status,
	] );
}

/**
 * AJAX handler: check EDD payment status (logged-in users).
 */
add_action( 'wp_ajax_theypaid_check_edd_status', 'theypaid_ajax_check_edd_status' );

/**
 * AJAX handler: check EDD payment status (guest users).
 */
add_action( 'wp_ajax_nopriv_theypaid_check_edd_status', 'theypaid_ajax_check_edd_status' );

function theypaid_ajax_check_edd_status(): void {
	check_ajax_referer( 'theypaid_check_edd_status', 'nonce' );

	$payment_id = absint( $_POST['order_id'] ?? 0 );

	if ( ! function_exists( 'edd_get_payment_status' ) ) {
		wp_send_json_error( 'edd_not_active', 400 );
	}

	$edd_status = edd_get_payment_status( $payment_id );

	if ( false === $edd_status ) {
		wp_send_json_error( 'invalid_payment', 400 );
	}

	$invoice_id = function_exists( 'edd_get_payment_meta' )
		? (string) edd_get_payment_meta( $payment_id, '_theypaid_invoice_id', true )
		: '';
	$remote     = $invoice_id ? theypaid_sync_remote_invoice( $invoice_id ) : null;
	$edd_status = edd_get_payment_status( $payment_id );

	$status_map = [
		'complete'   => 'paid',
		'publish'    => 'paid',
		'abandoned'  => 'expired',
		'failed'     => 'expired',
		'processing' => 'insufficient',
		'pending'    => 'waiting',
	];

	$status = $status_map[ $edd_status ] ?? 'waiting';
	if ( 'waiting' === $status && $remote && 'CONFIRMING' === theypaid_resolve_status( $remote ) ) {
		$status = 'confirming';
	}

	wp_send_json_success( [
		'status' => $status,
	] );
}
