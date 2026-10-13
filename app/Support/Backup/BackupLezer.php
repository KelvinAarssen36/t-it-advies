<?php

namespace App\Support\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Opent een back-upbestand en kijkt of het nog deugt.
 *
 * **Dit is de nul uit 3-2-1-1-0.** Een back-up die nooit is teruggelezen
 * is een aanname. Deze klasse is het verschil tussen "er staat een bestand"
 * en "ik weet dat dit bestand werkt".
 *
 * Wat er wordt nagegaan:
 *
 * 1. de zip gaat open;
 * 2. er zit een `manifest.json` in, met een formaatversie die we kennen;
 * 3. de SHA-256 van `data.json` klopt met wat het manifest zegt;
 * 4. elk beeld dat het manifest noemt zit erin, en de inhoud hasht naar
 *    de naam waaronder het staat;
 * 5. de aantallen per tabel kloppen met het manifest.
 *
 * En met `metProef` erbij: de back-up wordt echt teruggezet binnen een
 * transactie die daarna wordt teruggedraaid. Dat is de enige controle die
 * bewijst dat het terugzetten óók werkt; zie BackupTerugzetter.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupLezer
{
    public function __construct(private readonly Inhoudsregister $register) {}

    /**
     * Het manifest uit een bestand lezen.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function manifest(string $pad): array
    {
        $zip = new ZipArchive;

        if ($zip->open($pad, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('Dit bestand is geen leesbare back-up.'));
        }

        $ruw = $zip->getFromName('manifest.json');
        $zip->close();

        if (! is_string($ruw)) {
            throw new RuntimeException(__('Er zit geen manifest in dit bestand.'));
        }

        $manifest = json_decode($ruw, true);

        if (! is_array($manifest)) {
            throw new RuntimeException(__('Het manifest in dit bestand is onleesbaar.'));
        }

        if (($manifest['formaat'] ?? null) !== BackupMaker::FORMAAT) {
            throw new RuntimeException(__('Dit bestand komt uit een andere versie van het portaal.'));
        }

        return $manifest;
    }

    /**
     * De rijen uit een bestand, met de checksum nagerekend.
     *
     * @return array<string, array<int, array<string, mixed>>>
     *
     * @throws RuntimeException
     */
    public function data(string $pad, string $verwachteChecksum): array
    {
        $zip = new ZipArchive;

        if ($zip->open($pad, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('Dit bestand is geen leesbare back-up.'));
        }

        $ruw = $zip->getFromName('data.json');
        $zip->close();

        if (! is_string($ruw)) {
            throw new RuntimeException(__('Er zitten geen gegevens in dit bestand.'));
        }

        if (! hash_equals($verwachteChecksum, hash('sha256', $ruw))) {
            throw new RuntimeException(__('Dit bestand is beschadigd: de inhoud komt niet overeen met de controlecode.'));
        }

        $data = json_decode($ruw, true);

        if (! is_array($data)) {
            throw new RuntimeException(__('De gegevens in dit bestand zijn onleesbaar.'));
        }

        /** @var array<string, array<int, array<string, mixed>>> $data */
        return $data;
    }

    /**
     * De volledige controle.
     *
     * @param  bool  $metProef  ook echt terugzetten en terugdraaien
     */
    public function controleer(Backup $backup, bool $metProef = false): Controleuitkomst
    {
        $pad = $backup->pad();

        if ($pad === null) {
            return Controleuitkomst::fout(__('Het bestand van deze back-up staat er niet meer.'));
        }

        try {
            $manifest = $this->manifest($pad);
            $data = $this->data($pad, (string) ($manifest['checksum'] ?? ''));
        } catch (RuntimeException $fout) {
            return Controleuitkomst::fout($fout->getMessage());
        }

        $opmerkingen = [];

        // De aantallen per tabel tegen wat het manifest belooft.
        /** @var array<string, int> $beloofd */
        $beloofd = is_array($manifest['aantallen'] ?? null) ? $manifest['aantallen'] : [];

        foreach ($beloofd as $tabel => $aantal) {
            $werkelijk = count($data[$tabel] ?? []);

            if ($werkelijk !== $aantal) {
                return Controleuitkomst::fout(__(
                    'Dit bestand is onvolledig: :tabel zou :beloofd rijen moeten hebben maar heeft er :werkelijk.',
                    ['tabel' => $tabel, 'beloofd' => $aantal, 'werkelijk' => $werkelijk],
                ));
            }
        }

        $beeldfout = $this->controleerBeelden($pad, $manifest);

        if ($beeldfout !== null) {
            return Controleuitkomst::fout($beeldfout);
        }

        if (! $metProef) {
            return Controleuitkomst::goed($opmerkingen);
        }

        return $this->proef($data, $opmerkingen);
    }

    /**
     * Elk beeld zit erin en hasht naar zijn eigen naam.
     *
     * Die tweede controle is de echte: de bestandsnaam ís de hash van de
     * inhoud, dus een beeld dat onderweg is beschadigd valt hier door de
     * mand.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function controleerBeelden(string $pad, array $manifest): ?string
    {
        /** @var array<string, string> $beelden */
        $beelden = is_array($manifest['beelden'] ?? null) ? $manifest['beelden'] : [];

        if ($beelden === []) {
            return null;
        }

        $zip = new ZipArchive;

        if ($zip->open($pad, ZipArchive::RDONLY) !== true) {
            return __('Dit bestand is geen leesbare back-up.');
        }

        try {
            foreach (array_keys($beelden) as $hash) {
                $inhoud = $zip->getFromName('media/'.$hash.'.webp');

                if (! is_string($inhoud)) {
                    return __('Er ontbreekt een afbeelding in dit bestand.');
                }

                if (! hash_equals((string) $hash, hash('sha256', $inhoud))) {
                    return __('Een afbeelding in dit bestand is beschadigd.');
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    /**
     * De proefterugzetting.
     *
     * Echt terugzetten, en daarna alles terugdraaien. Dat is de enige
     * manier om te wéten dat het werkt -- een controle op de vorm van het
     * bestand zegt niets over of de rijen er ook in passen.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $data
     * @param  array<int, string>  $opmerkingen
     */
    private function proef(array $data, array $opmerkingen): Controleuitkomst
    {
        try {
            // Zonder beeldkaart: een proef raakt geen bestanden aan.
            app(BackupTerugzetter::class)->zetTerug($data, proef: true);
        } catch (Throwable $fout) {
            return Controleuitkomst::fout(__(
                'De proef is mislukt: :reden',
                ['reden' => $fout->getMessage()],
            ));
        }

        return Controleuitkomst::goed($opmerkingen, proefGedraaid: true);
    }

    /**
     * De beelden uit een bestand halen en in de gedeelde map zetten.
     *
     * Gebruikt bij een upload: daar staan de beelden alleen in de zip en
     * nog niet in de pool.
     *
     * **Nooit een pad uit de zip volgen.** De naam wordt hier opnieuw
     * opgebouwd uit de hash, dus een zip met `../../.env` erin kan niets
     * raken wat niet bedoeld is.
     *
     * @param  array<string, mixed>  $manifest
     * @return int het aantal beelden dat erbij kwam
     */
    public function pakBeeldenUit(string $pad, array $manifest): int
    {
        /** @var array<string, string> $beelden */
        $beelden = is_array($manifest['beelden'] ?? null) ? $manifest['beelden'] : [];

        if ($beelden === []) {
            return 0;
        }

        $zip = new ZipArchive;

        if ($zip->open($pad, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('Dit bestand is geen leesbare back-up.'));
        }

        $schijf = Storage::disk('local');
        $aantal = 0;

        try {
            foreach (array_keys($beelden) as $hash) {
                if (preg_match('/^[a-f0-9]{64}$/', (string) $hash) !== 1) {
                    throw new RuntimeException(__('Er staat een afbeelding met een onverwachte naam in dit bestand.'));
                }

                $inhoud = $zip->getFromName('media/'.$hash.'.webp');

                if (! is_string($inhoud) || ! hash_equals((string) $hash, hash('sha256', $inhoud))) {
                    throw new RuntimeException(__('Een afbeelding in dit bestand is beschadigd.'));
                }

                if ($this->isGeenAfbeelding($inhoud)) {
                    throw new RuntimeException(__('Er staat een bestand in dit back-upbestand dat geen afbeelding is.'));
                }

                $doel = Backup::BEELDMAP.'/'.$hash.'.webp';

                if (! $schijf->exists($doel)) {
                    $schijf->put($doel, $inhoud);
                    $aantal++;
                }
            }
        } finally {
            $zip->close();
        }

        return $aantal;
    }

    /**
     * Is dit echt een afbeelding?
     *
     * `getimagesizefromstring` kijkt naar de inhoud en niet naar de naam.
     * Zonder deze controle zou een geüploade zip een PHP-bestand kunnen
     * bevatten dat wij braaf wegschrijven -- en dan is de vraag alleen
     * nog of iemand het ooit kan aanroepen.
     */
    private function isGeenAfbeelding(string $inhoud): bool
    {
        $maten = @getimagesizefromstring($inhoud);

        return $maten === false;
    }

    public function register(): Inhoudsregister
    {
        return $this->register;
    }
}
