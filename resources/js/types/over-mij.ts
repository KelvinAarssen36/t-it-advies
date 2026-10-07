/**
 * De vormen van de module "Over mij".
 *
 * Twee soorten per ding, net als bij de andere modules: `...OpDeSite` is
 * wat de bezoeker krijgt (taal al gekozen), `...Rij` is wat het
 * beheerscherm krijgt (beide talen los).
 *
 * Zie docs/architecture/modules/over-mij.md.
 */

/** Het beeld bij "Over mij": het medaillon of een eigen foto. */
export type OverMijFoto = {
    src: string;
    /**
     * De maten van het medaillon, of `null` bij een eigen foto.
     *
     * Het medaillon bestaat in vier breedtes; een eigen foto wordt als één
     * vierkant van 640 bewaard. Zie App\Models\AboutSetting::foto().
     */
    srcset: string | null;
    /** Of dit de eigen foto van de eigenaar is, of het medaillon. */
    eigen: boolean;
};

/** Eén punt onder het verhaal, zoals het beheerscherm het toont. */
export type PuntRij = {
    id: number;
    /** SortableList eist een string als sleutel. */
    key: string;
    text_nl: string;
    text_en: string | null;
};

/** Alle velden van "Over mij", zoals het beheerscherm ze bewerkt. */
export type OverMijInstelling = {
    summary_nl: string | null;
    summary_en: string | null;
    page_enabled: boolean;
    page_title_nl: string | null;
    page_title_en: string | null;
    page_intro_nl: string | null;
    page_intro_en: string | null;
    story_nl: string | null;
    story_en: string | null;
    automatisch_vertaald: boolean;
    foto: OverMijFoto;
};

export type OverMijOpties = {
    /**
     * De grenzen, van de server.
     *
     * Ze staan hier niet apart maar komen uit `AboutSetting`, want het
     * scherm laat er tellers op meelopen. Zou een getal hier eigen staan,
     * dan krijgt de eigenaar een foutmelding op iets wat het scherm net
     * nog goedkeurde.
     */
    samenvattingMax: number;
    verhaalMax: number;
    puntenMax: number;
};

/**
 * Het korte blok, zoals de bezoeker het ziet.
 *
 * `pagina` zegt of er een knop naar de aparte pagina onder komt. Dat komt
 * van de server en niet van `page_enabled` alleen: die pagina bestaat pas
 * als er ook een verhaal in staat. Zie
 * App\Models\AboutSetting::paginaStaatKlaar().
 */
export type OverMijOpDeSite = {
    samenvatting: string;
    foto: OverMijFoto;
    pagina: boolean;
};
