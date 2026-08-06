# PlugnPay Remote API Module for AbanteCart 1.2.x (legacy)

**Version:** v1.0.0

Legacy onsite credit card payments via PlugnPay Remote API (`https://pay1.plugnpay.com/payment/pnpremote.cgi`) for AbanteCart **1.2.x**.

For AbanteCart **1.4.x**, use the updated module:

- [AbanteCart_v1.4.x](../../../AbanteCart_v1.4.x/)
- Download: [abantecart_1.4_api_module.tar.gz](../../../AbanteCart_v1.4.x/abantecart_1.4_api_module.tar.gz)

## Features (legacy)

- Onsite credit card collection
- Transaction method: Authorization or Capture (older setting labels)
- Decline attempt limit
- Location restriction

## Requirements

- AbanteCart **1.2.x**
- PHP with **cURL**
- Storefront HTTPS recommended
- PlugnPay publisher-name and Remote Client Password

## Installation

1. Unzip `abantecart_1.2_api_module.zip` into the AbanteCart **root** so you have:

   ```
   extensions/plugnpay_api_cc/...
   ```

   Or copy from source:

   ```
   AbanteCart_v1.2.x/src/extensions/plugnpay_api_cc/ → <shop>/extensions/plugnpay_api_cc/
   ```

2. Admin → **Extensions** → **Payments** → install / enable **PlugnPay (API CC)**.

3. Configure Gateway Account, Remote Client Password, transaction method, order status, and location.

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Gateway Account | `plugnpay_api_cc_login` | Publisher name |
| Remote Client Password | `plugnpay_api_cc_key` | `publisher-password` |
| Transaction Method | `plugnpay_api_cc_method` | `authorization` or `capture` |
| Order Status | `plugnpay_api_cc_order_status_id` | Status after successful payment |
| Decline attempts limit | `plugnpay_api_cc_decline_limit` | Lock customer after N declines |
| Location | `plugnpay_api_cc_location_id` | Optional geo restriction |

## File map

```
src/extensions/plugnpay_api_cc/
  config.xml
  main.php
  core/plugnpay_api_cc.php
  admin/language/english/plugnpay_api_cc/plugnpay_api_cc.xml
  storefront/
    controller/responses/extension/plugnpay_api_cc.php
    model/extension/plugnpay_api_cc.php
    language/english/plugnpay_api_cc/plugnpay_api_cc.xml
    view/default/template/responses/plugnpay_api_cc.tpl
  image/icon.png
```

## Support

Provided AS IS. Prefer the 1.4.x module for new deployments.
