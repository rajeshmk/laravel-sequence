<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Exceptions\RollNumberConfigException;
use Hatchyu\RollNumber\Exceptions\RollNumberModelException;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Illuminate\Database\Eloquent\Model;

it('creates config from array and returns prefix and minimum length', function () {
    $config = RollNumberConfig::create('TEST', 3);

    expect($config->getPrefix())->toBe('TEST');
    expect($config->minimumLength())->toBe(3);
});

it('throws when minimum length negative', function () {
    expect(fn () => RollNumberConfig::create('', -1))
        ->toThrow(RollNumberConfigException::class)
    ;
});

it('throws when grouping by non-persisted model', function () {
    $model = new class() extends Model {};
    $cfg = RollNumberConfig::create();

    expect(fn () => $cfg->groupBy($model))
        ->toThrow(RollNumberModelException::class)
    ;
});
