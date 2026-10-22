# ChangeLog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the version numbers
follow [Semantic Versioning](https://semver.org/).

## 1.0.0 (2026-10-22)

First stable, public release. The 0.4.0 payment flow has been running in production
with real customer payments; this release packages it for publication on GitHub.

### Added

- About page shows the source repository, the support address and a disclaimer.
- Module README in English and Dutch (shown on the module card in Dolibarr).

### Changed

- The webhook URL sent to Mollie is now resolved with `dol_buildpath()`, so the
  module also works when it is installed outside `htdocs/custom/`.
- The API client loads the module's language file itself, so its error messages
  are translated in every context (webhook, return page).
- Project documentation, changelog and build tooling are in English; the
  repository moved to https://github.com/KleijnICT/dolibarr-mollie-plugin.

## 0.4.0 (2026-08-31)

The complete payment flow: payment link → Mollie hosted checkout → automatic
recording of the payment. (Includes the previously planned 0.2.0 and 0.3.0.)

- `MollieClient`: thin curl client for the Mollie v2 API (Bearer authentication,
  TLS verification, time-outs, `Idempotency-Key` support, structured error
  handling). No SDK, no Composer dependencies.
- Setup page: test and live API key (masked, prefix validation), live-mode switch,
  bank account, payment mode and "Save and test connection" (lists the active
  payment methods).
- The `newpayment` hooks: registration as a payment method as soon as a key is
  configured, payment button, checkout creation with redirect, status verification
  on the return page (fetch-and-verify via the transaction id in the session) and
  the bank account hook. Extra hook context `invoicecard` so the payment link
  appears on the invoice card.
- `public/webhook.php` as the primary recorder of payments: fetch-and-verify,
  deduplication on `ext_payment_id`, canonical recording path
  (`Paiement->create` + `addPaymentToBank`), Mollie's `paidAt` as payment date
  and defensive handling of all edge cases. The return page deliberately does
  not record anything (no `FinalPaymentAmt` in the session).
- No idempotency key when creating a checkout (re-creating one for the same
  invoice is legitimate; Mollie rejects a reused key as soon as any parameter
  differs).
- `webhookUrl` is omitted on localhost (fire the webhook by hand locally; on a
  public host Mollie calls it itself).

## 0.1.0 (2026-08-30)

Module skeleton.

- Module descriptor following the Dolibarr 23 conventions (hook context
  `newpayment` declared).
- Empty hook class `ActionsMollie` (does not register a payment method yet).
- Setup and About pages (without settings yet).
- Language files nl_NL and en_US.
