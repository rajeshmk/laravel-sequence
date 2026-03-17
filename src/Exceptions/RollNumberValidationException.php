<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use Throwable;

class RollNumberValidationException extends RollNumberException
{
    public const int CODE_NAME_REQUIRED = 400;

    public const int CODE_NAME_TOO_LONG = 401;

    public const int  CODE_GROUP_BY_TOKEN_TOO_LONG = 402;

    public static function nameRequired(?Throwable $previous = null): self
    {
        return new self(__('Name required for the roll number type.'), self::CODE_NAME_REQUIRED, $previous);
    }

    public static function nameTooLong(int $limit, ?Throwable $previous = null): self
    {
        return new self(__('Name must be :limit characters or fewer.', [
            'limit' => $limit,
        ]), self::CODE_NAME_TOO_LONG, $previous);
    }

    public static function groupByTokenTooLong(int $limit, ?Throwable $previous = null): self
    {
        return new self(__('Group key must be :limit characters or fewer.', [
            'limit' => $limit,
        ]), self::CODE_GROUP_BY_TOKEN_TOO_LONG, $previous);
    }
}
