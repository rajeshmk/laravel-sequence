<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Exceptions;

use Throwable;

class SequenceConfigException extends SequenceException
{
    public const int CODE_PAD_LENGTH_NEGATIVE = 100;

    public const int CODE_MIN_NEGATIVE = 101;

    public const int CODE_MAX_TOO_SMALL = 102;

    public const int CODE_INVALID_MODEL_CLASS = 103;

    public static function padLengthMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Pad length must be 0 or greater.'), self::CODE_PAD_LENGTH_NEGATIVE, $previous);
    }

    public static function minMustBeNonNegative(?Throwable $previous = null): self
    {
        return new self(__('Minimum value must be 0 or greater.'), self::CODE_MIN_NEGATIVE, $previous);
    }

    public static function maxMustBeAtLeastOne(?Throwable $previous = null): self
    {
        return new self(__('Maximum value must be 1 or greater.'), self::CODE_MAX_TOO_SMALL, $previous);
    }

    public static function invalidModelClass(string $class, ?Throwable $previous = null): self
    {
        return new self(__('Roll number model must be a valid Eloquent Model class. Given: :class', [
            'class' => $class,
        ]), self::CODE_INVALID_MODEL_CLASS, $previous);
    }
}
