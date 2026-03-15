<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use RuntimeException;
use Throwable;

class RollNumberException extends RuntimeException
{
    public static function minimumLengthMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Minimum length must be 0 or greater.'), 0, $previous);
    }

    public static function rolloverLimitMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Rollover limit must be 0 or greater.'), 0, $previous);
    }

    public static function modelKeyMustBeString(?Throwable $previous = null): self
    {
        return new self(__('Model key must be a string.'), 0, $previous);
    }

    public static function modelMustBePersisted(?Throwable $previous = null): self
    {
        return new self(__('Model must be persisted before generating roll number.'), 0, $previous);
    }

    public static function modelMustExist(?Throwable $previous = null): self
    {
        return self::modelMustBePersisted($previous);
    }

    public static function nameRequired(?Throwable $previous = null): self
    {
        return new self(__('Name required for the roll number type.'), 0, $previous);
    }

    public static function transactionNotInitiated(?Throwable $previous = null): self
    {
        return new self(__('Database transaction not yet initiated.'), 0, $previous);
    }
}
