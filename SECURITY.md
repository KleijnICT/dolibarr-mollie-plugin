# Security policy

This module handles payment traffic between Dolibarr and Mollie and writes payment
records into your accounting data. We take reports about its security seriously.

## Supported versions

Only the latest release on the
[releases page](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases) receives
security fixes.

## Reporting a vulnerability

Please **do not** open a public issue for security problems. Report them privately to
**support@kleijnict.nl** with the subject "Security: dolibarr-mollie-plugin" and include:

- the module version, Dolibarr version and PHP version;
- a description of the problem and, if possible, steps to reproduce it;
- the impact you expect (for example: payment recorded twice, key disclosure).

Never include real API keys in a report.

We will acknowledge your report as soon as we can and keep you informed about the fix.
The module is maintained on a best-effort basis, so we cannot guarantee a response
time. Please give us reasonable time to publish a fix before disclosing the problem
publicly; we will credit you in the changelog if you wish.

## Security model (for researchers)

- `public/webhook.php` is deliberately reachable without authentication, because Mollie
  calls it. It accepts only `POST id=tr_...`, never trusts the request body, fetches the
  payment from the Mollie API with the configured key and acts solely on that answer
  ("fetch-and-verify"). A payment is recorded at most once (deduplication on
  `llx_paiement.ext_payment_id`), only for status `paid`, only in the company currency
  and never for more than the remaining amount of the invoice.
- The public payment page and its per-invoice secure key are provided by Dolibarr core
  (`PAYMENT_SECURITY_TOKEN`); the module does not add payment pages of its own.
- API keys are stored as Dolibarr constants (encrypted by Dolibarr where supported),
  are only shown masked in the setup page and are never written to the log.
- All API calls use TLS with certificate verification and time-outs.
