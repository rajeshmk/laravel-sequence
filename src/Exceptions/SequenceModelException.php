<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Exceptions;

use Throwable;

class SequenceModelException extends SequenceException
{
    public const int CODE_MODEL_KEY_MUST_BE_STRING = 200;

    public const int CODE_MODEL_MUST_BE_PERSISTED = 201;

    public static function modelKeyMustBeString(?Throwable $previous = null): self
    {
        return new self(__('Model key must be a string.'), self::CODE_MODEL_KEY_MUST_BE_STRING, $previous);
    }

    public static function modelMustBePersisted(?Throwable $previous = null): self
    {
        return new self(__('Model must be persisted before generating a sequence number.'), self::CODE_MODEL_MUST_BE_PERSISTED, $previous);
    }

    public static function modelMustExist(?Throwable $previous = null): self
    {
        return self::modelMustBePersisted($previous);
    }
}
