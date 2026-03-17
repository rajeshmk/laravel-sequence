<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Support\NextRollNumber;

function roll_number(string $name, string $prefix = '', int $minimumLength = 0): NextRollNumber
{
    return NextRollNumber::create($name, $prefix, $minimumLength);
}
