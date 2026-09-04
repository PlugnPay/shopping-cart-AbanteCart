# AbanteCart 1.4.x — PlugnPay Payment Modules

Extension packages for AbanteCart **1.4.x** (target **1.4.4**). Current packages: Remote API **v1.0.3**, Smart Screens v2 **v1.0.3**.

Install through Admin → **Extensions** → **Install Extension** (`.tar.gz` upload), then enable under Admin → **Extensions** → **Payments**.

## Choose a module

| | Remote API | Smart Screens v2 |
|---|---|---|
| Package | `plugnpay_api_cc` | `plugnpay_ss2` |
| Download | [abantecart_1.4_api_module.tar.gz](./abantecart_1.4_api_module.tar.gz) | [abantecart_1.4_ss2_module.tar.gz](./abantecart_1.4_ss2_module.tar.gz) |
| Checkout | Onsite card fields → `pnpremote.cgi` | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card data on your server | Yes | No |
| PCI scope | Higher | Lower |
| Transaction mode | Auth-only or sale (`authonly` / `authpostauth`) | Authorization-only |
| Admin Capture / Void / Refund | No (use PlugnPay Admin) | No (use PlugnPay Admin) |
| Public demo account | No — merchant credentials only | No — merchant credentials only |

You may install both extensions; enable only the payment method(s) you need under Extensions → Payments.

## Remote API (onsite)

- Source: [src/extensions/plugnpay_api_cc/](./src/extensions/plugnpay_api_cc/)
- Full docs: [src/extensions/plugnpay_api_cc/README.md](./src/extensions/plugnpay_api_cc/README.md)
- Quick install: [INSTALL.txt](./INSTALL.txt)
- Package version: **v1.0.3**

Collects card data on your storefront (fast-checkout friendly stacked form) and posts from the server to PlugnPay Remote API (`authonly` or `authpostauth`). On approval the customer is sent to **`checkout/finalize`**. Capture / void / refund are done in PlugnPay Merchant Admin.

If upgrading from API **v1.0.2 or older**, re-upload `abantecart_1.4_api_module.tar.gz` so the stricter card-field, amount, and payment-method validation is applied.

## Smart Screens v2 (hosted)

- Source: [src/extensions/plugnpay_ss2/](./src/extensions/plugnpay_ss2/)
- Full docs: [src/extensions/plugnpay_ss2/README.md](./src/extensions/plugnpay_ss2/README.md)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)
- Package version: **v1.0.3**

Redirects customers to PlugnPay hosted Smart Screens. An authenticated return POST hits `rt=r/extension/plugnpay_ss2/callback`, then completes the AbanteCart order as **Pending** and sends the customer to **`checkout/finalize`** (authorization-only). A server-only PlugnPay Response Verification Hash is required; SHA-256 is preferred. Capture / void / refund are done in PlugnPay Merchant Admin, not from AbanteCart.

If upgrading from SS2 **v1.0.2 or older**, re-upload `abantecart_1.4_ss2_module.tar.gz`, configure the Response Verification Hash, and verify secure `SameSite=None` session cookies before re-enabling the method.

## Common install steps (both)

1. Upload the module `.tar.gz` via Admin → **Extensions** → **Install Extension** → **Extension Upload**.
2. Accept the license agreement.
3. Admin → **Extensions** → **Payments** → enable the method and configure credentials.

AbanteCart’s package installer accepts **`.tar.gz` only** (not `.zip`).

## Requirements

- AbanteCart **1.4.x** (tested target: **1.4.4**)
- PHP **8.2+**
- Storefront **HTTPS**

## Development layout

```
AbanteCart_v1.4.x/
  README.md
  INSTALL.txt                 # API quick install
  INSTALL_SS2.txt             # Smart Screens v2 quick install
  package.xml / license.txt   # API packaging metadata
  package_ss2.xml / license_ss2.txt
  abantecart_1.4_api_module.tar.gz
  abantecart_1.4_ss2_module.tar.gz
  tests/run.php               # php tests/run.php
  src/extensions/
    plugnpay_api_cc/
    plugnpay_ss2/
```
