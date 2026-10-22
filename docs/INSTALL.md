# Installation guide

**English** · [Nederlands](INSTALL.nl.md)

This guide takes you from an empty Dolibarr instance to live Mollie payments in a safe
order: install and configure the module in **test mode** first, prove the whole flow
with a test payment, and only then switch to live mode.

> **Use at your own risk.** The module writes payment records into your accounting
> data. Make a backup before you start, test everything with a Mollie test key, and
> read the [disclaimer](../README.md#disclaimer-use-at-your-own-risk).

## 1. Prerequisites

Check these once before you install:

- [ ] Dolibarr 23.0 or newer, PHP 7.4 or newer with the `curl` and `json` extensions.
- [ ] The modules **Invoices** and **Banks & Cash** are enabled.
- [ ] A bank account for Mollie exists: Banks & Cash › New account, type current
      account, in your company currency (for example named "Mollie"). This is the
      operational counter account on which Mollie payments are recorded; Mollie pays
      the balance out to your business bank account later.
- [ ] A payment mode for online payments is active: Home › Setup › Dictionaries ›
      Payment modes, enable **Online payment** (code `VAD`) or create your own.
- [ ] Your Dolibarr instance is served over **HTTPS** and reachable from the internet.
      Mollie must be able to call the webhook.
- [ ] A Mollie account with a **test API key** at hand (Mollie dashboard › Developers ›
      API keys). You will add the live key at go-live.

## 2. Install the module

1. Download `module_mollie-<version>.zip` from the
   [releases page](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest).
2. In Dolibarr go to **Home › Setup › Modules/Applications**, open the tab
   **Deploy/install external app/module** and upload the zip.
3. Enable the module **Mollie** in the module list (family "Interfaces").

**Manual alternative** (shell access): unzip the archive so that the module lands in
`<dolibarr>/htdocs/custom/mollie/`, make sure the web server can read it, then enable
the module. Dolibarr must allow external modules (`$dolibarr_main_url_root_alt` and
`$dolibarr_main_document_root_alt` in `conf/conf.php`, which the zip installer also
needs).

## 3. Configure the module

Open **Home › Setup › Modules/Applications › Mollie** (gear icon):

1. **Test API key**: paste your `test_…` key.
2. **Live mode**: leave on **No** until go-live.
3. **Bank account**: the "Mollie" account from step 1.
4. **Payment mode**: "Online payment".
5. Click **Save and test connection**. A green message lists the payment methods that
   are active in your Mollie dashboard. A red message means the key is wrong or Mollie
   cannot be reached from the server.

Keys are stored as Dolibarr constants and only shown masked afterwards. Leave a key
field empty to keep the stored key.

## 4. Dolibarr settings for online payments

Dolibarr's online payment framework needs a security token so that payment links
cannot be guessed:

- [ ] Go to **Home › Setup › Other setup** and check that the constant
      `PAYMENT_SECURITY_TOKEN` exists with a long random value, and that
      `PAYMENT_SECURITY_TOKEN_UNIQUE` is set to `1` (one secure key per invoice). Add
      them if they are missing.
- [ ] Open a validated invoice: the invoice card now shows an **online payment link**
      (the module registers itself as soon as a key is configured).
- [ ] If you send invoices by e-mail, put `__ONLINE_PAYMENT_URL__` or
      `__ONLINE_PAYMENT_TEXT_AND_URL__` in your invoice e-mail template
      (Home › Setup › E-mails › E-mail templates).

## 5. Check that the webhook is reachable

Open this URL in a browser, with your own domain:

```
https://<your-domain>/custom/mollie/public/webhook.php
```

The expected answer is **"Method not allowed, POST id=tr_... expected"** with HTTP
status 405. That is correct: the page is reachable and refuses everything except
Mollie's POST. A 404 means the module is not where the URL expects it; a login page
means Dolibarr's public pages are blocked.

If your Dolibarr is installed in a sub-directory, include it in the URL. The module
uses the public URL from Dolibarr's configuration (`$dolibarr_main_url_root`) when it
tells Mollie where the webhook is, so that value must be correct and public.

## 6. End-to-end test in test mode

Test payments are harmless: with a test key Mollie shows its test checkout and no money
moves.

1. Create a test customer and a small test invoice, and validate it.
2. Open the **online payment link** on the invoice card, click **Pay with Mollie** and
   choose **Paid** in the Mollie test checkout.
3. The return page in Dolibarr confirms the payment. Wait a minute: Mollie now calls
   the webhook itself.
4. Check the result: the invoice is **Paid**, there is a payment record with payment
   mode "Online payment" and the `tr_…` id in its public note, and the amount is
   credited on the Mollie bank account.
5. Also test the e-mail flow: send the invoice by e-mail and check that the payment
   link in the message works.
6. Clean up: delete the payment and the bank entry and set the invoice back to unpaid,
   or keep the test invoice as a clearly marked reference (a credit note works too).

## 7. Go live

Only after the test above succeeded:

1. Enter the **Live API key** (`live_…`) on the setup page.
2. Set **Live mode** to **Yes** and click **Save and test connection** again. The
   message must say "live mode" and list your live payment methods.
3. Make one small real payment to yourself (for example an invoice of 1 euro) and
   verify the complete recording, including the automatic webhook call.
4. Add the payment link to your default invoice e-mail template if you have not done
   so already.

Keep in mind: the money arrives on your Mollie balance and is paid out by Mollie to
your business bank account according to your Mollie settings. Payouts and transaction
fees are not reconciled inside Dolibarr by this module.

## 8. Upgrading

1. Download the new zip and upload it through **Deploy/install external app/module**;
   it overwrites the module files. Your settings are kept (they live in the database).
2. Read the [changelog](../mollie/ChangeLog.md) for anything that needs your attention.
3. Open the setup page and click **Save and test connection** once.

## 9. Disabling or removing the module

Disabling the module is enough to stop offering Mollie payments: the payment links and
buttons disappear immediately. No data is lost; payment records and bank entries stay
where they are. To remove the module completely, disable it and delete
`htdocs/custom/mollie/`.

## 10. Troubleshooting

| Symptom | Likely cause | What to do |
| --- | --- | --- |
| No "Pay with Mollie" button on the payment page, no payment link on the invoice card | No API key stored for the active mode (test or live) | Enter the key for that mode and save. In live mode the live key is required. |
| "Connection to Mollie failed" on the setup page | Wrong key, or the server cannot reach `api.mollie.com` | Check the key prefix (`test_`/`live_`), outgoing HTTPS from the server and the PHP curl extension. |
| Customer paid, but the invoice stays unpaid | Mollie could not reach the webhook, or the webhook refused the payment | Check step 5. Then read the log: the module logs to the Dolibarr log under the `_payment` suffix (`dolibarr_payment.log` when file logging is enabled); every webhook call ends with a one-line result. Mollie retries non-200 answers for about a day. |
| Log says "manual handling required" | The payment does not match the invoice (already paid, other currency, amount above the remaining amount, draft invoice) | Record the payment by hand in Dolibarr and investigate why the invoice changed after the link was sent. |
| Log says "Mollie module not configured" (HTTP 503) | No key for the active mode at the moment Mollie called | Configure the key; Mollie will retry automatically. |
| Payment link opens but shows an error about the secure key | `PAYMENT_SECURITY_TOKEN` missing or changed after the link was generated | See step 4; resend the invoice e-mail so the customer gets a fresh link. |
| Local development: no webhook call | Mollie cannot reach `localhost`; the module omits the webhook URL there | Fire it by hand: `curl -d "id=tr_…" http://localhost:8080/custom/mollie/public/webhook.php`. |

Still stuck? Contact **support@kleijnict.nl** (installation help, best effort) or open
an issue on GitHub. Never include API keys in a message or report.
