# Mollie voor Dolibarr

[English](README.md) · **Nederlands**

[![Licentie: GPL v3](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![Laatste release](https://img.shields.io/github/v/release/KleijnICT/dolibarr-mollie-plugin)](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest)
[![CI](https://github.com/KleijnICT/dolibarr-mollie-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/KleijnICT/dolibarr-mollie-plugin/actions/workflows/ci.yml)

Een betaalmodule voor [Dolibarr ERP/CRM](https://www.dolibarr.org/) 23+ waarmee je
klanten facturen betalen via [Mollie](https://www.mollie.com/). De klant klikt op de
betaallink in de factuur-e-mail of op de factuurkaart, betaalt in de Mollie hosted
checkout (iDEAL, kaarten, Bancontact, PayPal en elke andere methode die in je
Mollie-dashboard aanstaat) en de betaling wordt automatisch in Dolibarr geboekt. De
factuur gaat op "betaald".

Gebouwd en in productie gebruikt door [KleijnICT B.V.](https://kleijnict.nl),
gepubliceerd onder de GPL v3 zodat andere Dolibarr-gebruikers er ook iets aan hebben.

## Functies

- **Betalen via link.** Werkt met de betaallink op elke gevalideerde verkoopfactuur en
  met de placeholders `__ONLINE_PAYMENT_URL__` / `__ONLINE_PAYMENT_TEXT_AND_URL__` in de
  e-mailsjablonen van Dolibarr. Aanbetalingsfacturen worden ondersteund.
- **Hosted checkout.** Mollie toont de betaalmethoden die je in het dashboard hebt
  ingeschakeld; in Dolibarr hoef je per methode niets in te stellen.
- **Automatisch boeken.** De Mollie-webhook verifieert de betaling bij de Mollie API
  ("fetch-and-verify") en boekt haar op een bankrekening naar keuze, met de
  Mollie-transactie-id op het betaalrecord. Een betaling wordt nooit twee keer geboekt.
- **Test- en live-modus** met aparte API-sleutels en een verbindingstest met één klik.
- **Geen externe afhankelijkheden.** De Mollie v2 API wordt aangesproken met een kleine
  curl-client van zo'n 200 regels die je volledig kunt controleren. Geen SDK, geen
  Composer.
- **Minimale voetafdruk.** De module haakt in op het online-betaalraamwerk van Dolibarr
  zelf (hetzelfde raamwerk als de PayPal- en Stripe-integraties). Ze voegt geen
  tabellen, menu's of rechten toe en kan zonder gegevensverlies worden uitgeschakeld.

## Vereisten

- Dolibarr 23.0 of nieuwer (ontwikkeld en getest op 23.0.x)
- PHP 7.4 of nieuwer met de extensies `curl` en `json`
- De Dolibarr-modules **Facturen** en **Banken & Kas** geactiveerd
- Een [Mollie](https://www.mollie.com/)-account met API-sleutels
- HTTPS en een Dolibarr-instantie die vanaf internet bereikbaar is (voor de webhook)

## Installatie

1. Download `module_mollie-<versie>.zip` van de
   [laatste release](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest).
2. Ga in Dolibarr naar **Start › Instellingen › Modules/Applicaties**, open het tabblad
   **Installeer/update externe app/module** en upload de zip.
3. Activeer de module **Mollie** (familie "Interfaces") en open de instellingenpagina.
4. Vul je Mollie-**test**sleutel in, kies de bankrekening en de betaalwijze en klik op
   **Opslaan en verbinding testen**.

Het volledige stappenplan, inclusief de benodigde Dolibarr-instellingen, de
webhook-controle, een end-to-end-test en de livegang-checklist, staat in
**[docs/INSTALL.nl.md](docs/INSTALL.nl.md)** (Engels: [docs/INSTALL.md](docs/INSTALL.md)).

## Configuratie

| Instelling | Betekenis |
| --- | --- |
| Test-API-sleutel / Live-API-sleutel | Je Mollie-sleutels (`test_…` / `live_…`). Opgeslagen als Dolibarr-constanten, gemaskeerd getoond. |
| Live-modus | Uit: klanten zien de Mollie-testcheckout, er stroomt geen geld. Aan: echte betalingen met de livesleutel. |
| Bankrekening | De Dolibarr-bankrekening waarop Mollie-betalingen worden geboekt. Gebruik een aparte operationele rekening zoals "Mollie". |
| Betaalwijze | De betaalwijze op de betaalrecords, bijvoorbeeld "Online payment" (code `VAD`). |

Betaalmethoden, huisstijl en uitbetalingsinstellingen beheer je in je Mollie-dashboard.

## Hoe het werkt

```
klant ──klik──▶ Dolibarr-betaalpagina ──"Betalen via Mollie"──▶ Mollie hosted checkout
                                                                      │
       ┌── retourpagina: toont het resultaat (geverifieerd bij Mollie) ◀──┤
       │                                                              │
       └── webhook: haalt de betaling op bij Mollie, boekt haar  ◀────┘
           en zet de factuur op betaald (één keer, gededupliceerd)
```

- De module registreert zich via de `newpayment`-hooks van Dolibarr als online
  betaalmethode zodra er een API-sleutel is ingesteld. De beveiligde betaal-URL per
  factuur, de e-mailplaceholders en de berekening van het openstaande bedrag komen
  allemaal uit Dolibarr zelf.
- `public/webhook.php` is de enige plek waar een betaling wordt geboekt. Mollie stuurt
  alleen een betalings-id; de module haalt de betaling met haar eigen sleutel op,
  controleert status, valuta en bedrag tegen de factuur en boekt via het standaard
  betaalpad van Dolibarr (`Paiement->create` + `addPaymentToBank`). Het betaalrecord
  bevat de Mollie-id in `ext_payment_id`, tevens de deduplicatiesleutel.
- De retourpagina toont alleen het resultaat en boekt nooit zelf. Een klant die de
  browser tijdens het betalen sluit, kan dus geen boeking laten verdwijnen.

## Bekende beperkingen

- Alleen verkoopfacturen (inclusief aanbetalingsfacturen). Offertes, orders,
  lidmaatschappen en donaties worden niet ondersteund.
- Betalingen moeten in de bedrijfsvaluta zijn.
- Eén Dolibarr-entiteit: de module Multicompany wordt niet ondersteund.
- Uitbetalingen, transactiekosten en terugbetalingen van Mollie worden niet
  teruggekoppeld naar Dolibarr. Verwerk die in je boekhouding zoals je nu al doet.

## Disclaimer: gebruik op eigen risico

Deze module verwerkt betaalverkeer en schrijft betaalrecords in je boekhoudgegevens.
Ze wordt geleverd **"as is", zonder enige garantie**, uitdrukkelijk of stilzwijgend,
zoals vastgelegd in artikel 15 en 16 van de GNU GPL v3. KleijnICT B.V. en de
bijdragers aanvaarden geen aansprakelijkheid voor verloren of niet-geboekte
betalingen, onjuiste boekingen, gegevensverlies of andere schade die uit het gebruik
voortvloeit.

Je bent zelf verantwoordelijk voor het testen van de module met een Mollie-testsleutel
voordat je de live-modus inschakelt, voor back-ups, voor de beveiliging van je
Dolibarr-installatie en voor het naleven van de voorwaarden van Mollie.

Dit project is onafhankelijk en is niet gelieerd aan, goedgekeurd door of ondersteund
door Mollie B.V. of het Dolibarr-project. "Mollie" en "Dolibarr" zijn merken van hun
respectieve eigenaren.

## Hulp en ondersteuning

- **Hulp bij installatie en ondersteuning.** Hulp nodig bij het installeren of
  configureren van de module, of wil je dat KleijnICT het voor je inricht? Neem contact
  op via **[support@kleijnict.nl](mailto:support@kleijnict.nl)**.
- **Bugs en wensen.** Gebruik de
  [issue-tracker](https://github.com/KleijnICT/dolibarr-mollie-plugin/issues). Zet nooit
  API-sleutels in een melding.
- **Beveiligingsproblemen.** Meld die vertrouwelijk, zie [SECURITY.md](SECURITY.md).

Ondersteuning wordt op basis van best effort geboden. Er is geen gegarandeerde
reactietijd en geen serviceovereenkomst, tenzij je die met KleijnICT hebt afgesloten.

## Bijdragen

Bijdragen zijn welkom. Zie [CONTRIBUTING.md](CONTRIBUTING.md) (Engels) voor de
ontwikkelomgeving (Docker Compose met Dolibarr 23), de codeerstandaard en het
releaseproces. Dit project volgt het [Contributor Covenant](CODE_OF_CONDUCT.md).

## Indeling van de repository

| Pad | Inhoud |
| --- | --- |
| `mollie/` | De module zelf (dit is de inhoud van de release-zip) |
| `docs/` | Installatiehandleiding in het Engels en Nederlands |
| `dev/` | Lokale Docker-ontwikkelomgeving en controlescripts |
| `build.sh` | Bouwt `dist/module_mollie-<versie>.zip` |
| `.github/` | CI (syntax- en taalbestandcontroles) en de release-workflow |

Het wijzigingenoverzicht staat in [`mollie/ChangeLog.md`](mollie/ChangeLog.md) (Engels).

## Licentie

Copyright © 2026 KleijnICT B.V.

Dit programma is vrije software: je mag het verspreiden en/of wijzigen onder de
voorwaarden van de GNU General Public License zoals gepubliceerd door de Free Software
Foundation, versie 3 van de licentie of (naar jouw keuze) een latere versie. Zie
[LICENSE](LICENSE).
