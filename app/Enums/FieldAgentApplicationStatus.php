<?php

namespace App\Enums;

enum FieldAgentApplicationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /** @return array<int, string> */
    public static function reviewableStatuses(): array
    {
        return [self::Pending->value, self::UnderReview->value];
    }

    /** @return array<int, string> */
    public static function nonRejectedStatuses(): array
    {
        return [self::Pending->value, self::UnderReview->value, self::Approved->value];
    }
}
