# PlugnPay Smart Screens v2 Module for AbanteCart 1.4.x

**Version:** v1.0.3

Hosted **authorization-only** payments via PlugnPay Smart Screens v2 (`https://pay1.plugnpay.com/pay/`).

This extension follows the same setup and usage as the Zen Cart 2.2 `PlugnPaySs2` plugin.

## Features

- Offsite / hosted checkout (card data collected on PlugnPay)
- Authorization-only (`pb_post_auth=no`) — orders stay Pending
- Dedicated `plugnpay_ss2` database table for gateway communications (optional)
- Debug logging with PAN / CVV / password redaction
- Authenticated gateway returns using a server-only Response Verification Hash
- One-time session, order, amount, currency, and gateway-account binding
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

This module does **not** collect cardholder data through the AbanteCart payment form. Customers are redirected to PlugnPay’s hosted Smart Screens pages. Your actual PCI DSS scope depends on the complete environment and must be confirmed with your acquirer or QSA.

## Requirements

- AbanteCart **1.4.x** (tested target: **1.4.4**)
- PHP **8.2+**
- Storefront **HTTPS** (required for reliable return session)
- PlugnPay **gateway account username** (merchant-supplied; there is no public demo account)
- PlugnPay **Response Verification Hash** configured under Security Administration (SHA-256 preferred)
- Secure cookies configured with `SameSite=None` for the cross-site POST callback

No Remote Client Password or cURL is required for this module (those apply to the Remote API module).

## Installation

### Admin package upload (recommended)

AbanteCart’s package installer accepts **`.tar.gz` only**.

1. Upload `abantecart_1.4_ss2_module.tar.gz` via Admin → **Extensions** → **Install Extension** → **Extension Upload**.
2. Accept the license and finish the installer.
3. Admin → **Extensions** → **Payments** → enable **PlugnPay Smart Screens v2**.
4. In PlugnPay Security Administration, enable the outbound Response Verification Hash and choose SHA-256 when available.
5. Configure the same server-only hash in AbanteCart along with Gateway Account, currency, database storage, debug logging, and location.
6. Place a test order with your merchant account (per your PlugnPay procedures).

### Manual FTP install

```
AbanteCart_v1.4.x/src/extensions/plugnpay_ss2/ → <shop>/extensions/plugnpay_ss2/
```

Then enable under Admin → Extensions → Payments.

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Gateway Account | `plugnpay_ss2_login` | PlugnPay username (`pt_gateway_account`) |
| Response Verification Hash | `plugnpay_ss2_response_hash` | Required server-only outbound verification secret; SHA-256 preferred |
| Currency Supported | `plugnpay_ss2_currency` | USD, CAD, GBP, EUR, AUD, NZD |
| Enable Database Storage | `plugnpay_ss2_store_data` | Allowlisted snapshots only; default **No** |
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
https://{store}/index.php?rt=r/extension/plugnpay_ss2/callback
```

Do **not** use bare `rt=extension/plugnpay_ss2/callback` — that can 404 on storefront routing. Success then goes to `checkout/finalize` (not the removed `checkout/success` page).

### Return validation

Accepted return POST must include `pi_response_status`. On `success`, the module checks:

- PlugnPay `pt_transaction_response_hash` (or legacy `resphash`) against the server-only Response Verification Hash; SHA-256 and legacy MD5 responses are supported
- One-time return token and session-binding MAC (`abc_return_token` / `abc_return_mac`) against values stored in the checkout session
- Session `expected_order_id` (POST/GET order ids are ignored)
- Returned `pt_transaction_amount` matches the amount stored in session (integer cents)
- Returned `pt_currency` is present and matches
- Returned `pt_gateway_account` is present and matches the configured Gateway Account
- Order `payment_method_key` is present and exactly `plugnpay_ss2`
- Already-confirmed orders are not status-downgraded; customer is sent to finalize

No session or order identifier is placed in `pb_success_url`. The browser must return the secure session cookie; configure it with `SameSite=None`.

### Key fields submitted to `/pay/`

| Field | Purpose |
|---|---|
| `pt_gateway_account` | Merchant gateway account |
| `pt_transaction_amount` | Order total (converted to gateway currency if needed) |
| `pt_currency` / `pt_currency_code` | Currency sent to Smart Screens |
| `pb_post_auth` | Always `no` (authorization-only) |
| `pt_account_code_1` | AbanteCart order id |
| `pt_payment_name` + billing fields | Prefill billing on hosted page |
| `pb_success_url` | Return URL → `r/extension/plugnpay_ss2/callback` (no session id in URL) |
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

When **Enable Database Storage** is Yes, install creates `{prefix}plugnpay_ss2` and stores allowlisted status/amount/auth/txn snapshots (not full POST, not session ids). Default is **No**. Uninstall drops the table.

Never logged: PAN, CVV, passwords, return tokens, or session ids.

Standalone filter tests (no AbanteCart bootstrap):

```
php AbanteCart_v1.4.x/tests/run.php
```

## Troubleshooting

| Symptom | What to check |
|---|---|
| Customer returns but order not confirmed / session error | Ensure return hits HTTPS `rt=r/extension/plugnpay_ss2/callback`; configure the session cookie `Secure; SameSite=None` |
| “The page you requested cannot be found!” after return | Confirm package is **v1.0.1+** (`r/` callback route + `checkout/finalize`). Re-upload/reinstall if still on v1.0.0. |
| “Amount did not match” | Cart total changed between confirm and return, or currency conversion mismatch |
| “Gateway account” mismatch | Returned `pt_gateway_account` ≠ configured Gateway Account |
| Decline / fraud message | Expected; customer is sent back to fast checkout |
| Need to capture / void / refund | Use PlugnPay Merchant Admin |

## Changelog

### v1.0.3

- Require PlugnPay's server-secret Response Verification Hash before confirming an order
- Prefer SHA-256 while retaining legacy MD5 response-hash compatibility
- Remove session identifiers from the callback URL
- Fail closed when the order payment-method key is absent or different
- Require returned `pt_account_code_1` to match the session order
- Consume a correctly session-bound callback once, including failed validation
- Reject malformed, array-shaped, over-precise, or incomplete callback fields
- Disable logging when no protected writable log directory is configured
- Use InnoDB storage without a legacy session-id column

### v1.0.2

- Merchant return token + HMAC required on callback (POST/GET order ids ignored)
- Amount compared in integer cents; currency and gateway account required
- Do not downgrade already-confirmed orders; require `plugnpay_ss2` payment method
- Canned shopper errors; allowlisted hosted fields, logs, and DB columns
- Database storage defaults off; no session id stored in the SS2 table
- Require an actual HTTPS request for method, redirect, and callback

### v1.0.1

- Fix return callback routing for AbanteCart 1.4.x: `rt=r/extension/plugnpay_ss2/callback`
- Success redirect uses `checkout/finalize` (replaces removed `checkout/success`)
- Decline / error redirect uses `checkout/fast_checkout`

### v1.0.0

- Initial Smart Screens v2 release for AbanteCart 1.4.x

## Manual test checklist

- [ ] Extension installs via `.tar.gz` package upload
- [ ] Configuration shows Gateway Account / Response Verification Hash / Currency / Store Data / Debug
- [ ] Forged or missing `pt_transaction_response_hash` cannot confirm an order
- [ ] Approved payment creates a **Pending** order with AUTH + orderID history
- [ ] After approval, customer lands on **`checkout/finalize`** (not a 404)
- [ ] Return URL uses `rt=r/extension/plugnpay_ss2/callback` without a session id
- [ ] Declined card shows canned message and restores fast checkout
- [ ] Debug log never contains PAN/CVV/tokens
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
    PnPSs2Filter.php
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
