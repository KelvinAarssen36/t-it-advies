<?php

namespace App\Enums;

enum SecurityOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';

    /**
     * De Nederlandse tekst, onvertaald.
     *
     * In dit project is de Nederlandse zin zelf de vertaalsleutel, dus dit
     * is ook meteen de sleutel om in een andere taal op te zoeken. Dat is
     * nodig voor de antwoordtekst op het scherm Juridisch: die wordt in
     * allebei de talen tegelijk opgebouwd, en kan dus niet leunen op de
     * taal die het portaal op dat moment aanstaat.
     */
    public function sleutel(): string
    {
        return match ($this) {
            self::Success => 'Gelukt',
            self::Failure => 'Mislukt',
        };
    }

    public function label(): string
    {
        return __($this->sleutel());
    }
}
