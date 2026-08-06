# PlugnPay Smart Screens v1 for AbanteCart 1.2.x (incomplete)

**Version:** v1.0.2 (draft)

Incomplete hosted Smart Screens payment draft for AbanteCart **1.2.x**.

**Not recommended for production.** Prefer the Remote API module for AbanteCart 1.4.x:

- [AbanteCart_v1.4.x](../../../AbanteCart_v1.4.x/)

## Status

- Hosted redirect toward PlugnPay payment screens
- Draft / incomplete callback and order-update handling
- Kept for historical reference only

## Requirements

- AbanteCart **1.2.x**
- PlugnPay account / secret settings as configured in the extension

## Installation (reference only)

1. Unzip `abantecart_1.2_ss1_module.zip` into the AbanteCart **root** so you have:

   ```
   extensions/plugnpay/...
   ```

   Or copy from source:

   ```
   AbanteCart_v1.2.x/src/extensions/plugnpay/ → <shop>/extensions/plugnpay/
   ```

2. Admin → **Extensions** → **Payments** → install / enable (if usable on your build).

## File map

```
src/extensions/plugnpay/
  config.xml
  main.php
  core/hooks.php
  admin/language/english/plugnpay/plugnpay.xml
  storefront/
    controller/responses/extension/plugnpay.php
    model/extension/plugnpay.php
    language/english/plugnpay/plugnpay.xml
    view/default/template/responses/plugnpay.tpl
    view/default/template/responses/pending_ipn.tpl
  image/icon.png
```

## Support

Provided AS IS. This draft is incomplete and unsupported for new installs.
