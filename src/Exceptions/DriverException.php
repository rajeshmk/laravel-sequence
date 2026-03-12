<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use Throwable;

final class DriverException extends RollNumberException
{
    public static function transactionNotInitiated(?Throwable $previous = null): self
    {
        return new self(__('Database transaction not yet initiated.'), 0, $previous);
    }
}
