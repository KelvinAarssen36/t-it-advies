import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

/**
 * Het TLS-certificaat van Valet, als deze site beveiligd is.
 *
 * **`laravel-vite-plugin` regelt dit zelf, maar niet op Valet Linux.** Hij
 * leest de topdomeinnaam uit `~/.valet/config.json` onder de sleutel `tld`,
 * en dat is wat Valet op macOS en Herd daar neerzetten. Valet **Linux**
 * noemt diezelfde sleutel `domain`. De plugin leest dan `undefined`, zoekt
 * naar een certificaat voor `t-it-advies.undefined`, vindt niets en valt
 * zonder mopperen terug op http.
 *
 * Dat is niet erg zolang de site ook op http draait. Maar zodra je hem met
 * `valet secure` beveiligt, staat de pagina op https en de ontwikkelserver
 * op http -- en dan blokkeert de browser het script van Vite als onveilige
 * inhoud. Je ziet een pagina zonder opmaak en geen enkele foutmelding die
 * uitlegt waarom.
 *
 * **Daarom zoeken we het certificaat hier zelf op.** Staat het er niet, dan
 * geven we `undefined` terug en blijft alles zoals het was. Zo breekt dit
 * niet op een machine zonder Valet, en niet voor wie zijn site op http laat
 * staan.
 *
 * Dit raakt alleen `npm run dev`; `server` doet niets bij een build. Zie
 * docs/development/setup.md.
 */
function valetCertificaat():
    | { host: string; key: string; cert: string }
    | undefined {
    try {
        const config = path.resolve(os.homedir(), '.valet', 'config.json');

        if (!fs.existsSync(config)) {
            return undefined;
        }

        const { domain, tld } = JSON.parse(fs.readFileSync(config, 'utf-8'));
        const host = `${path.basename(process.cwd())}.${domain ?? tld}`;

        const map = path.resolve(os.homedir(), '.valet', 'Certificates');
        const key = path.join(map, `${host}.key`);
        const cert = path.join(map, `${host}.crt`);

        return fs.existsSync(key) && fs.existsSync(cert)
            ? { host, key, cert }
            : undefined;
    } catch {
        // Een onleesbare of onverwachte configuratie is geen reden om de
        // ontwikkelserver te laten omvallen. Dan maar zonder https.
        return undefined;
    }
}

const certificaat = valetCertificaat();

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        /*
          Alleen gezet als er echt een certificaat ligt; zie
          `valetCertificaat()`. De `host` hoort erbij: het certificaat is
          uitgegeven op die naam, dus op `localhost` zou de browser hem
          alsnog weigeren.
        */
        ...(certificaat
            ? {
                  host: certificaat.host,
                  hmr: { host: certificaat.host },
                  https: { key: certificaat.key, cert: certificaat.cert },
              }
            : {}),
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
