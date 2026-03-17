<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Models\RollNumber;
use Hatchyu\RollNumber\Support\NextRollNumber;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;

require __DIR__ . '/../vendor/autoload.php';

final class CustomerStub extends Model
{
    public $timestamps = false;

    protected $table = 'customers';

    protected $guarded = [];
}

function assertSameString(string $expected, string $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected "' . $expected . '", got "' . $actual . '"');
    }
}

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Minimal container bindings for helpers like config() and event().
$container = new Container();
Container::setInstance($container);
$container->instance('config', new ConfigRepository([
    'roll-number' => [
        'table' => 'roll_numbers',
        'connection' => null,
        'model' => RollNumber::class,
        'strict_mode' => true,
    ],
]));
$container->instance('events', new Dispatcher($container));

Capsule::schema()->create('roll_numbers', function (Blueprint $table): void {
    $table->id();
    $table->string('name', 100);
    $table->string('group_by', 250)->default('');
    $table->unsignedBigInteger('last_number');
    $table->timestamps();

    $table->unique(['name', 'group_by']);
});

$conn = Capsule::connection();

$conn->transaction(function (): void {
    assertSameString('1', roll_number('sequence_number')->next(), 'ungrouped first number');
    assertSameString('2', roll_number('sequence_number')->next(), 'ungrouped second number');

    assertSameString('C001', roll_number('category_code', 'C', 3)->next(), 'prefix/minLength first number');
    assertSameString('C002', roll_number('category_code', 'C', 3)->next(), 'prefix/minLength second number');

    $customer = new CustomerStub();

    $configA = RollNumberConfig::create('CU', 3)->groupBy('branch', 'A');
    $configB = RollNumberConfig::create('CU', 3)->groupBy('branch', 'B');

    assertSameString(
        'CU001',
        NextRollNumber::createForModel($customer, 'customer_code', $configA)->next(),
        'group A first number'
    );
    assertSameString(
        'CU002',
        NextRollNumber::createForModel($customer, 'customer_code', $configA)->next(),
        'group A second number'
    );
    assertSameString(
        'CU001',
        NextRollNumber::createForModel($customer, 'customer_code', $configB)->next(),
        'group B first number'
    );
});

echo "OK\n";
