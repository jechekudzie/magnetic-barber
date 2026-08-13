<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::InProgress => 'In the chair',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /**
     * Statuses that still hold a slot, so a conflicting booking must be
     * refused against them.
     *
     * @return list<string>
     */
    public static function blocking(): array
    {
        return [
            self::Pending->value,
            self::Confirmed->value,
            self::CheckedIn->value,
            self::InProgress->value,
        ];
    }
}
