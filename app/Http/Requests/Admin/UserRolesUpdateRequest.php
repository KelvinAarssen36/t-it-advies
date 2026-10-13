<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * De rollen die aan een gebruiker worden gehangen.
 *
 * De toegestane waarden komen uit de rollentabel en niet uit een lijst in de
 * code: zo kan er nooit een rol worden toegekend die niet bestaat, en hoeft
 * deze klasse niet mee te veranderen als er een rol bij komt.
 */
class UserRolesUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'present' en niet 'required': alle rollen weghalen is een
            // geldige wijziging, en dan is de array leeg.
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in(Role::query()->pluck('name')->all())],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        /** @var array<int, string> $roles */
        $roles = $this->validated('roles') ?? [];

        return array_values(array_unique($roles));
    }
}
