<?php

declare(strict_types=1);
use Hatchyu\RollNumber\Models\RollNumber;

return [
    // Table that stores roll number counters.
    'table' => env('ROLL_NUMBER_TABLE', 'roll_numbers'),

    // Database connection to use for the roll number table. Null uses default connection.
    'connection' => env('ROLL_NUMBER_CONNECTION', null),

    // Model class used to represent roll numbers (can be customized if you extend it).
    'model' => RollNumber::class,
];
