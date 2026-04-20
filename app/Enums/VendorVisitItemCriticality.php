<?php

namespace App\Enums;

enum VendorVisitItemCriticality: string
{
    case Critical = 'critical';
    case Informational = 'informational';
}
