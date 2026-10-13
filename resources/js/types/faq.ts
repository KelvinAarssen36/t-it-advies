/**
 * De vormen van de module FAQ.
 *
 * Twee soorten per ding, net als bij de andere modules: `...OpDeSite` is
 * wat de bezoeker krijgt (taal al gekozen), `...Rij` is wat het
 * beheerscherm krijgt (beide talen los).
 *
 * Zie docs/architecture/modules/faq.md.
 */

/** Eén vraag, zoals het beheerscherm hem toont. */
export type VraagRij = {
    id: number;
    /** SortableList eist een string als sleutel. */
    key: string;
    published: boolean;
    question_nl: string;
    question_en: string | null;
    answer_nl: string;
    answer_en: string | null;
    automatisch_vertaald: boolean;
};

/**
 * Eén vraag, zoals de bezoeker hem ziet.
 *
 * De keuze tussen Nederlands en Engels is op de server al gemaakt, en
 * allebei de velden vallen terug op het Nederlands: een vraag die opengaat
 * en leeg is, is erger dan geen vraag. Zie App\Models\FaqItem.
 */
export type VraagOpDeSite = {
    id: number;
    vraag: string;
    antwoord: string;
};

export type FaqOpties = {
    /**
     * De hoogste lengte van een antwoord.
     *
     * Komt van de server, uit `FaqItemRequest::ANTWOORD_MAX`. Zou dit
     * getal hier apart staan, dan krijgt de eigenaar een foutmelding op
     * iets wat het scherm net nog goedkeurde.
     */
    antwoordMax: number;
};
