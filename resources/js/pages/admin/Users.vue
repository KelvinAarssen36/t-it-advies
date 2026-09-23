<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/admin';
import users from '@/routes/admin/users';
import { update as updateUserRoles } from '@/routes/admin/users/roles';

type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    two_factor: boolean;
    email_verified: boolean;
    created_at: string | null;
    is_self: boolean;
};

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

const props = defineProps<{
    users: Paginator<UserRow>;
    filters: { search: string | null };
    roles: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Gebruikers', href: users.index() },
        ],
    },
});

const page = usePage();
const status = computed(() => page.props.flash?.status);

const search = ref(props.filters.search ?? '');

let timeout: ReturnType<typeof setTimeout> | undefined;

// Even wachten met zoeken, anders vuurt elke toetsaanslag een request af.
watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            users.index().url,
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});

// Welke rij staat open, en welke rollen zijn daar aangevinkt. De selectie
// staat los van de rij zelf, zodat annuleren echt annuleert.
const editing = ref<number | null>(null);
const selected = ref<string[]>([]);

const startEditing = (row: UserRow) => {
    editing.value = row.id;
    selected.value = [...row.roles];
};

const toggleRole = (role: string) => {
    selected.value = selected.value.includes(role)
        ? selected.value.filter((name) => name !== role)
        : [...selected.value, role];
};

const processing = ref(false);

// Is de 2FA-bevestiging verlopen, dan stuurt de middleware naar het
// bevestigingsscherm en komt de gebruiker daarna op deze pagina terug.
// Zie docs/security/gevoelige-acties.md.
const saveRoles = (row: UserRow) => {
    processing.value = true;

    router.put(
        updateUserRoles(row.id).url,
        { roles: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const remove = (row: UserRow) => {
    const confirmed = window.confirm(
        'Weet je zeker dat je het account van ' +
            row.name +
            ' verwijdert? Dit kan niet ongedaan worden gemaakt.',
    );

    if (!confirmed) {
        return;
    }

    router.delete(users.destroy(row.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head title="Gebruikers" />

    <div class="flex flex-col gap-4 p-4">
        <div
            v-if="status"
            class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm"
        >
            {{ status }}
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                placeholder="Zoek op naam of e-mailadres"
                class="max-w-xs"
            />
            <span class="text-sm text-muted-foreground">
                {{ props.users.total }} gebruikers
            </span>
        </div>

        <p class="text-sm text-muted-foreground">
            Rollen wijzigen en accounts verwijderen vragen om een verse code uit
            je authenticator. Je eigen account en de laatste beheerder kun je
            hier niet aanpassen.
        </p>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Naam</th>
                        <th class="px-3 py-2 font-medium">E-mailadres</th>
                        <th class="px-3 py-2 font-medium">Rollen</th>
                        <th class="px-3 py-2 font-medium">2FA</th>
                        <th class="px-3 py-2 font-medium">Sinds</th>
                        <th class="px-3 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in props.users.data" :key="row.id">
                        <tr class="border-t">
                            <td class="px-3 py-2">
                                {{ row.name }}
                                <span
                                    v-if="row.is_self"
                                    class="text-muted-foreground"
                                >
                                    (jij)
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                {{ row.email }}
                                <Badge
                                    v-if="!row.email_verified"
                                    variant="secondary"
                                >
                                    niet geverifieerd
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    v-if="row.roles.length === 0"
                                    class="text-muted-foreground"
                                >
                                    geen
                                </span>
                                <Badge
                                    v-for="role in row.roles"
                                    :key="role"
                                    class="mr-1"
                                >
                                    {{ role }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        row.two_factor
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    {{ row.two_factor ? 'aan' : 'uit' }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ row.created_at ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <Button
                                    v-if="!row.is_self"
                                    variant="outline"
                                    size="sm"
                                    class="mr-2"
                                    @click="startEditing(row)"
                                >
                                    Rollen
                                </Button>
                                <Button
                                    v-if="!row.is_self"
                                    variant="destructive"
                                    size="sm"
                                    @click="remove(row)"
                                >
                                    Verwijder
                                </Button>
                            </td>
                        </tr>
                        <tr
                            v-if="editing === row.id"
                            class="border-t bg-muted/30"
                        >
                            <td colspan="6" class="px-3 py-3">
                                <div class="flex flex-wrap items-center gap-4">
                                    <label
                                        v-for="role in props.roles"
                                        :key="role"
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selected.includes(role)"
                                            @change="toggleRole(role)"
                                        />
                                        {{ role }}
                                    </label>

                                    <Button
                                        size="sm"
                                        :disabled="processing"
                                        @click="saveRoles(row)"
                                    >
                                        Opslaan
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="editing = null"
                                    >
                                        Annuleren
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="props.users.data.length === 0">
                        <td
                            colspan="6"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            Geen gebruikers gevonden.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="props.users.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="link in props.users.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="rounded px-3 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    preserve-state
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
