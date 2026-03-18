<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Exceptions;

use Throwable;

class SequenceOverflowException extends SequenceException
{
    public const int CODE_SEQUENCE_OVERFLOW = 500;

    public static function limitReached(string $name, int $max, ?Throwable $previous = null): self
    {
        return new self(__('Sequence limit reached for :name at :max', [
            'name' => $name,
            'max' => $max,
        ]), self::CODE_SEQUENCE_OVERFLOW, $previous);
    }
}
