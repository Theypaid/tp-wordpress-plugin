# TheyPaid Crypto Payments — Manual QA Checklist

Run through this checklist before every release. Test against the minimum supported
versions (WordPress 6.0, PHP 8.0, WooCommerce 8.0) and the latest stable versions.

---

## Setup & Activation

- [ ] Plugin installs and activates without errors on WP 6.0+ / PHP 8.0+
- [ ] No PHP warnings or notices in debug mode (`WP_DEBUG=true`, `WP_DEBUG_LOG=true`)
- [ ] Plugin activates cleanly when only WooCommerce is active (no EDD)
- [ ] Plugin activates cleanly when only EDD is active (no WooCommerce)
- [ ] Plugin activates cleanly when both WooCommerce and EDD are active
- [ ] Admin notice is shown when neither WooCommerce nor EDD is active
- [ ] Deactivating and reactivating the plugin preserves all settings

## Admin Settings — WooCommerce

- [ ] Settings page appears at WooCommerce → Settings → Payments → TheyPaid Crypto
- [ ] All fields render correctly: Enable, Title, Description, API Key, Tolerance, Expiry
- [ ] API Key field is masked (password type)
- [ ] "Test Connection" button with a valid API key returns asset count and shows green success message
- [ ] "Test Connection" button with an invalid API key shows red error message
- [ ] "Test Connection" button with an empty API key shows "Please enter your API key first"
- [ ] Saving settings persists all values correctly
- [ ] Gateway does not appear on the checkout page when disabled
- [ ] Gateway does not appear on the checkout page when enabled but API key is empty

## Admin Settings — Easy Digital Downloads

- [ ] Settings appear at Downloads → Settings → Payment Gateways under the TheyPaid header
- [ ] API Key, Tolerance, and Expiry fields render and save correctly
- [ ] Gateway can be enabled/disabled from the EDD gateway list

## WooCommerce Classic Checkout

- [ ] Coin selector renders with logos, symbols, and network names
- [ ] First coin is pre-selected by default
- [ ] Selecting a different coin highlights the new card and deselects the previous
- [ ] Submitting checkout without selecting a coin shows validation error
- [ ] Successful checkout creates WC order in "Pending" status
- [ ] Order meta is stored correctly:
  - [ ] `_theypaid_invoice_id` is a non-empty string
  - [ ] `_theypaid_asset_symbol` matches selected coin (e.g. "BTC")
  - [ ] `_theypaid_asset_network` is set (e.g. "Bitcoin")
  - [ ] `_theypaid_crypto_amount` is set
  - [ ] `_theypaid_crypto_address` is a valid-looking address
  - [ ] `_theypaid_crypto_tag` is set for XRP/XLM, empty for others
  - [ ] `_theypaid_asset_logo` is a valid URL
  - [ ] `_theypaid_amount_usd` matches the order total
  - [ ] `_theypaid_expires_at` is a future ISO 8601 timestamp
- [ ] Cart is emptied after successful checkout
- [ ] Customer is redirected to the payment pending page

## Payment Pending Page (WooCommerce)

- [ ] Page loads with the active theme's header and footer
- [ ] Asset logo, symbol, and network name are displayed correctly
- [ ] Order number is shown
- [ ] "Waiting for payment…" status badge is displayed with pulsing dot
- [ ] QR code image loads (from api.qrserver.com)
- [ ] Crypto address is displayed in monospace font
- [ ] "Copy" button for address copies to clipboard and flashes "Copied!"
- [ ] **XRP/XLM orders:** Tag/Memo field is shown with yellow warning box
- [ ] **Non-XRP/XLM orders:** Tag/Memo field is NOT shown
- [ ] "Copy" button for tag/memo works correctly
- [ ] Crypto amount is displayed with "Copy" button
- [ ] USD amount is displayed and matches order total
- [ ] Countdown timer counts down in real time
- [ ] Countdown turns red when under 5 minutes remaining
- [ ] Countdown shows 00:00 when expired and status updates to "expired"
- [ ] Navigating directly to the pending URL with a wrong order key redirects to checkout
- [ ] Navigating to the pending URL for an already-completed order redirects to order-received

## AJAX Status Polling (WooCommerce)

- [ ] Polling fires every 15 seconds
- [ ] PAID webhook → next poll returns `paid` → status badge changes → redirects to order-received page after 1.5s
- [ ] EXPIRED webhook → next poll returns `expired` → status badge changes → polling stops
- [ ] INSUFFICIENT webhook → next poll returns `insufficient` → status badge changes → polling stops
- [ ] Invalid order ID → poll returns error → no crash
- [ ] Wrong order key → poll returns error → no status leak

## WooCommerce Block Checkout

- [ ] TheyPaid payment method appears in the block checkout
- [ ] Coin selector renders with logos, symbols, and network names
- [ ] Selecting a coin works correctly
- [ ] `canMakePayment` returns false when no assets are available (gateway hidden)
- [ ] Successful payment creates invoice and redirects to pending page
- [ ] Full flow works end-to-end identical to classic checkout

## Webhook Handling — WooCommerce

- [ ] Webhook URL is accessible: `POST /wp-json/theypaid/v1/webhook`
- [ ] Payload with valid invoice ID → returns 200
- [ ] Payload with unknown invoice ID → returns 400
- [ ] Payload with missing `id` field → returns 400
- [ ] Empty/malformed JSON body → returns 400
- [ ] **PAID** status:
  - [ ] Order moves from "Pending" to "Processing"
  - [ ] Order note added with "TheyPaid payment confirmed" and transaction ID(s)
  - [ ] Duplicate PAID webhook does NOT re-process (idempotent)
- [ ] **EXPIRED** status:
  - [ ] Order moves to "Cancelled"
  - [ ] Order note added with "invoice expired"
  - [ ] Duplicate EXPIRED webhook does NOT re-process
- [ ] **INSUFFICIENT** status:
  - [ ] Order moves to "On Hold"
  - [ ] Order note shows paid amount vs expected amount
  - [ ] Duplicate INSUFFICIENT webhook does NOT re-process
- [ ] **WAITING** status:
  - [ ] No order status change (silently ignored)
- [ ] API re-fetch: webhook handler fetches `GET /invoice/{id}` to verify status independently

## Easy Digital Downloads

- [ ] Coin selector renders on EDD checkout page
- [ ] Submitting without selecting a coin shows EDD error
- [ ] Successful checkout creates EDD payment in "Pending" status
- [ ] All payment meta fields are stored (same 9 fields as WooCommerce)
- [ ] Payment note "TheyPaid invoice created: {id}" is added
- [ ] Customer is redirected to EDD success page
- [ ] Payment receipt page shows payment instructions when status is pending:
  - [ ] QR code, address, amount, countdown, copy buttons all work
  - [ ] Tag/Memo shown for XRP/XLM with warning
- [ ] AJAX polling works with EDD-specific endpoint
- [ ] Payment receipt stops showing instructions once payment is complete

## Webhook Handling — Easy Digital Downloads

- [ ] **PAID** webhook → EDD payment status changes to "Complete", note added with txid
- [ ] **EXPIRED** webhook → EDD payment status changes to "Abandoned", note added
- [ ] **INSUFFICIENT** webhook → EDD payment status changes to "Processing", note added with amounts
- [ ] All EDD webhook handlers are idempotent (no double processing)

## Edge Cases

- [ ] Plugin works when only WooCommerce is installed (no EDD classes loaded)
- [ ] Plugin works when only EDD is installed (no WC classes loaded)
- [ ] Plugin works when both WooCommerce and EDD are active simultaneously
- [ ] Webhook correctly routes to WC order OR EDD payment (or both if somehow duplicated)
- [ ] `/store/assets` returning empty array → coin selector shows "No cryptocurrencies" message
- [ ] `/store/assets` API failure → coin selector shows "Unable to load" error message
- [ ] Asset transient cache expires after 5 minutes and re-fetches
- [ ] `create_invoice` API failure → checkout shows clear error, order not created
- [ ] Invoice expiry set to minimum (5 min) → countdown and webhook both work correctly
- [ ] Invoice expiry set to maximum (1440 min) → countdown displays hours correctly
- [ ] Very long crypto address → address field wraps without breaking layout
- [ ] Multiple browser tabs on same pending page → all poll independently without conflict

## Performance

- [ ] Asset list is cached in transient (verify no API call on second page load within 5 min)
- [ ] No unnecessary database queries on non-checkout pages
- [ ] CSS and JS are only enqueued on checkout and payment pending pages
- [ ] Admin JS is only enqueued on the TheyPaid settings page

## Security

- [ ] All AJAX handlers reject requests without valid nonce
- [ ] WC status check handler rejects requests with wrong order key
- [ ] Test Connection handler rejects non-admin users
- [ ] No PHP errors or stack traces visible to non-admin users
- [ ] API key is not exposed in page source, JS variables, or AJAX responses
- [ ] Webhook rejects payloads with invoice IDs not in the local database
