<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Een back-upbestand van buiten naar binnen.
 *
 * **Dit is de enige plek waar een bestand van buiten de applicatie
 * database-inhoud kan worden.** Alles wat hier langskomt is dus
 * onvertrouwd, ook al heeft de eigenaar het zelf net gedownload.
 *
 * Wat hier wordt afgevangen is alleen de buitenkant: een zip, en niet te
 * groot. De echte controle -- het manifest, de checksums, en of elke
 * tabel in ons eigen register staat -- gebeurt in `BackupLezer`, want
 * daarvoor moet het bestand eerst open.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupUploadRequest extends FormRequest
{
    /**
     * Het maximum, in kilobytes.
     *
     * 64 MB. Ruim boven wat een volle site oplevert (enkele megabytes),
     * en ver onder wat een server zonder nadenken wegschrijft.
     *
     * **Let op de PHP-instellingen.** `upload_max_filesize` en
     * `post_max_size` moeten hier overheen; staan die lager, dan kapt PHP
     * het verzoek af vóórdat Laravel het ziet en krijgt de eigenaar geen
     * foutmelding maar een formulier dat niets doet. Zie
     * docs/operations/deployment.md.
     */
    public const MAX_KB = 65536;

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'bestand' => [
                'required',
                'file',
                'mimetypes:application/zip,application/x-zip-compressed',
                'max:'.self::MAX_KB,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bestand.required' => __('Kies eerst een bestand.'),
            'bestand.mimetypes' => __('Dit moet het zip-bestand zijn dat je eerder hebt gedownload.'),
            'bestand.max' => __('Dit bestand is te groot; hoogstens :aantal MB.', [
                'aantal' => (int) (self::MAX_KB / 1024),
            ]),
        ];
    }
}
