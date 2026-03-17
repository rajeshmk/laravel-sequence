<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use Throwable;

class RollNumberConfigException extends RollNumberException
{
    public const int CODE_MIN_LENGTH_NEGATIVE = 100;

    public const int CODE_ROLLOVER_LIMIT_NEGATIVE = 101;

    public const int CODE_INVALID_MODEL_CLASS = 102;

    public static function minimumLengthMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Minimum length must be 0 or greater.'), self::CODE_MIN_LENGTH_NEGATIVE, $previous);
    }

    public static function rolloverLimitMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Rollover limit must be 0 or greater.'), self::CODE_ROLLOVER_LIMIT_NEGATIVE, $previous);
    }

    public static function invalidModelClass(string $class, ?Throwable $previous = null): self
    {
        return new self(__('Roll number model must be a valid Eloquent Model class. Given: :class', [
            'class' => $class,
        ]), self::CODE_INVALID_MODEL_CLASS, $previous);
    }
}
