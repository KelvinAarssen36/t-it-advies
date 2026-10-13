# De sitemap

Op `/sitemap.xml`, met een verwijzing in `public/robots.txt`.

## Waarom hij er nu pas is

`docs/openstaand.md` had hem staan met één regel erbij: _"Pas zinvol zodra
er pagina's zijn, en die komen uit de modules."_ Zolang de site één pagina
met ankers was, was een sitemap een bestand met één regel erin.

Dat moment is gepasseerd. Er zijn zes vaste adressen -- de voorpagina, de
privacyverklaring, contact, Over mij, de werkwijze en de projectenlijst --
plus één per online project. `PublicProjectController` noemt die sitemap
zelfs met zoveel woorden als tweede reden dat een project een eigen adres
kreeg in plaats van een venster.

## Een route en geen bestand

Welke pagina's bestaan hangt af van wat de eigenaar heeft aangezet, en dat
verandert terwijl de site draait. Een `sitemap.xml` die bij de bouw is
gegenereerd klopt tot de eerste keer dat hij een onderdeel uitzet -- en
wijst daarna naar een 404 zonder dat iemand het merkt.

Daarom een route: `App\Http\Controllers\SitemapController`, met
`App\Support\Page\Sitemap` die de lijst opbouwt.

**Zonder `TelBezoek`**, anders dan de pagina's zelf. Een crawler is geen
bezoeker; zou deze route meetellen, dan beweegt het bezoekcijfer mee met
hoe vaak Google langskomt.

## Vier van de zes pagina's bestaan voorwaardelijk

En dat is het hele probleem dat deze klasse oplost.

| Adres               | Bestaat wanneer                                                    |
| ------------------- | ------------------------------------------------------------------ |
| `/`                 | Altijd                                                             |
| `/privacy`          | Altijd                                                             |
| `/contact`          | Weergave staat op "eigen pagina" én het onderdeel staat aan        |
| `/over-mij`         | De aparte pagina staat klaar én het onderdeel staat aan            |
| `/werkwijze`        | Het onderdeel staat aan, er zijn stappen, en er valt iets te lezen |
| `/projecten`        | Het onderdeel staat aan en er staat minstens één project online    |
| `/projecten/{slug}` | Het project staat online                                           |

`Sitemap` stelt per pagina dezelfde vraag als de pagina zelf, en gebruikt
daarvoor de publieke methoden die de controllers al hadden
(`sectieStaatAan()`, `ietsTeLezen()`, `staatAan()`). Twee eigen antwoorden
op dezelfde vraag gaan vroeg of laat uiteen lopen.

### De test die dat bewaakt

`SitemapTest::test_every_address_in_the_sitemap_exists` haalt élk adres uit
de sitemap op en eist een 200. Verandert de voorwaarde van een pagina
zonder dat de sitemap meegaat, dan valt die test om in plaats van dat er
stil een dood adres in komt te staan.

Een sitemap die naar een 404 wijst is erger dan geen sitemap: dan ben je
niet alleen onvindbaar maar ook onbetrouwbaar.

## Wat er bewust niet in staat

- **Geen `hreflang`.** Deze site heeft geen taal-URL's: de taal komt uit
  het profiel, de sessie of de browser, en `/` is in beide talen hetzelfde
  adres. Een `hreflang` zou wijzen naar adressen die niet bestaan.
- **Geen `priority` en geen `changefreq`.** Google negeert allebei al
  jaren. Ze opnemen betekent een getal verzinnen dat niemand leest.
- **`lastmod` alleen bij een project.** Daar weten we wanneer hij is
  gewijzigd. Voor de voorpagina zou het een optelsom van een stuk of tien
  tabellen zijn, en een datum die we gokken is een datum waarop een
  crawler ten onrechte vertrouwt.

## Het adres in robots.txt

`public/robots.txt` noemt de sitemap met een volledig adres, want de
standaard eist dat. Het staat daar hardgecodeerd op `atitadvies.nl`:
`robots.txt` is een statisch bestand en kent `APP_URL` niet.

**En dat blijft zo, bewust.** De voor de hand liggende verbetering is er
een route van maken, zoals de sitemap zelf -- dan volgt hij `APP_URL` en is
er één handmatige stap minder. Dat is hier juist gevaarlijk: een route
hangt aan de applicatie, en Google behandelt een `robots.txt` die een
5xx geeft als **"crawl voorlopig helemaal niets"**. Eén storing van een
half uur zou dan de hele site uit de index kunnen halen. Een statisch
bestand staat er ook als de applicatie omvalt.

De prijs is één regel die bij een verhuizing meemoet, en die staat op de
[livegangslijst](../operations/livegang.md).

## Zie ook

- [Bezoekcijfers](bezoekcijfers.md)
- [Module Projecten](modules/projecten.md)
- [Openstaande punten](../openstaand.md)
