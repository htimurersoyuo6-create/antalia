<?php
return [
    'insurance_enabled' => (bool) env('PET_INSURANCE_ENABLED', false),
    'insurance_provider_name' => (string) env('PET_INSURANCE_PROVIDER_NAME', ''),
    'insurance_terms' => (string) env('PET_INSURANCE_TERMS', ''),
    'daily_insurance_rate' => (float) env('PET_DAILY_INSURANCE_RATE', 0),
    'insurance_provider_share_percent' => (float) env('PET_INSURANCE_PROVIDER_SHARE_PERCENT', 100),
    'transfer_commission_percent' => (float) env('PET_TRANSFER_COMMISSION_PERCENT', 0),
    'loyalty_earn_percent' => (float) env('POTIPUAN_EARN_PERCENT', 5),
    // VIP discount reduces hotel payout by default; the optional site share is deducted from commission.
    'vip_platform_share_percent' => (float) env('VIP_PLATFORM_SHARE_PERCENT', 0),
    'vip_tiers' => [
        'bronze' => ['label' => 'Bronze', 'min_stays' => 0, 'min_spend' => 0, 'discount_percent' => 0],
        'silver' => ['label' => 'Silver', 'min_stays' => 3, 'min_spend' => 0, 'discount_percent' => 2],
        'gold' => ['label' => 'Gold', 'min_stays' => 10, 'min_spend' => 0, 'discount_percent' => 5],
    ],
];
