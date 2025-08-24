<?php

return [
    'currency' => env('PRICING_CURRENCY', 'GBP'),

    // minor units (pence)
    // Define exact matrix to avoid ambiguity:
    'rates' => [
        'summer' => [ 'weekday' => 1500, 'weekend' => 2000 ], // £15 / £20
        'winter' => [ 'weekday' => 1200, 'weekend' => 1600 ], // £12 / £16
    ],

    // months mapping (1..12)
    'summer_months' => [6,7,8],
    'winter_months' => [12,1,2],


];
