# Mollie for Dolibarr

Accept online payments on customer invoices through [Mollie](https://www.mollie.com/).
Customers pay through the Mollie hosted checkout (iDEAL, cards, Bancontact, PayPal and
every other method enabled in your Mollie dashboard). Paid payments are verified through
the Mollie webhook and recorded automatically on a configurable bank account; the invoice
is marked as paid.

The module plugs into Dolibarr's built-in online payment framework, so the payment link
on the invoice card and the `__ONLINE_PAYMENT_URL__` placeholder in e-mail templates work
out of the box. It has no external PHP dependencies: the Mollie API is called through a
small, auditable curl client.

## Configuration

Home › Setup › Modules/Applications › Mollie (gear icon):

1. **Test API key** (`test_…`); add the live key only at go-live.
2. **Live mode**: off for test payments, on for real payments.
3. **Bank account**: the operational counter account on which payments are recorded
   (for example "Mollie").
4. **Payment mode** used for the payment records (for example "Online payment",
   code `VAD`, enabled in the Payment modes dictionary).
5. **Save and test connection**: shows the payment methods active in your Mollie
   dashboard.

Also required: `PAYMENT_SECURITY_TOKEN` (and `PAYMENT_SECURITY_TOKEN_UNIQUE` = 1) on
the instance, and `custom/mollie/public/webhook.php` must be reachable from the
internet (an HTTP 405 answer in the browser is the expected behaviour).

## Documentation and support

- Installation guide, go-live checklist and troubleshooting:
  https://github.com/KleijnICT/dolibarr-mollie-plugin/blob/main/docs/INSTALL.md
- Bugs and feature requests: https://github.com/KleijnICT/dolibarr-mollie-plugin/issues
- Help with installation or configuration: support@kleijnict.nl

## Disclaimer

This module is free software distributed under the GNU GPL v3 or later, without any
warranty. It processes payments and writes payment records into your Dolibarr data.
Use it at your own risk: test it with a Mollie test key before enabling live mode and
keep backups. This project is not affiliated with Mollie B.V. or the Dolibarr project.

## License

GPL v3 or later, see `COPYING`. © KleijnICT B.V., https://kleijnict.nl
