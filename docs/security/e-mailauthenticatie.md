# E-mailauthenticatie: SPF, DKIM en DMARC

Zonder deze drie records komt je mail in de spammap of helemaal niet aan, en
kan iedereen mail versturen die eruitziet alsof hij van jouw domein komt. Dit
is DNS-werk, geen code, maar het hoort bij de applicatie: zonder deze records
werkt de mailfunctie niet betrouwbaar.

## SPF

SPF zegt welke servers namens jouw domein mogen versturen.

Eén TXT-record op het hoofddomein:

```
v=spf1 include:_spf.resend.com -all
```

Twee dingen die vaak misgaan:

- **Er mag maar één SPF-record per domein zijn.** Gebruik je meerdere
  diensten, voeg dan extra `include:`-onderdelen toe aan hetzelfde record.
  Twee losse SPF-records betekent dat SPF ongeldig is.
- **`-all` versus `~all`.** `-all` (hard fail) zegt: alles wat niet in deze
  lijst staat is vals. Dat is wat je wilt. `~all` (soft fail) is bedoeld om
  mee te testen. Begin desnoods met `~all`, maar ga naar `-all` zodra je zeker
  weet dat alle verzendende diensten in het record staan.

## DKIM

DKIM ondertekent elke uitgaande mail met een sleutel, zodat de ontvanger kan
controleren dat het bericht onderweg niet is aangepast.

De provider geeft je een of meer CNAME- of TXT-records om toe te voegen.
Resend geeft ze bij het toevoegen van het domein in het dashboard.

Controleer na het toevoegen in het dashboard van de provider dat het domein
als geverifieerd wordt weergegeven. DNS-wijzigingen hebben tijd nodig.

## DMARC

DMARC vertelt ontvangers wat ze moeten doen met mail die SPF en DKIM niet
doorstaat, en zorgt dat je rapportages krijgt.

Een TXT-record op `_dmarc.<domein>`:

```
v=DMARC1; p=none; rua=mailto:dmarc@t-it-advies.nl; fo=1
```

Bouw het beleid op in drie stappen, en sla ze niet over:

1. **`p=none`** -- niets afdwingen, alleen rapporteren. Draai dit minimaal een
   paar weken en lees de rapportages. Je ontdekt hier verzendende diensten
   waar je niet aan had gedacht: een boekhoudpakket, een nieuwsbrieftool, een
   formulier op een oude site.
2. **`p=quarantine`** -- verdachte mail gaat naar de spammap.
3. **`p=reject`** -- verdachte mail wordt geweigerd.

Ga niet meteen naar `p=reject`. Je blokkeert dan gegarandeerd legitieme mail
van een dienst die je vergeten was.

## Afzenderadressen

- Versturen gebeurt altijd vanaf een adres op het eigen domein
  (`MAIL_FROM_ADDRESS`, standaard `no-reply@t-it-advies.nl`). Alleen daarvan
  kloppen SPF en DKIM.
- Het adres van de bezoeker gaat in **Reply-To**, nooit in From. Zet je het
  adres van de bezoeker in From, dan verstuur je mail namens een domein waar
  je niets over te zeggen hebt, en die mail wordt terecht geweigerd. Zie
  [`ContactMessageMail`](../../app/Mail/ContactMessageMail.php).
- Ontvangen gebeurt op `MAIL_CONTACT_ADDRESS`: een postbus die iemand echt
  leest, los van het no-reply-adres.

## Controleren

Na het instellen:

- Stuur een testmail naar een Gmail-adres en bekijk "Origineel weergeven".
  Daar zie je of SPF, DKIM en DMARC alle drie op `PASS` staan.
- Gebruik een hulpmiddel als mail-tester.com voor een volledige controle.
- Houd de eerste weken de DMARC-rapportages in de gaten.

## Als er toch mail mist

Loop dit af, in deze volgorde:

1. Draait de queue worker? Zonder worker blijft mail in de tabel `jobs`
   staan. Zie [mail en queues](../architecture/mail-en-queues.md).
2. Staat er iets in `/admin/mail`? Zo nee, dan is er niets verstuurd.
3. Staat de status daar op `bounced` of `complained`? Dan is het bij de
   ontvanger misgegaan, en zegt de tijdlijn waarom.
4. Klopt het domein in het dashboard van de provider nog?
