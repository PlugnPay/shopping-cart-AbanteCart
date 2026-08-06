# PlugnPay Remote API Module for AbanteCart 1.4.x

**Version:** v1.0.0

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

1. Customer enters card details on the payment confirmation page.
2. Storefront AJAX POSTs to `extension/plugnpay_api_cc/send`.
3. Module POSTs to `pnpremote.cgi` with hyphenated Remote API fields and configured `authtype`.
4. On `FinalStatus=success`, order is confirmed; `orderID` and auth code are written to order history.
5. On decline / error, customer stays on payment with the gateway message.

## Logging

Set **Debug Logging** to **Log File**. Sanitized logs are written under the AbanteCart logs directory as:

```
plugnpay_api_YYYYMMDD.log
```

Never logged: full card number, CVV, or publisher-password.

## Troubleshooting

Test connectivity from the server:

```bash
curl -d "publisher-name=YOUR_ACCOUNT&publisher-password=YOUR_REMOTE_PASSWORD&mode=auth&authtype=authonly&card-name=cardtest&card-number=4111111111111111&card-exp=01/30&card-cvv=123&card-amount=1.23" https://pay1.plugnpay.com/payment/pnpremote.cgi
```

You should receive a URL-encoded response containing `FinalStatus=…`.

If the response is blank: firewall / outbound HTTPS / DNS issue.

If the method does not appear at checkout: confirm storefront HTTPS is enabled and credentials are set.

## Manual test checklist

- [ ] Extension installs via `.tar.gz` package upload
- [ ] Extension appears under Extensions → Payments
- [ ] Configuration shows Publisher Name / Remote Client Password / Auth Type (no Test Mode / Capture-Void-Refund UI)
- [ ] Approved authonly creates a **Pending** order with AUTH + orderID history
- [ ] Approved authpostauth uses Completed Order Status
- [ ] Declined card shows gateway message and restores checkout
- [ ] Debug log redacts PAN/CVV/password
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

## Uninstall

1. Admin → Extensions → Payments → uninstall **PlugnPay Remote API**.
2. Optionally remove `extensions/plugnpay_api_cc/` from the shop filesystem.

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) for Remote API details.
