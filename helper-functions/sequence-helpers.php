<?php

declare(strict_types=1);

use Hatchyu\Sequence\Support\NextSequence;

if (! function_exists('sequence')) {
    function sequence(string $name, string $prefix = '', int $padLength = 0): NextSequence
    {
        return NextSequence::create($name, $prefix, $padLength);
    }
}
