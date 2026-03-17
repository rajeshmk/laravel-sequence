<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Exceptions;

use Throwable;

class RollNumberTransactionException extends RollNumberException
{
    public const int CODE_TRANSACTION_NOT_INITIATED = 300;

    public const int CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION = 301;

    public static function transactionNotInitiated(?Throwable $previous = null): self
    {
        return new self(__('Database transaction not yet initiated.'), self::CODE_TRANSACTION_NOT_INITIATED, $previous);
    }

    public static function transactionNotInitiatedOnConnection(string $connection, ?Throwable $previous = null): self
    {
        return new self(
            __('Database transaction not yet initiated on connection ":connection".', [
                'connection' => $connection,
            ]),
            self::CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION,
            $previous
        );
    }
}
