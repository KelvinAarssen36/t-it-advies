<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Languages, LogOut, Settings } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ $t('Instellingen') }}
            </Link>
        </DropdownMenuItem>

        <!--
            Snel wisselen zonder langs de instellingen te hoeven. Dezelfde
            route als de andere twee knoppen; zie docs/architecture/vertalingen.md.
        -->
        <DropdownMenuItem :as-child="true" @select.prevent>
            <div class="w-full cursor-pointer">
                <Languages class="mr-2 inline h-4 w-4" />
                <LocaleSwitcher class="inline-flex w-auto" />
            </div>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ $t('Uitloggen') }}
        </Link>
    </DropdownMenuItem>
</template>
