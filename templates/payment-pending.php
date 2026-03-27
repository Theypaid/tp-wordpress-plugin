<?php
/**
 * Payment pending template — shown after checkout while awaiting crypto payment.
 *
 * @var WC_Order $order — set by the template_redirect handler in theypaid-payments.php
 */

defined( 'ABSPATH' ) || exit;

$invoice_id     = $order->get_meta( '_theypaid_invoice_id' );
$symbol         = $order->get_meta( '_theypaid_asset_symbol' );
$network        = $order->get_meta( '_theypaid_asset_network' );
$crypto_amount  = $order->get_meta( '_theypaid_crypto_amount' );
$address        = $order->get_meta( '_theypaid_crypto_address' );
$tag            = $order->get_meta( '_theypaid_crypto_tag' );
$logo_url       = $order->get_meta( '_theypaid_asset_logo' );
$amount_usd     = $order->get_meta( '_theypaid_amount_usd' );
$expires_at     = $order->get_meta( '_theypaid_expires_at' );
$has_tag        = ! empty( $tag );

$qr_data = theypaid_build_payment_uri( $symbol, $address, $crypto_amount, $tag );
$qr_url  = 'https://api.qrserver.com/v1/create-qr-code/?data=' . rawurlencode( $qr_data ) . '&size=250x250&margin=8&ecc=H';

get_header(); ?>

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
					<img
						src="<?php echo esc_url( $logo_url ); ?>"
						alt="<?php echo esc_attr( $symbol ); ?>"
						class="theypaid-pending__asset-logo"
						width="44" height="44"
					/>
				<?php endif; ?>
				<div>
					<div class="theypaid-pending__asset-name"><?php echo esc_html( $symbol ); ?></div>
					<div class="theypaid-pending__asset-network"><?php echo esc_html( $network ); ?></div>
				</div>
			</div>
			<p class="theypaid-pending__order-num"><?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'theypaid-payments' ),
					esc_html( $order->get_order_number() )
				);
			?></p>
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

		<!-- Payment details wrapper (fades on expire/paid) -->
		<div id="theypaid-payment-details" class="theypaid-pending__details">

			<!-- QR code -->
			<div class="theypaid-pending__qr">
				<div class="theypaid-pending__qr-frame">
					<img
						src="<?php echo esc_url( $qr_url ); ?>"
						alt="<?php esc_attr_e( 'Payment QR Code', 'theypaid-payments' ); ?>"
						width="250" height="250"
						class="theypaid-pending__qr-img"
					/>
					<?php if ( $logo_url ) : ?>
						<img
							src="<?php echo esc_url( $logo_url ); ?>"
							alt=""
							class="theypaid-pending__qr-logo"
							width="56" height="56"
							aria-hidden="true"
						/>
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

			<!-- Tag/Memo -->
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
			<div
				id="theypaid-countdown-timer"
				class="theypaid-pending__countdown-timer"
				data-expires="<?php echo esc_attr( $expires_at ); ?>"
			>--:--</div>
		</div>

		<!-- Success overlay (shown via JS) -->
		<div id="theypaid-success-overlay" class="theypaid-pending__overlay theypaid-pending__overlay--hidden">
			<div class="theypaid-pending__overlay-icon theypaid-pending__overlay-icon--success">&#10003;</div>
			<p class="theypaid-pending__overlay-text"><?php esc_html_e( 'Payment confirmed! Redirecting…', 'theypaid-payments' ); ?></p>
		</div>

		<!-- Expired overlay (shown via JS) -->
		<div id="theypaid-expired-overlay" class="theypaid-pending__overlay theypaid-pending__overlay--hidden">
			<div class="theypaid-pending__overlay-icon theypaid-pending__overlay-icon--expired">&#10005;</div>
			<p class="theypaid-pending__overlay-text"><?php esc_html_e( 'This invoice has expired.', 'theypaid-payments' ); ?></p>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="theypaid-pending__overlay-btn">
				<?php esc_html_e( 'Return to cart', 'theypaid-payments' ); ?>
			</a>
		</div>

		<!-- Footer -->
		<div class="theypaid-pending__footer">
			<span><?php esc_html_e( 'Secured by', 'theypaid-payments' ); ?></span>
			<a href="https://theypaid.io" target="_blank" rel="noopener noreferrer">TheyPaid</a>
		</div>

	</div><!-- /.theypaid-pending__card -->
</div>

<?php get_footer();
