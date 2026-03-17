<?php

declare(strict_types=1);

use Hatchyu\Sequence\Support\NextSequence;

function sequence(string $name, string $prefix = '', int $minimumLength = 0): NextSequence
{
    return NextSequence::create($name, $prefix, $minimumLength);
}
