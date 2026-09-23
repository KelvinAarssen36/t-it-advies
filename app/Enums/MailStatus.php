<?php

namespace App\Enums;

enum MailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Deferred = 'deferred';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';

    /**
     * Hoe ver een status in de levensloop van een mail staat.
     *
     * Problemen (bounce, klacht, mislukt) krijgen de hoogste waarde: die
     * mogen nooit worden overschreven door een later binnenkomend
     * delivered- of opened-event.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Sent => 1,
            self::Deferred => 2,
            self::Delivered => 3,
            self::Opened => 4,
            self::Clicked => 5,
            self::Bounced, self::Complained, self::Failed => 100,
        };
    }

    public function outranks(?self $other): bool
    {
        return $other === null || $this->rank() > $other->rank();
    }

    public function isProblem(): bool
    {
        return in_array($this, [self::Bounced, self::Complained, self::Failed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('In wachtrij'),
            self::Sent => __('Verzonden'),
            self::Delivered => __('Afgeleverd'),
            self::Opened => __('Geopend'),
            self::Clicked => __('Aangeklikt'),
            self::Deferred => __('Uitgesteld'),
            self::Bounced => __('Gebounced'),
            self::Complained => __('Als spam gemeld'),
            self::Failed => __('Mislukt'),
        };
    }
}
