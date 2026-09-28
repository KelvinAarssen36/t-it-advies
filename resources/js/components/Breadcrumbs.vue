<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();

/*
 * De titels worden hier vertaald en niet op de pagina zelf.
 *
 * Een kruimelpad staat in `defineOptions`, en dat wordt uitgevoerd zodra de
 * module geladen wordt. Bij een volledige paginalading is dat vóórdat
 * Inertia een pagina heeft, dus daar is nog geen taal om in te vertalen --
 * en een `t()` op die plek levert een wit scherm op. De titel is daarom de
 * Nederlandse zin, en die is tegelijk de sleutel.
 *
 * Zie docs/architecture/vertalingen.md.
 */
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage>{{ $t(item.title) }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ $t(item.title) }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
