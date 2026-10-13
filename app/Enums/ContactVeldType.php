<?php

namespace App\Enums;

/**
 * Hoe een veld op het contactformulier wordt getekend.
 *
 * Dit bepaalt welk invoerelement de bezoeker krijgt, en niets anders. De
 * validatie hangt aan `ContactVeld::regels()` en niet hieraan: een
 * telefoonveld is een `tel`-invoer omdat dat op een telefoon het juiste
 * toetsenbord opent, en niet omdat de browser er iets van vindt.
 */
enum ContactVeldType: string
{
    case Tekst = 'tekst';
    case Email = 'email';
    case Telefoon = 'telefoon';
    case Onderwerp = 'onderwerp';
    case Tekstvak = 'tekstvak';
}
