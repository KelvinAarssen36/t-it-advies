<?php

namespace Database\Seeders;

use App\Enums\MailStatus;
use App\Enums\ProjectType;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\SecurityAlertMail;
use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\FaqItem;
use App\Models\MailLog;
use App\Models\Project;
use App\Models\WorkStep;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Verzonnen data om de schermen mee te bekijken.
 *
 * **Dit is het enige bestand in `database/seeders` met testdata erin.** Alle
 * andere seeders horen in élke omgeving te draaien -- dat zijn de rollen, het
 * account van de eigenaar, de secties en de echte loopbaan van de klant. Deze
 * hoort daar níet bij en staat daarom bewust **niet** in `DatabaseSeeder`.
 *
 * Draai hem expliciet:
 *
 *     php artisan db:seed --class=VoorbeeldDataSeeder
 *
 * En gooi hem net zo makkelijk weg:
 *
 *     php artisan db:seed --class=VoorbeeldDataSeeder -- --opruimen
 *
 * Dat laatste kan niet via `db:seed`, dus daarvoor is er een vlag op het
 * model: elke rij die hier wordt gemaakt krijgt een herkenbaar e-mailadres
 * op `@voorbeeld.test`. Zie `opruimen()`.
 *
 * **Hij weigert buiten local en testing.** Niet uit voorzichtigheid maar
 * omdat het anders echt misgaat: deze rijen zien er in het portaal
 * precies zo uit als echte aanvragen van echte mensen, en die twee door
 * elkaar laten lopen is onherstelbaar. `DatabaseSeeder` schrijft die regel
 * voor; zie het commentaar daar.
 *
 * Wat je ervan krijgt:
 *
 * - **vijf onderwerpen**, waarvan één offline en één zonder Engelse naam,
 *   zodat je ziet wat er op de site komt en wat niet;
 * - **negen aanvragen** in alle standen die bestaan: ongelezen, gelezen,
 *   beantwoord, met en zonder bedrijf en telefoon, Nederlands en Engels,
 *   een zelf getypt onderwerp, een onderwerp dat later is verwijderd, een
 *   heel lang bericht en een heel kort;
 * - **zeven regels in het mailoverzicht**, inclusief een bounce en een
 *   mislukte verzending, want dat is wat je wil kunnen zien.
 */
class VoorbeeldDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Het domein waarmee verzonnen rijen te herkennen zijn.
     *
     * `.test` is door de IANA gereserveerd en bestaat dus nooit echt. Een
     * mail hierheen kan niet per ongeluk bij iemand aankomen.
     */
    private const DOMEIN = '@voorbeeld.test';

    /**
     * Zet de verzonnen data neer.
     *
     * **Deze seeder zegt niets.** Dat is geen karigheid: een seeder heeft
     * alleen een terminal als hij via `db:seed` is aangeroepen, en dat is
     * niet te zien aan de typen -- `Seeder::$command` is volgens Laravel
     * nooit leeg, dus zowel `?->` als `isset()` wordt door de statische
     * analyse afgekeurd, terwijl hij in werkelijkheid wél leeg is bij een
     * rechtstreekse aanroep.
     *
     * De uitweg is niet een slimmere controle maar een andere verdeling:
     * `voorbeeld:zaaien` doet het praten en deze klasse het werk. Dat is
     * bovendien symmetrisch met `voorbeeld:opruimen`.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'VoorbeeldDataSeeder draait alleen in local en testing. '
                    .'Deze rijen zijn niet van echte mensen te onderscheiden in het portaal.'
            );
        }

        $this->aanvragen($this->onderwerpen());
        $this->mailoverzicht();
        $this->vragen();
        $this->overMij();
        $this->projecten();
        $this->werkwijze();
    }

    /**
     * Het onderdeel "Over mij", met de aparte pagina aan.
     *
     * **De pagina staat aan en er staat een verhaal in**, want anders zie
     * je de helft van deze module niet: zonder verhaal bestaat die pagina
     * niet en komt er ook geen knop op de voorpagina.
     *
     * De foto blijft met opzet het medaillon. Een verzonnen foto neerzetten
     * zou een bestand in de uploadmap betekenen dat het opruimen moet
     * kennen, en het medaillon is bovendien de stand waarin de eigenaar
     * begint.
     */
    /**
     * Drie projecten, met één uitgelicht.
     *
     * **Drie en niet één**, want het blok op de voorpagina laat juist de
     * verhouding zien: één groot en de rest als kaarten. Met één project
     * zie je dat niet.
     *
     * Het middelste heeft een eigen soort, zodat de uitweg "Anders" ook
     * echt een keer op het scherm staat.
     */
    /**
     * De vier stappen die hiervoor in WerkwijzeSection.vue stonden.
     *
     * **Woord voor woord dezelfde teksten**, en dat is het hele punt: de
     * verhuizing van het Vue-bestand naar de database hoort niets aan de
     * website te veranderen. Wat erbij komt is de duur en het resultaat --
     * velden die er eerst niet waren -- en bij de eerste twee stappen een
     * uitgebreid verhaal, zodat de pagina /werkwijze ook echt bestaat in
     * de voorbeelddata.
     */
    private function werkwijze(): void
    {
        $rijen = [
            [
                'title_nl' => 'Kennismaken',
                'title_en' => 'Getting to know each other',
                'summary_nl' => 'Wat speelt er, wat is er al, en waar loopt het vast.',
                'summary_en' => 'What is going on, what is already there, and where it gets stuck.',
                'duration_nl' => 'Een gesprek',
                'duration_en' => 'One conversation',
                'result_nl' => 'Een eerlijk beeld van wat er nodig is.',
                'result_en' => 'An honest picture of what is needed.',
                'body_nl' => "We beginnen met kijken en luisteren, niet met oplossingen. Wat draait er nu, wie werkt ermee, en waar gaat het elke week mis?\n\nSoms blijkt daar al dat je geen nieuw systeem nodig hebt maar een andere afspraak. Dat zeg ik dan ook.",
                'body_en' => "We start by looking and listening, not with solutions. What is running today, who works with it, and where does it go wrong every week?\n\nSometimes that already shows you do not need a new system but a different agreement. In that case I will say so.",
            ],
            [
                'title_nl' => 'Voorstel',
                'title_en' => 'Proposal',
                'summary_nl' => 'Een plan met een prijs, en wat er buiten valt.',
                'summary_en' => 'A plan with a price, and what falls outside it.',
                'duration_nl' => '1-2 weken',
                'duration_en' => '1-2 weeks',
                'result_nl' => 'Een plan met een prijs die niet achteraf verandert.',
                'result_en' => 'A plan with a price that does not change afterwards.',
                'body_nl' => "Je krijgt op papier wat er gaat gebeuren, wat het kost en hoe lang het duurt. En net zo belangrijk: wat er níet bij zit.\n\nDat laatste is waar de meeste verrassingen vandaan komen, dus daar ben ik liever te uitgebreid dan te kort.",
                'body_en' => "You get on paper what will happen, what it costs and how long it takes. And just as important: what is not included.\n\nThat last part is where most surprises come from, so I would rather be too thorough there than too brief.",
            ],
            [
                'title_nl' => 'Bouwen',
                'title_en' => 'Building',
                'summary_nl' => 'In korte stappen, zodat je onderweg kunt bijsturen.',
                'summary_en' => 'In short steps, so you can adjust along the way.',
                'duration_nl' => 'Per twee weken',
                'duration_en' => 'Every two weeks',
                'result_nl' => 'Elke twee weken iets dat je kunt bekijken.',
                'result_en' => 'Something you can look at every two weeks.',
                'body_nl' => null,
                'body_en' => null,
            ],
            [
                'title_nl' => 'Overdragen',
                'title_en' => 'Handing over',
                'summary_nl' => 'Werkende software plus de documentatie om er zelf mee verder te kunnen.',
                'summary_en' => 'Working software plus the documentation to carry on by yourself.',
                'duration_nl' => 'Een dagdeel',
                'duration_en' => 'Half a day',
                'result_nl' => 'Alles in jouw handen, niet in de mijne.',
                'result_en' => 'Everything in your hands, not in mine.',
                'body_nl' => null,
                'body_en' => null,
            ],
        ];

        foreach ($rijen as $plek => $rij) {
            WorkStep::query()->updateOrCreate(
                ['title_nl' => $rij['title_nl']],
                [...$rij, 'position' => $plek + 1, 'published' => true],
            );
        }
    }

    private function projecten(): void
    {
        $rijen = [
            [
                'type' => ProjectType::Migratie,
                'title_nl' => 'Migratie naar Exchange Online',
                'title_en' => 'Migration to Exchange Online',
                'organisation' => 'Zorgkoepel Midden',
                'role_nl' => 'Technisch projectleider',
                'role_en' => 'Technical project lead',
                'started_on' => '2023-03-01',
                'ended_on' => '2023-11-01',
                'summary_nl' => 'Driehonderd postbussen over, zonder dat iemand een mail miste.',
                'summary_en' => 'Three hundred mailboxes moved without anyone missing an email.',
                'featured' => true,
            ],
            [
                'type' => ProjectType::Anders,
                'type_label_nl' => 'Haalbaarheidsonderzoek',
                'type_label_en' => 'Feasibility study',
                'title_nl' => 'Onderzoek naar een eigen datacentrum',
                'title_en' => null,
                'organisation' => 'Van Dalen Techniek',
                'role_nl' => 'Adviseur',
                'role_en' => null,
                'started_on' => '2024-01-01',
                'ended_on' => '2024-04-01',
                'summary_nl' => 'Uitgezocht of eigen hardware goedkoper zou zijn. Dat was het niet.',
                'summary_en' => null,
                'featured' => false,
            ],
            [
                'type' => ProjectType::Interim,
                'title_nl' => 'Interim ICT-coördinator',
                'title_en' => 'Interim IT coordinator',
                'organisation' => 'Gemeente Noorderveld',
                'role_nl' => 'ICT-coördinator',
                'role_en' => 'IT coordinator',
                'started_on' => '2025-02-01',
                'ended_on' => null,
                'summary_nl' => 'De afdeling draaiende houden terwijl er een vaste coördinator wordt gezocht.',
                'summary_en' => 'Keeping the department running while a permanent coordinator is found.',
                'featured' => false,
            ],
        ];

        foreach ($rijen as $plek => $rij) {
            Project::query()->updateOrCreate(
                ['title_nl' => $rij['title_nl']],
                [
                    ...$rij,
                    'slug' => Project::vrijeSlug($rij['title_nl']),
                    'position' => $plek + 1,
                    'published' => true,
                ],
            );
        }
    }

    private function overMij(): void
    {
        AboutSetting::query()->updateOrCreate(
            // Eén rij; welke dat is maakt niet uit, er is er maar één.
            [],
            [
                'summary_nl' => 'Ik werk sinds 2008 in de IT en sinds 2016 voor mezelf. '
                    .'Geen bureau met een accountmanager ertussen: je hebt met mij te maken, '
                    .'en dat blijft zo tot het werk is overgedragen.',
                'summary_en' => 'I have worked in IT since 2008, and for myself since 2016. '
                    .'No agency with an account manager in between: you deal with me, '
                    .'and that stays that way until the work is handed over.',

                'page_enabled' => true,

                'page_title_nl' => 'Even voorstellen',
                'page_title_en' => 'Let me introduce myself',
                'page_intro_nl' => 'Hoe ik hier terecht ben gekomen, en waarom ik het zo doe.',
                'page_intro_en' => 'How I ended up here, and why I work this way.',

                'story_nl' => 'Ik begon in 2008 aan een helpdesk, en dat is de beste leerschool die er is: '
                    ."je ziet elke dag wat er in de praktijk omvalt in plaats van wat er op papier zou moeten werken.\n\n"
                    .'Daarna heb ik twaalf jaar aan de binnenkant van organisaties gewerkt -- beheer, migraties, '
                    .'en een paar keer de wat minder leuke klus van iets opruimen dat door drie leveranciers was '
                    .'achtergelaten. Dat laatste bepaalt nog steeds hoe ik werk: ik lever de documentatie mee '
                    ."waarmee een ander het kan overnemen.\n\n"
                    .'Sinds 2016 doe ik dat voor mezelf. Dat betekent dat ik minder klanten heb dan een bureau, '
                    .'en dat is geen nadeel maar de opzet.',
                'story_en' => 'I started on a helpdesk in 2008, and that is the best training there is: '
                    ."you see every day what actually falls over, instead of what should work on paper.\n\n"
                    .'After that I spent twelve years on the inside of organisations -- management, migrations, '
                    .'and a few times the less enjoyable job of clearing up something three suppliers had left '
                    .'behind. That last part still shapes how I work: I hand over the documentation someone else '
                    ."needs to take it on.\n\n"
                    .'Since 2016 I have done that for myself. That means I have fewer clients than an agency, '
                    .'and that is not a drawback but the point.',
            ],
        );

        // vraag-nl, vraag-en -- één zonder Engels, om de terugval te zien.
        $punten = [
            ['Geen afhankelijkheid van één leverancier', 'No dependence on a single supplier'],
            ['Documentatie waarmee een ander het overneemt', 'Documentation so someone else can take over'],
            ['Werkt met wat er al staat', 'Works with what you already have'],
            ['Bereikbaar zonder tussenpersoon', null],
        ];

        foreach ($punten as $plek => [$nl, $en]) {
            AboutPoint::query()->updateOrCreate(
                ['text_nl' => $nl],
                ['text_en' => $en, 'position' => $plek + 1],
            );
        }
    }

    /**
     * De veelgestelde vragen.
     *
     * **Acht vragen, en dat getal is gekozen.** Het blok op de site bladert
     * per zes, dus met acht zie je het bladeren echt gebeuren in plaats van
     * dat je het op je woord moet geloven. Eén staat offline, dus er komen
     * er zeven op de site: zes op de eerste bladzijde en één op de tweede.
     *
     * Verder zit er bewust een bijzonder geval in elk van de vier
     * eigenschappen die je anders moet uitproberen: een vraag zonder
     * Engels, een antwoord met een witregel, een antwoord dat bijna tegen
     * de grens zit, en een vraag die offline staat.
     */
    private function vragen(): void
    {
        // vraag-nl, vraag-en, antwoord-nl, antwoord-en, online
        $rijen = [
            [
                'Wat kost een migratie?',
                'What does a migration cost?',
                "Dat hangt af van je omvang en van wat er nu staat. Een kantoor met twaalf mensen is een ander verhaal dan een productieomgeving met ploegendiensten.\n\nIn het eerste gesprek geef ik een indicatie, en die onderbouw ik. Geen bedrag zonder uitleg waar het vandaan komt.",
                "That depends on your size and on what you have now. An office with twelve people is a different story from a production environment running shifts.\n\nI give an indication in the first call, and I explain where it comes from. No figure without the reasoning behind it.",
                true,
            ],
            [
                'Hoe snel kun je beginnen?',
                'How soon can you start?',
                'Meestal binnen twee weken. Bij een storing dezelfde dag -- dan schuift het andere werk op.',
                'Usually within two weeks. In the event of an outage the same day; other work moves back then.',
                true,
            ],
            [
                'Werk je ook voor kleine bedrijven?',
                'Do you work for small businesses too?',
                'Ja. Onder de tien mensen is het vaak juist eenvoudiger: er is minder dat al vastligt, dus er is meer te winnen met een paar goede keuzes.',
                'Yes. Under ten people it is often simpler: less is already fixed in place, so a few good choices gain you more.',
                true,
            ],
            [
                'Zit ik daarna aan je vast?',
                'Am I then tied to you?',
                'Nee, en dat is het punt. Ik lever de documentatie mee waarmee een ander het kan overnemen. Dat hoort bij het werk en niet bij een eindafrekening.',
                'No, and that is the point. I hand over the documentation someone else needs to take it on. That is part of the work, not part of a final invoice.',
                true,
            ],
            [
                'Doe je ook beheer, of alleen advies?',
                'Do you also do management, or only advice?',
                'Allebei. Advies zonder beheer blijft een rapport; beheer zonder advies wordt op een dag een probleem dat niemand heeft zien aankomen.',
                'Both. Advice without management stays a report; management without advice becomes a problem nobody saw coming.',
                true,
            ],
            /*
             * Zonder Engelse vraag én zonder Engels antwoord: op de Engelse
             * site valt deze helemaal terug op het Nederlands. Zo zie je
             * dat gedrag zonder het te hoeven uitproberen.
             */
            [
                'Wat gebeurt er als jij ziek bent?',
                null,
                'Dan is er een achterwacht die erbij kan. Wie dat is en hoe hij erin komt staat in de documentatie die je van mij krijgt -- dat is precies waarom die bestaat.',
                null,
                true,
            ],
            /*
             * Deze valt op de tweede bladzijde. Handig om te zien dat het
             * bladeren werkt én dat Google hem toch krijgt.
             */
            [
                'Kun je met mijn huidige leverancier samenwerken?',
                'Can you work with my current supplier?',
                'Ja, en meestal is dat ook de bedoeling. Een overstap is zelden de goedkoopste oplossing; er valt vaak meer te winnen door de afspraken scherper te maken.',
                'Yes, and usually that is the intention. Switching is rarely the cheapest answer; there is often more to gain by tightening the agreements.',
                true,
            ],
            // En één die offline staat: die hoort niet op de site te komen.
            [
                'Geef je ook trainingen?',
                'Do you give training as well?',
                'Op aanvraag, en alleen over de omgeving die ik zelf heb gebouwd. Een cursus over software die ik niet beheer kan iemand anders beter geven.',
                'On request, and only about the environment I built myself. A course on software I do not manage is better given by someone else.',
                false,
            ],
        ];

        foreach ($rijen as $plek => [$vraagNl, $vraagEn, $antwoordNl, $antwoordEn, $online]) {
            FaqItem::query()->updateOrCreate(
                ['question_nl' => $vraagNl],
                [
                    'question_en' => $vraagEn,
                    'answer_nl' => $antwoordNl,
                    'answer_en' => $antwoordEn,
                    'published' => $online,
                    'position' => $plek + 1,
                ],
            );
        }
    }

    /**
     * De onderwerpen waaruit een bezoeker kan kiezen.
     *
     * @return array<string, ContactSubject>
     */
    private function onderwerpen(): array
    {
        // naam-nl, naam-en, online, plek, uitgelicht
        $rijen = [
            'gesprek' => ['Vrijblijvend gesprek', 'Informal conversation', true, 1, false],
            'offerte' => ['Offerte aanvragen', 'Request a quote', true, 2, false],

            /*
             * Uitgelicht. Hier stond dat zo'n onderwerp als snelkeuze
             * bóven de keuzelijst komt; dat was de eerste lezing en die
             * is teruggedraaid. Uitlichten gaat over de postbus: een
             * aanvraag met dit onderwerp springt eruit onder Beheer →
             * Aanvragen, met een sterretje en een gele tint. Op het
             * formulier verandert er niets.
             *
             * Spoed is het geval waarvoor dat bestaat -- wie een storing
             * meldt wil niet achteraan in de rij staan.
             */
            'storing' => ['Storing of spoed', 'Outage or urgent', true, 3, true],

            // Zonder Engelse naam: op de Engelse site valt hij terug op het
            // Nederlands. Zo zie je dat gedrag zonder het te hoeven
            // uitproberen.
            'samenwerking' => ['Samenwerken', null, true, 4, false],

            // En één die offline staat: die hoort niet op de site te komen.
            'vacature' => ['Vacature of stage', 'Vacancy or internship', false, 5, false],
        ];

        $gemaakt = [];

        foreach ($rijen as $sleutel => [$nl, $en, $online, $plek, $uitgelicht]) {
            $gemaakt[$sleutel] = ContactSubject::query()->updateOrCreate(
                ['label_nl' => $nl],
                [
                    'label_en' => $en,
                    'published' => $online,
                    'position' => $plek,
                    'featured' => $uitgelicht,
                ],
            );
        }

        return $gemaakt;
    }

    /**
     * De aanvragen, in elke stand die het scherm kent.
     *
     * @param  array<string, ContactSubject>  $onderwerpen
     */
    private function aanvragen(array $onderwerpen): void
    {
        $compleet = ['name', 'email', 'company', 'phone', 'subject', 'message'];
        $standaard = ['name', 'email', 'subject', 'message'];

        /*
         * Elk met een eigen tijdstip, want het scherm sorteert op
         * binnenkomst en een rij van negen gelijke tijdstempels zegt niets
         * over de volgorde.
         */
        $rijen = [
            // Vers en ongelezen: dit is wat de eigenaar als eerste ziet.
            [
                'naam' => 'Sanne de Boer',
                'onderwerp' => $onderwerpen['offerte'],
                'bericht' => 'We zoeken iemand die onze werkplekken en ons netwerk onder handen neemt. Een kantoor met twaalf mensen, nu alles via een losse laptopbeheerder. Kun je een indicatie geven van wat zo'."'".'n overstap kost?',
                'bedrijf' => 'De Boer Interieurbouw',
                'telefoon' => '06 24 55 10 92',
                'velden' => $compleet,
                'uren' => 2,
            ],
            [
                'naam' => 'Youssef el Amrani',
                'onderwerp' => $onderwerpen['storing'],
                'bericht' => 'Onze mail doet het sinds vanmorgen niet meer. Niemand kan erbij. Kun je bellen?',
                'telefoon' => '06 11 87 44 03',
                'velden' => ['name', 'email', 'phone', 'subject', 'message'],
                'uren' => 6,
            ],

            // Gelezen maar nog niet beantwoord.
            [
                'naam' => 'Marieke Visser',
                'onderwerp' => $onderwerpen['gesprek'],
                'bericht' => 'Ik werd op je doorverwezen door een collega. Zou je een half uur hebben om mee te denken over onze overstap naar Microsoft 365?',
                'bedrijf' => 'Praktijk Visser',
                'velden' => ['name', 'email', 'company', 'subject', 'message'],
                'gelezen' => true,
                'uren' => 26,
            ],

            // Een Engelstalige bezoeker. Zijn bevestiging was dus Engels.
            [
                'naam' => 'Daniel Okafor',
                'onderwerp' => $onderwerpen['samenwerking'],
                'bericht' => 'We are a small agency in Rotterdam looking for a partner to handle IT for our clients. Would you be open to a conversation about working together?',
                'bedrijf' => 'Northbound Studio',
                'taal' => 'en',
                'velden' => ['name', 'email', 'company', 'subject', 'message'],
                'gelezen' => true,
                'uren' => 30,
            ],

            // Beantwoord: beide bollen aan.
            [
                'naam' => 'Peter Hoekstra',
                'onderwerp' => $onderwerpen['offerte'],
                'bericht' => 'Graag een voorstel voor het beheer van onze zes werkplekken, inclusief back-up.',
                'bedrijf' => 'Hoekstra Transport',
                'telefoon' => '0512 - 54 19 83',
                'velden' => $compleet,
                'beantwoord' => true,
                'uren' => 52,
            ],
            [
                'naam' => 'Fatima Yildiz',
                'onderwerp' => $onderwerpen['gesprek'],
                'bericht' => 'Dank voor het gesprek van vorige week. Ik stuur de gegevens die je vroeg nog na.',
                'velden' => $standaard,
                'beantwoord' => true,
                'uren' => 96,
            ],

            // Een zelf getypt onderwerp: de bezoeker koos niet uit de lijst.
            [
                'naam' => 'Rob Mulder',
                'eigenOnderwerp' => 'Vraag over een oude factuur',
                'bericht' => 'Ik kan factuur 2024-118 niet meer vinden in mijn administratie. Kun je die opnieuw sturen?',
                'velden' => $standaard,
                'gelezen' => true,
                'uren' => 150,
            ],

            // Een heel lang bericht: zo zie je of het scherm dat aankan.
            [
                'naam' => 'Ingrid van Dijk',
                'onderwerp' => $onderwerpen['samenwerking'],
                'bericht' => trim(str_repeat(
                    'We zijn aan het nadenken over hoe we onze IT de komende jaren willen inrichten, en ik merk dat ik daar graag eens uitgebreid over zou willen sparren met iemand die er vaker mee te maken heeft. ',
                    6,
                )),
                'bedrijf' => 'Van Dijk & Partners',
                'telefoon' => '06 38 20 71 45',
                'velden' => $compleet,
                'uren' => 200,
            ],

            // En een heel kort, met een onderwerp dat later is verwijderd.
            [
                'naam' => 'Tom Beekman',
                'eigenOnderwerp' => 'Terugbelverzoek',
                'bericht' => 'Kun je me bellen? Alvast dank.',
                'telefoon' => '06 49 03 66 21',
                'velden' => ['name', 'email', 'phone', 'subject', 'message'],
                'verdwenenOnderwerp' => true,
                'beantwoord' => true,
                'uren' => 400,
            ],
        ];

        foreach ($rijen as $rij) {
            $moment = now()->subHours($rij['uren']);

            $onderwerp = $rij['onderwerp'] ?? null;

            $aanvraag = ContactSubmission::query()->updateOrCreate(
                ['email' => $this->adres($rij['naam'])],
                [
                    'locale' => $rij['taal'] ?? 'nl',
                    'name' => $rij['naam'],
                    'company' => $rij['bedrijf'] ?? null,
                    'phone' => $rij['telefoon'] ?? null,

                    /*
                     * Een verdwenen onderwerp: de tekst blijft, de
                     * verwijzing niet. Zo ziet de eigenaar de melding
                     * "dit onderwerp bestaat niet meer" zonder dat hij er
                     * eerst een hoeft weg te gooien.
                     */
                    'subject_id' => ($rij['verdwenenOnderwerp'] ?? false)
                        ? null
                        : $onderwerp?->id,

                    'subject_text' => $rij['eigenOnderwerp']
                        ?? $onderwerp?->naam()
                        ?? 'Onbekend',

                    'subject_custom' => isset($rij['eigenOnderwerp'])
                        && ! ($rij['verdwenenOnderwerp'] ?? false),

                    'message' => $rij['bericht'],
                    'shown' => $rij['velden'],

                    'read_at' => ($rij['gelezen'] ?? false) || ($rij['beantwoord'] ?? false)
                        ? $moment->copy()->addHours(1)
                        : null,

                    'answered_at' => ($rij['beantwoord'] ?? false)
                        ? $moment->copy()->addHours(2)
                        : null,

                    'created_at' => $moment,
                    'updated_at' => $moment,
                ],
            );

            /*
             * `created_at` gaat niet mee via `updateOrCreate` als de rij al
             * bestond, dus hier nog een keer. Anders staan ze bij een
             * tweede keer zaaien allemaal op vandaag.
             */
            $aanvraag->forceFill([
                'created_at' => $moment,
                'updated_at' => $moment,
            ])->saveQuietly();
        }
    }

    /**
     * Het mailoverzicht, met de gevallen die je wil kunnen zien.
     *
     * Niet alleen geslaagde mail: een bounce en een mislukte verzending
     * zijn precies waarvoor dat scherm bestaat.
     */
    private function mailoverzicht(): void
    {
        $rijen = [
            [
                'mailable' => ContactMessageMail::class,
                'subject' => ContactMessageMail::logboekOnderwerp(),
                'to' => [config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 2,
            ],
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Sanne de Boer')],
                'status' => MailStatus::Opened,
                'uren' => 2,
            ],
            [
                'mailable' => ContactMessageMail::class,
                'subject' => ContactMessageMail::logboekOnderwerp(),
                'to' => [config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 6,
            ],
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Youssef el Amrani')],
                'status' => MailStatus::Sent,
                'uren' => 6,
            ],

            // Een adres dat niet bestaat: de provider stuurt hem terug.
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => ['typefout'.self::DOMEIN],
                'status' => MailStatus::Bounced,
                'error' => '550 5.1.1 The email account that you tried to reach does not exist.',
                'uren' => 30,
            ],

            // En een die nooit is vertrokken; zie MeldtMislukteVerzending.
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Rob Mulder')],
                'status' => MailStatus::Failed,
                'error' => 'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host "smtp:587".',
                'uren' => 150,
                'nietVerstuurd' => true,
            ],

            [
                'mailable' => SecurityAlertMail::class,
                'subject' => 'Beveiligingsmelding: Problemen met uitgaande mail',
                'to' => [config('security.alerts.address') ?? config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 149,
            ],
        ];

        foreach ($rijen as $rij) {
            $moment = now()->subHours($rij['uren']);

            MailLog::query()->updateOrCreate(
                ['message_id' => 'voorbeeld-'.md5((string) json_encode($rij))],
                [
                    'mailable' => $rij['mailable'],
                    'mailer' => config('mail.default'),
                    'subject' => $rij['subject'],
                    'to' => $rij['to'],
                    'status' => $rij['status'],
                    'error' => $rij['error'] ?? null,

                    // Een mail die nooit vertrok heeft geen verzendmoment.
                    'sent_at' => ($rij['nietVerstuurd'] ?? false) ? null : $moment,

                    'last_event_at' => $moment,
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ],
            );
        }
    }

    /**
     * Een herkenbaar adres bij een naam.
     *
     * Altijd op `@voorbeeld.test`, zodat `voorbeeld:opruimen` precies weet
     * wat van hem is en er nooit een echte aanvraag per ongeluk meegaat.
     */
    private function adres(string $naam): string
    {
        $kaal = str_replace(' ', '.', mb_strtolower($naam));

        return preg_replace('/[^a-z0-9.]/', '', $kaal).self::DOMEIN;
    }
}
