/**
 * Het Nederlands als grijze hint in een leeg Engels veld.
 *
 *     <Input v-model="vorm.title_en" :placeholder="nederlandsAlsHint(vorm.title_nl)" />
 *
 * **Waarom dit er is.** Lukt het automatisch vertalen niet -- de dienst
 * ligt eruit, of er is geen sleutel ingesteld -- dan stond de klant voor
 * een leeg Engels vakje zonder te zien waar het ook alweer over ging. In
 * een venster met tien velden moest hij ernaast gaan staan en vergelijken.
 * Nu staat zijn eigen Nederlandse tekst er grijs in.
 *
 * **Waarom een `placeholder` en niet de waarde zelf.** Een placeholder
 * wordt nooit meeverstuurd. De klant kan dus niet per ongeluk het
 * Nederlands opslaan alsóf het de Engelse vertaling is -- precies de fout
 * die we hier wilden uitsluiten. Zet je de tekst in het veld zelf, dan
 * gebeurt dat juist wél zodra hij op opslaan drukt.
 *
 * **Wanneer wél en wanneer niet.** Staat het Engelse veld op een eigen
 * stap, dan hoort het Nederlands er als citaat boven te staan --
 * `<p class="brand-bron">`, zoals bij een certificaat, een dienst, een
 * ervaring, een opleiding en een project. Dat blijft staan terwijl je
 * typt, en dat is precies wat je wilt als je niet kunt terugbladeren.
 * Deze hint is voor het andere geval: het Engelse veld staat onder het
 * Nederlandse op hetzelfde scherm, en een citaat zou dan letterlijk
 * herhalen wat twee centimeter hoger staat.
 *
 * **Waarom hij niets belooft over de site.** Wat er met een leeg Engels
 * veld gebeurt, verschilt per veld: het ene valt terug op het Nederlands,
 * het andere blijft weg op de Engelse pagina. Die uitleg staat bij het
 * veld zelf, in de regel eronder. De hint zegt alleen: dít is de tekst die
 * je aan het vertalen bent.
 *
 * Velden met een eigen Engelse standaardtekst -- de LinkedIn-knop, het
 * woord onder een cijfer -- houden hun eigen hint. Daar is die standaard
 * wél wat de bezoeker te zien krijgt, en dat weegt zwaarder.
 *
 * Zie docs/architecture/vertalingen.md.
 */
export function nederlandsAlsHint(
    nederlands: string | null | undefined,
): string | undefined {
    const tekst = (nederlands ?? '').trim();

    return tekst === '' ? undefined : tekst;
}
