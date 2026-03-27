<?php

defined( 'ABSPATH' ) || exit;

class TheyPaid_API {

	private string $api_key;
	private string $base_url;

	public function __construct( string $api_key ) {
		$this->api_key  = $api_key;
		$this->base_url = THEYPAID_API_BASE;
	}

	/**
	 * Retrieve the list of enabled assets from the merchant store.
	 *
	 * @return array|WP_Error Array of StoreAssetGetResDto on success, WP_Error on failure.
	 */
	public function get_store_assets(): array|WP_Error {
		return $this->get( '/store/assets' );
	}

	/**
	 * Create a new payment invoice.
	 *
	 * @param array $params {
	 *     @type string $assetId
	 *     @type float  $amountInTether
	 *     @type float  $acceptToleranceInTether
	 *     @type int    $expiresMins
	 *     @type string $callbackUrl
	 * }
	 * @return array|WP_Error InvoiceCreateResDto on success, WP_Error on failure.
	 */
	public function create_invoice( array $params ): array|WP_Error {
		return $this->post( '/invoice', $params );
	}

	/**
	 * Fetch a single invoice by ID.
	 *
	 * @param string $invoice_id
	 * @return array|WP_Error InvoiceGetResDto on success, WP_Error on failure.
	 */
	public function get_invoice( string $invoice_id ): array|WP_Error {
		return $this->get( '/invoice/' . urlencode( $invoice_id ) );
	}

	/**
	 * List invoices for the store with pagination.
	 *
	 * @param int $offset
	 * @param int $limit
	 * @return array|WP_Error Array of InvoiceReportResDto on success, WP_Error on failure.
	 */
	public function get_store_invoices( int $offset = 0, int $limit = 20 ): array|WP_Error {
		return $this->get( '/store/invoices', [
			'offset' => $offset,
			'limit'  => $limit,
		] );
	}

	private function get( string $endpoint, array $query = [] ): array|WP_Error {
		$url = $this->base_url . $endpoint;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => $this->headers(),
		] );

		return $this->handle_response( $response, 'GET ' . $endpoint );
	}

	private function post( string $endpoint, array $body ): array|WP_Error {
		$response = wp_remote_post( $this->base_url . $endpoint, [
			'timeout' => 15,
			'headers' => array_merge( $this->headers(), [
				'Content-Type' => 'application/json',
			] ),
			'body' => wp_json_encode( $body ),
		] );

		return $this->handle_response( $response, 'POST ' . $endpoint );
	}

	private function headers(): array {
		return [
			'apiKey' => $this->api_key,
		];
	}

	private function handle_response( $response, string $context ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( '%s — HTTP error: %s', $context, $response->get_error_message() ) );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['message'] ?? $body;
			$this->log( sprintf( '%s — HTTP %d: %s', $context, $code, $message ) );

			return new WP_Error(
				'theypaid_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'TheyPaid API error (%1$d): %2$s', 'theypaid-payments' ),
					$code,
					$message
				),
				[ 'status' => $code ]
			);
		}

		if ( ! is_array( $data ) ) {
			$this->log( sprintf( '%s — Invalid JSON response', $context ) );

			return new WP_Error(
				'theypaid_api_error',
				__( 'Invalid response from TheyPaid API.', 'theypaid-payments' )
			);
		}

		return $data;
	}

	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, [ 'source' => 'theypaid' ] );
		}
	}
}
