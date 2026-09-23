<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/admin';
import security from '@/routes/admin/security';

type EventRow = {
    id: number;
    event: string;
    label: string;
    outcome: string;
    outcome_label: string;
    user: { id: number; name: string; email: string } | null;
    email: string | null;
    ip_address: string | null;
    user_agent: string | null;
    context: Record<string, unknown> | null;
    created_at: string;
};

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

const props = defineProps<{
    events: Paginator<EventRow>;
    filters: {
        search: string | null;
        event: string | null;
        outcome: string | null;
    };
    eventTypes: Array<{ value: string; label: string }>;
    outcomes: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Beveiliging', href: security.index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const event = ref(props.filters.event ?? '');
const outcome = ref(props.filters.outcome ?? '');

let timeout: ReturnType<typeof setTimeout> | undefined;

watch([search, event, outcome], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            security.index().url,
            {
                search: search.value || undefined,
                event: event.value || undefined,
                outcome: outcome.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const expanded = ref<number | null>(null);
</script>

<template>
    <Head title="Beveiligingslogboek" />

    <div class="flex flex-col gap-4 p-4">
        <p class="text-sm text-muted-foreground">
            De context van elke regel is geschoond: wachtwoorden, codes, secrets
            en recovery codes worden nooit vastgelegd.
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                placeholder="Zoek op e-mail of IP"
                class="max-w-xs"
            />
            <select
                v-model="event"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
                <option value="">Alle gebeurtenissen</option>
                <option
                    v-for="option in eventTypes"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <select
                v-model="outcome"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
                <option value="">Gelukt en mislukt</option>
                <option
                    v-for="option in outcomes"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <span class="text-sm text-muted-foreground">
                {{ events.total }} gebeurtenissen
            </span>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Wanneer</th>
                        <th class="px-3 py-2 font-medium">Gebeurtenis</th>
                        <th class="px-3 py-2 font-medium">Uitkomst</th>
                        <th class="px-3 py-2 font-medium">Wie</th>
                        <th class="px-3 py-2 font-medium">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in events.data" :key="row.id">
                        <tr
                            class="cursor-pointer border-t hover:bg-muted/40"
                            @click="
                                expanded = expanded === row.id ? null : row.id
                            "
                        >
                            <td class="px-3 py-2 tabular-nums">
                                {{ row.created_at }}
                            </td>
                            <td class="px-3 py-2">{{ row.label }}</td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        row.outcome === 'failure'
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                >
                                    {{ row.outcome_label }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                {{ row.user?.email ?? row.email ?? '-' }}
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ row.ip_address ?? '-' }}
                            </td>
                        </tr>
                        <tr
                            v-if="expanded === row.id"
                            class="border-t bg-muted/30"
                        >
                            <td colspan="5" class="px-3 py-3">
                                <p class="mb-2 break-all">
                                    <span class="text-muted-foreground">
                                        User agent:
                                    </span>
                                    {{ row.user_agent ?? '-' }}
                                </p>
                                <pre
                                    v-if="row.context"
                                    class="overflow-x-auto rounded bg-background p-3 text-xs"
                                    >{{
                                        JSON.stringify(row.context, null, 2)
                                    }}</pre>
                                <p v-else class="text-muted-foreground">
                                    Geen extra context.
                                </p>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="events.data.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            Nog niets vastgelegd.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="events.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="link in events.links" :key="link.label">
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
