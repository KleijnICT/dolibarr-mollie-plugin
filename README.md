# Dolibarr Mollie-module

Eigen Mollie-betaalmodule voor Dolibarr 23+, door [KleijnICT B.V.](https://kleijnict.nl).

Klanten betalen verkoopfacturen (inclusief aanbetalingsfacturen) via de Mollie hosted
checkout — iDEAL, kaarten en alle andere methoden die in het Mollie-dashboard aanstaan.
Geslaagde betalingen worden via de Mollie-webhook geverifieerd (fetch-and-verify) en
automatisch geboekt op een instelbare tegenrekening; de factuur gaat op "betaald".
Geen externe PHP-dependencies: de Mollie v2 API wordt aangesproken met een eigen dunne
curl-client, integraal te auditen.

## Repo-indeling

| Pad | Inhoud |
| --- | --- |
| `mollie/` | De module zelf (dit wordt de zip) |
| `dev/` | Lokale Docker-ontwikkelomgeving (MariaDB + Dolibarr 23) |
| `docs/` | Beslisdocument (01) en deploy-draaiboek (02) |
| `build.sh` | Bouwt `dist/module_mollie-<versie>.zip` |

## Installatie (productie)

1. Bouw de zip: `./build.sh` → `dist/module_mollie-<versie>.zip`
2. In Dolibarr: **Start › Instellingen › Modules › Installeer/update externe app/module** → upload de zip
3. Activeer de module **Mollie** (familie "Interfaces") en open de instellingen

Het volledige stappenplan inclusief voorwaarden, controles, end-to-end-test en
livegang staat in [`docs/02-deploy-draaiboek.md`](docs/02-deploy-draaiboek.md).
Configuratiedetails staan in [`mollie/README.md`](mollie/README.md).

## Ontwikkelen

```sh
cd dev
docker compose up -d        # Dolibarr 23 op http://localhost:8080 (admin/admin)
```

De module in `mollie/` is live gemount in de custom-modulemap van de container:
elke codewijziging is direct zichtbaar. De webhook is lokaal handmatig af te vuren
(Mollie kan localhost niet bereiken):

```sh
curl -d "id=tr_XXXXXXXXXX" http://localhost:8080/custom/mollie/public/webhook.php
```

## Architectuur in het kort

- Integratie via Dolibarr's core online-betaalraamwerk: de zes hooks in context
  `newpayment` (plus `invoicecard` voor de link op de factuurkaart). Geen eigen
  betaalpagina's — maximaal hergebruik van core (e-maillink, bedragslogica, boekingspad).
- `public/webhook.php` is de **primaire boeker**: haalt de betaling zelf op bij Mollie
  en boekt bij status `paid` via `Paiement->create($user, 1)` + `addPaymentToBank`,
  met deduplicatie op `ext_payment_id`. De retourpagina toont alleen de status.
- Test/live is puur sleutel-gebaseerd; betaalmethoden beheer je in het Mollie-dashboard.

De volledige onderbouwing staat in [`docs/01-onderzoek-en-beslissing.md`](docs/01-onderzoek-en-beslissing.md).

## Licentie

GPL v3+ — zie [`mollie/COPYING`](mollie/COPYING).
