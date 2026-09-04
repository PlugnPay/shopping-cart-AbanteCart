# Shopping Cart - AbanteCart Payment Modules

Easy to install payment modules for the AbanteCart shopping cart.
Multiple payment styles are supported, each covering a different checkout need.

## Downloads by AbanteCart version

### AbanteCart v1.4.x (current)

* **API (Remote Auth)** — onsite card collection
  - [Download](./AbanteCart_v1.4.x/abantecart_1.4_api_module.tar.gz) (`.tar.gz` — required for Admin package upload)
  - Source: [./AbanteCart_v1.4.x/src/extensions/plugnpay_api_cc/](./AbanteCart_v1.4.x/src/extensions/plugnpay_api_cc/)
  - Docs: [API README](./AbanteCart_v1.4.x/src/extensions/plugnpay_api_cc/README.md)
* **Smart Screens v2** — gateway hosted checkout
  - [Download](./AbanteCart_v1.4.x/abantecart_1.4_ss2_module.tar.gz) (`.tar.gz` — required for Admin package upload)
  - Source: [./AbanteCart_v1.4.x/src/extensions/plugnpay_ss2/](./AbanteCart_v1.4.x/src/extensions/plugnpay_ss2/)
  - Docs: [Smart Screens v2 README](./AbanteCart_v1.4.x/src/extensions/plugnpay_ss2/README.md)

Package overview: [./AbanteCart_v1.4.x/README.md](./AbanteCart_v1.4.x/README.md)

### AbanteCart v1.2.x (legacy)

* **API (Remote Auth)** — onsite card collection
  - [Download](./AbanteCart_v1.2.x/abantecart_1.2_api_module.zip)
  - Source: [./AbanteCart_v1.2.x/src/extensions/plugnpay_api_cc/](./AbanteCart_v1.2.x/src/extensions/plugnpay_api_cc/)
  - Docs: [API README](./AbanteCart_v1.2.x/src/extensions/plugnpay_api_cc/README.md)
* **Smart Screens v1** — gateway hosted checkout (incomplete draft)
  - [Download](./AbanteCart_v1.2.x/abantecart_1.2_ss1_module.zip)
  - Source: [./AbanteCart_v1.2.x/src/extensions/plugnpay/](./AbanteCart_v1.2.x/src/extensions/plugnpay/)
  - Docs: [SS1 README](./AbanteCart_v1.2.x/src/extensions/plugnpay/README.md)

Package overview: [./AbanteCart_v1.2.x/README.md](./AbanteCart_v1.2.x/README.md)

## Installation

For complete instructions, open the README inside the package (or the linked docs above).

### AbanteCart 1.4.x

1. Download the `.tar.gz` for the module you want (API or Smart Screens v2).
2. Admin → **Extensions** → **Install Extension** → **Extension Upload** → select the `.tar.gz`.
3. Accept the license agreement and finish the installer.
4. Admin → **Extensions** → **Payments** → enable the method and enter credentials.

- API quick install: [AbanteCart_v1.4.x/INSTALL.txt](./AbanteCart_v1.4.x/INSTALL.txt)
- Smart Screens v2 quick install: [AbanteCart_v1.4.x/INSTALL_SS2.txt](./AbanteCart_v1.4.x/INSTALL_SS2.txt)

### AbanteCart 1.2.x (legacy)

1. Download the zip for the module you want.
2. Unzip into the AbanteCart **root** so `extensions/…` merges in place.
3. Admin → **Extensions** → **Payments** → install / enable and configure.

## Usage

### API (Remote Auth)

* AbanteCart handles checkout on your site.
* Your store collects payment information (increases PCI scope).
* Customer never leaves your site and does not see the PlugnPay billing pages.
* Storefront HTTPS is required (production only; no Test/Production toggle).
* Authorization Type: `authonly` or `authpostauth` (1.4.x module).
* Capture / void / refund are done in PlugnPay Merchant Admin (not from AbanteCart).
* For AbanteCart 1.4.x: extension package **v1.0.3** with strict card-field, amount, payment-method, TLS, and response filtering.

### Smart Screens v2

* Hosted checkout at `https://pay1.plugnpay.com/pay/`.
* AbanteCart does **not** collect sensitive payment data at checkout.
* Customer is redirected to PlugnPay, then returned to AbanteCart after approval or decline.
* Return route (1.4.x): `rt=r/extension/plugnpay_ss2/callback` → success lands on `checkout/finalize`.
* HTTPS on your store is required (return URL / session).
* For AbanteCart 1.4.x: extension package **v1.0.3**; authenticated gateway return using a server-only Response Verification Hash (SHA-256 preferred).
* No public demo / test publisher — use merchant-supplied credentials.

### Smart Screens v1 (legacy / incomplete)

* Hosted checkout draft for AbanteCart **1.2.x only**.
* Incomplete — not recommended for production.
* Prefer Smart Screens **v2** on AbanteCart 1.4.x for new hosted installs.

## Repository layout

```
shopping-cart-AbanteCart/
  README.md
  AbanteCart_v1.4.x/          # current (1.4.x) — API + Smart Screens v2
  AbanteCart_v1.2.x/          # legacy (1.2.x)
```

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the module README for integration details.
