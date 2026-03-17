<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use Throwable;

class RollNumberModelException extends RollNumberException
{
    public const int CODE_MODEL_KEY_MUST_BE_STRING = 200;

    public const int CODE_MODEL_MUST_BE_PERSISTED = 201;

    public static function modelKeyMustBeString(?Throwable $previous = null): self
    {
        return new self(__('Model key must be a string.'), self::CODE_MODEL_KEY_MUST_BE_STRING, $previous);
    }

    public static function modelMustBePersisted(?Throwable $previous = null): self
    {
        return new self(__('Model must be persisted before generating roll number.'), self::CODE_MODEL_MUST_BE_PERSISTED, $previous);
    }

    public static function modelMustExist(?Throwable $previous = null): self
    {
        return self::modelMustBePersisted($previous);
    }
}
