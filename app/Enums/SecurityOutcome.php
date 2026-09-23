<?php

namespace App\Enums;

enum SecurityOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => __('Gelukt'),
            self::Failure => __('Mislukt'),
        };
    }
}
