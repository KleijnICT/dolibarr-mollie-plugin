# ChangeLog

## 0.4.0 (2026-08-31)

De volledige betaalflow: betaallink → Mollie hosted checkout → automatische boeking.
(Bevat de eerder geplande 0.2.0 en 0.3.0.)

- `MollieClient`: dunne curl-client voor de Mollie v2 API (Bearer-auth, TLS-verificatie,
  time-outs, `Idempotency-Key`-support, gestructureerde foutafhandeling). Geen SDK.
- Configuratiepagina: test-/livesleutel (gemaskeerd, prefix-validatie), modus-schakelaar,
  bankrekening, betaalwijze, en "Opslaan en verbinding testen" (toont actieve methoden).
- De zes `newpayment`-hooks: registratie als betaalmethode zodra een sleutel is
  geconfigureerd, betaalknop, checkout-aanmaak met redirect, statusverificatie op de
  retourpagina (fetch-and-verify via sessie-transactie-id), bankrekening-hook.
  Extra hook-context `invoicecard` zodat de betaallink op de factuurkaart verschijnt.
- `public/webhook.php` als primaire boeker: fetch-and-verify, dedupe op
  `ext_payment_id`, canoniek boekingspad (`Paiement->create` + `addPaymentToBank`),
  Mollie's `paidAt` als betaaldatum, defensieve afhandeling van alle randgevallen.
  De retourpagina boekt bewust niét (geen `FinalPaymentAmt` in de sessie).
- Geen idempotency-key bij checkout-aanmaak (heraanmaken voor dezelfde factuur is
  legitiem; Mollie weigert hergebruikte sleutels bij elk parameterverschil).
- `webhookUrl` wordt weggelaten op localhost (lokaal handmatig afvuren; publiek
  roept Mollie hem zelf aan).

## 0.1.0 (2026-08-30)

Moduleskelet.

- Moduledescriptor volgens Dolibarr 23-conventies (hooks-context `newpayment` gedeclareerd).
- Lege hook-klasse `ActionsMollie` (registreert nog geen betaalmethode).
- Configuratiepagina en About-pagina (nog zonder instellingen).
- Taalbestanden nl_NL en en_US.

Gepland:

- 0.5.0 — Productieverificatie met testsleutel (e-maillink en factuurkaart-link draaien
  al op core-functionaliteit en werken zodra de module zich registreert).
- 1.0.0 — Hardening, logging, security-review, livegang.
