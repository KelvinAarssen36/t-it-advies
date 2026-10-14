/**
 * "1250" wordt "1.250".
 *
 * De duizendtalscheiding verschilt per taal -- een punt in het
 * Nederlands, een komma in het Engels -- dus het teken komt van de
 * aanroeper. Die weet welke taal er staat; deze functie niet.
 *
 * **Dit staat bewust los van `motion.ts`.** Het stond daar eerst, naast
 * `vulStatistieken`, en dat leek logisch: die schrijft de tussenstanden en
 * de componenten de beginwaarde, en die twee moeten er hetzelfde uitzien.
 * Maar de statistiekcomponenten worden ook in het portaal gebruikt, in het
 * voorbeeld van het beheerscherm -- en één import uit `motion.ts` trok
 * daar de hele animatiechunk mee: GSAP, drie plugins en Lenis, 158 kB voor
 * een functie van drie regels. Zie docs/architecture/frontend-en-animatie.md.
 */
export function formatteerGetal(waarde: number, scheiding = '.'): string {
    return String(Math.round(waarde)).replace(
        /\B(?=(\d{3})+(?!\d))/g,
        scheiding,
    );
}
