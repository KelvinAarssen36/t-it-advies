<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, ShieldAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import mail from '@/routes/admin/mail';
import security from '@/routes/admin/security';

type Stats = {
    failed_security_events: number;
    security_events_total: number;
    mails_sent: number;
    mail_problems: number;
};

type Failure = {
    id: number;
    label: string;
    email: string | null;
    ip_address: string | null;
    created_at: string;
};

defineProps<{
    stats: Stats;
    recentFailures: Failure[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Beheer', href: dashboard() }],
    },
});
</script>

<template>
    <Head :title="$t('Beheer')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ $t('Mislukte pogingen (24u)') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ stats.failed_security_events }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ $t('Gebeurtenissen (24u)') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ stats.security_events_total }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ $t('Mails verzonden (24u)') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ stats.mails_sent }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ $t('Mailproblemen (24u)') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-3xl font-semibold tabular-nums"
                        :class="
                            stats.mail_problems > 0 ? 'text-destructive' : ''
                        "
                    >
                        {{ stats.mail_problems }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ $t('Laatste mislukte pogingen') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="recentFailures.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Niets te melden.') }}
                </p>

                <table v-else class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="pb-2 font-medium">
                                {{ $t('Gebeurtenis') }}
                            </th>
                            <th class="pb-2 font-medium">
                                {{ $t('E-mail') }}
                            </th>
                            <th class="pb-2 font-medium">{{ $t('IP') }}</th>
                            <th class="pb-2 font-medium">
                                {{ $t('Wanneer') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="failure in recentFailures"
                            :key="failure.id"
                            class="border-t border-border"
                        >
                            <td class="py-2">{{ failure.label }}</td>
                            <td class="py-2">{{ failure.email ?? '-' }}</td>
                            <td class="py-2 font-mono text-xs">
                                {{ failure.ip_address ?? '-' }}
                            </td>
                            <td class="py-2 tabular-nums">
                                {{ failure.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <!--
            Doorverwijzingen naar de twee logboeken waarvan hierboven een
            samenvatting staat. Dezelfde vorm als elders in het portaal:
            een knop met de rand, een pictogram vooraan en `gap-2`. Het
            waren onderstreepte tekstlinks, en die lazen als voetnoot
            terwijl het de enige weg naar die schermen is.

            De pictogrammen zijn dezelfde als in de zijbalk, zodat je aan
            het teken al ziet waar je heen gaat.
        -->
        <div class="flex flex-wrap gap-2">
            <Button variant="outline" as-child class="gap-2">
                <Link :href="security.index()">
                    <ShieldAlert class="size-4" />
                    {{ $t('Volledig beveiligingslogboek') }}
                </Link>
            </Button>

            <Button variant="outline" as-child class="gap-2">
                <Link :href="mail.index()">
                    <Mail class="size-4" />
                    {{ $t('Mailoverzicht') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
