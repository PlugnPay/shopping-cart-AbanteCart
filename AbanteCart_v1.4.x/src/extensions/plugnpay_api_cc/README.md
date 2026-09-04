# PlugnPay Remote API Module for AbanteCart 1.4.x

**Version:** v1.0.3

Credit card payments via PlugnPay’s Remote API (`https://pay1.plugnpay.com/payment/pnpremote.cgi`).

This is an AbanteCart payment **extension**. It does not modify AbanteCart core files.

This extension follows the same setup and usage as the Zen Cart 2.2 `PlugnPayApi` plugin.

## Features

- Onsite credit card collection (checkout stays on your store)
- Authorize-only (`authonly`) or Sale (`authpostauth`)
- Production only (HTTPS required; no Test/Production toggle)
- Debug logging with PAN / CVV / password redaction
- Decline attempt limit (locks customer after repeated declines)
- PHP **8.2+** / AbanteCart **1.4.x** compatible (tested target: **1.4.4**)
- Requires PHP cURL with SSL

This module does **not** include AbanteCart admin Capture / Void / Refund. Settle or reverse transactions in [PlugnPay Merchant Admin](https://pay1.plugnpay.com/admin/).

## PCI notice

This module collects cardholder data on your server, which increases PCI DSS scope. For a lower-scope hosted option, use a PlugnPay Smart Screens (hosted redirect) module when available.

## Requirements

- AbanteCart **1.4.x** (tested target: **1.4.4**)
- PHP **8.2+** with **cURL + OpenSSL**
- Storefront **HTTPS** (method hides itself without SSL)
- PlugnPay publisher-name (username)
- **Remote Client Password** (from PlugnPay Security Administration — not your admin login password)

## Installation

### Admin package upload (recommended)

AbanteCart’s package installer accepts **`.tar.gz` only**.

1. Upload `abantecart_1.4_api_module.tar.gz` via Admin → **Extensions** → **Install Extension** → **Extension Upload**.

2. Accept the license agreement and finish the installer.

3. Admin → **Extensions** → **Payments** → enable **PlugnPay Remote API**.

4. Configure:
   - Publisher Name
   - Remote Client Password
   - Publisher Email (optional notify address)
   - Authorization Type (`authonly` or `authpostauth`)
   - Completed order status (used for `authpostauth`), CVV, debug logging, location

5. Place a live/test order with your merchant credentials (per your PlugnPay procedures).

There is **no** Transaction Mode toggle — the module always runs in production (HTTPS required).

### Manual FTP install

1. Copy from this repo’s development tree:

   ```
   AbanteCart_v1.4.x/src/extensions/plugnpay_api_cc/ → <shop>/extensions/plugnpay_api_cc/
   ```

2. Admin → **Extensions** → **Payments** → install / enable **PlugnPay Remote API**.

3. Configure as above.

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Publisher Name | `plugnpay_api_cc_login` | PlugnPay username (`publisher-name`) |
| Remote Client Password | `plugnpay_api_cc_key` | `publisher-password` |
| Publisher Email | `plugnpay_api_cc_pubemail` | Optional notify address |
| Authorization Type | `plugnpay_api_cc_authtype` | `authonly` or `authpostauth` |
| Prevent Gateway Customer Email | `plugnpay_api_cc_emailcust` | `yes` / `no` |
| Request CVV | `plugnpay_api_cc_use_cvv` | `1` / `0` |
| Completed Order Status | `plugnpay_api_cc_order_status_id` | Used for `authpostauth`; `authonly` forces Pending |
| Decline attempts limit | `plugnpay_api_cc_decline_limit` | Locks customer after N sequential declines |
| Debug Logging | `plugnpay_api_cc_debugging` | `0` = Off, `1` = Log File |
| Location | `plugnpay_api_cc_location_id` | Optional geo restriction |

**Authorization Type mapping**

- `authonly` → authorize only; order forced to Pending until settled in PlugnPay Admin
- `authpostauth` → authorize and settle (sale); uses Completed Order Status

## Checkout flow

Designed for AbanteCart **1.4.x fast checkout**:

1. Customer selects PlugnPay Remote API and enters card details on the payment confirmation pane (stacked full-width fields).
2. Browser AJAX POSTs to `extension/plugnpay_api_cc/send` (same route style as core gateways such as Authorize.Net / CardConnect).
3. Module POSTs to `pnpremote.cgi` with hyphenated Remote API fields and configured `authtype`.
4. On `FinalStatus=success`, the order is confirmed; `orderID` and auth code are written to order history; customer is redirected to **`checkout/finalize`**.
5. On decline / error, the customer stays on the payment form with the gateway message (spinner overlay is cleared).

### Card form (storefront UI)

- Labels sit **above** inputs (not skinny side-by-side `col-sm-*` columns).
- Short labels: Name on Card, Card Number, Expiry, Security Code.
- Expiry months use short names (`01 - Jan`).
- Confirm button is standard `btn-primary` (no `lock-on-click` — that class disabled the button mid-click and blocked submit on 1.4).

## Logging

Set **Debug Logging** to **Log File**. Sanitized logs are written under the AbanteCart logs directory as:

```
plugnpay_api_YYYYMMDD.log
```

Never logged: PAN, CVV, expiry, or publisher-password (including last-4). Debug must stay Off in production.

Standalone filter tests (no AbanteCart bootstrap):

```
php AbanteCart_v1.4.x/tests/run.php
```

## Troubleshooting

| Symptom | What to check |
|---|---|
| Endless spinner on Confirm / nothing happens | Confirm package is **v1.0.1+** (no `lock-on-click`; delegated `#plugnpay` submit). Hard-refresh or re-upload the extension. |
| “The page you requested cannot be found!” after approval | Success must go to `checkout/finalize` (v1.0.1+). Re-upload if still on v1.0.0. |
| Method missing at checkout | Storefront HTTPS enabled; Publisher Name + Remote Client Password set |
| Blank / communication error | Outbound HTTPS from the shop server to `pay1.plugnpay.com` (see curl test below) |
| CSRF / unexpected error on retry | Decline path refreshes CSRF tokens; reload payment step if retries keep failing |

Test connectivity from the server:

```bash
curl -d "publisher-name=YOUR_ACCOUNT&publisher-password=YOUR_REMOTE_PASSWORD&mode=auth&authtype=authonly&card-name=cardtest&card-number=4111111111111111&card-exp=01/30&card-cvv=123&card-amount=1.23" https://pay1.plugnpay.com/payment/pnpremote.cgi
```

You should receive a URL-encoded response containing `FinalStatus=…`.

If the response is blank: firewall / outbound HTTPS / DNS issue.

## Manual test checklist

- [ ] Extension installs via `.tar.gz` package upload
- [ ] Extension appears under Extensions → Payments
- [ ] Configuration shows Publisher Name / Remote Client Password / Auth Type (no Test Mode / Capture-Void-Refund UI)
- [ ] Card form fields are full-width and readable in fast checkout (no clipped inputs / severe label wrap)
- [ ] Confirm processes payment (no endless spinner); Network tab shows POST to `extension/plugnpay_api_cc/send`
- [ ] Approved authonly creates a **Pending** order with AUTH + orderID history and lands on **`checkout/finalize`**
- [ ] Approved authpostauth uses Completed Order Status
- [ ] Declined card shows canned message (not raw gateway HTML) and restores the payment form
- [ ] Debug log never contains PAN/CVV/password (including last-4)
- [ ] Method hidden without HTTPS
- [ ] Location restriction works

## File map

```
src/extensions/plugnpay_api_cc/
  config.xml
  main.php
  README.md
  core/
    plugnpay_api_cc.php
    PnPApi.php
    PnPFilter.php
    PnPLogger.php
  admin/language/english/plugnpay_api_cc/plugnpay_api_cc.xml
  storefront/
    controller/responses/extension/plugnpay_api_cc.php
    model/extension/plugnpay_api_cc.php
    language/english/plugnpay_api_cc/plugnpay_api_cc.xml
    view/default/template/responses/plugnpay_api_cc.tpl
    view/default/image/securitycode.jpg
  image/
    icon.png
    icon-hi-resolution.png
```

## Changelog

### v1.0.3

- Reject PAN/CVV values containing unexpected characters instead of silently stripping them
- Reject array-shaped card fields and invalid/non-finite amounts
- Fail closed when the order payment-method key is absent or different
- Clear the raw gateway response after parsing
- Disable logging when no protected writable log directory is configured

### v1.0.2

- Strict PAN/CVV/expiry/name filtering (Luhn, length, charset)
- Reject already-paid orders before a second gateway authorize
- Approve only `FinalStatus=success`; require HTTP 200 and TLS 1.2+
- Canned shopper errors; do not echo gateway or cURL messages
- Debug logs never store PAN/SAD/passwords (including last-4)
- Require an actual HTTPS request to offer and process payment
- $0 `checkcard` path removed

### v1.0.1

- Restructure onsite credit card form for AbanteCart 1.4 fast checkout (stacked full-width fields)
- Shorten field labels / expiry month text to reduce wrapping
- Fix submit hang: remove `lock-on-click` (it disabled the button mid-click and blocked submit); use delegated submit + `validateForm()`
- POST payment to `extension/plugnpay_api_cc/send` (same pattern as core gateways)
- Success redirect uses `checkout/finalize` (replaces removed `checkout/success`)

### v1.0.0

- Initial Remote API release for AbanteCart 1.4.x

## Uninstall

1. Admin → Extensions → Payments → uninstall **PlugnPay Remote API**.
2. Optionally remove `extensions/plugnpay_api_cc/` from the shop filesystem.

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) for Remote API details.
