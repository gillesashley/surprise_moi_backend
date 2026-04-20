<?php

namespace App\Enums;

enum VendorVisitStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Passed = 'passed';
    case Failed = 'failed';
    case Revoked = 'revoked';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Passed, self::Failed, self::Revoked, self::Submitted => true,
            self::Draft => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Awaiting admin review',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Revoked => 'Revoked',
        };
    }
}
