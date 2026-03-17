<?php

declare(strict_types=1);

use Hatchyu\Sequence\Support\NextSequence;

function sequence(string $name, string $prefix = '', int $padLength = 0): NextSequence
{
    return NextSequence::create($name, $prefix, $padLength);
}
