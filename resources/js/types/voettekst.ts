/**
 * De voet van de publieke site.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */

/**
 * Het telefoonnummer, zoals de bezoeker het krijgt.
 *
 * Twee vormen, want ze verschillen: `tekst` is hoe de eigenaar het heeft
 * opgeschreven -- met spaties, want zo leest een nummer -- en `link` is
 * hetzelfde nummer zonder alles wat een telefoon niet kan bellen.
 */
export type VoetTelefoon = {
    tekst: string;
    link: string;
};

/**
 * Wat de eigenaar zelf in zijn voet heeft gezet.
 *
 * **Elk veld mag `null` zijn, en dan hoort er niets te staan.** De server
 * stuurt geen lege strings; zie FooterSetting::voorDeSite(). De browser
 * hoeft dus nergens te controleren óf er iets staat -- alleen of het veld
 * gevuld is.
 */
export type VoetOpDeSite = {
    tagline: string | null;
    telefoon: VoetTelefoon | null;
    plaats: string | null;
    kvk: string | null;
    btw: string | null;
};

/** Alle velden los, zoals het beheerscherm ze bewerkt. */
export type VoetInstelling = {
    tagline_nl: string | null;
    tagline_en: string | null;
    phone: string | null;
    region_nl: string | null;
    region_en: string | null;
    kvk: string | null;
    vat: string | null;
    automatisch_vertaald: boolean;
};

/** De grenzen waarmee de server rekent, zodat het scherm hetzelfde doet. */
export type VoetOpties = {
    taglineMax: number;
    plaatsMax: number;
    telefoonMax: number;
    nummerMax: number;
    kvkCijfers: number;
};
