<?php

namespace App\Enums;

enum ClientSource: string
{
    case Walkin = 'walkin';
    case Qr = 'qr';
    case App = 'app';
    case Web = 'web';
    case Whatsapp = 'whatsapp';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Walkin => 'Walk in',
            self::Qr => 'QR scan',
            self::App => 'Mobile app',
            self::Web => 'Website',
            self::Whatsapp => 'WhatsApp',
            self::Referral => 'Referral',
        };
    }
}
