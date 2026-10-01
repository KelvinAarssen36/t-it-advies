import type { VariantProps } from "class-variance-authority";
import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

export const buttonVariants = cva(
    "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-primary-hover",
                // Alleen op een donkere achtergrond. Op wit verliest de gradient
                // zijn contrast en wordt de knop juist zwakker dan de gewone.
                //
                // Dit is de actieknop van de publieke site: "Neem contact op"
                // in de kop en in de hero, en de verzendknop onder het
                // formulier. `brand-glans` laat er af en toe een lichte veeg
                // over trekken; dat hoort bij de variant en niet bij één knop,
                // zodat een nieuwe actieknop meegaat zonder dat iemand eraan
                // hoeft te denken. Zie .brand-glans in app.css.
                brand: "brand-surface brand-glans text-white shadow-xs hover:brightness-110",
                // De secundaire knop op donker: doorzichtig, zodat de gradient of
                // de navy eronder gewoon doorloopt.
                "brand-outline":
                    "border border-brand-line bg-transparent text-white hover:bg-brand-navy-deep",
                // --- De drie handelingen op een item -------------------
                //
                // Aanmaken, bewerken en verwijderen hebben elk een eigen
                // kleur, en die is overal in het portaal dezelfde:
                //
                //   aanmaken     merkblauw
                //   bewerken     oker  (--bewerken, zie resources/css/app.css)
                //   verwijderen  rood
                //
                // Van elke kleur zijn er twee vormen. De volle vorm is
                // voor de knop die de handeling écht uitvoert: de knop
                // bovenaan een scherm, de opslaan-knop in een venster, de
                // bevestigknop. De -zacht vorm is voor een knop die in een
                // rij naast tien soortgenoten staat; vijftien volle vlakken
                // onder elkaar is geen lijst meer maar een waarschuwing.
                //
                // De zachte vorm is in rust alleen zijn pictogram in de
                // kleur van de handeling, en krijgt bij het aanwijzen een
                // zachte schijf in diezelfde kleur. Dat gedrag staat in
                // .brand-knop-zacht in app.css, zodat het één keer
                // beschreven is en alle drie de kleuren meegaan.
                //
                // Ze heten net als bevestigAanmaken/Bewerken/Verwijderen
                // uit lib/bevestiging.ts, zodat de knop en zijn bevestiging
                // dezelfde naam dragen.
                //
                // Zie docs/architecture/formulieren-en-schuifbalken.md.

                aanmaken:
                    "bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover",
                "aanmaken-zacht":
                    "brand-knop-zacht brand-knop-aanmaken focus-visible:ring-primary/35",

                bewerken:
                    "bg-bewerken text-bewerken-contrast shadow-xs hover:brightness-110 focus-visible:ring-bewerken/30",
                "bewerken-zacht":
                    "brand-knop-zacht brand-knop-bewerken focus-visible:ring-bewerken/35",

                verwijderen:
                    "bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
                "verwijderen-zacht":
                    "brand-knop-zacht brand-knop-verwijderen focus-visible:ring-destructive/35",

                destructive:
                    "bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
                outline:
                    "border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50",
                secondary:
                    "bg-secondary text-secondary-foreground hover:bg-secondary/80",
                ghost: "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",
                link: "text-primary underline-offset-4 hover:underline",
            },
            size: {
                default: "h-9 px-4 py-2 has-[>svg]:px-3",
                sm: "h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
                lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
                icon: "size-9",
                "icon-sm": "size-8",
                "icon-lg": "size-10",
            },
        },
        defaultVariants: {
            variant: "default",
            size: "default",
        },
    },
);
export type ButtonVariants = VariantProps<typeof buttonVariants>;
