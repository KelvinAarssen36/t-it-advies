<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Eén punt onder het verhaal op de pagina "Over mij".
 *
 * Twee velden en niets meer. Tachtig tekens, want dit is een punt en geen
 * zin: past het er niet in, dan hoort het in het verhaal zelf.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class AboutPointRequest extends FormRequest
{
    use SchoneVelden;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'text_nl' => ['required', 'string', 'max:80'],
            'text_en' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'text_nl' => __('punt'),
            'text_en' => __('Engelse punt'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text_nl.max' => __(
                'Een punt hoort op één regel te passen. Is er meer over te zeggen, zet dat dan in je verhaal.',
            ),
            'text_en.max' => __(
                'Een punt hoort op één regel te passen. Is er meer over te zeggen, zet dat dan in je verhaal.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'text_nl' => $this->string('text_nl')->trim()->toString(),
            'text_en' => $this->tekst('text_en'),
        ];
    }
}
