# Mollie voor Dolibarr

Eigen Mollie-betaalmodule voor Dolibarr 23+. Klanten betalen verkoopfacturen (inclusief aanbetalingsfacturen) via de Mollie hosted checkout — iDEAL, kaarten en alle andere methoden die in het Mollie-dashboard aanstaan. Geslaagde betalingen worden via de Mollie-webhook automatisch geboekt op een instelbare tegenrekening en de factuur gaat op "betaald".

De module integreert in Dolibarr's ingebouwde online-betaalraamwerk (hooks in context `newpayment`) en heeft geen externe PHP-dependencies: de Mollie API wordt aangesproken met een eigen dunne curl-client.

## Installatie

1. Ga naar **Start › Instellingen › Modules/Applicaties › Installeer externe app/module**.
2. Upload `module_mollie-x.y.z.zip`.
3. Activeer de module **Mollie** in de modulelijst (familie "Interfaces").
4. Open de module-instellingen voor de configuratie.

## Configuratie

Instellingen → Modules → Mollie (tandwiel):

1. Test-API-sleutel invullen (`test_…`); de livesleutel pas bij de livegang
2. Live-modus: uit voor testbetalingen, aan voor echte betalingen
3. Bankrekening kiezen (de operationele tegenrekening, bijv. "Mollie")
4. Betaalwijze kiezen (bijv. "Online payment" — activeer code `VAD` in het woordenboek Betalingswijzen)
5. "Opslaan en verbinding testen" → toont de actieve betaalmethoden uit het Mollie-dashboard

Vereist verder: `PAYMENT_SECURITY_TOKEN` (+ `PAYMENT_SECURITY_TOKEN_UNIQUE=1`) op de
instantie, en een publiek bereikbare `custom/mollie/public/webhook.php` (een 405-antwoord
in de browser is het verwachte gedrag).

## Status

Versie 0.4.0: de volledige betaalflow werkt — betaallink op factuurkaart en in
e-mailsjablonen (`__ONLINE_PAYMENT_URL__`), Mollie hosted checkout, en automatische
boeking via de webhook (met deduplicatie). Zie `ChangeLog.md`.

## Licentie

GPL v3+ — zie `COPYING`.

## Auteur

[KleijnICT B.V.](https://kleijnict.nl)

---

*English: own Mollie payment module for Dolibarr 23+. Customers pay sales invoices through the Mollie hosted checkout; successful payments are recorded automatically via the Mollie webhook. Integrates with Dolibarr's built-in online payment framework, no external PHP dependencies.*
