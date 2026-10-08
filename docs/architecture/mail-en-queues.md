# Mail en queues

## Uitgangspunt

Alle mail gaat via de queue. Een bezoeker die een contactformulier verstuurt
hoort niet te wachten op een API-aanroep naar een mailprovider, en een
tijdelijke storing bij die provider mag geen bericht kosten. Mailables
implementeren daarom `ShouldQueue` en worden met `Mail::queue()` verstuurd.

De queue draait standaard op de `database`-connectie. Dat is genoeg voor dit
volume en scheelt een Redis-server. Wordt het druk, dan is overstappen op
Redis een wijziging in `.env`, niet in de code.

## De twee adressen

**Er zijn twee e-mailadressen in dit project en ze doen iets anders.** Dat
onderscheid is uitdrukkelijk afgesproken en het is het soort ding dat je
per ongeluk door elkaar haalt:

| Adres                   | Waarvoor                                                                                                                             |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `aarssen@atitadvies.nl` | **Uitsluitend inloggen** in het portaal. Zie `config/security.php`.                                                                  |
| `info@atitadvies.nl`    | **Al het andere**: wat er aan contactgegevens op de website staat, en waar het contactformulier naartoe gaat. Zie `config/site.php`. |

Het inlogadres hoort nergens op de publieke site te staan, en het publieke
adres hoort nergens als inlog te worden gebruikt.

`SITE_EMAIL` in `.env` is de enige bron voor dat tweede adres. Zowel het
afzendadres als het ontvangstadres van het contactformulier valt daarop
terug, dus ze kunnen niet uiteen gaan lopen. Vul je `MAIL_FROM_ADDRESS` of
`MAIL_CONTACT_ADDRESS` toch zelf in, dan wint die.

> **Wat hier eerst fout zat.** Het ontvangstadres viel terug op het
> from-adres, en dat stond op `no-reply@t-it-advies.nl` -- een no-reply op
> een domein dat niet eens klopt. Liet iemand `MAIL_CONTACT_ADDRESS` leeg,
> dan kwam een bericht van een bezoeker dus binnen op een postbus die
> niemand leest. Daar komt niemand achter, behalve de klant die zich
> afvraagt waarom er nooit iemand mailt.

**Het afzendadres is ook `info@` en geen verzonnen no-reply.** Op gedeelde
hosting -- en daar gaat dit naartoe, zie
[deployment](../operations/deployment.md) -- komt mail van een adres dat
niet als echte postbus bestaat eerder in de spam terecht; SPF en DKIM
horen bij een bestaand adres.

[`EmailAdressenTest`](../../tests/Feature/EmailAdressenTest.php) houdt
allebei de adressen vast, inclusief een controle die afgaat zodra het
inlogadres in een publiek onderdeel belandt.

## Mailprovider

De basis staat ingesteld op **Resend**. Dat is een keuze, geen verplichting:
Laravel ondersteunt Postmark, Mailgun en SES net zo goed. Resend is gekozen
omdat de inrichting eenvoudig is en de webhooks goed gedocumenteerd zijn.

### Lokaal: MailHog, en niet het logbestand

Lokaal staat `MAIL_MAILER=smtp` met `MAIL_HOST=127.0.0.1` en
`MAIL_PORT=1025`. Dat is MailHog (of Mailpit, dezelfde poorten): de mail
komt binnen op <http://localhost:8025> en je ziet hem zoals de ontvanger
hem krijgt, met opmaak en al. Er gaat nog steeds niets de deur uit.

`MAIL_MAILER=log` kan ook, en dan belandt de mail als platte tekst in
`storage/logs/laravel.log`. Dat werkt, maar het is niet de stand waar je
in wil zitten als je iets wil _bekijken_.

> **Hier is tijd in gaan zitten, dus het staat er met nadruk.** De stand
> was `log`, en dat ziet er van buiten precies hetzelfde uit als een
> kapotte mailinstelling: je drukt op "Stuur de link opnieuw", het scherm
> zegt "Er is een nieuwe link naar je e-mailadres gestuurd", en in MailHog
> blijft het leeg. Er was niets stuk -- de mail stond in het logbestand.
> Zie je een mail niet aankomen, controleer dan **eerst** deze instelling.

**En zet er een worker naast.** Het meeste gaat via de queue, dus zonder
`php artisan queue:work` blijft een bericht in de tabel `jobs` staan en
lijkt het weer alsof er niets gebeurt. Een worker van een ánder project
helpt niet: die leest de database van dat project. Zie
[Queue draaiend houden](#queue-draaiend-houden).

| Wat je stuurt                    | Via de queue? |
| -------------------------------- | ------------- |
| De verificatiemail               | nee, meteen   |
| De drie mails van het inlogadres | nee, meteen   |
| Het contactformulier             | ja            |
| Een beveiligingsmelding          | ja            |

**Waarom het inlogadres niet wacht.** `InlogadresBevestigenMail`,
`InlogadresAangevraagdMail` en `InlogadresGewijzigdMail` gaan direct de
deur uit. De eigenaar staat op dat moment naar zijn scherm te kijken en
wacht op die bevestiging; een mail die pas komt als de wachtrij eraan toe
is, is daar het verschil tussen "het werkt" en "het werkt niet". Zie
[het inlogadres wijzigen](../security/inlogadres-wijzigen.md).

#### De schuifbalken in MailHog zijn niet van ons

In het HTML-tabblad van MailHog staan **altijd** twee verticale
schuifbalken, ook bij een mail die ruim in beeld past. Dat is geen fout in
onze mail. MailHog zet in zijn eigen `css/style.css` twee keer
`overflow-y: scroll` -- en dat betekent "balk altijd tonen", waar `auto`
"alleen als het nodig is" betekent:

```css
.content {
    overflow-y: scroll;
} /* de rechterkolom  */
.preview .tab-content {
    overflow-y: scroll;
} /* het voorbeeldvak */
```

Die tweede kan zelfs nooit iets schuiven. MailHog's `js/controllers.js`
geeft het vak en het `iframe` erin **dezelfde** hoogte:

```js
$('.tab-content').height(
    $(window).innerHeight() - $('.tab-content').offset().top,
);
$('.tab-content .tab-pane').height(
    $(window).innerHeight() - $('.tab-content').offset().top,
);
```

De inhoud is dus precies zo hoog als de doos. Trek je aan die balk, dan
beweegt er niets -- dat is het snelste bewijs dat hij niet van ons is.

Is een mail langer dan het kader, dan komt er een derde bij, en díe is van
ons: de balk van het maildocument zelf. Die is wel in stijl; zie
[formulieren en schuifbalken](formulieren-en-schuifbalken.md#de-schuifbalk-van-een-mail).
In ons eigen voorbeeld onder Instellingen → Weergave staat nergens `scroll`
-- daar verschijnt een balk alleen als er echt iets te schuiven valt.

De `height: 100%` op `body` in de mailthema's is hier niet de oorzaak. Die
komt uit het sjabloon van Laravel en doet niets: een percentage rekent
tegen de hoogte van `html`, en die staat op `auto`, dus het valt terug op
`auto`. Daarom staat hij er nog.

### Overstappen naar een andere provider

1. Zet `MAIL_MAILER` op `postmark`, `mailgun` of `ses`.
2. Installeer de bijbehorende transport (`symfony/postmark-mailer`,
   `symfony/mailgun-mailer`, `aws/aws-sdk-php`).
3. Zet de credentials in `config/services.php` en `.env`.
4. **Pas de webhookcontroller aan.** `ResendWebhookController` begrijpt het
   formaat en de handtekening van Resend. Elke provider doet dat anders; zie
   hieronder.
5. Werk dit document bij.

## Twee stijlen, en de klant kiest

Onder **Instellingen → Weergave** staat één keuze met twee kaarten:

| Stijl       | Wat het is                             | Thema            |
| ----------- | -------------------------------------- | ---------------- |
| `licht`     | Witte mail met de merkkleur als accent | `atit`           |
| `huisstijl` | Midnight Navy, zoals de website zelf   | `atit-huisstijl` |

**`licht` is de standaard, en dat is geen smaak.** Een donkere mail is
mooier maar riskanter: oudere Outlooks op Windows negeren
achtergrondkleuren op sommige elementen, en een postvak dat zelf een
donkere modus forceert kan tekstkleuren omzetten. Een lichte mail komt
overal goed aan. Wie de huisstijl wil kiest hem bewust.

### Waar de keuze staat, en waarom daar

In `site_settings`, één rij. Niet in `contact_settings` -- de stijl geldt
voor élke mail, ook de beveiligingsmeldingen die niets met contact te maken
hebben, en een instelling die overal over gaat in een tabel die "contact"
heet is het soort ding waar je een jaar later op de verkeerde plek naar
zoekt. En niet bij de gebruiker, zoals de tijdzone van het dashboard: die
is een voorkeur van wie kijkt, dit is een eigenschap van de website. De
mail gaat naar bezoekers, en die hebben geen account.

### Ook de mail van Laravel zelf volgt hem

**Hier zat een gat.** Onze vier mailables pakken de keuze op via de trait
(zie hieronder), maar twee mails komen niet van ons: "bevestig je
e-mailadres" en "kies een nieuw wachtwoord" zijn notificaties van het
framework. Die bouwen een `MailMessage`, en `MailChannel` pakt het thema uit
`config('mail.markdown.theme')` -- een vaste waarde. Dus zette de eigenaar de
huisstijl aan, werd alles donkerblauw, en bleven precies de twee mails die je
krijgt als je buitengesloten bent wit.

Dat doet nu
[`ZetDeMailstijl`](../../app/Listeners/ZetDeMailstijl.php), een listener op
`NotificationSending`. Twee keuzes daarin:

- **Een listener en niet een service provider.** Daar zou je de instelling
  bij élk verzoek uit de database lezen, ook bij de duizenden waarin geen
  mail uitgaat. Erger: een provider draait ook tijdens `migrate` op een
  verse database, en dan bestaat de tabel nog niet.
- **`NotificationSending` en niet `MessageSending`.** De eerste vuurt in
  `shouldSendNotification()`, vóórdat het kanaal de mail opbouwt; bij de
  tweede is de inhoud al gerenderd en is het te laat.

Kan de instelling niet gelezen worden, dan blijft de vaste waarde uit de
config staan -- de lichte stijl, die overal goed aankomt. Een mail die uitgaat
mag niet stuklopen op de vraag hoe hij eruitziet. `FrameworkMailstijlTest`
houdt alle vier de gevallen vast, gemeten op de gerenderde mail.

### Elke mailable zet hem zelf

```php
// in de constructor, via de trait VolgtDeMailstijl
$this->theme = SiteSetting::mailstijl()->thema();
```

`Mailable::$theme` wint van `config('mail.markdown.theme')`; zie
`Mailable::markdownRenderer()`. Die config blijft staan als terugval voor
een mail die de trait niet gebruikt.

**In de constructor en niet bij de aanroeper**, om dezelfde reden als bij de
taal: zo kan niemand het vergeten. Een mail die per ongeluk het
standaardthema van Laravel gebruikt valt in geen enkele test op -- hij komt
gewoon aan, alleen in een andere stijl dan de rest. `MailstijlTest` loopt
alle vier de mailables langs, dus een vijfde zonder de trait valt om.

### Vijf dingen die een donkere mail anders maken

Ze staan ook bovenaan `atit-huisstijl.css`, want daar leest iemand ze als
hij er iets aan verandert:

1. **Elk vlak krijgt een eigen achtergrondkleur**, ook als het dezelfde is
   als die van zijn ouder. Sommige Outlooks negeren een achtergrond op een
   buitenste tabel maar niet op een binnenste; laat je er één weg, dan krijg
   je een wit gat midden in de mail.
2. **Geen `transparent` en geen `rgba()`.** Daar valt een mailprogramma op
   terug naar wit, en dan staat lichte tekst op wit. `MailstijlTest` houdt
   dat vast.
3. **Geen `box-shadow`.** Op donker doet die niets zichtbaar en Outlook
   tekent hem toch niet.
4. **Tekst is licht maar niet wit.** Zuiver wit op donkerblauw flikkert op
   een scherm met weinig contrast; `#e8eef5` leest rustiger en is nog ruim
   boven de norm.
5. **Het document moet zelf zeggen dat het donker is.** Een donkere
   webpagina heeft daar niets voor nodig; een donkere mail wel. Dit was een
   echte fout: Laravel zet in élke mail `color-scheme: light` als metategel,
   vast erin getypt, en een donkerblauwe mail die "licht" zegt krijgt een
   **witte schuifbalk met pijltjes** ernaast en wordt door Apple Mail en
   Outlook.com alsnog zelf omgekleurd. Zie hieronder bij de gepubliceerde
   sjablonen.

### Het logo in de kop

Hier stond de naam als tekst, met de reden dat mailprogramma's beelden
blokkeren. Dat is waar, en daar is `alt` voor: blokkeert een programma het
beeld, dan leest de ontvanger "@T IT Advies" in de kleur van het thema --
net als eerst. Laadt hij wel, dan staat er het echte merk.

Drie dingen die daarbij horen:

- **PNG en geen WebP.** Outlook kan geen WebP, en dan valt de kop weg bij
  precies de ontvangers die hem het hardst nodig hebben. Er staat een
  `logo-merk.webp` in de map; gebruik die daar niet.
- **`logo-mail.png` en niet `logo-breed.png`.** Dat origineel is 2172 pixels
  breed en 873 kB. Het maatje is 380 breed -- het dubbele van waarop hij
  wordt getoond -- en 42 kB. Een test houdt die grens vast.
- **Een absolute URL** via `asset()`: een mail heeft geen eigen domein om
  een pad vanaf te rekenen.

### Zelf kijken in een echte postbus

```bash
php artisan mail:test
```

Stuurt drie mails in **beide** stijlen naar je contactadres: de bevestiging,
de melding aan jou, en een beveiligingsmelding -- die laatste omdat dat de
enige is met een **knop** erin, en een knop is precies wat je wil
vergelijken. Lokaal komen ze in MailHog op <http://localhost:8025>.

Twee dingen die die opdracht goed doet en die je zelf makkelijk fout doet:

- **`sendNow()` en niet `send()`.** Die laatste zet een mailable die
  `ShouldQueue` is alsnog in de wachtrij -- zie `Mailer::sendMailable()` --
  en dan staat je testmail in `jobs` te wachten op een worker terwijl je in
  MailHog naar een leeg scherm kijkt.
- **De stijl wordt achteraf teruggezet** zoals hij stond. Een kijkje nemen
  hoort geen instelling te veranderen.

Het voorbeeld in het portaal blijft het snelste: dat rendert de échte
mailable in een `iframe`. Maar een mailprogramma doet nog zijn eigen dingen
-- stijlen wegknippen, kleuren herschrijven -- en dat zie je pas in een
echte postbus.

## Alle mail in de huisstijl

Er was geen eigen maillayout: de drie mails draaiden op het standaardthema
van Laravel, en zagen er dus uit als een mail van een willekeurige
applicatie. Dat is nu één thema voor alles wat deze site verstuurt --
de bevestiging aan een bezoeker, de beveiligingsmelding en de crashmelding.

Het staat in `resources/views/vendor/mail/html/themes/atit.css`, aangewezen
door `config('mail.markdown.theme')`.

**Bewust een kopie van Laravel's thema en geen eigen sjabloon.** De opbouw
van een mail -- tabellen in tabellen, inline stijlen -- is uitgevochten tegen
twintig jaar mailprogramma's, en die strijd doen we niet over. Alleen kleuren,
randen en ruimte zijn aangepast; blijf van de structuur af.

Twee keuzes die uitleg verdienen:

- **Geen gradient op de kop.** Outlook op Windows tekent die niet en laat dan
  een grijs vlak zien. Eén egale merkkleur doet het overal.
- **De naam als tekst en niet als afbeelding.** Veel mailprogramma's
  blokkeren beelden tot je ze toelaat, en dan staat er bovenaan een leeg vak
  waar de afzender hoort te staan.

### Drie sjablonen zijn gepubliceerd, de rest niet

In `resources/views/vendor/mail/html/` staan maar drie bestanden, en elk om
één reden:

| Bestand             | Waarom                                                     |
| ------------------- | ---------------------------------------------------------- |
| `header.blade.php`  | Het logo als beeld in plaats van de naam als tekst.        |
| `message.blade.php` | De voettekst, die anders in élke mail Engels bleef.        |
| `layout.blade.php`  | De twee metategels die in élke mail zeiden "ik ben licht". |

Die laatste is de fout uit punt 5 hierboven. Laravel's layout heeft
`content="light"` vast erin getypt, twee keer:

```html
<meta name="color-scheme" content="light" />
<meta name="supported-color-schemes" content="light" />
```

Bij de huisstijl klopt dat niet, en dat heeft twee zichtbare gevolgen. Een
browser tekent zijn **schuifbalk** naar het schema dat het document opgeeft,
dus kreeg de donkere mail een witte balk met pijltjes -- precies wat je in
het voorbeeld onder Instellingen → Weergave zag. En Apple Mail en
Outlook.com kijken naar `supported-color-schemes` om te beslissen of ze een
mail zélf naar donker omzetten; zegt onze donkere mail dat hij alleen licht
kan, dan gaan ze er alsnog met hun eigen omkleuring over.

Nu volgen die twee tegels de gekozen stijl. De stijl komt uit het thema dat
op dat moment wordt gerenderd: `Markdown` is een singleton en
`Mailable::markdownRenderer()` zet het thema erop vóór het renderen, dus
`app(Markdown::class)->getTheme()` is altijd het thema van déze mail --
`MailStijl::vanThema()` maakt er de stijl van. Dat werkt ook bij de twee
voorbeeldroutes, die het thema zelf meegeven.

De schuifbalk zélf staat niet hier maar in de twee thema's, want dat is
kleur; zie
[formulieren en schuifbalken](formulieren-en-schuifbalken.md#de-schuifbalk-van-een-mail).
Daar staat ook waarom er geen `::-webkit-scrollbar` in een mailthema kan.

De voettekst verdient ook uitleg. Laravel's voettekst zegt
`{{ __('All rights reserved.') }}` -- een **Engelse** brontekst. Onze
vertaling loopt de andere kant op: Nederlands is de sleuteltaal en
`lang/en.json` maakt er Engels van. Die zin had dus geen Nederlandse kant en
stond onderaan elke mail, ook de Nederlandse, als "All rights reserved."

Hem in `lang/nl.json` zetten zou ook werken, maar dat bestand gaat via de
gedeelde Inertia-props naar de browser; dan draagt elke pagina van de site
een zin mee die alleen in een mail voorkomt. Zie
[vertalingen](vertalingen.md).

Voor de rest zijn `message.blade.php` en `layout.blade.php` de sjablonen van
Laravel, ongewijzigd. Lopen ze ooit uit de pas met een nieuwe versie,
vergelijk dan met
`vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/`.

### Het voorbeeld onder Instellingen → Weergave

Twee `iframe`s naast elkaar, en het verschil tussen die twee is de reden dat
ze er allebei zijn.

| Voorbeeld          | Wat het is                                             |
| ------------------ | ------------------------------------------------------ |
| **De bevestiging** | De échte mailable, met de tekst van de eigenaar erin   |
| **De onderdelen**  | Een demo van een knop, een uitgelicht vak en een tabel |

Het eerste rendert de echte mailable met verzonnen gegevens
(`$mail->render()`, niets wordt verstuurd). Daardoor kan het niet uit de pas
lopen met de werkelijkheid: het ís de mail. Zou het een eigen stukje HTML
zijn, dan klopt het tot de dag dat iemand het thema aanpast en er niet aan
denkt.

**Het tweede is erbij gekomen omdat het eerste zonder hem misleidend was.** De
bevestiging heeft geen knop -- daar valt niets te openen -- dus zag de eigenaar
nooit hoe een knop in zijn huisstijl staat, terwijl zijn beveiligingsmelding er
wel een heeft. Een knop in de bevestiging plakken zou erger zijn: dan toont het
voorbeeld een mail die niet bestaat. Vandaar een apart vak dat er in de kop bij
zegt dat het geen echte mail is.

Dat tweede voorbeeld gaat via `Markdown` en niet via een mailable of
`Mail::render()`. Een mailable zou een klasse zijn die nooit iets verstuurt, en
`Mail::render()` laat de Blade door **zonder het thema in te voegen** -- dan zie
je de opbouw zonder de opmaak, en dat is precies het verkeerde voorbeeld.
`Markdown` is de renderer die elke markdown-mailable ook gebruikt, en het
exemplaar uit de container draagt `config('mail.markdown.theme')` al bij zich.

Zie [`MailVoorbeeldController`](../../app/Http/Controllers/Settings/MailVoorbeeldController.php)
en [`voorbeeld-onderdelen.blade.php`](../../resources/views/mail/voorbeeld-onderdelen.blade.php).

## De taal van een mail

**Niet van `app()->getLocale()` in de mailable.** Mail gaat via de wachtrij,
en die draait in een losse opdrachtregel waar de taal van het verzoek niet
meer bestaat.

Er zijn twee soorten mail in dit project, en ze krijgen hun taal op twee
verschillende plekken. Dat is geen inconsistentie maar het antwoord op de
vraag "van wie is deze taal".

### Mail aan een bezoeker: de aanroeper weet het

```php
Mail::to($aanvraag->email)->locale($aanvraag->locale)->queue(new ContactBevestigingMail(...));
```

Welke taal dat is weet alleen het verzoek waarin de bezoeker het formulier
invulde, dus hij wordt daar vastgelegd (de kolom `locale` op de aanvraag) en
bij het versturen meegegeven. `App::setLocale()` is geen alternatief; dat mag
in dit project alleen in `SetLocale` staan. Zie
[vertalingen](vertalingen.md).

### Mail aan de eigenaar: de mail weet het zelf

```php
// in de constructor van ContactMessageMail, CrashAlertMail en SecurityAlertMail
$this->locale(config('site.locale'));
```

**Hier zat een fout die er precies uitzag als de oplossing.** De melding uit
het contactformulier gebruikte `->locale(config('app.locale'))` om Nederlands
te forceren. Dat leest als "de ingestelde standaardtaal", maar
`Application::setLocale()` doet letterlijk
`config->set('app.locale', $locale)` -- dus na de middleware `SetLocale` ís
`app.locale` de taal van de bézoeker. Gemeten: de eigenaar kreeg
`Contact form: Een vraag` in zijn eigen postvak zodra er een Engelstalige
bezoeker schreef.

`config('site.locale')` hoort bij het bedrijf en wordt door geen verzoek
omgezet, net als `site.timezone`. Zie `config/site.php`.

**Waarom in de constructor en niet bij de aanroeper:** zo kan geen enkele
aanroeper het vergeten, en hoeft niemand te weten dat `app.locale` onderweg
verandert. Dat is geen theoretisch risico -- `CrashAlertMail` had dezelfde
fout en niemand had hem daar gezocht, want die mail wordt verstuurd vanuit de
foutafhandeling van een gewoon verzoek (zie `bootstrap/app.php`). Een
aanroeper die er toch van af wil wijken kan dat nog: `PendingMail::fill()`
overschrijft deze waarde alleen als er expliciet een taal is meegegeven.

Drie mails gaan naar de eigenaar, en bij twee ervan draait de afzender in een
verzoek:

| Mail                 | Vertrekt vanuit                     |
| -------------------- | ----------------------------------- |
| `ContactMessageMail` | het contactformulier -- een verzoek |
| `CrashAlertMail`     | de foutafhandeling -- een verzoek   |
| `SecurityAlertMail`  | de planner -- geen verzoek          |

> **De test die dit moest afvangen kon niet falen.** Hij vergeleek
> `$mail->locale` met `config('app.locale')`, en na een Engelstalig verzoek
> waren beide kanten `'en'`. `InterneTaalTest` legt nu vast dat een bezoeker
> de interne taal niet kan omzetten, en `ContactSubmissionTest` vergelijkt
> met de letterlijke `'nl'` plus het gerenderde onderwerp.

> **Staat er een datum in een mail, maak die dan als tekst klaar in het
> verzoek.** Carbon heeft zijn eigen taal, los van die van Laravel, en in de
> wachtrij staat die op het standaard.

## De onderwerpregel van een bezoeker gaat niet in het logboek

Het mailoverzicht legt metadata vast en geen mailinhoud. **Op dat "geen
inhoud" zat een gat**: bij een bericht uit het contactformulier is het
onderwerp "Contactformulier: wat de bezoeker typte", en dat stond honderd
tachtig dagen in onze database.

Een mailable kan daarom een neutraal onderwerp voor het logboek opgeven:

```php
public static function logboekOnderwerp(): string
{
    return __('Bericht via het contactformulier');
}
```

`RecordOutgoingMail` gebruikt die als hij bestaat, en anders het echte
onderwerp. De eigenaar houdt het volledige onderwerp in zijn eigen postvak;
dit gaat alleen over wat wij bewaren.

> **Zet dit op elke mailable waar tekst van een bezoeker in het onderwerp
> staat.** Het is gegevensminimalisatie, en het is wat de
> [privacyverklaring](bezoekcijfers.md#de-privacyverklaring) belooft: "niet
> je bericht en niet je onderwerp". `MailLoggingTest` houdt het vast -- die
> test stond er eerst omgekeerd in en controleerde juist dat het onderwerp
> van de bezoeker wél werd gelogd.

## Wat er wordt vastgelegd

Elke verstuurde mail komt in de tabel `mail_logs`, via de listener
[`RecordOutgoingMail`](../../app/Listeners/RecordOutgoingMail.php). We bewaren:

- het Message-ID (de sleutel waarmee webhooks terugkoppelen),
- de mailable-klasse, de mailer, het onderwerp,
- de ontvangers (to, cc, bcc),
- de status en de volledige tijdlijn van provider-events,
- wanneer het is verstuurd en wanneer het laatste event binnenkwam.

**Niet** de inhoud van de mail. Wil je die kunnen terugzien, maak daar dan een
bewuste keuze van met een bewaartermijn, en leg vast waarom.

De tabel wordt elke nacht opgeruimd door `mail:prune-logs`. De termijn staat
in `MAIL_LOG_RETENTION_DAYS` (standaard 180 dagen) en is korter dan die van
het beveiligingslogboek: een bounce van een jaar geleden zegt niets meer. Zie
[onderhoudstaken](../operations/onderhoudstaken.md).

Gaat er structureel iets mis met uitgaande mail, dan hoef je dat niet zelf op
te merken: `security:report` mailt bij een piek in bounces, klachten en
mislukte verzendingen.

### Een mail die nooit is verstuurd

**`MessageSent` komt alleen als de mail de deur uit is.** Ligt de mailserver
eruit, dan gooit de job, wordt hij opnieuw geprobeerd, en belandt hij in
`failed_jobs` -- en daarmee stond er niets in het mailoverzicht en kwam er
geen melding. De eigenaar zag een aanvraag in zijn portaal staan waar nooit
een mail bij is gekomen, en niets vertelde hem dat.

Laravel heeft hier een haakje voor: `SendQueuedMailable::failed()` roept
`failed()` op de mailable aan als die methode bestaat. Dat is de trait
[`MeldtMislukteVerzending`](../../app/Concerns/MeldtMislukteVerzending.php).
Die schrijft een rij met `MailStatus::Failed`, zonder Message-ID (de
provider heeft de mail nooit gezien), met het neutrale logboekonderwerp en
met de foutmelding ingekort tot 500 tekens.

Omdat `AnomalyScanner` een `Failed`-rij als mailprobleem telt, krijgt de
eigenaar er binnen het uur ook een melding over. Dat is het eigenlijke doel:
niet het logboek, maar dat iemand het weet.

> **Niet op `SecurityAlertMail`, en dat is geen vergetelheid.** Die mail ís
> de melding over mailproblemen. Zou hij bij mislukken een rij schrijven,
> dan is dat een nieuw mailprobleem, waarover de scanner weer een melding
> wil sturen, die weer kan mislukken. De afkoeltijd houdt dat klein, maar
> een kringetje blijft een kringetje. Loopt die mail mis, dan staat hij in
> `failed_jobs`, en dat is voor een alarmeringsmail de juiste plek.

**Het mailoverzicht sorteert daarom op `created_at` en niet op `sent_at`.**
Een mislukte rij heeft geen `sent_at`, en NULL zakt bij aflopend sorteren
naar de onderkant -- dan stond de mislukte verzending van vandaag onder de
geslaagde van vorig jaar. Voor een geslaagde mail verandert het niets: die
twee kolommen krijgen dezelfde waarde.

> De listener wordt niet handmatig geregistreerd. Laravel ontdekt listeners in
> `app/Listeners` automatisch aan de hand van een methode die met `handle`
> begint. Meld je hem daarnaast ook aan in een service provider, dan draait hij
> twee keer en krijg je dubbele regels.

## Statussen

`App\Enums\MailStatus` kent een rangorde. Problemen (bounce, klacht, mislukt)
staan bovenaan en kunnen niet worden overschreven door een later binnenkomend
`delivered`- of `opened`-event. Providers leveren events niet altijd op
volgorde af; zonder die rangorde zou een trage `delivered` een bounce kunnen
wegpoetsen en denk je dat alles goed ging.

De tijdlijn in `events` bewaart wél alles, in de volgorde van binnenkomst.

## Webhooks

Endpoint: `POST /webhooks/resend`, zie [`routes/webhooks.php`](../../routes/webhooks.php).

Drie dingen maken dit endpoint veilig:

1. **Handtekeningcontrole.** Resend gebruikt Svix-handtekeningen. De
   controle zit in
   [`SvixSignature`](../../app/Support/Security/SvixSignature.php) en
   vergelijkt met `hash_equals`, zodat de duur van de controle niets verraadt.
2. **Een tijdvenster.** Verzoeken ouder dan vijf minuten worden geweigerd.
   Zonder die grens kan iemand een oud, geldig ondertekend verzoek eindeloos
   opnieuw afspelen.
3. **Rate limiting.** Ook een geldig endpoint mag niet onbeperkt worden
   bestookt.

Zonder ingesteld `MAIL_WEBHOOK_SECRET` weigert het endpoint **alles**. Een
webhook zonder handtekeningcontrole is een open deur, dus bij twijfel gaat hij
dicht. Elke geweigerde poging komt in het beveiligingslogboek.

De route is uitgezonderd van CSRF (in `bootstrap/app.php`), omdat er geen
browser en dus geen sessie aan te pas komt.

### Instellen bij Resend

1. Maak een webhook aan in het Resend-dashboard, met als URL
   `https://<domein>/webhooks/resend`.
2. Abonneer op minimaal `email.delivered`, `email.bounced` en
   `email.complained`.
3. Zet het ondertekeningsgeheim (begint met `whsec_`) in `MAIL_WEBHOOK_SECRET`.

## Het mailoverzicht

Te vinden onder `/admin/mail`, achter het recht `manage portal`. Je ziet per
mail de status, de ontvangers en de volledige tijdlijn van wat de provider
heeft teruggemeld. Regels met een probleem zijn rood.

## Queue draaiend houden

Lokaal:

```bash
php artisan queue:listen
```

In productie draai je een `queue:work` als beheerde service. Zie
[deployment](../operations/deployment.md).

Zonder draaiende worker blijft mail in de tabel `jobs` staan en gebeurt er
niets. Dat is de **tweede** plek om te kijken als iemand meldt dat er geen
mail aankomt; de eerste is `MAIL_MAILER`, zie
[hierboven](#lokaal-mailhog-en-niet-het-logbestand).

Draai hem **vanuit deze map**. Op een machine met meerdere Laravel-projecten
staat er makkelijk al een worker, maar die leest de database van zijn eigen
project en raakt deze `jobs`-tabel niet aan. `pgrep -a -f queue:work` laat
zien wat er draait, maar niet waar; `readlink /proc/<pid>/cwd` wel.

Een snelle controle of er iets klaarstaat:

```bash
php artisan queue:monitor default
```
