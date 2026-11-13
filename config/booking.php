<?php
return [
    'platform_commission_percentage' => (float) env('PLATFORM_COMMISSION_PERCENTAGE', 15.00),
    'transfer_road_factor' => (float) env('PET_TRANSFER_ROAD_FACTOR', 1.25),
    'transfer_base_fee' => (float) env('PET_TRANSFER_BASE_FEE', 150),
    'transfer_per_km' => (float) env('PET_TRANSFER_PER_KM', 15),
    'transfer_osrm_url' => (string) env('PET_TRANSFER_OSRM_URL', 'https://router.project-osrm.org'),
];
