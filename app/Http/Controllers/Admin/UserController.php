<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRolesUpdateRequest;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Gebruikersbeheer in het beveiligde gedeelte.
 *
 * Dit is de eerste plek waar echt iets kapot kan: wie rollen mag uitdelen
 * kan zichzelf alles geven, en wie mag verwijderen kan een account weghalen.
 * Daarom zitten de twee wijzigende routes achter `can:manage users` én
 * `2fa.confirm`. Die twee beantwoorden verschillende vragen -- mag deze
 * persoon dit, en is hij het op dit moment zelf. Zie routes/admin.php en
 * docs/security/gevoelige-acties.md.
 *
 * Twee vangnetten die geen autorisatie zijn maar ongelukken voorkomen:
 * je kunt jezelf niet aanpassen of verwijderen, en de laatste beheerder
 * blijft staan. Zonder dat tweede kan één verkeerde klik de applicatie
 * onbeheerbaar maken, en er is geen scherm om dat terug te draaien.
 */
class UserController extends Controller
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString() ?: null;

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                // Niet welk geheim, maar of het aanstaat. Dat is precies wat
                // een beheerder moet kunnen zien om iemand erop aan te spreken.
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'email_verified' => $user->email_verified_at !== null,
                'created_at' => $user->created_at?->toDateString(),
                'is_self' => $request->user()?->is($user) ?? false,
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function updateRoles(UserRolesUpdateRequest $request, User $user): RedirectResponse
    {
        $roles = $request->roles();

        $blocked = $this->guard($request, $user, $roles);

        if ($blocked !== null) {
            return $blocked;
        }

        $before = $user->roles->pluck('name')->sort()->values()->all();
        $user->syncRoles($roles);

        $this->logger->success(SecurityEventType::UserRolesChanged, $request->user(), [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'van' => $before,
            'naar' => $roles,
        ]);

        return back()->with('status', __('De rollen van :naam zijn bijgewerkt.', ['naam' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $blocked = $this->guard($request, $user, []);

        if ($blocked !== null) {
            return $blocked;
        }

        // Vastleggen vóór het verwijderen. De koppeling in security_events
        // wordt bij het verwijderen op null gezet, dus zonder het e-mailadres
        // hier zou het spoor doodlopen op een id dat nergens meer bij hoort.
        $this->logger->success(SecurityEventType::UserDeleted, $request->user(), [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'rollen' => $user->roles->pluck('name')->all(),
        ]);

        $user->delete();

        return to_route('admin.users.index')
            ->with('status', __('Het account van :naam is verwijderd.', ['naam' => $user->name]));
    }

    /**
     * De twee vangnetten, op één plek zodat ze niet uiteen kunnen lopen.
     *
     * @param  array<int, string>  $roles  de rollen die de gebruiker zou overhouden
     */
    private function guard(Request $request, User $user, array $roles): ?RedirectResponse
    {
        if ($request->user()?->is($user) ?? false) {
            return back()->with('status', __('Je kunt je eigen account hier niet aanpassen. Vraag een andere beheerder.'));
        }

        if ($this->wouldRemoveLastAdmin($user, $roles)) {
            return back()->with('status', __('Dit is de laatste beheerder. Maak eerst iemand anders beheerder.'));
        }

        return null;
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function wouldRemoveLastAdmin(User $user, array $roles): bool
    {
        if (! $user->hasRole('admin') || in_array('admin', $roles, true)) {
            return false;
        }

        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->count();

        return $admins <= 1;
    }
}
