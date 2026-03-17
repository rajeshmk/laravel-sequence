<?php

declare(strict_types=1);

use Hatchyu\Sequence\Exceptions\SequenceConfigException;
use Hatchyu\Sequence\Exceptions\SequenceModelException;
use Hatchyu\Sequence\Exceptions\SequenceTransactionException;
use Hatchyu\Sequence\Exceptions\SequenceValidationException;

it('assigns config exception codes', function () {
    $ex = SequenceConfigException::minimumLengthMustBeNonNegative();
    expect($ex)->toBeInstanceOf(SequenceConfigException::class)
        ->and($ex->getCode())->toBe(SequenceConfigException::CODE_MIN_LENGTH_NEGATIVE)
    ;

    $ex = SequenceConfigException::rolloverLimitMustBeNonNegative();
    expect($ex->getCode())->toBe(SequenceConfigException::CODE_ROLLOVER_LIMIT_NEGATIVE);

    $ex = SequenceConfigException::invalidModelClass('App\\Models\\Sequence');
    expect($ex->getCode())->toBe(SequenceConfigException::CODE_INVALID_MODEL_CLASS);
});

it('assigns model exception codes', function () {
    $ex = SequenceModelException::modelKeyMustBeString();
    expect($ex)->toBeInstanceOf(SequenceModelException::class)
        ->and($ex->getCode())->toBe(SequenceModelException::CODE_MODEL_KEY_MUST_BE_STRING)
    ;

    $ex = SequenceModelException::modelMustBePersisted();
    expect($ex->getCode())->toBe(SequenceModelException::CODE_MODEL_MUST_BE_PERSISTED);
});

it('assigns transaction exception codes', function () {
    $ex = SequenceTransactionException::transactionNotInitiated();
    expect($ex)->toBeInstanceOf(SequenceTransactionException::class)
        ->and($ex->getCode())->toBe(SequenceTransactionException::CODE_TRANSACTION_NOT_INITIATED)
    ;

    $ex = SequenceTransactionException::transactionNotInitiatedOnConnection('sqlite');
    expect($ex->getCode())->toBe(SequenceTransactionException::CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION);
});

it('assigns validation exception codes', function () {
    $ex = SequenceValidationException::nameRequired();
    expect($ex)->toBeInstanceOf(SequenceValidationException::class)
        ->and($ex->getCode())->toBe(SequenceValidationException::CODE_NAME_REQUIRED)
    ;

    $ex = SequenceValidationException::nameTooLong(10);
    expect($ex->getCode())->toBe(SequenceValidationException::CODE_NAME_TOO_LONG);

    $ex = SequenceValidationException::groupByTokenTooLong(10);
    expect($ex->getCode())->toBe(SequenceValidationException::CODE_GROUP_BY_TOKEN_TOO_LONG);
});
