=== TheyPaid Crypto Payments ===
Contributors: theypaid
Tags: crypto, cryptocurrency, bitcoin, woocommerce, payment gateway
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments directly to your own wallet — non-custodial, no KYC, no middleman.

== Description ==

**TheyPaid Crypto Payments** integrates the [TheyPaid](https://theypaid.io) non-custodial crypto payment gateway into your WordPress store. Every payment goes straight to your own wallet address — TheyPaid never holds your funds.

= Why TheyPaid? =

* **Non-custodial** — Funds are sent directly to your wallet. TheyPaid never touches your money.
* **No KYC required** — Start accepting crypto in minutes with no identity verification.
* **Fair pricing** — Credits are consumed based on active invoice time, not payment amount. A $10 and a $10,000 invoice cost the same.
* **Multi-coin support** — Accept BTC, LTC, BCH, DASH, DOGE, TON, XRP, XLM, USDT, and more. Configure enabled coins in your TheyPaid merchant dashboard.
* **Works with WooCommerce and Easy Digital Downloads** — one plugin for both platforms.

= Features =

* Responsive coin selector at checkout with asset logos and network names
* Real-time payment status polling — customers see confirmation without refreshing
* QR code generation for easy wallet scanning
* Memo/Tag support for XRP and XLM with clear warnings to prevent lost funds
* Countdown timer showing remaining invoice time
* Configurable underpayment tolerance and invoice expiry
* Webhook-driven order status updates (PAID, EXPIRED, INSUFFICIENT)
* WooCommerce block checkout support
* HPOS (High-Performance Order Storage) compatible
* Fully translatable — all strings use WordPress i18n functions

= Supported Platforms =

* **WooCommerce** 8.0+ (classic and block checkout)
* **Easy Digital Downloads** 3.0+

= How It Works =

1. Customer selects a cryptocurrency at checkout
2. Plugin creates a payment invoice via the TheyPaid API
3. Customer sees the wallet address, amount, and QR code
4. Customer sends the payment from their own wallet
5. TheyPaid detects the on-chain payment and notifies your store
6. Order is automatically marked as paid

== Installation ==

1. Upload the `theypaid-payments` folder to `/wp-content/plugins/`, or install directly via the WordPress plugin installer.
2. Activate the plugin through the **Plugins** menu.
3. Sign up for a merchant account at [merchant.theypaid.io](https://merchant.theypaid.io) and get your API key.
4. **For WooCommerce:** Go to **WooCommerce → Settings → Payments → TheyPaid Crypto**, enter your API key, and click **Test Connection**.
5. **For Easy Digital Downloads:** Go to **Downloads → Settings → Payment Gateways**, enter your API key under the TheyPaid section.
6. Configure your underpayment tolerance (default: $0.10) and invoice expiry (default: 60 minutes).
7. Enable the payment method and you're ready to accept crypto!

== Frequently Asked Questions ==

= What does "non-custodial" mean? =

Non-custodial means TheyPaid never holds your funds. When a customer pays, the cryptocurrency is sent directly to your own wallet address. TheyPaid only monitors the blockchain to detect when payment is received.

= Do I need to complete KYC (identity verification)? =

No. TheyPaid does not require any identity verification. You can create a merchant account and start accepting payments immediately.

= What cryptocurrencies are supported? =

TheyPaid supports BTC, LTC, BCH, DASH, DOGE, TON, XRP, XLM, USDT, and more. The list of available coins is configured in your TheyPaid merchant dashboard — the plugin always fetches the live list automatically.

= What happens if a customer underpays? =

If the payment is below the required amount but within your configured tolerance (default: $0.10 USD), it is accepted as paid. If the underpayment exceeds the tolerance, the order is placed on hold and you are notified with the exact amounts received vs. expected.

= Does TheyPaid store my funds? =

No. Payments go directly from the customer's wallet to yours. TheyPaid is purely a detection and notification layer. You retain full control of your funds at all times.

= Do I need to configure a webhook or callback URL? =

No. The plugin automatically sends your site's callback URL to TheyPaid when each invoice is created. The URL is: `https://your-site.com/wp-json/theypaid/v1/webhook`. Your WordPress site must be publicly reachable from the internet for payment detection to work — local-only setups (e.g. LocalWP with a .local domain) will not receive webhooks unless you expose the site (e.g. via Live Link or ngrok).

= How is TheyPaid pricing structured? =

TheyPaid charges credits based on the duration an invoice is active — not the payment amount. A $10 invoice and a $10,000 invoice cost the same number of credits.

= Can I issue refunds through the plugin? =

No. Because TheyPaid is non-custodial, refunds must be processed manually from your own wallet. The plugin disables the WooCommerce refund button for TheyPaid orders.

= Does it work with WooCommerce block checkout? =

Yes. The plugin fully supports both the classic WooCommerce checkout and the new block-based checkout editor.


== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce classic checkout gateway
* WooCommerce block checkout support
* Easy Digital Downloads gateway
* Webhook handler with idempotent status updates
* Payment pending page with QR code, countdown, and AJAX polling
* Admin settings with API key validation

== Upgrade Notice ==

= 1.0.0 =
Initial release of TheyPaid Crypto Payments.
