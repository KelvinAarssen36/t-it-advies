<?php

namespace App\Console\Commands;

use App\Models\AboutPoint;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\FaqItem;
use App\Models\MailLog;
use Database\Seeders\VoorbeeldDataSeeder;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Zet verzonnen data neer om de schermen mee te bekijken.
 *
 * Kan ook met `php artisan db:seed --class=VoorbeeldDataSeeder`, maar deze
 * opdracht is korter, vertelt wat er is gebeurd, en staat naast
 * `voorbeeld:opruimen` in `artisan list`. Zaaien en opruimen horen bij
 * elkaar; dan moeten ze ook bij elkaar te vinden zijn.
 *
 * **Het praten zit hier en niet in de seeder.** Een seeder heeft alleen een
 * terminal als hij via `db:seed` is aangeroepen, en dat is aan de typen niet
 * te zien: `Seeder::$command` is volgens Laravel nooit leeg, dus zowel een
 * nullsafe-aanroep als een `isset()` wordt afgekeurd door de statische
 * analyse -- terwijl hij bij een rechtstreekse aanroep wél leeg is. Een
 * opdracht hééft altijd een terminal, en daarmee is het probleem er niet
 * meer in plaats van omzeild.
 *
 * Zie database/seeders/VoorbeeldDataSeeder.php en
 * docs/development/setup.md.
 */
class ZaaienVoorbeeldData extends Command
{
    protected $signature = 'voorbeeld:zaaien';

    protected $description = 'Zet verzonnen contactonderwerpen, aanvragen, mailregels, vragen en Over mij neer (alleen lokaal)';

    public function handle(): int
    {
        try {
            $this->components->task(
                'Voorbeelddata neerzetten',
                function (): bool {
                    (new VoorbeeldDataSeeder)->run();

                    return true;
                },
            );
        } catch (RuntimeException $fout) {
            /*
             * De seeder weigert buiten local en testing. Die controle staat
             * daar en niet hier, zodat hij ook geldt wanneer iemand de
             * seeder via `db:seed` aanroept.
             */
            $this->components->error($fout->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            'Onderwerpen',
            (string) ContactSubject::query()->count(),
        );
        $this->components->twoColumnDetail(
            'Aanvragen',
            (string) ContactSubmission::query()->count(),
        );
        $this->components->twoColumnDetail(
            'Regels in het mailoverzicht',
            (string) MailLog::query()->count(),
        );
        $this->components->twoColumnDetail(
            'Vragen',
            (string) FaqItem::query()->count(),
        );
        $this->components->twoColumnDetail(
            'Punten bij Over mij',
            (string) AboutPoint::query()->count(),
        );

        $this->newLine();
        $this->components->info(
            'Alles met @voorbeeld.test erin is verzonnen. Weghalen: php artisan voorbeeld:opruimen'
        );

        return self::SUCCESS;
    }
}
