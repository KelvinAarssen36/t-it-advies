# Documentatie @T IT Advies

Dit is de index. Alles wat je over dit project moet weten staat in deze map,
in Markdown, naast de code. Documentatie die niet meegroeit met de code is
erger dan geen documentatie, dus zie ook
[documentatieregels](development/documentatieregels.md) -- die regel is
bindend voor iedereen die hier werkt, mens of AI.

## Twee woorden die je overal terugziet

Deze site bestaat uit twee helften, en in de code, de commits en de
documentatie noemen we ze altijd zo:

| Woord       | Wat het is                                                                                                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------- |
| **Landing** | De publieke website, waar de klanten van onze klant op terechtkomen. De etalage: het visuele, representatieve deel. |
| **Portaal** | Het beheergedeelte, waar onze klant inlogt om zijn website aan te passen. Gereedschap, geen etalage.                |

Dat onderscheid is geen woordenspel, want de regels verschillen:

- De **landing** staat altijd in het donkere thema, mag zwaar aangezet
  worden qua beeld en animatie, en moet op een telefoon net zo goed zijn als
  op een desktop -- bezoekers zoeken het bedrijf waarschijnlijk op hun
  telefoon op.
- Het **portaal** volgt de themavoorkeur van de gebruiker, dus alles wat je
  daar bouwt moet in **licht én donker** kloppen. De lat voor mobiel ligt er
  lager: bruikbaar op een telefoon, maar gemaakt om achter een scherm te
  gebruiken.

Zie [huisstijl en kleuren](architecture/huisstijl-en-kleuren.md) voor wat dat
betekent voor wat je bouwt.

## Wat er nog open staat

[Openstaande punten](openstaand.md) -- wat er bewust nog niet af is, en wat
er nog besloten moet worden. Lees dat even voordat je iets "mist".

## Snel starten

Nieuw op dit project? Lees in deze volgorde:

1. [Lokale setup](development/setup.md) -- draaiend krijgen op WSL met Valet.
2. [Architectuuroverzicht](architecture/overzicht.md) -- hoe het in elkaar zit.
3. [Werkwijze en conventies](development/werkwijze.md) -- hoe we hier code schrijven.
4. [Documentatieregels](development/documentatieregels.md) -- wat je moet bijwerken en wanneer.

## Architectuur

- [Overzicht](architecture/overzicht.md) -- de stack, de lagen en waarom.
- [Frontend en animatie](architecture/frontend-en-animatie.md) -- Inertia, Vue, Tailwind, GSAP, Lenis, en wanneer wel of geen Three.js.
- [Huisstijl en kleuren](architecture/huisstijl-en-kleuren.md) -- het kleurenpalet, de gradients, en hoe die in de Tailwind-tokens landen.
- [Formulieren en schuifbalken](architecture/formulieren-en-schuifbalken.md) -- de standaard voor keuzevelden en schuifbalken in het portaal.
- [Pagina-indeling](architecture/pagina-indeling.md) -- hoe de klant de volgorde van de landing bepaalt, en hoe je er een onderdeel bij bouwt.
- [Dashboard](architecture/dashboard.md) -- het beginscherm van het portaal: de klok, de tijdzone en waar de instellingen ervan staan.
- [Bezoekcijfers](architecture/bezoekcijfers.md) -- hoeveel bezoek de site krijgt, hoe dat zonder cookies en zonder IP-opslag wordt geteld, en waarom er geen cookiebanner bij hoort.
- [Kopteksten](architecture/kopteksten.md) -- de gedeelde tabel met het opschrift, de titel en de zin boven elk onderdeel.
- [Meldingen](architecture/meldingen.md) -- de berichtjes rechtsonder, het bevestigingsvenster, en waarom je aan de kleur ziet wat er gebeurde.
- [Foutpagina's](architecture/foutpaginas.md) -- wat je ziet als er iets misgaat, en waarom portaal en landing verschillen.
- [Vertalingen](architecture/vertalingen.md) -- Nederlands en Engels, waar de taal vandaan komt en hoe je wisselt.
- [Automatisch vertalen](architecture/automatisch-vertalen.md) -- de knop waarmee de klant zijn Engelse tekst laat voorschrijven, en wat er gebeurt als dat misgaat.
- [Mail en queues](architecture/mail-en-queues.md) -- verzending, provider, webhooks en het mailoverzicht.
- [De handleiding voor de eigenaar](architecture/uitleg-voor-de-eigenaar.md) -- de uitlegpagina in het portaal, en de afspraak dat elk afgerond onderdeel daar een kaart krijgt.

## Modules

De onderdelen waarmee de klant zijn website vult. Wil je er een bijbouwen,
lees dan eerst [pagina-indeling](architecture/pagina-indeling.md) -- daar
staan de vijf stappen -- en daarna een bestaande module als voorbeeld.

- [Ervaring](architecture/modules/ervaring.md) -- de tijdlijn met functies en organisaties. De eerste module, en daarmee het voorbeeld voor de volgende.
- [Kop](architecture/modules/kop.md) -- de drie teksten bovenaan de landingspagina. De kleinste module: één rij, één venster, geen lijst.
- [Diensten](architecture/modules/diensten.md) -- wat de klant aanbiedt, met expertisepunten en een eigen volgorde. Combineert een lijst met beheerbare vaste tekst.
- [Certificaten](architecture/modules/certificaten.md) -- wat de klant heeft gehaald en bij wie, als raster van badges, met optioneel zijn opleiding eronder.
- [Statistieken](architecture/modules/statistieken.md) -- vaardigheden en kengetallen als balken, ringen en tellers, die zich als een golf vullen en daarna blijven glanzen. Ingedeeld in groepen, elk een eigen vak in het indelingsvenster.
- [Contact](architecture/modules/contact.md) -- het contactformulier: beheerbare onderwerpen, instelbare velden, een bevestigingsmail in de taal van de bezoeker, en de postbus onder Beheer. De enige module die inhoud van een bezoeker bewaart, met alles wat dat voor de privacyverklaring betekent.
- [LinkedIn](architecture/modules/linkedin.md) -- geen module maar een blok: één vaste link, wel te verslepen en uit te zetten.

## Beveiliging

- [Authenticatie en 2FA](security/authenticatie-en-2fa.md) -- Fortify, TOTP, recovery codes.
- [Gevoelige acties](security/gevoelige-acties.md) -- wanneer er opnieuw een authenticator-code nodig is.
- [Rollen en rechten](security/rollen-en-rechten.md) -- wie mag wat.
- [Spam- en botbescherming](security/spam-en-botbescherming.md) -- Turnstile, honeypot, rate limiting.
- [Activiteitenlogboek](security/activiteitenlogboek.md) -- wie heeft wat aan de website veranderd, en wat stond er eerst.
- [Beveiligingskoppen](security/headers.md) -- wat de browser van ons niet mag doen, en waarom er nog geen CSP staat.
- [Logging](security/logging.md) -- wat we vastleggen en wat we nooit vastleggen.
- [E-mailauthenticatie](security/e-mailauthenticatie.md) -- SPF, DKIM en DMARC.
- [Verzoeken van bezoekers](security/verzoeken-van-bezoekers.md) -- wat er gebeurt als iemand zijn gegevens opvraagt, en het scherm Juridisch waarmee de eigenaar dat afhandelt.
- [Het scherm Veiligheid](security/overzicht-voor-de-eigenaar.md) -- de uitlegpagina in de instellingen die laat zien hoe alles beschermd is, met echte cijfers en zonder iets te verzinnen.

## Ontwikkeling

- [Lokale setup](development/setup.md)
- [Werkwijze en conventies](development/werkwijze.md)
- [Testen](development/testen.md)
- [Documentatieregels](development/documentatieregels.md)

## Beheer

- [Deployment](operations/deployment.md) -- hosting, queue workers, scheduler.
- [Onderhoudstaken](operations/onderhoudstaken.md) -- wat er vanzelf draait: opruimen en alarmeren.
- [Monitoring](operations/monitoring.md) -- Pulse, logs, waar je kijkt als er iets mis is.

## Beslissingen

- [Beslislogboek](decisions/README.md) -- waarom bepaalde keuzes zijn gemaakt, en wat de alternatieven waren.
