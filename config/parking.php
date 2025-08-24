<?php
return [
    // default number of spaces per day when no override exists
    'capacity'      => (int) env('PARKING_CAPACITY', 10),
     // max number of days
    'max_stay_days' => (int) env('PARKING_MAX_STAY_DAYS', 10),
];
