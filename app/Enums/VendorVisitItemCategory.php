<?php

namespace App\Enums;

enum VendorVisitItemCategory: string
{
    case Identity = 'identity';
    case Physical = 'physical';
    case Documents = 'documents';
    case Financial = 'financial';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Identity',
            self::Physical => 'Physical verification',
            self::Documents => 'Documents',
            self::Financial => 'Financial',
        };
    }
}
