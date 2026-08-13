<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Walkin = 'walkin';
    case Scheduled = 'scheduled';
    case HouseCall = 'house_call';

    public function label(): string
    {
        return match ($this) {
            self::Walkin => 'Walk in',
            self::Scheduled => 'Scheduled',
            self::HouseCall => 'House call',
        };
    }
}
