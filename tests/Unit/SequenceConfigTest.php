<?php

declare(strict_types=1);

use Hatchyu\Sequence\Exceptions\SequenceConfigException;
use Hatchyu\Sequence\Exceptions\SequenceModelException;
use Hatchyu\Sequence\Support\SequenceConfig;
use Illuminate\Database\Eloquent\Model;

it('creates config from array and returns prefix and pad length', function () {
    $config = SequenceConfig::create()
        ->prefix('TEST')
        ->padLength(3)
    ;

    expect($config->getPrefix())->toBe('TEST');
    expect($config->getPadLength())->toBe(3);
});

it('throws when pad length negative', function () {
    expect(fn () => SequenceConfig::create()->padLength(-1))
        ->toThrow(SequenceConfigException::class)
    ;
});

it('throws when grouping by non-persisted model', function () {
    $model = new class() extends Model {};
    $cfg = SequenceConfig::create();

    expect(fn () => $cfg->groupBy($model))
        ->toThrow(SequenceModelException::class)
    ;
});

it('throws when max is less than min', function () {
    $cfg = SequenceConfig::create();

    expect(fn () => $cfg->range(10, 5))
        ->toThrow(SequenceConfigException::class)
    ;
});

it('stores a custom format template', function () {
    $config = SequenceConfig::create()->format('INV/' . date('Ymd') . '/?');

    expect($config->getFormat())->toBe('INV/' . date('Ymd') . '/?');
});

it('throws when format is missing the placeholder', function () {
    $cfg = SequenceConfig::create();

    expect(fn () => $cfg->format('INV/' . date('Ymd')))
        ->toThrow(SequenceConfigException::class)
    ;
});
