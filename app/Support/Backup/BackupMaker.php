<?php

namespace App\Support\Backup;

use App\Models\Backup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Maakt een back-up van de website-inhoud.
 *
 * Het resultaat is een zip met drie dingen:
 *
 * - `manifest.json` -- wat erin zit, van welke site, van welk schema, en
 *   de SHA-256 van alles;
 * - `data.json` -- de rijen, per tabel, in de volgorde van het register;
 * - `media/<sha256>.webp` -- de beelden.
 *
 * **De beelden staan daarnaast in een gedeelde map op schijf**, onder
 * diezelfde hash. Daardoor kost een beeld dat niet verandert maar één keer
 * ruimte, hoeveel back-ups er ook naar wijzen. Dat is wat deze voorziening
 * meegroeibaar maakt: bij honderd projecten is de tiende back-up niet tien
 * keer zo duur als de eerste.
 *
 * **Alles wordt in één transactie uitgelezen.** Niet omdat er een tweede
 * schrijver is -- dit portaal heeft één account -- maar omdat een back-up
 * die halverwege een wijziging is gemaakt achteraf niet van een goede te
 * onderscheiden is.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupMaker
{
    /**
     * De versie van het bestandsformaat.
     *
     * Verandert de vorm ooit, dan weigert BackupLezer een versie die hij
     * niet kent in plaats van hem verkeerd te lezen.
     */
    public const FORMAAT = 1;

    public function __construct(
        private readonly Inhoudsregister $register,
        private readonly BackupLezer $lezer,
    ) {}

    /**
     * Zet een nieuwe back-up neer en controleer hem meteen.
     *
     * @throws RuntimeException als het schrijven of het verifiëren mislukt
     */
    public function maak(string $soort = Backup::SOORT_HANDMATIG): Backup
    {
        $naam = $this->verseNaam();
        $bestand = Backup::MAP.'/'.$naam.'.zip';

        [$data, $beelden] = $this->leesAlles();

        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /*
         * Eén moment voor allebei. Het manifest en de rij moeten exact
         * dezelfde tijd dragen: bij een upload wordt de rij uit het
         * manifest opgebouwd, en lopen ze uiteen dan verschuift een
         * back-up een paar seconden in de volgorde zodra hij elders
         * wordt teruggeplaatst.
         */
        $nu = Carbon::now();

        $manifest = [
            'formaat' => self::FORMAAT,
            'gemaakt' => $nu->toIso8601String(),
            'naam' => $naam,
            'soort' => $soort,
            'schema_merk' => $this->schemaMerk(),
            'site_merk' => $this->siteMerk(),
            'checksum' => hash('sha256', $json),
            'aantallen' => array_map('count', $data),
            'beelden' => $beelden,
        ];

        $this->schrijfZip($bestand, $manifest, $json, $beelden);
        $this->bewaarInDeBeeldmap($beelden);

        $backup = Backup::query()->create([
            'naam' => $naam,
            'vastgelegd_op' => $nu,
            'bevestigcode' => Backup::verseCode(),
            'soort' => $soort,
            'bestand' => $bestand,
            'grootte' => (int) Storage::disk('local')->size($bestand),
            'checksum' => $manifest['checksum'],
            'aantallen' => $manifest['aantallen'],
            'schema_merk' => $manifest['schema_merk'],
            'site_merk' => $manifest['site_merk'],
        ]);

        /*
         * **Meteen teruglezen.** Dit is de nul uit 3-2-1-1-0: een back-up
         * die nooit is teruggelezen is een aanname. Lukt het niet, dan
         * gooien we hem weg -- een stil kapot bestand in de lijst is
         * erger dan geen bestand, want daar reken je op.
         */
        $uitkomst = $this->lezer->controleer($backup);

        if (! $uitkomst->geslaagd) {
            Storage::disk('local')->delete($bestand);
            $backup->delete();

            throw new RuntimeException($uitkomst->melding ?? 'De back-up kon niet worden teruggelezen.');
        }

        $backup->forceFill(['gecontroleerd_op' => Carbon::now()])->save();

        return $backup;
    }

    /**
     * Alle rijen, plus de beelden waar ze naar wijzen.
     *
     * @return array{0: array<string, array<int, array<string, mixed>>>, 1: array<string, string>}
     */
    private function leesAlles(): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $data */
        $data = [];

        /** @var array<string, string> $beelden hash => het pad op de publieke schijf */
        $beelden = [];

        DB::transaction(function () use (&$data, &$beelden): void {
            $publiek = Storage::disk('public');

            foreach ($this->register->bestaandeTabellen() as $tabel) {
                $rijen = DB::table($tabel)->orderBy('id')->get()
                    ->map(static fn (object $rij): array => (array) $rij)
                    ->all();

                $data[$tabel] = $rijen;

                foreach ($this->register->beeldkolommen($tabel) as $kolom) {
                    foreach ($rijen as $rij) {
                        $pad = $rij[$kolom] ?? null;

                        if (! is_string($pad) || $pad === '' || ! $publiek->exists($pad)) {
                            continue;
                        }

                        $hash = hash_file('sha256', $publiek->path($pad));

                        if (is_string($hash)) {
                            $beelden[$hash] = $pad;
                        }
                    }
                }
            }
        });

        return [$data, $beelden];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $beelden
     */
    private function schrijfZip(string $bestand, array $manifest, string $json, array $beelden): void
    {
        $schijf = Storage::disk('local');
        $schijf->makeDirectory(Backup::MAP);

        $pad = $schijf->path($bestand);
        $zip = new ZipArchive;

        if ($zip->open($pad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Het back-upbestand kon niet worden aangemaakt.');
        }

        $zip->addFromString('manifest.json', (string) json_encode(
            $manifest,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ));

        $zip->addFromString('data.json', $json);

        $publiek = Storage::disk('public');

        foreach ($beelden as $hash => $bronpad) {
            /*
             * De naam in de zip is de hash en niet het oorspronkelijke
             * pad. Dat scheelt dubbele bestanden als twee rijen hetzelfde
             * beeld gebruiken, en het maakt het uitpakken van een
             * geüploade zip veilig: er staat geen pad in dat we zouden
             * kunnen volgen.
             */
            $zip->addFile($publiek->path($bronpad), 'media/'.$hash.'.webp');
        }

        if (! $zip->close()) {
            throw new RuntimeException('Het back-upbestand kon niet worden afgesloten.');
        }
    }

    /**
     * De beelden ook los neerzetten, gedeeld door alle back-ups.
     *
     * @param  array<string, string>  $beelden
     */
    private function bewaarInDeBeeldmap(array $beelden): void
    {
        $schijf = Storage::disk('local');
        $publiek = Storage::disk('public');

        foreach ($beelden as $hash => $bronpad) {
            $doel = Backup::BEELDMAP.'/'.$hash.'.webp';

            // Staat hij er al, dan is hij per definitie gelijk: de naam
            // ís de inhoud.
            if ($schijf->exists($doel)) {
                continue;
            }

            $inhoud = $publiek->get($bronpad);

            if (is_string($inhoud)) {
                $schijf->put($doel, $inhoud);
            }
        }
    }

    /**
     * Een naam die nog niet bestaat.
     *
     * Op de minuut nauwkeurig, want twee back-ups in dezelfde minuut is
     * mogelijk -- en de naam moet uniek zijn omdat de eigenaar hem moet
     * overtypen om terug te zetten.
     */
    private function verseNaam(): string
    {
        $basis = Carbon::now()->format('Y-m-d-Hi');
        $naam = $basis;
        $nummer = 2;

        while (Backup::query()->where('naam', $naam)->exists()) {
            $naam = $basis.'-'.$nummer;
            $nummer++;
        }

        return $naam;
    }

    /**
     * De laatst gedraaide migratie.
     *
     * Hiermee is achteraf te zien of een back-up nog bij het huidige
     * schema past. Een los versienummer bijhouden zou een tweede ding
     * zijn dat iemand moet onthouden; dit loopt vanzelf mee.
     */
    private function schemaMerk(): ?string
    {
        $laatste = DB::table('migrations')->orderByDesc('id')->value('migration');

        return is_string($laatste) ? $laatste : null;
    }

    /**
     * Een vingerafdruk van déze installatie.
     *
     * Niet de `APP_KEY` zelf, vanzelfsprekend -- een hash ervan samen met
     * het adres. Zo is een back-up van een andere site te herkennen
     * zonder dat er een geheim in het bestand staat.
     */
    private function siteMerk(): string
    {
        return hash('sha256', (string) config('app.key').'|'.(string) config('app.url'));
    }
}
