<?php

return [
    'reference_prefix'        => env('BOOKING_REFERENCE_PREFIX', 'BK-'),
    'quote_horizon_days'      => (int) env('BOOKING_QUOTE_HORIZON_DAYS', 365),
    'weekend_days'            => [6, 0], // 0=Sun, 6=Sat (Carbon: 0..6)
    'default_season'          => env('PRICING_DEFAULT_SEASON', 'winter'), // when month not in summer/winter
];
