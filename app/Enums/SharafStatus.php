<?php

namespace App\Enums;

enum SharafStatus: string
{
    case PENDING = 'pending';
    case BS_APPROVED = 'bs_approved';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::BS_APPROVED => 'BS Approved',
            self::CONFIRMED => 'Confirmed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }
}
