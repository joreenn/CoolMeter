<?php
return [
    'model' => 'LG LA100ENT', 'rated_watts' => 1030, 'max_watts' => 1250,
    // Modelling assumptions, not measured LG performance. Eco savings are not presumed.
    'normal_watts' => 670, 'eco_watts' => 670, 'cooldown_minutes' => 20, 'uncertainty_percent' => 35,
    'rate' => 13.33, 'rate_from' => '2026-10-11', 'rate_until' => '2026-11-10',
    'rate_source' => 'News Fort reporting Davao Light’s October 2026 advisory',
    'rate_source_url' => 'https://www.newsfortph.com/local-news/davao-light-residential-rate-rises-by-0-09-per-kwh-for-october-billing',
    'rate_status' => 'reported', 'version' => '1.0',
];
