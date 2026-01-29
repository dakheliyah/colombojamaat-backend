<?php

namespace App\Enums;

enum WajebaatGroupType: string
{
    case BUSINESS_GROUPING = 'business_grouping';
    case PERSONAL_GROUPING = 'personal_grouping';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match($this) {
            self::BUSINESS_GROUPING => 'Business Grouping',
            self::PERSONAL_GROUPING => 'Personal Grouping',
            self::ORGANIZATION => 'Organization',
        };
    }
}
