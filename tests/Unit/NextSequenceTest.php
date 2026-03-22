<?php

declare(strict_types=1);

use Hatchyu\Sequence\Exceptions\SequenceOverflowException;
use Hatchyu\Sequence\Exceptions\SequenceTransactionException;
use Hatchyu\Sequence\Models\Sequence;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Str;

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

    Capsule::schema()->create('branches', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
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
        $values[] = app('db')->transaction(fn () => sequence('cycling')->cyclingRange(1, 2)->next());
    }

    expect($values)->toBe(['1', '2', '1']);
});

it('supports direct forwarding for bounded ranges', function () {
    app('db')->transaction(fn () => sequence('bounded')->bounded(5, 6)->next());
    app('db')->transaction(fn () => sequence('bounded')->bounded(5, 6)->next());

    expect(fn () => app('db')->transaction(fn () => sequence('bounded')->bounded(5, 6)->next()))
        ->toThrow(SequenceOverflowException::class)
    ;
});

it('supports fluent prefix and pad length configuration', function () {
    $value = app('db')->transaction(
        fn () => sequence('category_code')
            ->prefix('C')
            ->padLength(3)
            ->next()
    );

    expect($value)->toBe('C001');
});

it('supports custom format templates with a placeholder', function () {
    $value = app('db')->transaction(
        fn () => sequence('invoice')
            ->format('INV/' . date('Ymd') . '/?')
            ->padLength(4)
            ->next()
    );

    expect($value)->toBe('INV/' . date('Ymd') . '/0001');
});

it('supports custom format callbacks', function () {
    Str::createRandomStringsUsing(fn (): string => 'XYZ');

    $value = app('db')->transaction(
        fn () => sequence('tickets')
            ->padLength(4)
            ->format(fn (string $number): string => 'TIC-' . $number . '-' . Str::random(3))
            ->next()
    );

    Str::createRandomStringsNormally();

    expect($value)->toBe('TIC-0001-XYZ');
});

it('does not reset the counter when only the prefix changes', function () {
    $first = app('db')->transaction(
        fn () => sequence('batch_code')
            ->prefix('2026')
            ->padLength(2)
            ->next()
    );

    $second = app('db')->transaction(
        fn () => sequence('batch_code')
            ->prefix('2027')
            ->padLength(2)
            ->next()
    );

    expect($first)->toBe('202601')
        ->and($second)->toBe('202702')
    ;
});

it('does not reset the counter when only the format changes', function () {
    $first = app('db')->transaction(
        fn () => sequence('invoice')
            ->format('INV/20260318/?')
            ->padLength(4)
            ->next()
    );

    $second = app('db')->transaction(
        fn () => sequence('invoice')
            ->format('INV/20260319/?')
            ->padLength(4)
            ->next()
    );

    expect($first)->toBe('INV/20260318/0001')
        ->and($second)->toBe('INV/20260319/0002')
    ;
});

it('resets the counter when grouped by year-like values', function () {
    $first = app('db')->transaction(
        fn () => sequence('batch_code')
            ->prefix('2026')
            ->padLength(2)
            ->groupBy('2026')
            ->next()
    );

    $second = app('db')->transaction(
        fn () => sequence('batch_code')
            ->prefix('2026')
            ->padLength(2)
            ->groupBy('2026')
            ->next()
    );

    $third = app('db')->transaction(
        fn () => sequence('batch_code')
            ->prefix('2027')
            ->padLength(2)
            ->groupBy('2027')
            ->next()
    );

    expect($first)->toBe('202601')
        ->and($second)->toBe('202602')
        ->and($third)->toBe('202701')
    ;
});

it('supports convenience grouping helpers', function () {
    $yearlyA = app('db')->transaction(
        fn () => sequence('yearly_code')
            ->prefix(date('Y'))
            ->padLength(2)
            ->groupByYear()
            ->next()
    );

    $yearlyB = app('db')->transaction(
        fn () => sequence('yearly_code')
            ->prefix(date('Y'))
            ->padLength(2)
            ->groupByYear()
            ->next()
    );

    $monthly = app('db')->transaction(
        fn () => sequence('monthly_code')
            ->padLength(2)
            ->groupByMonth()
            ->next()
    );

    $daily = app('db')->transaction(
        fn () => sequence('daily_code')
            ->padLength(2)
            ->groupByDay()
            ->next()
    );

    expect($yearlyA)->toBe(date('Y') . '01')
        ->and($yearlyB)->toBe(date('Y') . '02')
        ->and($monthly)->toBe('01')
        ->and($daily)->toBe('01')
    ;
});

it('supports belongsTo as a fluent alias for model grouping', function () {
    $branchA = new class() extends Model
    {
        public $timestamps = false;

        protected $table = 'branches';

        protected $guarded = [];
    };
    $branchA->forceFill(['name' => 'Branch A']);
    $branchA->save();

    $branchB = new class() extends Model
    {
        public $timestamps = false;

        protected $table = 'branches';

        protected $guarded = [];
    };
    $branchB->forceFill(['name' => 'Branch B']);
    $branchB->save();

    $first = app('db')->transaction(
        fn () => sequence('branch_invoice')
            ->belongsTo($branchA)
            ->padLength(2)
            ->next()
    );

    $second = app('db')->transaction(
        fn () => sequence('branch_invoice')
            ->belongsTo($branchA)
            ->padLength(2)
            ->next()
    );

    $third = app('db')->transaction(
        fn () => sequence('branch_invoice')
            ->belongsTo($branchB)
            ->padLength(2)
            ->next()
    );

    expect($first)->toBe('01')
        ->and($second)->toBe('02')
        ->and($third)->toBe('01')
    ;
});

it('keeps distinct grouped keys isolated when values contain underscores', function () {
    $first = app('db')->transaction(
        fn () => sequence('underscore_groups')
            ->groupBy('a_b', 'c')
            ->next()
    );

    $second = app('db')->transaction(
        fn () => sequence('underscore_groups')
            ->groupBy('a', 'b_c')
            ->next()
    );

    $third = app('db')->transaction(
        fn () => sequence('underscore_groups')
            ->groupBy('a_b', 'c')
            ->next()
    );

    expect($first)->toBe('1')
        ->and($second)->toBe('1')
        ->and($third)->toBe('2')
    ;
});

it('keeps distinct grouped keys isolated across underscore boundary edge cases', function () {
    $pairs = [
        [['a_', 'b'], ['a', '_b']],
        [['a__', 'b'], ['a_', '_b']],
        [['_', '_'], ['__', '']],
        [['__', ''], ['', '__']],
    ];

    foreach ($pairs as $index => [$left, $right]) {
        $sequenceName = 'underscore_boundary_groups_' . $index;

        $first = app('db')->transaction(
            fn () => sequence($sequenceName)
                ->groupBy(...$left)
                ->next()
        );

        $second = app('db')->transaction(
            fn () => sequence($sequenceName)
                ->groupBy(...$right)
                ->next()
        );

        $third = app('db')->transaction(
            fn () => sequence($sequenceName)
                ->groupBy(...$left)
                ->next()
        );

        expect($first)->toBe('1')
            ->and($second)->toBe('1')
            ->and($third)->toBe('2')
        ;
    }
});

it('supports custom increment steps', function () {
    $first = app('db')->transaction(fn () => sequence('step_test')->step(5)->next());
    $second = app('db')->transaction(fn () => sequence('step_test')->step(5)->next());
    $third = app('db')->transaction(fn () => sequence('step_test')->step(5)->next());

    expect($first)->toBe('1')
        ->and($second)->toBe('6')
        ->and($third)->toBe('11')
    ;
});

it('supports direct forwarding for open ranges', function () {
    $first = app('db')->transaction(fn () => sequence('range_test')->range(5)->next());
    $second = app('db')->transaction(fn () => sequence('range_test')->range(5)->next());

    expect($first)->toBe('5')
        ->and($second)->toBe('6')
    ;
});
