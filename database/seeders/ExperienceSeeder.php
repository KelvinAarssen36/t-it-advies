<?php

namespace Database\Seeders;

use App\Models\Experience;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * De loopbaan van de eigenaar, zoals die op de tijdlijn komt te staan.
 *
 * **Dit is productiedata en geen testdata.** Het is de echte werkervaring
 * die de klant heeft aangeleverd, en hij hoort in elke omgeving te
 * bestaan -- ook in productie. Zie DatabaseSeeder.
 *
 * **De seeder is aanvullend en overschrijft nooit.** Hij zoekt op
 * organisatie, functie en begindatum; bestaat die combinatie al, dan blijft
 * de rij precies zoals hij is. Past de klant later een beschrijving aan,
 * dan gooit een volgende deploy die dus niet weg.
 *
 * **Let op één gevolg daarvan.** Verwijdert de klant een ervaring in het
 * portaal, dan zet de eerstvolgende deploy hem terug -- want voor deze
 * seeder is hij dan gewoon nieuw. Moet er iets echt weg, haal het dan ook
 * hier weg. Dat is de keerzijde van seeddata die productiedata is.
 *
 * **De Engelse teksten staan erbij.** Zonder dat zou de Engelse site
 * terugvallen op het Nederlands voor de functietitels en alle
 * beschrijvingen weglaten, en dan waren er zesentwintig formulieren in te
 * vullen voordat de site tweetalig is. Ze zijn met de hand geschreven en
 * niet automatisch vertaald; `machine_translated_at` blijft daarom leeg en
 * het portaal zet er geen merkje bij. De klant kan ze alsnog aanpassen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek. Zesentwintig regels "Systeem heeft een ervaring
     * aangemaakt" zou dat logboek bij de eerste deploy onleesbaar maken.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->loopbaan() as $ervaring) {
            if ($this->bestaatAl($ervaring)) {
                continue;
            }

            Experience::query()->create([
                ...$ervaring,
                'started_on' => $ervaring['started_on'].'-01',
                'ended_on' => $ervaring['ended_on'] === null
                    ? null
                    : $ervaring['ended_on'].'-01',
            ]);
        }
    }

    /**
     * Staat deze ervaring er al?
     *
     * **Met `whereDate` en niet met `firstOrCreate` op een datumstring.**
     * Dat laatste stond hier eerst, en het legde een verschil tussen de
     * twee databases bloot: Eloquent schrijft een datumkolom weg als
     * `2025-03-01 00:00:00`, MySQL kapt dat af tot de datum en SQLite
     * bewaart de hele string. Een vergelijking met `'2025-03-01'` klopt
     * dan wél op MySQL -- waar de applicatie op draait -- en níet op
     * SQLite, waar de tests op draaien.
     *
     * De applicatie liep dus geen gevaar, maar de test zou bij elke
     * volgende wijziging een dubbele loopbaan melden die er in het echt
     * niet is. Zo'n verschil moet je wegnemen en niet wegredeneren: een
     * test die iets anders doet dan productie, is een test die je op een
     * dag ten onrechte gelooft. `whereDate` klopt op allebei.
     *
     * Zie docs/development/testen.md over waarom de tests op SQLite draaien.
     *
     * @param  array<string, string|null>  $ervaring
     */
    private function bestaatAl(array $ervaring): bool
    {
        return Experience::query()
            ->where('organisation', $ervaring['organisation'])
            ->where('role_nl', $ervaring['role_nl'])
            ->whereDate('started_on', $ervaring['started_on'].'-01')
            ->exists();
    }

    /**
     * De loopbaan, van nieuw naar oud.
     *
     * De periodes staan als `jaar-maand`; de dag wordt er hier bij gezet en
     * betekent niets. Zie de migratie.
     *
     * Een lege einddatum betekent "tot heden" -- dat is de ervaring die nu
     * loopt, en die komt bovenaan de tijdlijn te staan.
     *
     * De opdrachten die via een ander bureau liepen, staan met dat bureau
     * erbij in de naam van de organisatie ("via PeopleWare"). Het model kent
     * geen constructie voor een opdracht binnen een dienstverband, en die
     * erbij bouwen zou meer opleveren voor de code dan voor de lezer: zo
     * staat er gewoon wat er was.
     *
     * @return array<int, array<string, string|null>>
     */
    private function loopbaan(): array
    {
        return [
            [
                'icon' => 'infrastructuur',
                'role_nl' => 'Manager Infrastructure & Service Delivery',
                'role_en' => 'Manager Infrastructure & Service Delivery',
                'organisation' => 'Eni',
                'employment' => 'freelance',
                'location_nl' => 'Den Haag, Zuid-Holland, Nederland',
                'location_en' => 'The Hague, South Holland, Netherlands',
                'started_on' => '2025-03',
                'ended_on' => null,
                'description_nl' => null,
                'description_en' => null,
            ],
            [
                'icon' => 'beheer',
                'role_nl' => 'Interim Manager Run & Information',
                'role_en' => 'Interim Manager Run & Information',
                'organisation' => 'DPD Pakketservice',
                'employment' => 'freelance',
                'location_nl' => 'Oirschot, Noord-Brabant, Nederland',
                'location_en' => 'Oirschot, North Brabant, Netherlands',
                'started_on' => '2022-11',
                'ended_on' => '2025-01',
                'description_nl' => 'Netwerken, strategisch leiderschap en diverse aanvullende management- en IT-vaardigheden.',
                'description_en' => 'Networking, strategic leadership and a range of further management and IT skills.',
            ],
            [
                'icon' => 'infrastructuur',
                'role_nl' => 'IT Operations Manager (EU)',
                'role_en' => 'IT Operations Manager (EU)',
                'organisation' => 'Aalberts hydronic flow control',
                'employment' => 'freelance',
                'location_nl' => 'Almere, Flevoland, Nederland',
                'location_en' => 'Almere, Flevoland, Netherlands',
                'started_on' => '2021-07',
                'ended_on' => '2022-10',
                'description_nl' => 'Netwerken, strategisch leiderschap en aanvullende IT operations- en managementvaardigheden.',
                'description_en' => 'Networking, strategic leadership and further IT operations and management skills.',
            ],
            [
                'icon' => 'beheer',
                'role_nl' => 'Senior Service Manager',
                'role_en' => 'Senior Service Manager',
                'organisation' => 'Valtech',
                'employment' => 'freelance',
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '2020-03',
                'ended_on' => '2021-07',
                'description_nl' => 'Netwerken, strategie en service management.',
                'description_en' => 'Networking, strategy and service management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Interim Information Technology Manager / Strategic Advisor',
                'role_en' => 'Interim Information Technology Manager / Strategic Advisor',
                'organisation' => 'Frames',
                'employment' => 'freelance',
                'location_nl' => 'Alphen aan den Rijn',
                'location_en' => 'Alphen aan den Rijn',
                'started_on' => '2019-08',
                'ended_on' => '2020-03',
                'description_nl' => 'Netwerken, strategisch leiderschap en IT-management.',
                'description_en' => 'Networking, strategic leadership and IT management.',
            ],
            [
                'icon' => 'beheer',
                'role_nl' => 'Manager Service Delivery',
                'role_en' => 'Manager Service Delivery',
                'organisation' => 'European Patent Office (via Indra)',
                'employment' => 'freelance',
                'location_nl' => 'Den Haag',
                'location_en' => 'The Hague',
                'started_on' => '2018-06',
                'ended_on' => '2019-07',
                'description_nl' => 'Netwerken, strategische visie en service delivery management.',
                'description_en' => 'Networking, strategic vision and service delivery management.',
            ],
            [
                'icon' => 'beheer',
                'role_nl' => 'Manager Operations',
                'role_en' => 'Manager Operations',
                'organisation' => 'True B.V.',
                'employment' => 'freelance',
                'location_nl' => 'Amsterdam en Maastricht',
                'location_en' => 'Amsterdam and Maastricht',
                'started_on' => '2017-06',
                'ended_on' => '2018-06',
                'description_nl' => 'Netwerken, strategisch leiderschap en operations management.',
                'description_en' => 'Networking, strategic leadership and operations management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Senior Consultant / Interim Manager / Projectmanager / COO',
                'role_en' => 'Senior Consultant / Interim Manager / Project Manager / COO',
                'organisation' => 'PADACO Automatisering',
                'employment' => 'tijdelijk',
                'location_nl' => 'Aalsmeer',
                'location_en' => 'Aalsmeer',
                'started_on' => '2016-09',
                'ended_on' => '2017-05',
                'description_nl' => 'Strategisch leiderschap, strategische bedrijfsvoering, consultancy en projectmanagement.',
                'description_en' => 'Strategic leadership, strategic business operations, consultancy and project management.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Teamleider / Projectmanager TALOS',
                'role_en' => 'Team Lead / Project Manager TALOS',
                'organisation' => 'Centraal Orgaan opvang asielzoekers (COA)',
                'employment' => 'freelance',
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '2014-03',
                'ended_on' => '2016-08',
                'description_nl' => "Leidinggeven aan de processen rondom het openen en sluiten van locaties. Verantwoordelijk voor de aansturing, logistiek en afstemming bij het inrichten en inhuizen van panden en kantoren, evenals het ontmantelen van werkplekken en infrastructuur bij sluitingen.\n\nAnalytisch denkvermogen, budgettering, prognoseplanning, projectmanagement en leidinggeven.",
                'description_en' => "Leading the processes around opening and closing locations. Responsible for the coordination, logistics and alignment involved in fitting out and moving into buildings and offices, as well as dismantling workplaces and infrastructure on closure.\n\nAnalytical thinking, budgeting, forecast planning, project management and leadership.",
            ],
            [
                'icon' => 'bedrijf',
                'role_nl' => 'Service Management / Interim Management / Procesmanagement / Bid Management / Projectmanagement',
                'role_en' => 'Service Management / Interim Management / Process Management / Bid Management / Project Management',
                'organisation' => 'PeopleWare',
                'employment' => 'vast',
                'location_nl' => 'Leiden',
                'location_en' => 'Leiden',
                'started_on' => '2007-08',
                'ended_on' => '2016-08',
                'description_nl' => 'Binnen PeopleWare verschillende interim-, consultancy- en managementopdrachten uitgevoerd voor grote organisaties.',
                'description_en' => 'Carried out a range of interim, consultancy and management assignments for large organisations from within PeopleWare.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Service Manager / Team Manager',
                'role_en' => 'Service Manager / Team Manager',
                'organisation' => 'Mercedes-Benz Nederland (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Randstad',
                'location_en' => 'Randstad',
                'started_on' => '2012-08',
                'ended_on' => '2013-12',
                'description_nl' => 'Probleemoplossing, teamleiderschap en service management.',
                'description_en' => 'Problem solving, team leadership and service management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Process Implementation and Improvement Manager / Business Consultant',
                'role_en' => 'Process Implementation and Improvement Manager / Business Consultant',
                'organisation' => 'Fujitsu Technology Solutions Nederland (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Maarssen',
                'location_en' => 'Maarssen',
                'started_on' => '2012-01',
                'ended_on' => '2012-07',
                'description_nl' => 'Strategische bedrijfsvoering, analytisch denkvermogen, procesverbetering en consultancy.',
                'description_en' => 'Strategic business operations, analytical thinking, process improvement and consultancy.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Project Manager / Advisor Service Management',
                'role_en' => 'Project Manager / Advisor Service Management',
                'organisation' => 'Sony Benelux (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Zaventem, België',
                'location_en' => 'Zaventem, Belgium',
                'started_on' => '2011-08',
                'ended_on' => '2011-12',
                'description_nl' => 'Analytisch denkvermogen, probleemoplossing, projectmanagement en service management.',
                'description_en' => 'Analytical thinking, problem solving, project management and service management.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Service Manager / Coach',
                'role_en' => 'Service Manager / Coach',
                'organisation' => 'Sony Benelux (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Brussel en omgeving',
                'location_en' => 'Brussels and surroundings',
                'started_on' => '2011-05',
                'ended_on' => '2011-08',
                'description_nl' => 'Netwerken, analytisch denkvermogen, coaching en service management.',
                'description_en' => 'Networking, analytical thinking, coaching and service management.',
            ],
            [
                'icon' => 'beheer',
                'role_nl' => 'Service Delivery Manager',
                'role_en' => 'Service Delivery Manager',
                'organisation' => 'COFELY GDF-SUEZ (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Bunnik',
                'location_en' => 'Bunnik',
                'started_on' => '2010-08',
                'ended_on' => '2011-05',
                'description_nl' => 'Analytisch denkvermogen, probleemoplossing en service delivery management.',
                'description_en' => 'Analytical thinking, problem solving and service delivery management.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Regiehouder / Projectleider / Senior Specialist',
                'role_en' => 'Demand Manager / Project Lead / Senior Specialist',
                'organisation' => 'Evides (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Randstad',
                'location_en' => 'Randstad',
                'started_on' => '2009-07',
                'ended_on' => '2010-09',
                'description_nl' => 'Analytisch denkvermogen, teamleiderschap en projectmanagement.',
                'description_en' => 'Analytical thinking, team leadership and project management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Service Management Consultant / Coach',
                'role_en' => 'Service Management Consultant / Coach',
                'organisation' => 'TNT Post (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Hoofddorp',
                'location_en' => 'Hoofddorp',
                'started_on' => '2009-04',
                'ended_on' => '2009-07',
                'description_nl' => 'Analytisch denkvermogen, probleemoplossing, consultancy en coaching.',
                'description_en' => 'Analytical thinking, problem solving, consultancy and coaching.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Test Manager / Coach / Projectmanager',
                'role_en' => 'Test Manager / Coach / Project Manager',
                'organisation' => 'FloraHolland (via PeopleWare)',
                'employment' => null,
                'location_nl' => 'Bleiswijk',
                'location_en' => 'Bleiswijk',
                'started_on' => '2009-01',
                'ended_on' => '2009-05',
                'description_nl' => 'Probleemoplossing, teamleiderschap, testmanagement en projectmanagement.',
                'description_en' => 'Problem solving, team leadership, test management and project management.',
            ],
            [
                'icon' => 'team',
                'role_nl' => 'Projectmanagement',
                'role_en' => 'Project Management',
                'organisation' => 'FloraHolland (via PeopleWare)',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '2007-12',
                'ended_on' => '2009-01',
                'description_nl' => 'Projectmanagement en probleemoplossing.',
                'description_en' => 'Project management and problem solving.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Interim I&A Manager',
                'role_en' => 'Interim Information & Automation Manager',
                'organisation' => 'FloraHolland (via PeopleWare)',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '2007-08',
                'ended_on' => '2009-03',
                'description_nl' => 'Netwerken, strategisch leiderschap en informatie- en automatiseringsmanagement.',
                'description_en' => 'Networking, strategic leadership and information and automation management.',
            ],
            [
                'icon' => 'bedrijf',
                'role_nl' => 'Hoofd Facilitair Bedrijf / Coördinator ICT / Projectmanager',
                'role_en' => 'Head of Facilities / ICT Coordinator / Project Manager',
                'organisation' => 'Rabobank West-Brabant Noord',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '2001-04',
                'ended_on' => '2007-08',
                'description_nl' => 'Netwerken, analytisch denkvermogen, ICT-coördinatie, facilitair management en projectmanagement.',
                'description_en' => 'Networking, analytical thinking, ICT coordination, facilities management and project management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Senior IT Consultant / Troubleshooter',
                'role_en' => 'Senior IT Consultant / Troubleshooter',
                'organisation' => 'Advisie Automatisering',
                'employment' => null,
                'location_nl' => 'Soest',
                'location_en' => 'Soest',
                'started_on' => '1999-01',
                'ended_on' => '2001-03',
                'description_nl' => 'Analytisch denkvermogen, infrastructuurservices, troubleshooting en consultancy.',
                'description_en' => 'Analytical thinking, infrastructure services, troubleshooting and consultancy.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'IT Consultant',
                'role_en' => 'IT Consultant',
                'organisation' => 'Rosys BV',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '1998-06',
                'ended_on' => '1998-12',
                'description_nl' => 'Probleemoplossing en infrastructuurbeheer.',
                'description_en' => 'Problem solving and infrastructure management.',
            ],
            [
                'icon' => 'infrastructuur',
                'role_nl' => 'Systems Supervisor Europe',
                'role_en' => 'Systems Supervisor Europe',
                'organisation' => 'McGhan Medical BV',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '1993-10',
                'ended_on' => '1998-07',
                'description_nl' => 'Infrastructuurservices, probleemoplossing en IT-beheer.',
                'description_en' => 'Infrastructure services, problem solving and IT management.',
            ],
            [
                'icon' => 'bedrijf',
                'role_nl' => 'Filiaalhouder',
                'role_en' => 'Branch Manager',
                'organisation' => 'Funtonics',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '1993-02',
                'ended_on' => '1993-09',
                'description_nl' => 'Teamleiderschap en operationele aansturing.',
                'description_en' => 'Team leadership and operational management.',
            ],
            [
                'icon' => 'advies',
                'role_nl' => 'Medewerker Ontwikkeling / ISO-certificering',
                'role_en' => 'Development Officer / ISO Certification',
                'organisation' => 'Isover BV',
                'employment' => null,
                'location_nl' => null,
                'location_en' => null,
                'started_on' => '1991-06',
                'ended_on' => '1993-01',
                'description_nl' => 'ISO 9000, kwaliteitsmanagement en certificering.',
                'description_en' => 'ISO 9000, quality management and certification.',
            ],
        ];
    }
}
