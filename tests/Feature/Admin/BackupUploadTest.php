<?php

namespace Tests\Feature\Admin;

use App\Models\Backup;
use App\Models\Project;
use App\Models\User;
use App\Support\Backup\BackupMaker;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Een back-upbestand van buiten naar binnen.
 *
 * **Dit is de enige plek waar een bestand van buiten de applicatie
 * database-inhoud kan worden**, en daarmee de enige plek in deze
 * voorziening met een echte aanvalsoppervlakte. Alles hieronder is een
 * poging om er iets doorheen te krijgen.
 *
 * Wat er niet door mag:
 *
 * - een zip met een pad als `../../.env` (zip-slip);
 * - een zip met een onderdeel dat wij niet kennen;
 * - een zip waarin een "afbeelding" geen afbeelding is;
 * - een zip waarvan de inhoud niet bij de controlecode past.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function eigenaar(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /** Een echt back-upbestand, als vertrekpunt om mee te knoeien. */
    private function echtBestand(): string
    {
        Project::factory()->count(2)->create();

        $backup = app(BackupMaker::class)->maak();
        $pad = (string) $backup->pad();

        $kopie = sys_get_temp_dir().'/backup-test-'.uniqid().'.zip';
        copy($pad, $kopie);

        // De rij uit de lijst halen, zodat de upload straks echt nieuw is.
        Storage::disk('local')->delete($backup->bestand);
        $backup->delete();

        return $kopie;
    }

    private function alsUpload(string $pad): UploadedFile
    {
        return new UploadedFile($pad, 'back-up.zip', 'application/zip', null, true);
    }

    /* --- De gelukkige weg --------------------------------------------------- */

    public function test_a_real_file_lands_in_the_list(): void
    {
        $eigenaar = $this->eigenaar();
        $pad = $this->echtBestand();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)])
            ->assertSessionHasNoErrors();

        $backup = Backup::query()->vanSoort(Backup::SOORT_GEUPLOAD)->first();

        $this->assertNotNull($backup);
        $this->assertSame(2, $backup->aantallen['projects']);
    }

    /**
     * Uploaden zet nog niets terug.
     *
     * Dat is met opzet: zo is er geen tweede, kortere weg naar het
     * overschrijven van de database. Terugzetten is daarna dezelfde knop
     * met dezelfde sloten.
     */
    public function test_uploading_does_not_restore_anything(): void
    {
        $eigenaar = $this->eigenaar();
        $pad = $this->echtBestand();

        Project::factory()->count(5)->create();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)]);

        $this->assertSame(
            7,
            Project::query()->count(),
            'De upload heeft zelf al iets teruggezet, en dat hoort niet.',
        );
    }

    /* --- Wat er niet door mag ------------------------------------------------ */

    public function test_a_path_traversal_entry_is_refused(): void
    {
        $eigenaar = $this->eigenaar();
        $pad = $this->echtBestand();

        $zip = new ZipArchive;
        $zip->open($pad);
        $zip->addFromString('../../../.env', 'APP_KEY=gestolen');
        $zip->close();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)]);

        /*
         * Er mag niets buiten de back-upmap zijn weggeschreven. De
         * controle hierop is niet "is het pad veilig" maar "wij volgen
         * geen enkel pad uit de zip" -- de naam wordt opnieuw opgebouwd
         * uit de hash. Zie BackupLezer::pakBeeldenUit().
         */
        $this->assertFileDoesNotExist(storage_path('app/private/.env'));
        $this->assertFileDoesNotExist(base_path('.env.gestolen'));

        foreach (Storage::disk('local')->allFiles() as $bestand) {
            $this->assertStringStartsWith(Backup::MAP, $bestand);
        }
    }

    public function test_an_unknown_table_is_refused(): void
    {
        $eigenaar = $this->eigenaar();
        $pad = $this->echtBestand();

        $this->knoeiMetData($pad, function (array $data): array {
            $data['geheime_tabel'] = [['id' => 1, 'iets' => 'kwaadaardig']];

            return $data;
        });

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)]);

        $this->assertSame(0, Backup::query()->count());
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $eigenaar = $this->eigenaar();

        // Een bestand mét een geldige hash, maar het is PHP en geen beeld.
        $inhoud = '<?php echo "hallo"; ?>';
        $hash = hash('sha256', $inhoud);

        $pad = $this->echtBestand();

        $zip = new ZipArchive;
        $zip->open($pad);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $manifest['beelden'][$hash] = 'projecten/kwaadaardig.webp';
        $zip->addFromString('manifest.json', (string) json_encode($manifest));
        $zip->addFromString('media/'.$hash.'.webp', $inhoud);
        $zip->close();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)]);

        $this->assertSame(0, Backup::query()->count());
        Storage::disk('local')->assertMissing(Backup::BEELDMAP.'/'.$hash.'.webp');
    }

    public function test_a_damaged_file_is_refused(): void
    {
        $eigenaar = $this->eigenaar();
        $pad = $this->echtBestand();

        $zip = new ZipArchive;
        $zip->open($pad);
        $zip->addFromString('data.json', (string) $zip->getFromName('data.json').' ');
        $zip->close();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), ['bestand' => $this->alsUpload($pad)]);

        $this->assertSame(0, Backup::query()->count());
    }

    public function test_something_that_is_not_a_zip_is_refused(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.upload'), [
                'bestand' => UploadedFile::fake()->create('niet-echt.zip', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('bestand');

        $this->assertSame(0, Backup::query()->count());
    }

    /**
     * De data in een bestand aanpassen, met de controlecode erbij.
     *
     * Anders valt het bestand al af op de checksum en toets je niet wat
     * je denkt te toetsen.
     */
    private function knoeiMetData(string $pad, callable $aanpassing): void
    {
        $zip = new ZipArchive;
        $zip->open($pad);

        $data = json_decode((string) $zip->getFromName('data.json'), true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $nieuw = (string) json_encode(
            $aanpassing($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $manifest['checksum'] = hash('sha256', $nieuw);

        $zip->addFromString('data.json', $nieuw);
        $zip->addFromString('manifest.json', (string) json_encode($manifest));
        $zip->close();
    }
}
