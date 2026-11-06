<?php

namespace App\Enums;

/**
 * How money actually arrives at this shop.
 *
 * Mobile money first, because that is how Zimbabwe pays. EcoCash and InnBucks
 * both settle outside this system, so a payment made either way is recorded
 * here with its transaction reference and a screenshot rather than confirmed
 * by a gateway callback.
 */
enum PaymentMethod: string
{
    case EcoCash = 'ecocash';
    case InnBucks = 'innbucks';
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EcoCash => 'EcoCash',
            self::InnBucks => 'InnBucks',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::Other => 'Other',
        };
    }

    /**
     * Whether this method settles somewhere else, so the shop has to see
     * proof before it can treat the money as received.
     */
    public function needsProof(): bool
    {
        return match ($this) {
            self::EcoCash, self::InnBucks, self::BankTransfer => true,
            self::Cash, self::Card, self::Other => false,
        };
    }

    /**
     * What a client can choose when paying for themselves. Cash and card are
     * desk methods: nobody hands cash to an app.
     */
    public function isSelfService(): bool
    {
        return $this->needsProof();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
