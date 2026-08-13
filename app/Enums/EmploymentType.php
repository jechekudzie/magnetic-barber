<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Employed = 'employed';
    case ChairRental = 'chair_rental';

    public function label(): string
    {
        return match ($this) {
            self::Employed => 'Employed',
            self::ChairRental => 'Chair rental',
        };
    }
}
