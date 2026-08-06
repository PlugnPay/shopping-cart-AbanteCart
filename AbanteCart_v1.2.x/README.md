# AbanteCart 1.2.x — PlugnPay Payment Modules (legacy)

Legacy packages for AbanteCart **1.2.x**. Prefer [AbanteCart_v1.4.x](../AbanteCart_v1.4.x/) for new installs.

## Choose a module

| | Remote API | Smart Screens v1 |
|---|---|---|
| Package | `plugnpay_api_cc` | `plugnpay` |
| Download | [abantecart_1.2_api_module.zip](./abantecart_1.2_api_module.zip) | [abantecart_1.2_ss1_module.zip](./abantecart_1.2_ss1_module.zip) |
| Source | [src/extensions/plugnpay_api_cc/](./src/extensions/plugnpay_api_cc/) | [src/extensions/plugnpay/](./src/extensions/plugnpay/) |
| Docs | [API README](./src/extensions/plugnpay_api_cc/README.md) | [SS1 README](./src/extensions/plugnpay/README.md) |
| Checkout | Onsite card → `pnpremote.cgi` | Hosted redirect |
| Card data on your server | Yes | No |
| Status | Legacy | Incomplete draft |

## Remote API (onsite)

- Source: [src/extensions/plugnpay_api_cc/](./src/extensions/plugnpay_api_cc/)
- Download: [abantecart_1.2_api_module.zip](./abantecart_1.2_api_module.zip)

Collects card data on your storefront and posts to PlugnPay Remote API. Authorization / capture options use the older `authorization` / `capture` setting labels.

## Smart Screens v1 (hosted)

- Source: [src/extensions/plugnpay/](./src/extensions/plugnpay/)
- Download: [abantecart_1.2_ss1_module.zip](./abantecart_1.2_ss1_module.zip)

Incomplete hosted checkout draft. Not recommended for production.

## Install steps (both)

1. Unzip the module zip into the AbanteCart **root** so `extensions/…` merges in place.
2. Admin → **Extensions** → **Payments** → install / enable the method.
3. Enter credentials and configure order status / location as needed.

## Development layout

```
AbanteCart_v1.2.x/
  README.md
  abantecart_1.2_api_module.zip
  abantecart_1.2_ss1_module.zip
  src/extensions/
    plugnpay_api_cc/
    plugnpay/
```
