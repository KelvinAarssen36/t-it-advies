<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Een geüpload beeldmerk klaarmaken om op de website te zetten.
 *
 * Wat de klant aanlevert is wat hij toevallig heeft: een schermafdruk van
 * 3000 pixels breed, een liggende banner, of een keurig vierkantje. Wat de
 * site nodig heeft is altijd hetzelfde: **een vierkant plaatje van 256 bij
 * 256 dat je overal zonder uitzondering kunt tonen.** Dat verschil
 * overbruggen we hier, en niet met CSS op de pagina -- dan downloadt elke
 * bezoeker alsnog drie megabyte, en moet elk scherm apart bedenken of dit
 * een logo of een foto is.
 *
 * **Hoe het beeld in het vierkant komt, bepaalt de klant** met de
 * uitsnede: zoom, verschuiving en een witte ondergrond. Zie
 * App\Support\Media\Uitsnede voor waarom dat een keuze is en geen regel.
 * Omdat de achtergrond wordt ingebakken, is er daarna niets bijzonders
 * meer aan zo'n bestand.
 *
 * **Vier dingen die in productie mis kunnen gaan en hier zijn afgevangen:**
 *
 * 1. **De bestandsnaam van de klant gaat niet mee.** De naam wordt hier
 *    verzonnen. Een naam als `../../.env` of `foto.php.jpg` hoort nooit op
 *    de schijf terecht te komen, ook niet als de webserver er niets mee
 *    zou doen.
 * 2. **Wat eruit komt is altijd een plaatje.** Het bestand wordt door GD
 *    heen gehaald en opnieuw opgebouwd uit de pixels. Zit er iets anders
 *    in dan een afbeelding, dan valt het er hier uit -- ook als de
 *    extensie en het mediatype er goed uitzagen.
 * 3. **Het geheugen loopt niet vol.** De validatie begrenst de afmetingen,
 *    want GD zet een afbeelding uitgepakt in het geheugen: een plaatje van
 *    20.000 bij 20.000 is op schijf misschien een megabyte, maar in het
 *    geheugen ruim een gigabyte. Zie ExperienceRequest.
 * 4. **Zonder GD werkt het nog steeds.** Draait de server zonder de
 *    GD-extensie, dan wordt het bestand opgeslagen zoals het binnenkwam.
 *    Een groot logo is vervelend; een beheerscherm dat stukgaat op een
 *    ontbrekende extensie is erger.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class Logo
{
    /**
     * De zijde van het vierkant, in pixels.
     *
     * Het rondje op de tijdlijn is veertig pixels, de grote bel zo'n
     * honderdveertig. Tweehonderdzesenvijftig is daar ruim boven, zodat
     * het ook op een scherm met dubbele pixeldichtheid scherp blijft.
     *
     * **Dit getal staat ook in de browser**, in LogoKiezer.vue: het
     * voorbeeld daar rekent met dezelfde verhoudingen, anders staat het
     * beeld straks nét anders dan wat de klant zag.
     */
    public const MAAT = 256;

    /**
     * Het logo opslaan en het pad teruggeven, relatief aan de schijf.
     *
     * @return string bijvoorbeeld `ervaring/k3n8....webp`
     */
    public function bewaar(
        UploadedFile $bestand,
        string $schijf,
        string $map,
        ?Uitsnede $uitsnede = null,
    ): string {
        $verkleind = $this->verklein($bestand, $uitsnede ?? Uitsnede::standaard());

        if ($verkleind === null) {
            /*
             * Terugvalweg: opslaan zoals het binnenkwam, maar wél met een
             * verzonnen naam. `store()` doet dat zelf -- het bewaart de
             * naam van de klant niet.
             */
            return (string) $bestand->store($map, $schijf);
        }

        [$inhoud, $extensie] = $verkleind;

        $pad = $map.'/'.Str::random(40).'.'.$extensie;

        Storage::disk($schijf)->put($pad, $inhoud);

        return $pad;
    }

    /**
     * Het beeld in het vierkant tekenen, volgens de uitsnede.
     *
     * De rekensom is met opzet dezelfde als die van het voorbeeld in de
     * browser: bij zoom 1 past de **langste** zijde precies, en `x` en `y`
     * verschuiven in halve vierkanten. Lopen die twee uiteen, dan krijgt
     * de klant iets anders te zien dan hij heeft ingesteld -- en dat merk
     * je pas op de website.
     *
     * @return array{string, string}|null de bytes en de extensie, of null
     *                                    als er geen GD is
     */
    private function verklein(UploadedFile $bestand, Uitsnede $uitsnede): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $bron = @imagecreatefromstring((string) file_get_contents($bestand->getRealPath()));

        if ($bron === false) {
            return null;
        }

        $breedte = imagesx($bron);
        $hoogte = imagesy($bron);

        // Bij zoom 1 past de langste zijde precies in het vierkant.
        $schaal = (self::MAAT / max($breedte, $hoogte)) * $uitsnede->zoom;

        $nieuweBreedte = max(1, (int) round($breedte * $schaal));
        $nieuweHoogte = max(1, (int) round($hoogte * $schaal));

        $doel = imagecreatetruecolor(self::MAAT, self::MAAT);

        $this->vulAchtergrond($doel, $uitsnede->plaat);

        imagecopyresampled(
            $doel,
            $bron,
            // Gecentreerd, plus de verschuiving van de klant.
            (int) round(
                (self::MAAT - $nieuweBreedte) / 2 + $uitsnede->x * self::MAAT / 2,
            ),
            (int) round(
                (self::MAAT - $nieuweHoogte) / 2 + $uitsnede->y * self::MAAT / 2,
            ),
            0,
            0,
            $nieuweBreedte,
            $nieuweHoogte,
            $breedte,
            $hoogte,
        );

        $uitkomst = $this->naarBytes($doel);

        imagedestroy($bron);
        imagedestroy($doel);

        return $uitkomst;
    }

    /**
     * De ondergrond: wit, of doorzichtig.
     *
     * Doorzichtigheid moet vóór het tekenen worden ingesteld, en met
     * `imagealphablending(false)` -- anders mengt GD het doorzichtige deel
     * van het logo met wat eronder ligt en wordt het alsnog zwart. Dat is
     * precies het soort logo dat een bedrijf aanlevert.
     *
     * @param  \GdImage  $doel
     */
    private function vulAchtergrond($doel, bool $plaat): void
    {
        imagealphablending($doel, false);
        imagesavealpha($doel, true);

        $kleur = $plaat
            ? imagecolorallocatealpha($doel, 255, 255, 255, 0)
            : imagecolorallocatealpha($doel, 0, 0, 0, 127);

        imagefill($doel, 0, 0, (int) $kleur);

        // Vanaf hier wél mengen, zodat een half doorzichtig logo netjes
        // over de ondergrond valt in plaats van hem weg te snijden.
        imagealphablending($doel, true);
    }

    /**
     * De afbeelding als bytes, het liefst als WebP.
     *
     * WebP is een stuk kleiner dan PNG bij hetzelfde beeld, maar de
     * GD-versie van een server hoeft het niet te kunnen. Dan wordt het PNG:
     * groter, maar het werkt overal en het houdt doorzichtigheid.
     *
     * @param  \GdImage  $beeld
     * @return array{string, string}
     */
    private function naarBytes($beeld): array
    {
        // Zonder dit gaat de doorzichtigheid verloren bij het wegschrijven,
        // ook al is hij tijdens het tekenen wel bewaard.
        imagesavealpha($beeld, true);

        if (function_exists('imagewebp')) {
            ob_start();
            imagewebp($beeld, null, 82);

            return [(string) ob_get_clean(), 'webp'];
        }

        ob_start();
        imagepng($beeld, null, 6);

        return [(string) ob_get_clean(), 'png'];
    }
}
