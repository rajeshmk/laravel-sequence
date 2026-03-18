<?php

declare(strict_types=1);

use Hatchyu\Sequence\Exceptions\SequenceOverflowException;
use Hatchyu\Sequence\Exceptions\SequenceTransactionException;
use Hatchyu\Sequence\Models\Sequence;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;

beforeEach(function () {
    $dbFile = sys_get_temp_dir() . '/sequence_test_' . uniqid('', true) . '.sqlite';
    if (file_exists($dbFile)) {
        @unlink($dbFile);
    }
    touch($dbFile);

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $dbFile,
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $container = Container::getInstance();

    $container->instance('db', $capsule->getDatabaseManager());
    $container->instance('config', new ConfigRepository([
        'sequence' => [
            'table' => 'sequences',
            'connection' => null,
            'model' => Sequence::class,
            'strict_mode' => true,
        ],
    ]));
    $container->instance('events', new Dispatcher($container));

    if (! function_exists('app')) {
        function app($abstract = null, $parameters = [])
        {
            $container = Container::getInstance();

            if ($abstract === null) {
                return $container;
            }

            return $container->make($abstract, $parameters);
        }
    }

    if (! function_exists('config')) {
        function config($key = null, $default = null)
        {
            return app('config')->get($key, $default);
        }
    }

    if (! function_exists('event')) {
        function event($event, $payload = [], $halt = false)
        {
            return app('events')->dispatch($event, $payload, $halt);
        }
    }

    Capsule::schema()->create('sequences', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('group_by', 250)->default('');
        $table->unsignedBigInteger('last_number');
        $table->timestamps();
        $table->unique(['name', 'group_by']);
    });

    $this->dbFile = $dbFile;
});

afterEach(function () {
    if (! empty($this->dbFile) && file_exists($this->dbFile)) {
        @unlink($this->dbFile);
    }
});

it('requires a DB transaction', function () {
    expect(fn () => sequence('transaction_test')->next())
        ->toThrow(SequenceTransactionException::class)
    ;
});

it('generates sequential numbers within a transaction', function () {
    $first = app('db')->transaction(fn () => sequence('normal')->next());
    $second = app('db')->transaction(fn () => sequence('normal')->next());

    expect($first)->toBe('1')
        ->and($second)->toBe('2')
    ;
});

it('throws when max is reached', function () {
    app('db')->transaction(fn () => sequence('bounded')->config(fn ($c) => $c->bounded(5, 6))->next());
    app('db')->transaction(fn () => sequence('bounded')->config(fn ($c) => $c->bounded(5, 6))->next());

    expect(fn () => app('db')->transaction(fn () => sequence('bounded')->config(fn ($c) => $c->bounded(5, 6))->next()))
        ->toThrow(SequenceOverflowException::class)
    ;
});

it('cycles when configured', function () {
    $values = [];

    for ($i = 0; $i < 3; $i++) {
        $values[] = app('db')->transaction(fn () => sequence('cycling')->config(fn ($c) => $c->cyclingRange(1, 2))->next());
    }

    expect($values)->toBe(['1', '2', '1']);
});

it('supports custom format templates with a placeholder', function () {
    $value = app('db')->transaction(
        fn () => sequence('invoice')
            ->config(fn ($c) => $c->format('INV/' . date('Ymd') . '/?')->prefix('', 4))
            ->next()
    );

    expect($value)->toBe('INV/' . date('Ymd') . '/0001');
});
