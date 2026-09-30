<?php

namespace App\Http\Controllers\Website\Concerns;

use App\Enums\PageSectionKey;
use App\Models\SectionHeading;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * De kop boven een onderdeel opslaan.
 *
 * Deze code stond drie keer bijna identiek in evenveel controllers, en
 * met de certificaten erbij zou het vier keer worden. Nu staat hij hier,
 * en zegt een controller alleen nog welk onderdeel hij is.
 *
 * **Waarom een trait en geen aparte controller.** De kop wordt bewerkt
 * vanaf het scherm van de module zelf -- je gaat naar Diensten en klikt
 * daar op "Kop erboven". De route hoort dus bij die module
 * (`website.diensten.kop`), en dan hoort de methode bij die controller.
 * Eén `SectionHeadingController` zou één route opleveren waar de sectie
 * als parameter in moet, en dan is elk beheerscherm aan het uitleggen
 * welk onderdeel het is aan een adres dat het al weet.
 *
 * Een controller die dit gebruikt zegt twee dingen: welk onderdeel het
 * is, en wat er in de melding komt te staan als het gelukt is.
 *
 * Zie docs/architecture/kopteksten.md.
 */
trait BewaartKoptekst
{
    /** Bij welk onderdeel de kop hoort die dit scherm bewerkt. */
    abstract protected function sectie(): PageSectionKey;

    /**
     * Of het opschrift verplicht is.
     *
     * Verschilt per onderdeel, en daarom staat het hier en niet in de
     * database: de kolom is nullable omdat hij het slapste geval moet
     * aankunnen, maar boven de diensten hóórt een opschrift te staan.
     * De tijdlijn heeft er nooit een gehad en zet dit dus op `false`.
     */
    protected function opschriftVerplicht(): bool
    {
        return true;
    }

    /**
     * De regels voor de zes tekstvelden.
     *
     * De lengtes zijn die van de kolommen, en ze zijn krap met reden:
     * dit is een kop en geen alinea. Een titel van tweehonderd tekens
     * breekt het ontwerp op een telefoon.
     *
     * @return array<string, array<int, string>>
     */
    protected function koptekstRegels(): array
    {
        return [
            'eyebrow_nl' => [$this->opschriftVerplicht() ? 'required' : 'nullable', 'string', 'max:60'],
            'eyebrow_en' => ['nullable', 'string', 'max:60'],
            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'intro_nl' => ['nullable', 'string', 'max:300'],
            'intro_en' => ['nullable', 'string', 'max:300'],

            /*
             * Of het Engels van de vertaaldienst kwam. De frontend zet
             * dit; hij weet als enige of de klant de Engelse tekst
             * daarna nog met de hand heeft aangeraakt.
             */
            'automatisch_vertaald' => ['boolean'],
        ];
    }

    /**
     * De teksten wegschrijven.
     *
     * @param  array<string, mixed>  $gegevens
     * @return bool Of er echt iets veranderde.
     */
    protected function bewaarKoptekst(array $gegevens): bool
    {
        $kop = SectionHeading::voor($this->sectie());

        /*
         * Een leeg optioneel veld wordt `null` en geen lege string. Dat
         * verschil bepaalt of de website er een zin onder zet, en het is
         * dezelfde regel als overal; zie docs/development/testen.md.
         */
        $kop->fill([
            'section' => $this->sectie(),
            'eyebrow_nl' => $this->koptekstLeeg($gegevens['eyebrow_nl'] ?? null),
            'eyebrow_en' => $this->koptekstLeeg($gegevens['eyebrow_en'] ?? null),
            'title_nl' => $gegevens['title_nl'],
            'title_en' => $this->koptekstLeeg($gegevens['title_en'] ?? null),
            'intro_nl' => $this->koptekstLeeg($gegevens['intro_nl'] ?? null),
            'intro_en' => $this->koptekstLeeg($gegevens['intro_en'] ?? null),
        ]);

        /*
         * Het merkje "automatisch vertaald" hangt aan de tekst en niet
         * aan deze opslag: het zegt dat het Engels dat er nú staat van de
         * dienst komt en nog door niemand is nagelezen. Het scherm
         * bepaalt of dat nog waar is -- het weet als enige of de klant
         * daarna nog in de Engelse velden heeft getypt.
         */
        $kop->machine_translated_at = ($gegevens['automatisch_vertaald'] ?? false)
            ? ($kop->machine_translated_at ?? Carbon::now())
            : null;

        /*
         * `isDirty()` op een rij die nog niet bestaat is altijd waar, en
         * dat klopt ook: hem voor het eerst wegschrijven ís een
         * wijziging.
         */
        $veranderd = ! $kop->exists || $kop->isDirty();

        $kop->save();

        return $veranderd;
    }

    /** De zes velden los, zoals het beheerscherm ze bewerkt. */
    protected function koptekst(): SectionHeading
    {
        return SectionHeading::voor($this->sectie());
    }

    /**
     * De validatie draaien en meteen opslaan.
     *
     * Voor de schermen waar de kop op zichzelf staat. De tijdlijn slaat
     * zijn kop samen met de cijfers op en gebruikt daarom de losse
     * onderdelen hierboven.
     *
     * @return bool Of er echt iets veranderde.
     */
    protected function verwerkKoptekst(Request $request): bool
    {
        return $this->bewaarKoptekst($request->validate($this->koptekstRegels()));
    }

    /**
     * Een leeg veld wordt `null` en geen lege string.
     *
     * De naam is met opzet lang. Een trait deelt zijn methoden met de
     * klasse die hem gebruikt, en een `leeg()` botst daar op de eerste
     * de beste controller die zelf ook zoiets heeft -- wat hier ook
     * meteen gebeurde.
     */
    private function koptekstLeeg(mixed $waarde): ?string
    {
        return blank($waarde) ? null : (string) $waarde;
    }
}
