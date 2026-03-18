<?php

declare(strict_types=1);
use Hatchyu\Sequence\Models\Sequence;

return [
    // Table that stores sequence counters.
    'table' => env('SEQUENCE_TABLE', 'sequences'),

    // Database connection to use for the sequence table. Null uses default connection.
    'connection' => env('SEQUENCE_CONNECTION', null),

    // Model class used to represent sequences (can be customized if you extend it).
    'model' => Sequence::class,

    // Enable strict validation to fail early (name/group_by length, invalid model class, etc.).
    'strict_mode' => env('SEQUENCE_STRICT_MODE', true),
];
