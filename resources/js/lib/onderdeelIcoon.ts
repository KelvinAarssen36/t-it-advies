import {
    Activity,
    AppWindow,
    ArrowRightLeft,
    BrickWall,
    Cable,
    Calculator,
    ChartNoAxesColumn,
    Check,
    ClipboardList,
    Cloud,
    Code,
    Database,
    DatabaseBackup,
    FileText,
    Fingerprint,
    FlaskConical,
    GraduationCap,
    Handshake,
    KeyRound,
    Laptop,
    LifeBuoy,
    LockKeyhole,
    MessageSquare,
    Network,
    Plug,
    RefreshCw,
    Server,
    Settings2,
    ShieldCheck,
    Siren,
    Wifi,
    Workflow,
} from '@lucide/vue';
import type { Component } from 'vue';

/**
 * Een pictogram bij een onderdeel van een dienst ("monitoring", "back-ups").
 *
 * **De onderdelen zijn vrije tekst**, in twee talen, door de klant getypt.
 * Er is geen veld om per onderdeel een pictogram te kiezen, en dat komt er
 * niet om dit ene doel: vier pictogrammen kiezen voor vier steekwoorden is
 * werk dat niemand doet, en een leeg veld zou dan overal hetzelfde tonen.
 * Daarom kiest de site er zelf een, op grond van het woord.
 *
 * **Een korte, vaste woordenlijst, en bij twijfel een vinkje.** Elke regel
 * is een woordbegin in het Nederlands én het Engels, zodat "back-ups" en
 * "backups" hetzelfde teken krijgen en de twee talen elkaar niet tegenspreken.
 * Wat niet herkend wordt, krijgt een vinkje: dat past bij elk steekwoord
 * ("dit hoort erbij"), terwijl een verkeerd geraden pictogram iets beweert.
 *
 * De volgorde telt: de eerste regel die past wint, dus de specifieke staan
 * boven de algemene ("dataverhuizing" is een migratie, geen database;
 * "back-upcontrole" is een back-up, geen controle). Komt er een woord bij
 * dat een eigen teken verdient, zet het dan hier, boven de regel waar het
 * nu onder valt.
 *
 * Alleen voor de publieke site; het portaal toont de onderdelen als tekst.
 * Zie docs/architecture/modules/diensten.md.
 */
const REGELS: ReadonlyArray<readonly [RegExp, Component]> = [
    [/\b(back-?ups?|herstel|restore)/i, DatabaseBackup],
    [/\b(monitor|bewak|observ|uptime|signal)/i, Activity],
    [/\b(updates?|patch|bijwerk)/i, RefreshCw],
    [/\b(storing|incident|calamit|outage|verstoring)/i, Siren],
    [/\b(migrat|verhuiz|overstap|overzet)/i, ArrowRightLeft],
    [/\b(wifi|wi-fi|draadloos|wireless)/i, Wifi],
    [/\b(bekabel|cabling|kabel|cable)/i, Cable],
    [/\b(firewall)/i, BrickWall],
    [/\b(vpn|toegang|access)/i, LockKeyhole],
    [/\b(tweestap|two-factor|2fa|mfa|authenticat)/i, Fingerprint],
    [/\b(licen)/i, KeyRound],
    [/\b(proef|rehears|test)/i, FlaskConical],
    [/\b(bewustwording|awareness|training|cursus|opleid)/i, GraduationCap],
    [/\b(beveilig|security|secur)/i, ShieldCheck],
    [/\b(cloud|azure|aws)/i, Cloud],
    [/\b(microsoft|365|office|google workspace)/i, AppWindow],
    [/\b(server|hosting)/i, Server],
    [/\b(netwerk|network)/i, Network],
    [/\b(koppeling|integrat|api)/i, Plug],
    [/\b(document)/i, FileText],
    [/\b(inricht|setup|configur|installat)/i, Settings2],
    [/\b(invent|inventar)/i, ClipboardList],
    [/\b(leverancier|supplier|vendor|inkoop)/i, Handshake],
    [/\b(kosten|cost|budget|begrot|offerte)/i, Calculator],
    [/\b(mening|opinion|advie|advice|sparr)/i, MessageSquare],
    [/\b(rapport|report|dashboard|cijfers|figures)/i, ChartNoAxesColumn],
    [/\b(data|database)/i, Database],
    [/\b(laptop|werkplek|workplace|device|apparat)/i, Laptop],
    [/\b(support|helpdesk|ondersteun)/i, LifeBuoy],
    [/\b(automatis|automat|workflow|proces)/i, Workflow],
    [/\b(software|code|ontwikkel|develop|maatwerk)/i, Code],
];

export function onderdeelIcoon(tekst: string): Component {
    return REGELS.find(([patroon]) => patroon.test(tekst))?.[1] ?? Check;
}
