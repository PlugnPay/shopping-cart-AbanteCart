# PlugnPay Smart Screens v2 Module for AbanteCart 1.4.x

**Version:** v1.0.1

Hosted **authorization-only** payments via PlugnPay Smart Screens v2 (`https://pay1.plugnpay.com/pay/`).

This extension follows the same setup and usage as the Zen Cart 2.2 `PlugnPaySs2` plugin.

## Features

- Offsite / hosted checkout (card data collected on PlugnPay)
- Authorization-only (`pb_post_auth=no`) — orders stay Pending
- Dedicated `plugnpay_ss2` database table for gateway communications (optional)
- Debug logging with PAN / CVV / password redaction
- Basic return checks (amount, gateway account, session / order id)
- PHP **8.2+** / AbanteCart **1.4.x** (tested target: **1.4.4**)
- Production only (HTTPS required; no Test/Production toggle)

This module does **not** include AbanteCart admin Capture / Void / Refund. Settle or reverse transactions in [PlugnPay Merchant Admin](https://pay1.plugnpay.com/admin/). For onsite checkout (`authonly` / `authpostauth`), use the Remote API module (`plugnpay_api_cc`).

## When to use this vs Remote API

| | Smart Screens v2 (this module) | Remote API (`plugnpay_api_cc`) |
|---|---|---|
| Card data | Collected on PlugnPay | Collected on your store |
| Customer experience | Redirect to hosted billing page | Stays on your checkout |
| PCI scope | Lower | Higher |
| Checkout endpoint | `https://pay1.plugnpay.com/pay/` | `pnpremote.cgi` (server-to-server) |
| Transaction mode | Authorization-only | Auth-only or sale (`authonly` / `authpostauth`) |
| Admin Capture / Void / Refund | No | No |
| Public demo account | None — merchant credentials only | None — merchant credentials only |

## PCI notice

This module does **not** collect cardholder data on your server. Customers are redirected to PlugnPay’s hosted Smart Screens pages.

## Requirements

- AbanteCart **1.4.x** (tested target: **1.4.4**)
- PHP **8.2+**
- Storefront **HTTPS** (required for reliable return session)
- PlugnPay **gateway account username** (merchant-supplied; there is no public demo account)
- Enable **shared session** in AbanteCart store settings if cross-site return drops cookies (`session_id` is appended to `pb_success_url`)

No Remote Client Password or cURL is required for this module (those apply to the Remote API module).

## Installation

### Admin package upload (recommended)

AbanteCart’s package installer accepts **`.tar.gz` only**.

1. Upload `abantecart_1.4_ss2_module.tar.gz` via Admin → **Extensions** → **Install Extension** → **Extension Upload**.
2. Accept the license and finish the installer.
3. Admin → **Extensions** → **Payments** → enable **PlugnPay Smart Screens v2**.
4. Configure Gateway Account, currency, database storage, debug logging, and location.
5. Place a test order with your merchant account (per your PlugnPay procedures).

### Manual FTP install

```
AbanteCart_v1.4.x/src/extensions/plugnpay_ss2/ → <shop>/extensions/plugnpay_ss2/
```

Then enable under Admin → Extensions → Payments.

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Gateway Account | `plugnpay_ss2_login` | PlugnPay username (`pt_gateway_account`) |
| Currency Supported | `plugnpay_ss2_currency` | USD, CAD, GBP, EUR, AUD, NZD |
| Enable Database Storage | `plugnpay_ss2_store_data` | Writes `{prefix}plugnpay_ss2` |
| Debug Logging | `plugnpay_ss2_debugging` | `0` = Off, `1` = Log File |
| Location | `plugnpay_ss2_location_id` | Optional geo restriction |

Checkout always sends `pb_post_auth=no`. Successful orders are set to **Pending**. Capture/settle in PlugnPay Admin when ready.

There is **no** Test/Production toggle and **no** public demo publisher.

## Checkout flow

1. Customer selects Credit Card (Smart Screens) — no card fields on your store.
2. Storefront auto-POSTs hidden order fields to `https://pay1.plugnpay.com/pay/`.
3. Customer completes payment on PlugnPay Smart Screens (authorization only).
4. PlugnPay POSTs back to `r/extension/plugnpay_ss2/callback` (`pb_success_url`, `pb_transition_type=post`).
5. Module validates the return (see below).
6. On success, the order is confirmed as Pending; AUTH and orderID are written to order history; customer is redirected to **`checkout/finalize`**.
7. On decline / error, the customer is returned to **`checkout/fast_checkout`** with the gateway message.

### Return URL (AbanteCart 1.4.x)

`pb_success_url` must use the **response** route prefix `r/`:

```text
https://{store}/index.php?rt=r/extension/plugnpay_ss2/callback&session_id={session}&order_id={id}
```

Do **not** use bare `rt=extension/plugnpay_ss2/callback` — that can 404 on storefront routing. Success then goes to `checkout/finalize` (not the removed `checkout/success` page).

### Return validation

Accepted return POST must include `pi_response_status`. On `success`, the module also checks:

- Returned `pt_transaction_amount` matches the amount stored in session at submit time (or order total if session was lost)
- Returned `pt_gateway_account` matches the configured Gateway Account (when present)
- Returned custom-field `abcsession` matches the session ID sent at submit and the current session

Cryptographic response-link / hash verification is **not** included in v1.0.1 (same as Zen Cart SS2 v1.0.1).

**Session restore:** `pb_success_url` includes `session_id=<session>` and `order_id=<id>` so AbanteCart can resume the checkout session after the cross-site POST. The same session and order id are also sent as `pt_custom_name_N` / `pt_custom_value_N`.

### Key fields submitted to `/pay/`

| Field | Purpose |
|---|---|
| `pt_gateway_account` | Merchant gateway account |
| `pt_transaction_amount` | Order total (converted to gateway currency if needed) |
| `pt_currency` / `pt_currency_code` | Currency sent to Smart Screens |
| `pb_post_auth` | Always `no` (authorization-only) |
| `pt_account_code_1` | AbanteCart order id |
| `pt_payment_name` + billing fields | Prefill billing on hosted page |
| `pb_success_url` | Return URL → `r/extension/plugnpay_ss2/callback` with `session_id` + `order_id` |
| `pb_transition_type` | `post` |
| `pd_display_items` | `no` |
| `pd_collect_shipping_information` | `no` |
| `pt_client_identifier` | `AbanteCart_SS2` |
| `pt_custom_name_1` / `pt_custom_value_1` | `abcsession` = session ID |
| `pt_custom_name_2` / `pt_custom_value_2` | `abc_order_id` = order ID |

## Logging

Set **Debug Logging** to **Log File**. Sanitized logs:

```
plugnpay_ss2_YYYYMMDD.log
```

When **Enable Database Storage** is Yes, install creates `{prefix}plugnpay_ss2` and stores sanitized submit/response snapshots. Uninstall drops the table.

Never logged: full card number, CVV, or publisher-password.

## Troubleshooting

| Symptom | What to check |
|---|---|
| Customer returns but order not confirmed / session error | Enable shared session; ensure return hits HTTPS `rt=r/extension/plugnpay_ss2/callback` with `session_id`; SameSite cookies |
| “The page you requested cannot be found!” after return | Confirm package is **v1.0.1+** (`r/` callback route + `checkout/finalize`). Re-upload/reinstall if still on v1.0.0. |
| “Amount did not match” | Cart total changed between confirm and return, or currency conversion mismatch |
| “Gateway account” mismatch | Returned `pt_gateway_account` ≠ configured Gateway Account |
| Decline / fraud message | Expected; customer is sent back to fast checkout |
| Need to capture / void / refund | Use PlugnPay Merchant Admin |

## Changelog

### v1.0.1

- Fix return callback routing for AbanteCart 1.4.x: `rt=r/extension/plugnpay_ss2/callback`
- Success redirect uses `checkout/finalize` (replaces removed `checkout/success`)
- Decline / error redirect uses `checkout/fast_checkout`

### v1.0.0

- Initial Smart Screens v2 release for AbanteCart 1.4.x

## Manual test checklist

- [ ] Extension installs via `.tar.gz` package upload
- [ ] Configuration shows Gateway Account / Currency / Store Data / Debug (no password / auth-type)
- [ ] Approved payment creates a **Pending** order with AUTH + orderID history
- [ ] After approval, customer lands on **`checkout/finalize`** (not a 404)
- [ ] Return URL in debug / DB storage uses `rt=r/extension/plugnpay_ss2/callback`
- [ ] Declined card shows gateway message and restores fast checkout
- [ ] Submit uses `pb_post_auth=no` (visible in debug log / DB storage)
- [ ] `plugnpay_ss2` table stores rows when Store Data is enabled
- [ ] Debug log redacts PAN/CVV/password
- [ ] Location restriction works
- [ ] Currency conversion path works when store currency ≠ gateway currency

## File map

```
src/extensions/plugnpay_ss2/
  config.xml
  main.php
  install.sql
  uninstall.sql
  README.md
  core/
    plugnpay_ss2.php
    PnPSs2Logger.php
  admin/language/english/plugnpay_ss2/plugnpay_ss2.xml
  storefront/
    controller/responses/extension/plugnpay_ss2.php
    model/extension/plugnpay_ss2.php
    language/english/plugnpay_ss2/plugnpay_ss2.xml
    view/default/template/responses/plugnpay_ss2.tpl
  image/icon.png
```

## Uninstall

1. Admin → Extensions → Payments → uninstall **PlugnPay Smart Screens v2** (drops table via uninstall.sql).
2. Optionally remove `extensions/plugnpay_ss2/` from the shop filesystem.

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/).
