<?php

namespace App\Enums;

enum UserType: string
{
    case BS = 'BS';
    case ADMIN = 'Admin';
    case HELP_DESK = 'Help Desk';
    case ANJUMAN = 'Anjuman';

    public function label(): string
    {
        return match($this) {
            self::BS => 'BS',
            self::ADMIN => 'Admin',
            self::HELP_DESK => 'Help Desk',
            self::ANJUMAN => 'Anjuman',
        };
    }
}
