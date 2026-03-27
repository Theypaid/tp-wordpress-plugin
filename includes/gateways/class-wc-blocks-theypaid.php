<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Blocks_TheyPaid extends AbstractPaymentMethodType {

	protected $name = 'theypaid';

	private array $gateway_settings = [];

	public function initialize(): void {
		$this->gateway_settings = get_option( 'woocommerce_theypaid_settings', [] );
	}

	public function is_active(): bool {
		return ( $this->gateway_settings['enabled'] ?? 'no' ) === 'yes'
			&& ! empty( $this->gateway_settings['api_key'] ?? '' );
	}

	public function get_payment_method_script_handles(): array {
		$asset_url = THEYPAID_PLUGIN_URL . 'assets/js/blocks/index.js';

		wp_register_script(
			'theypaid-blocks',
			$asset_url,
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ],
			THEYPAID_VERSION,
			true
		);

		wp_enqueue_style(
			'theypaid-blocks',
			THEYPAID_PLUGIN_URL . 'assets/css/checkout.css',
			[],
			THEYPAID_VERSION
		);

		return [ 'theypaid-blocks' ];
	}

	public function get_payment_method_data(): array {
		$settings = $this->gateway_settings;

		$title       = $settings['title'] ?? __( 'Pay with Crypto', 'theypaid-payments' );
		$description = $settings['description'] ?? __( 'Pay using Bitcoin, USDT, and other cryptocurrencies.', 'theypaid-payments' );

		$assets = $this->get_cached_assets();

		return [
			'title'       => $title,
			'description' => $description,
			'supports'    => [ 'products' ],
			'assets'      => $assets,
		];
	}

	private function get_cached_assets(): array {
		$cached = get_transient( 'theypaid_store_assets' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$api = theypaid_get_api();
		if ( ! $api ) {
			return [];
		}

		$assets = $api->get_store_assets();
		if ( is_wp_error( $assets ) ) {
			return [];
		}

		set_transient( 'theypaid_store_assets', $assets, 5 * MINUTE_IN_SECONDS );

		return $assets;
	}
}
