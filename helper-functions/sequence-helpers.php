<?php

declare(strict_types=1);

use Hatchyu\Sequence\Support\NextSequence;

if (! function_exists('sequence')) {
    function sequence(string $name): NextSequence
    {
        return NextSequence::create($name);
    }
}
