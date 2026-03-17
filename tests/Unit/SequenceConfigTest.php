<?php

declare(strict_types=1);

use Hatchyu\Sequence\Exceptions\SequenceConfigException;
use Hatchyu\Sequence\Exceptions\SequenceModelException;
use Hatchyu\Sequence\Support\SequenceConfig;
use Illuminate\Database\Eloquent\Model;

it('creates config from array and returns prefix and minimum length', function () {
    $config = SequenceConfig::create('TEST', 3);

    expect($config->getPrefix())->toBe('TEST');
    expect($config->minimumLength())->toBe(3);
});

it('throws when minimum length negative', function () {
    expect(fn () => SequenceConfig::create('', -1))
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
