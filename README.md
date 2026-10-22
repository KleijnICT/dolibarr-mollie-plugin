# Mollie for Dolibarr

**English** · [Nederlands](README.nl.md)

[![License: GPL v3](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![Latest release](https://img.shields.io/github/v/release/KleijnICT/dolibarr-mollie-plugin)](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest)
[![CI](https://github.com/KleijnICT/dolibarr-mollie-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/KleijnICT/dolibarr-mollie-plugin/actions/workflows/ci.yml)

A payment module for [Dolibarr ERP/CRM](https://www.dolibarr.org/) 23+ that lets your
customers pay invoices through [Mollie](https://www.mollie.com/). The customer clicks the
payment link in the invoice e-mail or on the invoice card, pays in the Mollie hosted
checkout (iDEAL, cards, Bancontact, PayPal and every other method enabled in your Mollie
dashboard), and the payment is recorded in Dolibarr automatically. The invoice is marked
as paid.

Built and used in production by [KleijnICT B.V.](https://kleijnict.nl), published under
the GPL v3 so other Dolibarr users can benefit from it.

## Features

- **Pay by link.** Works with the payment link on every validated customer invoice and
  with the `__ONLINE_PAYMENT_URL__` / `__ONLINE_PAYMENT_TEXT_AND_URL__` placeholders in
  Dolibarr's e-mail templates. Deposit (down payment) invoices are supported.
- **Hosted checkout.** Mollie shows the payment methods you enabled in the dashboard;
  nothing to configure per method in Dolibarr.
- **Automatic recording.** The Mollie webhook verifies the payment with the Mollie API
  ("fetch-and-verify") and records it on a bank account of your choice, with the Mollie
  transaction id on the payment record. A payment is never recorded twice.
- **Test and live mode** with separate API keys and a one-click connection test.
- **No external dependencies.** The Mollie v2 API is called through a small curl client
  of about 200 lines that you can audit in full. No SDK, no Composer.
- **Minimal footprint.** The module hooks into Dolibarr's core online payment framework
  (the same one used by the PayPal and Stripe integrations). It adds no tables, menus
  or permissions and can be disabled without losing data.

## Requirements

- Dolibarr 23.0 or newer (developed and tested on 23.0.x)
- PHP 7.4 or newer with the `curl` and `json` extensions
- Dolibarr modules **Invoices** and **Banks & Cash** enabled
- A [Mollie](https://www.mollie.com/) account with API keys
- HTTPS and a Dolibarr instance that is reachable from the internet (for the webhook)

## Installation

1. Download `module_mollie-<version>.zip` from the
   [latest release](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest).
2. In Dolibarr go to **Home › Setup › Modules/Applications**, open the tab
   **Deploy/install external app/module** and upload the zip.
3. Enable the module **Mollie** (family "Interfaces") and open its setup page.
4. Enter your Mollie **test** key, pick the bank account and payment mode and click
   **Save and test connection**.

The step-by-step guide, including the Dolibarr settings you need, the webhook check,
an end-to-end test and the go-live checklist, is in **[docs/INSTALL.md](docs/INSTALL.md)**
(Dutch: [docs/INSTALL.nl.md](docs/INSTALL.nl.md)).

## Configuration

| Setting | Meaning |
| --- | --- |
| Test API key / Live API key | Your Mollie keys (`test_…` / `live_…`). Stored as Dolibarr constants, shown masked. |
| Live mode | Off: customers see the Mollie test checkout, no money moves. On: real payments with the live key. |
| Bank account | The Dolibarr bank account on which Mollie payments are recorded. Use a dedicated operational account such as "Mollie". |
| Payment mode | The payment mode on the payment records, for example "Online payment" (code `VAD`). |

Payment methods, branding and payout settings are managed in your Mollie dashboard.

## How it works

```
customer ──click──▶ Dolibarr payment page ──"Pay with Mollie"──▶ Mollie hosted checkout
                                                                        │
         ┌── return page: shows the result (verified at Mollie) ◀───────┤
         │                                                              │
         └── webhook: fetches the payment at Mollie, records it,  ◀─────┘
             marks the invoice as paid (once, deduplicated)
```

- The module registers itself as an online payment method through the `newpayment`
  hooks of Dolibarr core as soon as an API key is configured. The secure per-invoice
  payment URL, the e-mail placeholders and the remaining-amount logic all come from core.
- `public/webhook.php` is the only place where a payment is recorded. Mollie only sends
  a payment id; the module fetches the payment with its own key, checks status, currency
  and amount against the invoice and records it through Dolibarr's standard payment path
  (`Paiement->create` + `addPaymentToBank`). The payment record carries the Mollie id in
  `ext_payment_id`, which is also the deduplication key.
- The return page only displays the result; it never records anything, so closing the
  browser during payment cannot lose a booking.

## Known limitations

- Customer invoices only (including deposit invoices). Proposals, orders, memberships
  and donations are not supported.
- Payments must be in the company currency.
- One Dolibarr entity: the Multicompany module is not supported.
- Mollie payouts, transaction fees and refunds are not synchronised back to Dolibarr.
  Reconcile them in your accounting as you do today.

## Disclaimer: use at your own risk

This module handles payment traffic and writes payment records into your accounting
data. It is provided **"as is", without warranty of any kind**, express or implied, as
set out in sections 15 and 16 of the GNU GPL v3. KleijnICT B.V. and the contributors
accept no liability for lost or unrecorded payments, incorrect bookings, data loss or
any other damage arising from its use.

You are responsible for testing the module with a Mollie test key before enabling live
mode, for keeping backups, for the security of your Dolibarr installation and for
complying with Mollie's terms of service.

This project is independent and is not affiliated with, endorsed by or supported by
Mollie B.V. or the Dolibarr project. "Mollie" and "Dolibarr" are trademarks of their
respective owners.

## Help and support

- **Installation help and support.** Need a hand installing or configuring the module,
  or would you like KleijnICT to set it up for you? Contact us at
  **[support@kleijnict.nl](mailto:support@kleijnict.nl)**.
- **Bugs and feature requests.** Please use the
  [issue tracker](https://github.com/KleijnICT/dolibarr-mollie-plugin/issues). Never
  include API keys in a report.
- **Security problems.** Report them privately, see [SECURITY.md](SECURITY.md).

Support is provided on a best-effort basis. There is no guaranteed response time and
no service level agreement unless you have one with KleijnICT.

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for the development
environment (Docker Compose with Dolibarr 23), the coding standard and the release
process. This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).

## Repository layout

| Path | Contents |
| --- | --- |
| `mollie/` | The module itself (this is what the release zip contains) |
| `docs/` | Installation guide in English and Dutch |
| `dev/` | Local Docker development environment and check scripts |
| `build.sh` | Builds `dist/module_mollie-<version>.zip` |
| `.github/` | CI (syntax and language checks) and the release workflow |

The changelog is in [`mollie/ChangeLog.md`](mollie/ChangeLog.md).

## License

Copyright © 2026 KleijnICT B.V.

This program is free software: you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version. See [LICENSE](LICENSE).
