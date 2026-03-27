# TheyPaid Crypto Payments — Asset Specifications

Design assets needed for the WordPress.org plugin listing and the plugin itself.

---

## WordPress.org Plugin Page Assets

These go in the `.wordpress-org/` directory (SVN `/assets/` branch, not shipped in the zip).

### Plugin Banners

| File | Dimensions | Notes |
|---|---|---|
| `banner-772x250.png` | 772 x 250 px | Standard resolution. Displayed at the top of the plugin page. |
| `banner-1544x500.png` | 1544 x 500 px | Hi-DPI (Retina) version of the banner. |

**Design guidance:**
- Dark or gradient background that conveys crypto/blockchain
- TheyPaid logo on the left, tagline on the right: "Non-custodial crypto payments — direct to your wallet"
- Subtle coin icons (BTC, LTC, XRP, etc.) as background elements
- Keep text away from edges — WordPress.org crops banners on smaller viewports
- Format: PNG (no transparency needed)

### Plugin Icons

| File | Dimensions | Notes |
|---|---|---|
| `icon-128x128.png` | 128 x 128 px | Standard resolution. Shown in search results and plugin cards. |
| `icon-256x256.png` | 256 x 256 px | Hi-DPI (Retina) version. |

**Design guidance:**
- Square icon, must work at small sizes (32px in some WP admin views)
- Use the TheyPaid brand mark or a simplified version
- Solid or gradient background — avoid transparency
- Format: PNG

---

## Screenshots

Referenced in `readme.txt` under `== Screenshots ==`. Files go in `.wordpress-org/` as well.

| File | Description |
|---|---|
| `screenshot-1.png` | Coin selector on the WooCommerce checkout page |
| `screenshot-2.png` | Payment pending page with address, QR code, and countdown |
| `screenshot-3.png` | Tag/Memo warning for XRP and XLM payments |
| `screenshot-4.png` | Admin settings page with Test Connection button |
| `screenshot-5.png` | WooCommerce order note showing confirmed payment with transaction ID |

**Screenshot guidance:**
- Capture at 1280px wide browser window for consistency
- Use a clean WP theme (Storefront or Twenty Twenty-Four)
- Use realistic but fake data (no real wallet addresses or API keys)
- Crop to content area — no browser chrome
- Format: PNG, max 1200px wide (WordPress.org resizes larger images)

---

## Brand Colors (for reference)

| Usage | Color |
|---|---|
| Primary | `#2563eb` (blue) |
| Success | `#059669` (green) |
| Warning | `#92400e` on `#fef3c7` (amber) |
| Error | `#dc2626` (red) |
| Dark text | `#111827` |
| Muted text | `#6b7280` |
