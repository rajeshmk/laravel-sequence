<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Exceptions;

use Throwable;

class SequenceConfigException extends SequenceException
{
    public const int CODE_PAD_LENGTH_NEGATIVE = 100;

    public const int CODE_MIN_NEGATIVE = 101;

    public const int CODE_MAX_TOO_SMALL = 102;

    public const int CODE_MAX_LESS_THAN_MIN = 103;

    public const int CODE_INVALID_MODEL_CLASS = 104;

    public const int CODE_FORMAT_PLACEHOLDER_MISSING = 105;

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

    public static function maxMustBeGreaterOrEqualMin(int $min, ?Throwable $previous = null): self
    {
        return new self(__('Maximum value must be greater than or equal to minimum value (:min).', [
            'min' => $min,
        ]), self::CODE_MAX_LESS_THAN_MIN, $previous);
    }

    public static function invalidModelClass(string $class, ?Throwable $previous = null): self
    {
        return new self(__('Sequence model must be a valid Eloquent Model class. Given: :class', [
            'class' => $class,
        ]), self::CODE_INVALID_MODEL_CLASS, $previous);
    }

    public static function formatPlaceholderMissing(?Throwable $previous = null): self
    {
        return new self(
            __('Sequence format must contain a "?" placeholder for the sequence number.'),
            self::CODE_FORMAT_PLACEHOLDER_MISSING,
            $previous,
        );
    }
}
