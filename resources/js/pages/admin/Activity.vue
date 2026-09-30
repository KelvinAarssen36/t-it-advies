<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import activity from '@/routes/admin/activity';

/**
 * Het activiteitenlogboek.
 *
 * Bedoeld voor de klant zelf, dus de taal gaat over zijn website en niet
 * over onze database. Klap een regel open en je ziet per veld wat er stond
 * en wat er nu staat.
 */
type Wijziging = { van: unknown; naar: unknown };

type Regel = {
    id: number;
    action: string;
    action_label: string;
    actor_name: string;
    subject_name: string;
    subject_label: string | null;
    changes: Record<string, Wijziging> | null;
    changed_fields: string[];
    created_at: string;
    created_at_diff: string;
};

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

const props = defineProps<{
    entries: Paginator<Regel>;
    filters: {
        search: string | null;
        action: string | null;
        subject: string | null;
    };
    actions: Array<{ value: string; label: string }>;
    subjects: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Activiteit', href: activity.index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const action = ref(props.filters.action ?? '');
const subject = ref(props.filters.subject ?? '');

let timeout: ReturnType<typeof setTimeout>;

// Even wachten met zoeken, anders vuurt elke toetsaanslag een request af.
watch([search, action, subject], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            activity.index().url,
            {
                search: search.value || undefined,
                action: action.value || undefined,
                subject: subject.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const expanded = ref<number | null>(null);

/**
 * Een waarde zoals je hem in het logboek wilt lezen.
 *
 * Een lege waarde is informatie -- er stond niets, of er staat nu niets --
 * dus die krijgt een streepje in plaats van te verdwijnen.
 */
const toon = (waarde: unknown): string => {
    if (waarde === null || waarde === undefined || waarde === '') {
        return '—';
    }

    if (typeof waarde === 'object') {
        return JSON.stringify(waarde);
    }

    return String(waarde);
};

const kleur = (soort: string) =>
    soort === 'deleted' ? 'destructive' : 'secondary';

/**
 * De gewijzigde velden, kort samengevat.
 *
 * Bij aanmaken en verwijderen staan álle velden in de lijst, en die opsomming
 * zegt dan niets -- de handeling zegt het al. Daar tonen we dus een streepje.
 */
const velden = (regel: Regel): string => {
    if (regel.action !== 'updated' || regel.changed_fields.length === 0) {
        return '—';
    }

    const eerste = regel.changed_fields.slice(0, 3).join(', ');
    const rest = regel.changed_fields.length - 3;

    return rest > 0 ? `${eerste} +${rest}` : eerste;
};
</script>

<template>
    <Head :title="$t('Activiteit')" />

    <div class="flex flex-col gap-4 p-4">
        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'Hier staat wat er aan de inhoud van de website is veranderd, door wie en wanneer. Inloggen en beveiliging staan in het beveiligingslogboek.',
                )
            }}
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Zoek op onderdeel')"
                class="max-w-xs"
            />
            <BrandSelect
                v-model="action"
                :aria-label="$t('Filter op handeling')"
                class="max-w-xs"
                :options="[
                    { value: '', label: $t('Alle handelingen') },
                    ...actions,
                ]"
            />
            <BrandSelect
                v-if="subjects.length > 0"
                v-model="subject"
                :aria-label="$t('Filter op onderdeel')"
                class="max-w-xs"
                :options="[
                    { value: '', label: $t('Alle onderdelen') },
                    ...subjects,
                ]"
            />
            <span class="text-sm text-muted-foreground">
                {{ $t(':aantal regels', { aantal: entries.total }) }}
            </span>
        </div>

        <div
            class="brand-tabelvak brand-scrollbar overflow-x-auto rounded-xl border"
        >
            <table class="brand-tabel-kaarten w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">
                            {{ $t('Wanneer') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ $t('Handeling') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ $t('Onderdeel') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ $t('Wat er veranderde') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in entries.data" :key="row.id">
                        <tr
                            class="cursor-pointer border-t hover:bg-muted/40"
                            @click="
                                expanded = expanded === row.id ? null : row.id
                            "
                        >
                            <!--
                                "twee uur geleden" is wat je wilt weten; de
                                exacte tijd hangt eronder voor als het er
                                echt op aankomt.
                            -->
                            <td
                                :data-label="$t('Wanneer')"
                                class="px-3 py-2 whitespace-nowrap"
                            >
                                <span>{{ row.created_at_diff }}</span>
                                <span
                                    class="block text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ row.created_at }}
                                </span>
                            </td>
                            <td :data-label="$t('Handeling')" class="px-3 py-2">
                                <Badge :variant="kleur(row.action)">
                                    {{ row.action_label }}
                                </Badge>
                            </td>
                            <td :data-label="$t('Onderdeel')" class="px-3 py-2">
                                <span class="text-muted-foreground">
                                    {{ row.subject_name }}
                                </span>
                                <span v-if="row.subject_label">
                                    — {{ row.subject_label }}
                                </span>
                            </td>
                            <td
                                :data-label="$t('Wat er veranderde')"
                                class="px-3 py-2 text-muted-foreground"
                            >
                                {{ velden(row) }}
                            </td>
                        </tr>
                        <tr
                            v-if="expanded === row.id"
                            class="border-t bg-muted/30"
                        >
                            <td colspan="4" class="px-3 py-3">
                                <p class="mb-3 text-xs text-muted-foreground">
                                    {{
                                        $t('Door :naam', {
                                            naam: row.actor_name,
                                        })
                                    }}
                                </p>

                                <p
                                    v-if="
                                        !row.changes ||
                                        Object.keys(row.changes).length === 0
                                    "
                                    class="text-muted-foreground"
                                >
                                    {{ $t('Geen verdere details vastgelegd.') }}
                                </p>

                                <!--
                                    Per veld wat er stond en wat er nu staat.
                                    Dat is het verschil tussen een logboek dat
                                    zegt dát er iets veranderde en een dat
                                    zegt wát.
                                -->
                                <dl v-else class="grid gap-2">
                                    <div
                                        v-for="(waarde, veld) in row.changes"
                                        :key="veld"
                                        class="grid gap-1 sm:grid-cols-[10rem_1fr]"
                                    >
                                        <dt
                                            class="font-medium text-muted-foreground"
                                        >
                                            {{ veld }}
                                        </dt>
                                        <dd class="flex flex-wrap gap-2">
                                            <span
                                                v-if="'van' in (waarde ?? {})"
                                                class="rounded bg-background px-2 py-0.5 text-xs text-muted-foreground line-through"
                                            >
                                                {{ toon(waarde.van) }}
                                            </span>
                                            <span
                                                class="rounded bg-background px-2 py-0.5 text-xs"
                                            >
                                                {{
                                                    toon(
                                                        'naar' in (waarde ?? {})
                                                            ? waarde.naar
                                                            : waarde,
                                                    )
                                                }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="entries.data.length === 0">
                        <td
                            colspan="4"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('Er is nog niets gewijzigd.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="entries.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="link in entries.links" :key="link.label">
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
