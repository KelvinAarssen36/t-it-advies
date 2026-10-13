<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drie koptabellen worden er één.
 *
 * `hero_headings`, `experience_headings` en `service_headings` waren drie
 * bijna identieke tabellen met één rij: een opschrift, een titel, een zin
 * eronder en een merkje voor automatisch vertaald. Elke module met een
 * beheerbare kop maakte er een bij, en de certificaten zouden de vierde
 * zijn geweest.
 *
 * Nu is het één tabel met een regel per onderdeel. Elk toekomstig
 * onderdeel heeft daarmee gratis een beheerbare kop, en er is één model,
 * één FormRequest en één test in plaats van vier.
 *
 * **De inhoud verhuist mee.** Hier staat tekst in die de eigenaar zelf
 * heeft geschreven en die op zijn voorpagina staat; een migratie die dat
 * weggooit en de seeder er daarna weer overheen laat gaan, zet zijn
 * eigen woorden terug naar de onze.
 *
 * Om diezelfde reden zet `down()` alles echt terug en doet hij niet
 * alleen een `dropIfExists`. Een teruggedraaide migratie die zijn tekst
 * kwijtraakt is erger dan de migratie zelf.
 *
 * Zie docs/architecture/kopteksten.md.
 */
return new class extends Migration
{
    /**
     * De oude tabellen, met het onderdeel waar ze bij horen.
     *
     * De sleutels van PageSectionKey staan hier als tekst en niet als
     * enum. Een migratie is een momentopname van het schema: hernoemt
     * iemand over een jaar een `case`, dan hoort deze verhuizing nog
     * steeds te doen wat hij toen deed.
     *
     * @var array<string, string>
     */
    private const HERKOMST = [
        'hero_headings' => 'hero',
        'service_headings' => 'diensten',
        'experience_headings' => 'ervaring',
    ];

    public function up(): void
    {
        Schema::create('section_headings', function (Blueprint $table) {
            $table->id();

            /*
             * De waarde van PageSectionKey. Een string en geen vreemde
             * sleutel, want de lijst van onderdelen staat in de code en
             * niet in een tabel; zie de toelichting bij die enum.
             *
             * Uniek, want één onderdeel heeft één kop. Zonder deze index
             * levert een dubbele opslag twee rijen op en wint de oudste
             * -- een fout die je pas op de website ziet.
             */
            $table->string('section', 40)->unique();

            /*
             * Het opschrift is hier nullable en bij de kop en de diensten
             * toch verplicht. Dat is met opzet: de kolom moet het slapste
             * geval aankunnen -- de tijdlijn heeft nooit een opschrift
             * gehad -- en wát verplicht is verschilt per onderdeel. Dat
             * oordeel hoort in SectionHeadingRequest en niet in het
             * schema.
             */
            $table->string('eyebrow_nl', 60)->nullable();
            $table->string('eyebrow_en', 60)->nullable();

            // De lengtes zijn krap met reden: dit is een kop en geen
            // alinea. Een titel van tweehonderd tekens breekt het
            // ontwerp op een telefoon.
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });

        foreach (self::HERKOMST as $tabel => $sectie) {
            $this->verhuis($tabel, $sectie);
        }

        foreach (array_keys(self::HERKOMST) as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }

    public function down(): void
    {
        $this->maakHeroHeadings();
        $this->maakServiceHeadings();
        $this->maakExperienceHeadings();

        foreach (self::HERKOMST as $tabel => $sectie) {
            $this->verhuisTerug($tabel, $sectie);
        }

        Schema::dropIfExists('section_headings');
    }

    /**
     * Eén oude tabel overzetten naar de nieuwe.
     *
     * `first()` en geen loop: er stond er altijd precies één in. Stond er
     * niets, dan is er niets te verhuizen en vult de seeder hem straks.
     */
    private function verhuis(string $tabel, string $sectie): void
    {
        if (! Schema::hasTable($tabel)) {
            return;
        }

        $rij = DB::table($tabel)->first();

        if ($rij === null) {
            return;
        }

        $gegevens = (array) $rij;

        DB::table('section_headings')->insert([
            'section' => $sectie,

            /*
             * `experience_headings` had geen opschrift. Het `??` vangt
             * dat op; zonder deze regel valt de migratie om op een
             * kolom die daar niet bestaat.
             */
            'eyebrow_nl' => $gegevens['eyebrow_nl'] ?? null,
            'eyebrow_en' => $gegevens['eyebrow_en'] ?? null,

            'title_nl' => $gegevens['title_nl'],
            'title_en' => $gegevens['title_en'],
            'intro_nl' => $gegevens['intro_nl'],
            'intro_en' => $gegevens['intro_en'],
            'machine_translated_at' => $gegevens['machine_translated_at'],
            'created_at' => $gegevens['created_at'],
            'updated_at' => $gegevens['updated_at'],
        ]);
    }

    /** De weg terug, voor `down()`. */
    private function verhuisTerug(string $tabel, string $sectie): void
    {
        $rij = DB::table('section_headings')->where('section', $sectie)->first();

        if ($rij === null) {
            return;
        }

        $gegevens = [
            'title_nl' => $rij->title_nl,
            'title_en' => $rij->title_en,
            'intro_nl' => $rij->intro_nl,
            'intro_en' => $rij->intro_en,
            'machine_translated_at' => $rij->machine_translated_at,
            'created_at' => $rij->created_at,
            'updated_at' => $rij->updated_at,
        ];

        // De tijdlijn kende die twee kolommen niet, dus daar horen ze ook
        // bij het terugdraaien niet in het verzoek te zitten.
        if ($tabel !== 'experience_headings') {
            $gegevens['eyebrow_nl'] = $rij->eyebrow_nl ?? '';
            $gegevens['eyebrow_en'] = $rij->eyebrow_en;
        }

        DB::table($tabel)->insert($gegevens);
    }

    private function maakHeroHeadings(): void
    {
        Schema::create('hero_headings', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow_nl', 60);
            $table->string('eyebrow_en', 60)->nullable();
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();
            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    private function maakServiceHeadings(): void
    {
        Schema::create('service_headings', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow_nl', 60);
            $table->string('eyebrow_en', 60)->nullable();
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();
            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    private function maakExperienceHeadings(): void
    {
        Schema::create('experience_headings', function (Blueprint $table) {
            $table->id();
            $table->string('title_nl');
            $table->string('title_en')->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();
            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }
};
