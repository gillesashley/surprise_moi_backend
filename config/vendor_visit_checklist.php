<?php

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;

return [
    /*
    |--------------------------------------------------------------------------
    | Vendor visit checklist items
    |--------------------------------------------------------------------------
    |
    | The single source of truth for what rows are seeded into
    | `vendor_visit_items` when a field agent starts a visit.
    |
    | 'seed_if' is one of:
    |   'always'            — row is always seeded
    |   'has_business_cert' — seed only if VendorApplication.has_business_certificate = true
    |   'has_tin'           — seed only if VendorApplication.tin_number is non-empty
    */

    'items' => [
        [
            'key' => 'identity.person_matches_ghana_card',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the person in front of you match the Ghana Card photo on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'identity.name_matches_records',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the name on the physical Ghana Card match the name on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.location_is_real',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Is the claimed business address a real, findable location?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.business_name_matches',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Does the business at this address match the business name on file?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'physical.business_is_operational',
            'category' => VendorVisitItemCategory::Physical->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Is there signage, stock, or active service — a real going concern?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'documents.business_cert_seen',
            'category' => VendorVisitItemCategory::Documents->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Have you seen the physical business certificate, and does it match the file?',
            'seed_if' => 'has_business_cert',
        ],
        [
            'key' => 'documents.tin_seen',
            'category' => VendorVisitItemCategory::Documents->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'label' => 'Have you seen the physical TIN document, and does it match the file?',
            'seed_if' => 'has_tin',
        ],
        [
            'key' => 'financial.phone_reachable',
            'category' => VendorVisitItemCategory::Financial->value,
            'criticality' => VendorVisitItemCriticality::Informational->value,
            'label' => 'Did you call the registered phone and have it ring / be answered?',
            'seed_if' => 'always',
        ],
        [
            'key' => 'financial.momo_test_received',
            'category' => VendorVisitItemCategory::Financial->value,
            'criticality' => VendorVisitItemCriticality::Informational->value,
            'label' => 'Did your GHS 1 test MoMo reach the registered mobile money number?',
            'seed_if' => 'always',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge validity period (months)
    |--------------------------------------------------------------------------
    */
    'badge_validity_months' => 12,
];
