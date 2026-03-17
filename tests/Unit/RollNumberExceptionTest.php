<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Exceptions\RollNumberConfigException;
use Hatchyu\RollNumber\Exceptions\RollNumberModelException;
use Hatchyu\RollNumber\Exceptions\RollNumberTransactionException;
use Hatchyu\RollNumber\Exceptions\RollNumberValidationException;

it('assigns config exception codes', function () {
    $ex = RollNumberConfigException::minimumLengthMustBeNonNegative();
    expect($ex)->toBeInstanceOf(RollNumberConfigException::class)
        ->and($ex->getCode())->toBe(RollNumberConfigException::CODE_MIN_LENGTH_NEGATIVE)
    ;

    $ex = RollNumberConfigException::rolloverLimitMustBeNonNegative();
    expect($ex->getCode())->toBe(RollNumberConfigException::CODE_ROLLOVER_LIMIT_NEGATIVE);

    $ex = RollNumberConfigException::invalidModelClass('App\\Models\\RollNumber');
    expect($ex->getCode())->toBe(RollNumberConfigException::CODE_INVALID_MODEL_CLASS);
});

it('assigns model exception codes', function () {
    $ex = RollNumberModelException::modelKeyMustBeString();
    expect($ex)->toBeInstanceOf(RollNumberModelException::class)
        ->and($ex->getCode())->toBe(RollNumberModelException::CODE_MODEL_KEY_MUST_BE_STRING)
    ;

    $ex = RollNumberModelException::modelMustBePersisted();
    expect($ex->getCode())->toBe(RollNumberModelException::CODE_MODEL_MUST_BE_PERSISTED);
});

it('assigns transaction exception codes', function () {
    $ex = RollNumberTransactionException::transactionNotInitiated();
    expect($ex)->toBeInstanceOf(RollNumberTransactionException::class)
        ->and($ex->getCode())->toBe(RollNumberTransactionException::CODE_TRANSACTION_NOT_INITIATED)
    ;

    $ex = RollNumberTransactionException::transactionNotInitiatedOnConnection('sqlite');
    expect($ex->getCode())->toBe(RollNumberTransactionException::CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION);
});

it('assigns validation exception codes', function () {
    $ex = RollNumberValidationException::nameRequired();
    expect($ex)->toBeInstanceOf(RollNumberValidationException::class)
        ->and($ex->getCode())->toBe(RollNumberValidationException::CODE_NAME_REQUIRED)
    ;

    $ex = RollNumberValidationException::nameTooLong(10);
    expect($ex->getCode())->toBe(RollNumberValidationException::CODE_NAME_TOO_LONG);

    $ex = RollNumberValidationException::groupByTokenTooLong(10);
    expect($ex->getCode())->toBe(RollNumberValidationException::CODE_GROUP_BY_TOKEN_TOO_LONG);
});
