<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupUploadRequest;
use App\Models\Backup;
use App\Models\User;
use App\Support\Backup\BackupLezer;
use App\Support\Backup\BackupMaker;
use App\Support\Backup\BackupTerugzetter;
use App\Support\Backup\Inhoudsregister;
use App\Support\Backup\Opruimer;
use App\Support\Backup\Verschilrapport;
use App\Support\Security\Authenticator;
use App\Support\Security\SecurityLogger;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Beheer → Back-ups.
 *
 * De eigenaar legt hier de inhoud van zijn website vast en kan die
 * terugzetten. **Niet zijn account en niet de berichten van bezoekers**;
 * zie App\Support\Backup\Inhoudsregister voor waarom.
 *
 * ## De sloten op het terugzetten
 *
 * Dit is de meest ingrijpende knop van de applicatie. Vier drempels:
 *
 * 1. `can:manage portal` op de route;
 * 2. een verse authenticator-code, **in het verzoek zelf**;
 * 3. de naam van de back-up overtypen;
 * 4. een automatische veiligheidskopie, vlak ervoor.
 *
 * Die vierde is de belangrijkste, want die werkt ook als de andere drie
 * zijn genegeerd.
 *
 * **De code zit in het verzoek en niet in `2fa.confirm`.** Die middleware
 * kan een POST niet onthouden: ze stuurt je naar het codescherm en gooit
 * het verzoek weg. Bij een formulier met invoer is dat onbruikbaar; zie
 * App\Support\Security\Authenticator.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupMaker $maker,
        private readonly BackupLezer $lezer,
        private readonly Opruimer $opruimer,
        private readonly Verschilrapport $verschil,
        private readonly Inhoudsregister $register,
    ) {}

    public function index(Request $request): Response
    {
        /*
         * Op `vastgelegd_op` en niet op `created_at`: bij een geüpload
         * bestand is de rij van vandaag en de inhoud van veel eerder.
         * Sorteren op het verkeerde veld zet het oudste bestand
         * bovenaan.
         */
        $backups = Backup::query()
            ->orderByDesc('vastgelegd_op')
            ->orderByDesc('id')
            ->get();

        /*
         * De nieuwste van de eigenaar zelf. Een veiligheidskopie die
         * toevallig later is gemaakt verdient dat merkje niet: die is
         * van het portaal en niet van hem.
         */
        $nieuwste = $backups->first(fn (Backup $b): bool => $b->isEigen());

        /*
         * De oudste die weg zouden mogen. Dat zijn er meer dan één zodra
         * er te veel staan -- dan moet de eigenaar kunnen zien wélke
         * twee hij kwijt moet.
         *
         * Niet zomaar de onderste regels: vastgezette back-ups en
         * veiligheidskopieën komen niet in aanmerking, en juist daar zou
         * een label "oudste" hem de verkeerde kant op sturen.
         */
        $oudsteIds = $backups
            ->filter(fn (Backup $b): bool => $b->isEigen() && ! $b->vastgezet)
            ->sortBy('vastgelegd_op')
            ->take(Backup::aantalOudsteTeTonen())
            ->pluck('id')
            ->all();

        /*
         * Twee lijsten en niet één. De eigen back-ups zijn waar het om
         * gaat; de veiligheidskopieën staan eronder in een eigen blok.
         *
         * Stonden ze door elkaar -- zoals eerst -- dan telt de eigenaar
         * zeven regels terwijl er vijf mogen staan, en lijkt er iets
         * stuk. Het getal klopte wel, de lijst vertelde iets anders.
         */
        $eigen = $backups->filter(fn (Backup $b): bool => $b->isEigen());
        $kopieen = $backups->reject(fn (Backup $b): bool => $b->isEigen());

        $naarHetScherm = fn (Backup $b): array => $b->voorHetScherm(
            nieuwste: $nieuwste !== null && $b->is($nieuwste),
            oudste: in_array($b->id, $oudsteIds, true),
        );

        return Inertia::render('admin/Backups', [
            'backups' => $eigen->map($naarHetScherm)->values()->all(),
            'kopieen' => $kopieen->map($naarHetScherm)->values()->all(),

            'cijfers' => [
                'ruimte' => Backup::prettigeGrootte($this->opruimer->ruimtegebruik()),
                'maximumEigen' => Backup::MAXIMUM_EIGEN,
                'maximumVast' => Backup::MAXIMUM_VAST,
                'eigen' => Backup::eigenInGebruik(),
                'vol' => Backup::isVol(),

                /*
                 * Hoeveel er te veel staan. Boven nul opent het scherm
                 * meteen het opruimvenster; zie Backups.vue. Stil laten
                 * staan zou betekenen dat de eigenaar een grens leest
                 * die niet geldt.
                 */
                'teveel' => Backup::teveel(),
                'maximumAutomatisch' => Backup::MAXIMUM_AUTOMATISCH,
                'vastgezet' => $backups->where('vastgezet', true)->count(),

                /*
                 * De twee dingen die de eigenaar moet weten zonder erom
                 * te vragen: staat er nog niets, en staat wat er is
                 * alleen op deze server.
                 */
                'nooitGedownload' => $nieuwste !== null && $nieuwste->gedownload_op === null,
                'oud' => $nieuwste !== null
                    && $nieuwste->created_at->diffInDays() >= Backup::OUD_NA_DAGEN,
                'oudNaDagen' => Backup::OUD_NA_DAGEN,
            ],

            /*
             * Het verschil van één back-up, en alleen als ernaar wordt
             * gevraagd. Het scherm haalt dit op met een gedeeltelijke
             * herlading zodra het terugzetvenster opengaat.
             *
             * Niet voor alle back-ups tegelijk: dat zijn negentien
             * tellingen per back-up, en daar wordt de lijst traag van
             * zonder dat iemand de cijfers ziet.
             */
            'verschil' => Inertia::optional(function () use ($request, $backups) {
                $gevraagd = $request->integer('verschil');

                $backup = $backups->firstWhere('id', $gevraagd);

                return $backup === null ? null : $this->verschil->voor($backup);
            }),
        ]);
    }

    /**
     * Een nieuwe back-up maken.
     *
     * **Is de lijst vol, dan kiest de eigenaar zelf wat er weg mag.** Dat
     * ging eerst vanzelf: bij de zesde verdween de oudste. Dat is precies
     * het soort hulpvaardigheid waar je spijt van krijgt -- je maakt even
     * een back-up voor de zekerheid en raakt daarmee de back-up kwijt die
     * je wilde bewaren.
     */
    public function store(
        Request $request,
        Authenticator $authenticator,
        SecurityLogger $logboek,
    ): RedirectResponse {
        $this->maakPlaatsIndienNodig($request, $authenticator);

        try {
            $backup = $this->maker->maak();
        } catch (RuntimeException $fout) {
            Toast::fout(
                __('De back-up is niet gelukt.'),
                $fout->getMessage(),
            );

            return back();
        }

        $this->opruimer->ruimOp();

        $logboek->success(SecurityEventType::BackupGemaakt, auth()->user(), [
            'naam' => $backup->naam,
        ]);

        Toast::aangemaakt(
            __('De back-up :naam staat klaar.', ['naam' => $backup->naam]),
            (string) __('Hij is meteen teruggelezen en in orde bevonden. Download hem en bewaar hem ergens anders dan op deze server.'),
        );

        return back();
    }

    /**
     * Plaats maken als de lijst vol is.
     *
     * De eigenaar stuurt `vervang` mee: de back-up die hij heeft gekozen
     * om weg te gooien. Zonder die keuze gaat er niets door -- dat is het
     * verschil met hoe het eerst werkte, toen de oudste vanzelf verdween.
     *
     * Het scherm weet zelf ook dat de lijst vol is en vraagt het vooraf;
     * deze controle staat er voor het geval dat niet gebeurt. Een grens
     * die alleen in de browser bestaat is geen grens.
     */
    private function maakPlaatsIndienNodig(Request $request, Authenticator $authenticator): void
    {
        if (! Backup::isVol()) {
            return;
        }

        /** @var array<int, int> $gekozen */
        $gekozen = array_filter(array_map('intval', (array) $request->input('vervang', [])));

        if ($gekozen === []) {
            throw ValidationException::withMessages([
                'vervang' => __('Je hebt er al :aantal. Kies er eerst een die weg mag.', [
                    'aantal' => Backup::MAXIMUM_EIGEN,
                ]),
            ]);
        }

        /*
         * **Ook hier de authenticator-code.** Er verdwijnt een back-up,
         * en dat hoort nergens zonder die code te kunnen -- anders is
         * "een nieuwe maken" een sluiproute om de beveiliging op
         * verwijderen te omzeilen.
         */
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $authenticator->bevestig($gebruiker, (string) $request->input('code', ''));

        $this->gooiWeg($gekozen);
    }

    /**
     * De gekozen back-ups weggooien.
     *
     * @param  array<int, int>  $ids
     *
     * @throws ValidationException
     */
    private function gooiWeg(array $ids): void
    {
        foreach ($ids as $id) {
            $backup = Backup::query()->find($id);

            if ($backup === null) {
                throw ValidationException::withMessages([
                    'vervang' => __('Die back-up bestaat niet meer.'),
                ]);
            }

            try {
                $this->opruimer->maakPlaats($backup);
            } catch (RuntimeException $fout) {
                throw ValidationException::withMessages([
                    'vervang' => $fout->getMessage(),
                ]);
            }
        }
    }

    /**
     * Opruimen omdat er te veel staan.
     *
     * Een eigen ingang naast `maakPlaatsIndienNodig()`, want hier komt er
     * geen nieuwe back-up achteraan: de eigenaar brengt alleen zijn lijst
     * terug binnen de grens.
     */
    public function opruimen(
        Request $request,
        Authenticator $authenticator,
        SecurityLogger $logboek,
    ): RedirectResponse {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'code' => ['required', 'string'],
        ]);

        /** @var array<int, int> $ids */
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids'))));

        $nodig = Backup::teveel();

        if (count($ids) < $nodig) {
            throw ValidationException::withMessages([
                'ids' => __('Kies er :aantal om weg te gooien.', ['aantal' => $nodig]),
            ]);
        }

        $authenticator->bevestig($gebruiker, (string) $request->input('code', ''));

        $this->gooiWeg($ids);

        foreach ($ids as $id) {
            $logboek->success(SecurityEventType::BackupVerwijderd, $gebruiker, [
                'id' => $id,
                'reden' => 'te-veel',
            ]);
        }

        Toast::verwijderd(
            __(':aantal back-ups zijn weggegooid.', ['aantal' => count($ids)]),
            (string) __('Je lijst past weer binnen de grens.'),
        );

        return back();
    }

    /**
     * De teruglees-test: de nul uit 3-2-1-1-0.
     *
     * Zet de back-up echt terug binnen een transactie en draait die
     * daarna terug. Er verandert niets, en achteraf weet je dat dit
     * bestand het doet.
     */
    public function controleer(Backup $backup, SecurityLogger $logboek): RedirectResponse
    {
        $uitkomst = $this->lezer->controleer($backup, metProef: true);

        if (! $uitkomst->geslaagd) {
            $logboek->failure(SecurityEventType::BackupGecontroleerd, auth()->user(), [
                'naam' => $backup->naam,
                'reden' => $uitkomst->melding,
            ]);

            Toast::fout(
                __('Deze back-up is niet in orde.'),
                (string) $uitkomst->melding,
            );

            return back();
        }

        $backup->forceFill(['gecontroleerd_op' => Carbon::now()])->save();

        $logboek->success(SecurityEventType::BackupGecontroleerd, auth()->user(), [
            'naam' => $backup->naam,
        ]);

        Toast::bijgewerkt(
            __('Deze back-up is in orde.'),
            (string) __('Hij is proefgewijs teruggezet en daarna teruggedraaid; aan je website is niets veranderd.'),
        );

        return back();
    }

    /** Terugzetten. De zwaarste knop van de applicatie. */
    public function restore(
        Request $request,
        Backup $backup,
        Authenticator $authenticator,
        BackupTerugzetter $terugzetter,
        SecurityLogger $logboek,
    ): RedirectResponse {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $velden = $request->validate([
            'code' => ['required', 'string'],
            'bevestigcode' => ['required', 'string'],
        ]);

        /*
         * Eerst de overgetypte cijfers en pas daarna de authenticator.
         * Andersom zou een vergissing in het overtypen een geldige code
         * verbruiken -- een TOTP-code werkt één keer, dus dan moet de
         * eigenaar wachten op de volgende.
         */
        if (! hash_equals((string) $backup->bevestigcode, trim((string) $velden['bevestigcode']))) {
            throw ValidationException::withMessages([
                'bevestigcode' => __('Deze cijfers kloppen niet. Typ de vier cijfers over die hierboven staan.'),
            ]);
        }

        $authenticator->bevestig($gebruiker, (string) $velden['code']);

        $pad = $backup->pad();

        if ($pad === null) {
            Toast::fout(__('Het bestand van deze back-up staat er niet meer.'));

            return back();
        }

        try {
            $manifest = $this->lezer->manifest($pad);
            $data = $this->lezer->data($pad, (string) ($manifest['checksum'] ?? ''));
        } catch (RuntimeException $fout) {
            Toast::fout(__('Deze back-up kan niet worden gelezen.'), $fout->getMessage());

            return back();
        }

        /*
         * De veiligheidskopie. **Vóór** het terugzetten, zodat ook een
         * verkeerde terugzetting ongedaan te maken is. Mislukt dit, dan
         * gaan we niet door: zonder weg terug is dit een handeling
         * waarvan je spijt kunt krijgen zonder herstel.
         */
        try {
            $veiligheidskopie = $this->maker->maak(Backup::SOORT_AUTOMATISCH);
        } catch (RuntimeException $fout) {
            Toast::fout(
                __('Er kon geen veiligheidskopie worden gemaakt, dus er is niets teruggezet.'),
                $fout->getMessage(),
            );

            return back();
        }

        /** @var array<string, string> $beelden */
        $beelden = is_array($manifest['beelden'] ?? null) ? $manifest['beelden'] : [];

        $meldingen = $terugzetter->metBeelden($beelden)->zetTerug($data);

        $this->opruimer->ruimOp();

        $logboek->success(SecurityEventType::BackupTeruggezet, $gebruiker, [
            'naam' => $backup->naam,
            'veiligheidskopie' => $veiligheidskopie->naam,
        ]);

        Toast::bijgewerkt(
            __('Je website staat weer zoals in :naam.', ['naam' => $backup->naam]),
            (string) __('De stand van vlak hiervoor is bewaard als :kopie, voor het geval dit toch niet was wat je wilde.', [
                'kopie' => $veiligheidskopie->naam,
            ]),
        );

        foreach ($meldingen as $melding) {
            Toast::melding(__('Let op bij het terugzetten'), $melding);
        }

        return back();
    }

    /**
     * Een eerder gedownload bestand weer naar binnen.
     *
     * **Het bestand wordt niet vertrouwd**, ook al heeft de eigenaar het
     * zelf gedownload. Het komt eerst in de lijst te staan als gewone
     * back-up; terugzetten is daarna dezelfde knop met dezelfde sloten.
     * Zo is er geen tweede, kortere weg naar het overschrijven van de
     * database.
     *
     * De volgorde is met opzet: eerst alles controleren, en pas als dat
     * lukt het bestand bewaren. Een afgekeurd bestand laat niets achter.
     */
    public function upload(
        BackupUploadRequest $request,
        Authenticator $authenticator,
        SecurityLogger $logboek,
    ): RedirectResponse {
        $this->maakPlaatsIndienNodig($request, $authenticator);

        $bestand = $request->file('bestand');

        if (! $bestand instanceof UploadedFile) {
            Toast::fout(__('Kies eerst een bestand.'));

            return back();
        }

        $tijdelijk = $bestand->getRealPath();

        if (! is_string($tijdelijk)) {
            Toast::fout(__('Dit bestand kon niet worden gelezen.'));

            return back();
        }

        try {
            $manifest = $this->lezer->manifest($tijdelijk);

            $data = $this->lezer->data($tijdelijk, (string) ($manifest['checksum'] ?? ''));

            /*
             * Elke tabel in het bestand moet er een zijn die wij kennen.
             * Een onbekende tabel is niet "iets wat we overslaan" maar
             * een reden om het hele bestand te weigeren: dan klopt er
             * iets niet aan de herkomst.
             */
            foreach (array_keys($data) as $tabel) {
                if (! $this->register->kentTabel((string) $tabel)) {
                    throw new RuntimeException(__('Dit bestand bevat een onderdeel dat wij niet kennen (:tabel).', [
                        'tabel' => (string) $tabel,
                    ]));
                }
            }

            // Pakt de beelden uit, mét de controle op hun hash en op of
            // het echt afbeeldingen zijn.
            $this->lezer->pakBeeldenUit($tijdelijk, $manifest);
        } catch (RuntimeException $fout) {
            $logboek->failure(SecurityEventType::BackupGemaakt, auth()->user(), [
                'reden' => 'upload-geweigerd',
            ]);

            Toast::fout(__('Dit bestand is geweigerd.'), $fout->getMessage());

            return back();
        }

        $naam = $this->vrijeNaam((string) ($manifest['naam'] ?? 'upload'));
        $doel = Backup::MAP.'/'.$naam.'.zip';

        Storage::disk('local')->put($doel, (string) file_get_contents($tijdelijk));

        $backup = Backup::query()->create([
            'naam' => $naam,

            /*
             * **De datum uit het bestand, niet die van nu.** Een back-up
             * van januari die je vandaag terugplaatst is geen nieuwe
             * back-up; hij hoort op zijn eigen plek in de lijst te
             * staan. Stond hier `now()`, dan kreeg het oudste bestand
             * het merkje "Nieuwste".
             */
            'vastgelegd_op' => $this->momentUit($manifest),

            'bevestigcode' => Backup::verseCode(),
            'soort' => Backup::SOORT_GEUPLOAD,
            'bestand' => $doel,
            'grootte' => (int) Storage::disk('local')->size($doel),
            'checksum' => (string) ($manifest['checksum'] ?? ''),
            'aantallen' => is_array($manifest['aantallen'] ?? null) ? $manifest['aantallen'] : [],
            'schema_merk' => is_string($manifest['schema_merk'] ?? null) ? $manifest['schema_merk'] : null,
            'site_merk' => is_string($manifest['site_merk'] ?? null) ? $manifest['site_merk'] : null,
            'gecontroleerd_op' => Carbon::now(),
            // Hij kwam van buiten, dus er staat al een kopie elders.
            'gedownload_op' => Carbon::now(),
        ]);

        $this->opruimer->ruimOp();

        $logboek->success(SecurityEventType::BackupGemaakt, auth()->user(), [
            'naam' => $backup->naam,
            'bron' => 'upload',
        ]);

        Toast::aangemaakt(
            __('Het bestand staat in je lijst als :naam.', ['naam' => $backup->naam]),
            (string) __('Er is nog niets teruggezet. Kijk eerst bij Terugzetten wat er zou veranderen.'),
        );

        return back();
    }

    /**
     * Wanneer de inhoud van een geüpload bestand is vastgelegd.
     *
     * Uit het manifest. Staat daar niets leesbaars in -- een bestand van
     * voor deze kolom bestond, bijvoorbeeld -- dan is "nu" de enige
     * schatting die we hebben.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function momentUit(array $manifest): Carbon
    {
        $gemaakt = $manifest['gemaakt'] ?? null;

        if (! is_string($gemaakt)) {
            return Carbon::now();
        }

        try {
            return Carbon::parse($gemaakt);
        } catch (\Throwable) {
            return Carbon::now();
        }
    }

    /** Een naam die nog niet in de lijst staat. */
    private function vrijeNaam(string $voorstel): string
    {
        $basis = mb_substr(preg_replace('/[^A-Za-z0-9-]/', '', $voorstel) ?: 'upload', 0, 40);
        $naam = $basis.'-geupload';
        $nummer = 2;

        while (Backup::query()->where('naam', $naam)->exists()) {
            $naam = $basis.'-geupload-'.$nummer;
            $nummer++;
        }

        return $naam;
    }

    /** Vastzetten of weer loslaten. */
    public function vastzetten(Request $request, Backup $backup): RedirectResponse
    {
        $aan = $request->boolean('vastgezet');

        if ($aan && Backup::query()->where('vastgezet', true)->where('id', '!=', $backup->id)->count() >= Backup::MAXIMUM_VAST) {
            throw ValidationException::withMessages([
                'vastgezet' => __('Je kunt er hoogstens :aantal vastzetten. Laat er eerst een los.', [
                    'aantal' => Backup::MAXIMUM_VAST,
                ]),
            ]);
        }

        $backup->forceFill(['vastgezet' => $aan])->save();

        Toast::bijgewerkt(
            $aan
                ? __('Deze back-up blijft staan.')
                : __('Deze back-up kan weer worden opgeruimd.'),
        );

        return back();
    }

    /** Downloaden -- en onthouden dat dat is gebeurd. */
    public function download(Backup $backup): BinaryFileResponse|RedirectResponse
    {
        $pad = $backup->pad();

        if ($pad === null) {
            Toast::fout(__('Het bestand van deze back-up staat er niet meer.'));

            return back();
        }

        /*
         * Het moment waarop deze back-up pas echt een back-up wordt: tot
         * nu toe stond hij op dezelfde schijf als de website die hij moet
         * beschermen.
         */
        $backup->forceFill(['gedownload_op' => Carbon::now()])->save();

        return response()->download($pad, 'website-'.$backup->naam.'.zip');
    }

    public function destroy(
        Request $request,
        Backup $backup,
        Authenticator $authenticator,
        SecurityLogger $logboek,
    ): RedirectResponse {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $velden = $request->validate(['code' => ['required', 'string']]);

        $authenticator->bevestig($gebruiker, (string) $velden['code']);

        $naam = $backup->naam;

        Storage::disk('local')->delete($backup->bestand);
        $backup->delete();

        $this->opruimer->ruimBeeldenOp();

        $logboek->success(SecurityEventType::BackupVerwijderd, $gebruiker, ['naam' => $naam]);

        Toast::verwijderd(__('De back-up :naam is weg.', ['naam' => $naam]));

        return back();
    }
}
