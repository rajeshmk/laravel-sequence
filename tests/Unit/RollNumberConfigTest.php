<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Illuminate\Database\Eloquent\Model;

it('creates config from array and returns prefix and minimum length', function () {
    //
});

it('throws when minimum length negative', function () {
    $this->expectException(RollNumberException::class);

    // construct directly to trigger validation
    new RollNumberConfig('', -1);
});

it('throws when grouping by non-persisted model', function () {
    $model = new class() extends Model {};
    $cfg = new RollNumberConfig();

    $this->expectException(RollNumberException::class);

    $cfg->groupBy($model);
});
