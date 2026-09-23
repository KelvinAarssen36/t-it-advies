<script setup lang="ts">
/**
 * De voet van de publieke site. Geen inloglink: zie SiteHeader.vue.
 */
const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-brand-navy">
        <div class="brand-rule" />

        <div class="mx-auto max-w-6xl px-6 py-14">
            <div class="flex flex-col justify-between gap-10 sm:flex-row">
                <div class="max-w-sm">
                    <p class="text-lg font-semibold tracking-tight">
                        <span class="brand-text-gradient">@T</span>
                        <span class="text-white"> IT Advies</span>
                    </p>
                    <p class="mt-3 text-sm text-muted-foreground">
                        IT-advies en realisatie. Van advies tot bouw en beheer.
                    </p>
                </div>

                <nav class="flex flex-col gap-2 text-sm">
                    <a
                        href="#diensten"
                        class="text-muted-foreground transition-colors hover:text-brand-cyan"
                    >
                        Diensten
                    </a>
                    <a
                        href="#werkwijze"
                        class="text-muted-foreground transition-colors hover:text-brand-cyan"
                    >
                        Werkwijze
                    </a>
                    <a
                        href="#contact"
                        class="text-muted-foreground transition-colors hover:text-brand-cyan"
                    >
                        Contact
                    </a>
                </nav>
            </div>

            <p class="mt-12 text-sm text-muted-foreground">
                &copy; {{ year }} @T IT Advies
            </p>
        </div>
    </footer>
</template>
