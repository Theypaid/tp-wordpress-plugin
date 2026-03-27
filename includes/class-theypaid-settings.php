<?php

defined( 'ABSPATH' ) || exit;

class TheyPaid_Settings {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_ajax_theypaid_test_connection', [ $this, 'ajax_test_connection' ] );
	}

	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$section = sanitize_text_field( wp_unslash( $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'theypaid' !== $section ) {
			return;
		}

		wp_enqueue_script(
			'theypaid-admin',
			THEYPAID_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			THEYPAID_VERSION,
			true
		);

		wp_localize_script( 'theypaid-admin', 'theypaidAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'theypaid_test_connection' ),
			'i18n'    => [
				'testing'    => __( 'Testing…', 'theypaid-payments' ),
				'success'    => __( 'Connection successful! Found %d enabled asset(s).', 'theypaid-payments' ),
				'fail'       => __( 'Connection failed: %s', 'theypaid-payments' ),
				'noKey'      => __( 'Please enter your API key first.', 'theypaid-payments' ),
				'testButton' => __( 'Test Connection', 'theypaid-payments' ),
			],
		] );
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( 'theypaid_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'theypaid-payments' ), 403 );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'API key is required.', 'theypaid-payments' ) );
		}

		$api    = new TheyPaid_API( $api_key );
		$result = $api->get_store_assets();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( [
			'count'  => count( $result ),
			'assets' => array_map( function ( array $asset ): array {
				return [
					'symbol'  => $asset['symbol'] ?? '',
					'network' => $asset['network'] ?? '',
				];
			}, $result ),
		] );
	}
}
