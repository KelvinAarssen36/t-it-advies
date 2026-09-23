<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\SecurityEventType;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Maakt een account aan vanaf de opdrachtregel.
 *
 * Registratie via de website staat uit -- deze applicatie heeft één
 * gebruiker, de eigenaar. Zonder dit commando zou er geen manier zijn om dat
 * eerste account te maken behalve tinker of met de hand in de database, en
 * dat zijn allebei plekken waar een wachtwoord verkeerd terechtkomt.
 *
 * Het wachtwoord is bewust géén optie maar een vraag. Opties belanden in de
 * shell-geschiedenis en zijn op een gedeelde server zichtbaar in `ps`.
 *
 * Zie docs/security/authenticatie-en-2fa.md.
 */
class CreateUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'user:create
                            {--name= : De naam van de gebruiker}
                            {--email= : Het e-mailadres}
                            {--role=admin : De rol die de gebruiker krijgt}';

    protected $description = 'Maak een account aan (registratie via de website staat uit)';

    public function handle(SecurityLogger $logger): int
    {
        $roles = Role::query()->pluck('name')->all();

        if ($roles === []) {
            $this->error(__('Er zijn nog geen rollen. Draai eerst: php artisan db:seed --class=RolesAndPermissionsSeeder'));

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Naam');
        $email = $this->option('email') ?: $this->ask('E-mailadres');
        $role = $this->option('role');

        $password = $this->secret('Wachtwoord');
        $confirmation = $this->secret('Wachtwoord nogmaals');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
            'role' => $role,
        ], [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', 'string', Rule::in($roles)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Meteen geverifieerd. Dit account wordt met opzet door een beheerder
        // op de server aangemaakt, en op dat moment staat de mailprovider
        // vaak nog niet ingesteld -- dan zou de eigenaar niet binnen kunnen
        // komen op zijn eigen site.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($role);

        $logger->success(SecurityEventType::UserCreated, context: [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'rol' => $role,
            'via' => 'console',
        ]);

        $this->info(__('Account aangemaakt voor :naam (:email) met de rol :rol.', [
            'naam' => $user->name,
            'email' => $user->email,
            'rol' => $role,
        ]));

        $this->line(__('Zet er meteen tweestapsverificatie op via de beveiligingsinstellingen.'));

        return self::SUCCESS;
    }
}
