<?php

namespace App\Enums;

enum PlanType: string
{
    case Unlimited = 'unlimited';
    case SessionPack = 'session_pack';

    public function label(): string
    {
        return match ($this) {
            self::Unlimited => 'Unlimited',
            self::SessionPack => 'Session pack',
        };
    }
}
