<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Recorded, proof not yet checked by anyone at the shop. */
    case Pending = 'pending';

    /** Somebody at the shop has seen the money or the proof. */
    case Confirmed = 'confirmed';

    /** Checked and wrong: no such transaction, or the wrong amount. */
    case Rejected = 'rejected';

    /** Money given back after a cancellation. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting check',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
            self::Refunded => 'Refunded',
        };
    }

    /** Whether this payment counts towards what the client has paid. */
    public function counts(): bool
    {
        return $this === self::Confirmed;
    }
}
