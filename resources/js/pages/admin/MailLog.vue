<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/admin';
import mail from '@/routes/admin/mail';

type MailRow = {
    id: number;
    subject: string | null;
    mailable: string;
    to: string[];
    status: string;
    status_label: string;
    is_problem: boolean;
    sent_at: string | null;
    last_event_at: string | null;
    events: Array<{ status: string; occurred_at: string }>;
    error: string | null;
};

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

const props = defineProps<{
    logs: Paginator<MailRow>;
    filters: { search: string | null; status: string | null };
    statuses: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Mail', href: mail.index() },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let timeout: ReturnType<typeof setTimeout> | undefined;

// Even wachten met zoeken, anders vuurt elke toetsaanslag een request af.
watch([search, status], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            mail.index().url,
            {
                search: search.value || undefined,
                status: status.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

const expanded = ref<number | null>(null);
</script>

<template>
    <Head title="Mailoverzicht" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                type="search"
                placeholder="Zoek op onderwerp, type of ontvanger"
                class="max-w-xs"
            />
            <select
                v-model="status"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
                <option value="">Alle statussen</option>
                <option
                    v-for="option in statuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <span class="text-sm text-muted-foreground">
                {{ logs.total }} mails
            </span>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Onderwerp</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Aan</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Verzonden</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in logs.data" :key="row.id">
                        <tr
                            class="cursor-pointer border-t hover:bg-muted/40"
                            @click="
                                expanded = expanded === row.id ? null : row.id
                            "
                        >
                            <td class="px-3 py-2">{{ row.subject ?? '-' }}</td>
                            <td class="px-3 py-2">{{ row.mailable }}</td>
                            <td class="px-3 py-2">{{ row.to.join(', ') }}</td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        row.is_problem
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                >
                                    {{ row.status_label }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ row.sent_at ?? '-' }}
                            </td>
                        </tr>
                        <tr
                            v-if="expanded === row.id"
                            class="border-t bg-muted/30"
                        >
                            <td colspan="5" class="px-3 py-3">
                                <p
                                    v-if="row.error"
                                    class="mb-2 text-destructive"
                                >
                                    {{ row.error }}
                                </p>
                                <p
                                    v-if="row.events.length === 0"
                                    class="text-muted-foreground"
                                >
                                    Nog geen terugkoppeling van de provider.
                                </p>
                                <ol v-else class="space-y-1">
                                    <li
                                        v-for="(event, index) in row.events"
                                        :key="index"
                                        class="flex gap-3"
                                    >
                                        <span class="font-mono text-xs">
                                            {{ event.occurred_at }}
                                        </span>
                                        <span>{{ event.status }}</span>
                                    </li>
                                </ol>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="logs.data.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            Nog geen mails verstuurd.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="logs.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="link in logs.links" :key="link.label">
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
