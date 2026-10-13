/**
 * De bezoekcijfers, zoals het beheerscherm ze krijgt.
 *
 * Alles is al opgeteld door `App\Support\Bezoek\Bezoekcijfers`. Dit scherm
 * rekent niets: het tekent wat er binnenkomt.
 *
 * **De waarden van een uitsplitsing zijn sleutels en geen opschriften** --
 * `linkedin`, `mobiel`, `nl`. Het portaal is tweetalig, dus het opschrift
 * wordt in de frontend gekozen; een onbekende verwijzer staat er als zijn
 * host en komt dus ongewijzigd op het scherm.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */

/** Eén dag in de grafiek. Ook de dagen zonder bezoek staan erin. */
export type BezoekDag = {
    /** ISO-datum, bijvoorbeeld `2026-10-01`. */
    dag: string;
    bezoekers: number;
    weergaven: number;
};

export type BezoekTotalen = {
    bezoekers: number;
    weergaven: number;
    berichten: number;
};

/**
 * Het verschil met de vorige periode, in procenten.
 *
 * `null` betekent "niet te vergelijken": de vorige periode was nul, en dan
 * is elk percentage onzin. Het scherm laat dan niets zien in plaats van een
 * getal dat nergens op staat.
 */
export type BezoekVerschil = {
    bezoekers: number | null;
    weergaven: number | null;
    berichten: number | null;
};

export type BezoekRegel = {
    naam: string;
    aantal: number;
};

export type BezoekCijfers = {
    dagen: number;
    reeks: BezoekDag[];
    totalen: BezoekTotalen;
    verschil: BezoekVerschil;
    /** Per soort uitsplitsing een lijst, grootste eerst. */
    dimensies: Record<string, BezoekRegel[]>;
    /** De eerste dag waarvan we iets weten, of null als er nog niets is. */
    eerste: string | null;
};
