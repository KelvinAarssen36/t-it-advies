<?php

namespace App\Support\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Houdt de lijst met back-ups binnen zijn grenzen.
 *
 * **Eigen back-ups worden nooit stilletjes weggegooid.** Dat deed deze
 * klasse eerst wel: bij de zesde verdween de oudste vanzelf. Dat is
 * precies het soort hulpvaardigheid waar je spijt van krijgt -- je maakt
 * even een back-up voor de zekerheid en raakt daarmee de back-up kwijt
 * die je eigenlijk wilde bewaren.
 *
 * Nu is de lijst vol en kiest de eigenaar zelf welke mag verdwijnen; zie
 * `BackupController::store()`.
 *
 * **De veiligheidskopieën zijn de uitzondering.** Die ontstaan midden in
 * een terugzetting, en daar hoort geen vraag doorheen te komen. Ze ruimen
 * zichzelf op en tellen niet mee in het maximum van de eigenaar.
 *
 * ## De beelden
 *
 * Die staan in één gedeelde map onder hun eigen hash. Een beeld mag pas
 * weg als **geen enkele overgebleven back-up** er nog naar wijst --
 * anders maak je met het opruimen van de oudste de nieuwste kapot.
 *
 * Zie docs/operations/back-ups.md.
 */
class Opruimer
{
    public function __construct(private readonly BackupLezer $lezer) {}

    /**
     * De veiligheidskopieën bijhouden, en daarna de losse beelden.
     *
     * @return int het aantal verwijderde back-ups
     */
    public function ruimOp(): int
    {
        $teveel = Backup::query()
            ->vanSoort(Backup::SOORT_AUTOMATISCH)
            ->opruimbaar()
            ->orderByDesc('vastgelegd_op')
            ->orderByDesc('id')
            ->skip(Backup::MAXIMUM_AUTOMATISCH)
            ->take(100)
            ->get();

        foreach ($teveel as $backup) {
            Storage::disk('local')->delete($backup->bestand);
            $backup->delete();
        }

        $this->ruimBeeldenOp();

        return $teveel->count();
    }

    /**
     * Eén back-up weggooien om plaats te maken.
     *
     * Alleen eigen back-ups, en alleen als ze niet zijn vastgezet. Zou
     * dit ook een veiligheidskopie kunnen opruimen, dan kon de eigenaar
     * zijn weg terug weggeven zonder het te merken.
     *
     * @throws RuntimeException
     */
    public function maakPlaats(Backup $backup): void
    {
        if ($backup->vastgezet) {
            throw new RuntimeException(
                __('Deze back-up staat vast. Laat hem eerst los als je hem toch wilt weggooien.'),
            );
        }

        if ($backup->soort === Backup::SOORT_AUTOMATISCH) {
            throw new RuntimeException(
                __('Dit is een veiligheidskopie; die ruimt zichzelf op en telt niet mee.'),
            );
        }

        Storage::disk('local')->delete($backup->bestand);
        $backup->delete();

        $this->ruimBeeldenOp();
    }

    /**
     * Beelden weggooien waar geen enkele back-up meer naar wijst.
     */
    public function ruimBeeldenOp(): int
    {
        $schijf = Storage::disk('local');

        if (! $schijf->exists(Backup::BEELDMAP)) {
            return 0;
        }

        $nogNodig = [];

        foreach (Backup::query()->get() as $backup) {
            $pad = $backup->pad();

            if ($pad === null) {
                continue;
            }

            try {
                $manifest = $this->lezer->manifest($pad);
            } catch (RuntimeException) {
                /*
                 * Een bestand dat we niet kunnen lezen: dan weten we niet
                 * welke beelden het nodig heeft. Dan raken we de beelden
                 * met rust. Liever een los bestand te veel dan een
                 * back-up stilletjes uitkleden.
                 */
                return 0;
            }

            foreach (array_keys((array) ($manifest['beelden'] ?? [])) as $hash) {
                $nogNodig[(string) $hash] = true;
            }
        }

        $weg = 0;

        foreach ($schijf->files(Backup::BEELDMAP) as $bestand) {
            $hash = pathinfo($bestand, PATHINFO_FILENAME);

            if (! isset($nogNodig[$hash])) {
                $schijf->delete($bestand);
                $weg++;
            }
        }

        return $weg;
    }

    /** Hoeveel ruimte alle back-ups samen innemen, in bytes. */
    public function ruimtegebruik(): int
    {
        $schijf = Storage::disk('local');
        $totaal = 0;

        foreach ([Backup::MAP, Backup::BEELDMAP] as $map) {
            if (! $schijf->exists($map)) {
                continue;
            }

            foreach ($schijf->files($map) as $bestand) {
                $totaal += (int) $schijf->size($bestand);
            }
        }

        return $totaal;
    }
}
