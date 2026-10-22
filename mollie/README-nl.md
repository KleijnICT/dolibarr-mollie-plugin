# Mollie voor Dolibarr

Ontvang online betalingen op verkoopfacturen via [Mollie](https://www.mollie.com/).
Klanten betalen via de Mollie hosted checkout (iDEAL, kaarten, Bancontact, PayPal en
elke andere methode die in je Mollie-dashboard aanstaat). Geslaagde betalingen worden
via de Mollie-webhook geverifieerd en automatisch geboekt op een instelbare
bankrekening; de factuur gaat op "betaald".

De module sluit aan op het ingebouwde online-betaalraamwerk van Dolibarr, dus de
betaallink op de factuurkaart en de placeholder `__ONLINE_PAYMENT_URL__` in
e-mailsjablonen werken direct. Er zijn geen externe PHP-afhankelijkheden: de Mollie
API wordt aangesproken met een kleine, controleerbare curl-client.

## Configuratie

Start › Instellingen › Modules/Applicaties › Mollie (tandwiel):

1. **Test-API-sleutel** (`test_…`); de livesleutel pas bij de livegang.
2. **Live-modus**: uit voor testbetalingen, aan voor echte betalingen.
3. **Bankrekening**: de operationele tegenrekening waarop betalingen worden geboekt
   (bijvoorbeeld "Mollie").
4. **Betaalwijze** voor de betaalrecords (bijvoorbeeld "Online payment", code `VAD`,
   geactiveerd in het woordenboek Betalingswijzen).
5. **Opslaan en verbinding testen**: toont de betaalmethoden die in je
   Mollie-dashboard actief zijn.

Verder vereist: `PAYMENT_SECURITY_TOKEN` (en `PAYMENT_SECURITY_TOKEN_UNIQUE` = 1) op
de instantie, en `custom/mollie/public/webhook.php` moet vanaf internet bereikbaar
zijn (een HTTP 405-antwoord in de browser is het verwachte gedrag).

## Documentatie en ondersteuning

- Installatiehandleiding, livegang-checklist en probleemoplossing:
  https://github.com/KleijnICT/dolibarr-mollie-plugin/blob/main/docs/INSTALL.nl.md
- Bugs en wensen: https://github.com/KleijnICT/dolibarr-mollie-plugin/issues
- Hulp bij installatie of configuratie: support@kleijnict.nl

## Disclaimer

Deze module is vrije software onder de GNU GPL v3 of later, zonder enige garantie.
Ze verwerkt betalingen en schrijft betaalrecords in je Dolibarr-gegevens. Gebruik is
op eigen risico: test eerst met een Mollie-testsleutel voordat je de live-modus
inschakelt en zorg voor back-ups. Dit project is niet gelieerd aan Mollie B.V. of het
Dolibarr-project.

## Licentie

GPL v3 of later, zie `COPYING`. © KleijnICT B.V., https://kleijnict.nl
