<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Exceptions;

use RuntimeException;

class SequenceException extends RuntimeException
{
    // Intentionally empty: subclasses carry messages for each failure mode.
}
