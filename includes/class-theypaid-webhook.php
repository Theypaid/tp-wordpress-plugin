<?php

defined( 'ABSPATH' ) || exit;

class TheyPaid_Webhook {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'theypaid/v1', '/webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();

		if ( empty( $payload ) || empty( $payload['id'] ) ) {
			$this->log( 'Webhook rejected: missing or invalid JSON payload.' );
			return new WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
		}

		$invoice_id = sanitize_text_field( $payload['id'] );

		$wc_order    = $this->find_wc_order( $invoice_id );
		$edd_payment = $this->find_edd_payment( $invoice_id );

		if ( ! $wc_order && ! $edd_payment ) {
			$this->log( sprintf( 'Webhook rejected: no order found for invoice %s.', $invoice_id ) );
			return new WP_REST_Response( [ 'error' => 'Unknown invoice' ], 400 );
		}

		$invoice_data = $payload;
		$api          = theypaid_get_api();
		if ( $api ) {
			$verified = $api->get_invoice( $invoice_id );
			if ( ! is_wp_error( $verified ) ) {
				$invoice_data = $verified;
			} else {
				$this->log( sprintf( 'Webhook: API re-fetch failed for invoice %s, using payload data.', $invoice_id ) );
			}
		}

		$status = $invoice_data['status'] ?? '';

		if ( $wc_order ) {
			$this->log( sprintf( 'Webhook received: invoice %s, status %s, WC order #%s.', $invoice_id, $status, $wc_order->get_order_number() ) );
			$this->process_wc_status( $wc_order, $status, $invoice_data );
		}

		if ( $edd_payment ) {
			$this->log( sprintf( 'Webhook received: invoice %s, status %s, EDD payment #%d.', $invoice_id, $status, $edd_payment ) );
			$this->process_edd_status( $edd_payment, $status, $invoice_data );
		}

		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	/* ── WooCommerce ─────────────────────────────────────── */

	private function process_wc_status( WC_Order $order, string $status, array $invoice_data ): void {
		switch ( $status ) {
			case 'PAID':
				$this->wc_handle_paid( $order, $invoice_data );
				break;
			case 'EXPIRED':
				$this->wc_handle_expired( $order );
				break;
			case 'INSUFFICIENT':
				$this->wc_handle_insufficient( $order, $invoice_data );
				break;
			case 'WAITING':
				break;
			default:
				$this->log( sprintf( 'Webhook: unknown status "%s" for WC order #%s.', $status, $order->get_order_number() ) );
				break;
		}
	}

	private function wc_handle_paid( WC_Order $order, array $invoice_data ): void {
		if ( in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) {
			return;
		}

		$txids = $this->extract_txids( $invoice_data );
		$note  = __( 'TheyPaid payment confirmed.', 'theypaid-payments' );
		if ( ! empty( $txids ) ) {
			$note .= ' ' . sprintf(
				/* translators: %s: comma-separated transaction IDs */
				__( 'Transaction ID(s): %s', 'theypaid-payments' ),
				implode( ', ', $txids )
			);
		}

		$order->payment_complete();
		$order->add_order_note( $note );
		$order->save();
	}

	private function wc_handle_expired( WC_Order $order ): void {
		if ( 'cancelled' === $order->get_status() ) {
			return;
		}

		$order->update_status(
			'cancelled',
			__( 'TheyPaid invoice expired — no payment received.', 'theypaid-payments' )
		);
	}

	private function wc_handle_insufficient( WC_Order $order, array $invoice_data ): void {
		if ( 'on-hold' === $order->get_status() ) {
			return;
		}

		$paid_amount = $invoice_data['paidAmount'] ?? '0';
		$expected    = $invoice_data['amount'] ?? '0';

		$note = sprintf(
			/* translators: 1: amount received, 2: amount expected */
			__( 'TheyPaid: insufficient payment received. Got %1$s, expected %2$s. Order placed on hold.', 'theypaid-payments' ),
			$paid_amount,
			$expected
		);

		$order->update_status( 'on-hold', $note );
	}

	/* ── Easy Digital Downloads ───────────────────────────── */

	private function process_edd_status( int $payment_id, string $status, array $invoice_data ): void {
		switch ( $status ) {
			case 'PAID':
				$this->edd_handle_paid( $payment_id, $invoice_data );
				break;
			case 'EXPIRED':
				$this->edd_handle_expired( $payment_id );
				break;
			case 'INSUFFICIENT':
				$this->edd_handle_insufficient( $payment_id, $invoice_data );
				break;
			case 'WAITING':
				break;
			default:
				$this->log( sprintf( 'Webhook: unknown status "%s" for EDD payment #%d.', $status, $payment_id ) );
				break;
		}
	}

	private function edd_handle_paid( int $payment_id, array $invoice_data ): void {
		$current = edd_get_payment_status( $payment_id );
		if ( in_array( $current, [ 'complete', 'publish' ], true ) ) {
			return;
		}

		$txids = $this->extract_txids( $invoice_data );
		$note  = __( 'TheyPaid payment confirmed.', 'theypaid-payments' );
		if ( ! empty( $txids ) ) {
			$note .= ' ' . sprintf(
				/* translators: %s: comma-separated transaction IDs */
				__( 'Transaction ID(s): %s', 'theypaid-payments' ),
				implode( ', ', $txids )
			);
		}

		edd_update_payment_status( $payment_id, 'complete' );
		edd_insert_payment_note( $payment_id, $note );
	}

	private function edd_handle_expired( int $payment_id ): void {
		$current = edd_get_payment_status( $payment_id );
		if ( in_array( $current, [ 'abandoned', 'failed' ], true ) ) {
			return;
		}

		edd_update_payment_status( $payment_id, 'abandoned' );
		edd_insert_payment_note(
			$payment_id,
			__( 'TheyPaid invoice expired — no payment received.', 'theypaid-payments' )
		);
	}

	private function edd_handle_insufficient( int $payment_id, array $invoice_data ): void {
		$current = edd_get_payment_status( $payment_id );
		if ( 'processing' === $current ) {
			return;
		}

		$paid_amount = $invoice_data['paidAmount'] ?? '0';
		$expected    = $invoice_data['amount'] ?? '0';

		edd_update_payment_status( $payment_id, 'processing' );
		edd_insert_payment_note(
			$payment_id,
			sprintf(
				/* translators: 1: amount received, 2: amount expected */
				__( 'TheyPaid: insufficient payment received. Got %1$s, expected %2$s.', 'theypaid-payments' ),
				$paid_amount,
				$expected
			)
		);
	}

	/* ── Shared helpers ───────────────────────────────────── */

	private function extract_txids( array $invoice_data ): array {
		$txids = [];

		if ( ! empty( $invoice_data['txs'] ) && is_array( $invoice_data['txs'] ) ) {
			foreach ( $invoice_data['txs'] as $tx ) {
				if ( ! empty( $tx['txid'] ) ) {
					$txids[] = sanitize_text_field( $tx['txid'] );
				}
			}
		}

		return $txids;
	}

	private function find_wc_order( string $invoice_id ): ?WC_Order {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$orders = wc_get_orders( [
			'meta_key'   => '_theypaid_invoice_id',
			'meta_value' => $invoice_id,
			'limit'      => 1,
			'return'     => 'objects',
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	private function find_edd_payment( string $invoice_id ): ?int {
		if ( ! function_exists( 'edd_get_payments' ) ) {
			return null;
		}

		$payments = edd_get_payments( [
			'meta_key'   => '_theypaid_invoice_id',
			'meta_value' => $invoice_id,
			'number'     => 1,
			'output'     => 'payments',
		] );

		if ( empty( $payments ) ) {
			return null;
		}

		return (int) $payments[0]->ID;
	}

	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, [ 'source' => 'theypaid' ] );
		} elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[TheyPaid] ' . $message );
		}
	}
}

new TheyPaid_Webhook();
