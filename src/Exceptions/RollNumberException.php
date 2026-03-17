<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use RuntimeException;

class RollNumberException extends RuntimeException
{
    // Intentionally empty: subclasses carry messages for each failure mode.
}
