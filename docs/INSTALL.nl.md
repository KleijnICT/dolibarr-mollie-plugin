# Installatiehandleiding

[English](INSTALL.md) · **Nederlands**

Deze handleiding brengt je in een veilige volgorde van een lege Dolibarr-instantie naar
live Mollie-betalingen: installeer en configureer de module eerst in **testmodus**,
bewijs de hele flow met een testbetaling en schakel pas daarna over naar live.

> **Gebruik op eigen risico.** De module schrijft betaalrecords in je boekhoudgegevens.
> Maak een back-up voordat je begint, test alles met een Mollie-testsleutel en lees de
> [disclaimer](../README.nl.md#disclaimer-gebruik-op-eigen-risico).

## 1. Voorwaarden

Controleer dit eenmalig voordat je installeert:

- [ ] Dolibarr 23.0 of nieuwer, PHP 7.4 of nieuwer met de extensies `curl` en `json`.
- [ ] De modules **Facturen** en **Banken & Kas** zijn geactiveerd.
- [ ] Er bestaat een bankrekening voor Mollie: Banken & Kas › Nieuwe rekening, type
      lopende rekening, in je bedrijfsvaluta (bijvoorbeeld met de naam "Mollie"). Dit
      is de operationele tegenrekening waarop Mollie-betalingen worden geboekt; Mollie
      betaalt het saldo later uit naar je zakelijke bankrekening.
- [ ] Er is een betaalwijze voor onlinebetalingen actief: Start › Instellingen ›
      Woordenboeken › Betalingswijzen, activeer **Online payment** (code `VAD`) of maak
      een eigen betaalwijze.
- [ ] Je Dolibarr-instantie draait over **HTTPS** en is vanaf internet bereikbaar.
      Mollie moet de webhook kunnen aanroepen.
- [ ] Een Mollie-account met een **test-API-sleutel** bij de hand (Mollie-dashboard ›
      Developers › API-keys). De livesleutel voeg je pas bij de livegang toe.

## 2. Module installeren

1. Download `module_mollie-<versie>.zip` van de
   [releases-pagina](https://github.com/KleijnICT/dolibarr-mollie-plugin/releases/latest).
2. Ga in Dolibarr naar **Start › Instellingen › Modules/Applicaties**, open het tabblad
   **Installeer/update externe app/module** en upload de zip.
3. Activeer de module **Mollie** in de modulelijst (familie "Interfaces").

**Handmatig alternatief** (shell-toegang): pak het archief uit zodat de module in
`<dolibarr>/htdocs/custom/mollie/` terechtkomt, zorg dat de webserver de bestanden kan
lezen en activeer daarna de module. Dolibarr moet externe modules toestaan
(`$dolibarr_main_url_root_alt` en `$dolibarr_main_document_root_alt` in
`conf/conf.php`; de zip-installer heeft die ook nodig).

## 3. Module configureren

Open **Start › Instellingen › Modules/Applicaties › Mollie** (tandwiel):

1. **Test-API-sleutel**: plak je `test_…`-sleutel.
2. **Live-modus**: laat op **Nee** staan tot de livegang.
3. **Bankrekening**: de rekening "Mollie" uit stap 1.
4. **Betaalwijze**: "Online payment".
5. Klik op **Opslaan en verbinding testen**. Een groene melding toont de betaalmethoden
   die in je Mollie-dashboard actief zijn. Een rode melding betekent dat de sleutel
   fout is of dat Mollie niet bereikbaar is vanaf de server.

Sleutels worden als Dolibarr-constanten opgeslagen en daarna alleen gemaskeerd getoond.
Laat een sleutelveld leeg om de opgeslagen sleutel te behouden.

## 4. Dolibarr-instellingen voor onlinebetalingen

Het online-betaalraamwerk van Dolibarr heeft een beveiligingstoken nodig zodat
betaallinks niet te raden zijn:

- [ ] Ga naar **Start › Instellingen › Overige instellingen** en controleer dat de
      constante `PAYMENT_SECURITY_TOKEN` bestaat met een lange willekeurige waarde, en
      dat `PAYMENT_SECURITY_TOKEN_UNIQUE` op `1` staat (één beveiligde sleutel per
      factuur). Voeg ze toe als ze ontbreken.
- [ ] Open een gevalideerde factuur: de factuurkaart toont nu een **online
      betaallink** (de module registreert zich zodra er een sleutel is ingesteld).
- [ ] Verstuur je facturen per e-mail, zet dan `__ONLINE_PAYMENT_URL__` of
      `__ONLINE_PAYMENT_TEXT_AND_URL__` in je factuur-e-mailsjabloon
      (Start › Instellingen › E-mails › E-mailsjablonen).

## 5. Controleren of de webhook bereikbaar is

Open deze URL in een browser, met je eigen domein:

```
https://<jouw-domein>/custom/mollie/public/webhook.php
```

Het verwachte antwoord is **"Method not allowed, POST id=tr_... expected"** met
HTTP-status 405. Dat is goed: de pagina is bereikbaar en weigert alles behalve de POST
van Mollie. Een 404 betekent dat de module niet staat waar de URL hem verwacht; een
inlogpagina betekent dat de publieke pagina's van Dolibarr geblokkeerd zijn.

Staat je Dolibarr in een submap, neem die dan op in de URL. De module gebruikt de
publieke URL uit de Dolibarr-configuratie (`$dolibarr_main_url_root`) wanneer ze
Mollie vertelt waar de webhook staat; die waarde moet dus juist en publiek zijn.

## 6. End-to-end-test in testmodus

Testbetalingen zijn onschadelijk: met een testsleutel toont Mollie de testcheckout en
stroomt er geen geld.

1. Maak een testklant en een kleine testfactuur aan en valideer die.
2. Open de **online betaallink** op de factuurkaart, klik op **Betalen via Mollie** en
   kies **Paid** in de Mollie-testcheckout.
3. De retourpagina in Dolibarr bevestigt de betaling. Wacht een minuut: Mollie roept nu
   zelf de webhook aan.
4. Controleer het resultaat: de factuur staat op **Betaald**, er is een betaalrecord
   met betaalwijze "Online payment" en de `tr_…`-id in de publieke notitie, en het
   bedrag is bijgeschreven op de bankrekening Mollie.
5. Test ook de e-mailflow: verstuur de factuur per e-mail en controleer dat de
   betaallink in het bericht werkt.
6. Ruim op: verwijder de betaling en de bankboeking en zet de factuur terug op
   onbetaald, of bewaar de testfactuur als duidelijk gemarkeerde referentie (een
   creditnota kan ook).

## 7. Livegang

Pas nadat de test hierboven is geslaagd:

1. Vul de **Live-API-sleutel** (`live_…`) in op de instellingenpagina.
2. Zet **Live-modus** op **Ja** en klik opnieuw op **Opslaan en verbinding testen**. De
   melding moet "live-modus" noemen en je live betaalmethoden tonen.
3. Doe één kleine echte betaling aan jezelf (bijvoorbeeld een factuur van 1 euro) en
   controleer de volledige boeking, inclusief de automatische webhook-aanroep.
4. Neem de betaallink op in je standaard factuur-e-mailsjabloon als dat nog niet is
   gebeurd.

Houd er rekening mee: het geld komt op je Mollie-saldo en wordt door Mollie volgens je
Mollie-instellingen uitbetaald naar je zakelijke bankrekening. Uitbetalingen en
transactiekosten worden door deze module niet in Dolibarr afgeletterd.

## 8. Bijwerken

1. Download de nieuwe zip en upload hem via **Installeer/update externe app/module**;
   de modulebestanden worden overschreven. Je instellingen blijven bewaard (die staan
   in de database).
2. Lees het [wijzigingenoverzicht](../mollie/ChangeLog.md) (Engels) voor punten die
   aandacht vragen.
3. Open de instellingenpagina en klik één keer op **Opslaan en verbinding testen**.

## 9. Module uitschakelen of verwijderen

De module uitschakelen volstaat om geen Mollie-betalingen meer aan te bieden: de
betaallinks en knoppen verdwijnen direct. Er gaat geen data verloren; betaalrecords en
bankboekingen blijven staan. Om de module volledig te verwijderen, schakel je haar uit
en verwijder je `htdocs/custom/mollie/`.

## 10. Problemen oplossen

| Symptoom | Waarschijnlijke oorzaak | Wat te doen |
| --- | --- | --- |
| Geen knop "Betalen via Mollie" op de betaalpagina, geen betaallink op de factuurkaart | Geen API-sleutel opgeslagen voor de actieve modus (test of live) | Vul de sleutel voor die modus in en sla op. In live-modus is de livesleutel vereist. |
| "Verbinding met Mollie mislukt" op de instellingenpagina | Verkeerde sleutel, of de server kan `api.mollie.com` niet bereiken | Controleer het voorvoegsel (`test_`/`live_`), uitgaand HTTPS vanaf de server en de PHP-extensie curl. |
| Klant heeft betaald, maar de factuur blijft onbetaald | Mollie kon de webhook niet bereiken, of de webhook heeft de betaling geweigerd | Controleer stap 5. Lees daarna het log: de module logt naar het Dolibarr-log met het achtervoegsel `_payment` (`dolibarr_payment.log` als bestandslogging aanstaat); elke webhook-aanroep eindigt met een resultaatregel. Mollie probeert niet-200-antwoorden ongeveer een dag lang opnieuw. |
| Log zegt "manual handling required" | De betaling past niet bij de factuur (al betaald, andere valuta, bedrag hoger dan het openstaande bedrag, conceptfactuur) | Boek de betaling handmatig in Dolibarr en zoek uit waarom de factuur is gewijzigd nadat de link is verstuurd. |
| Log zegt "Mollie module not configured" (HTTP 503) | Geen sleutel voor de actieve modus op het moment dat Mollie aanriep | Stel de sleutel in; Mollie probeert het automatisch opnieuw. |
| De betaallink opent, maar toont een fout over de beveiligde sleutel | `PAYMENT_SECURITY_TOKEN` ontbreekt of is gewijzigd nadat de link werd gemaakt | Zie stap 4; verstuur de factuur-e-mail opnieuw zodat de klant een verse link krijgt. |
| Lokale ontwikkeling: geen webhook-aanroep | Mollie kan `localhost` niet bereiken; de module laat de webhook-URL daar weg | Vuur hem handmatig af: `curl -d "id=tr_…" http://localhost:8080/custom/mollie/public/webhook.php`. |

Kom je er niet uit? Neem contact op via **support@kleijnict.nl** (installatiehulp, best
effort) of open een issue op GitHub. Zet nooit API-sleutels in een bericht of melding.
