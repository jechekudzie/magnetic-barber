<?php

namespace App\Enums;

enum GenderTag: string
{
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';
    case Kids = 'kids';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Men',
            self::Women => 'Women',
            self::Unisex => 'Unisex',
            self::Kids => 'Kids',
        };
    }
}
