<?php

declare(strict_types=1);

use Hatchyu\Sequence\Models\Sequence;
use Hatchyu\Sequence\Support\NextSequence;
use Hatchyu\Sequence\Support\SequenceConfig;
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
    'sequence' => [
        'table' => 'sequences',
        'connection' => null,
        'model' => Sequence::class,
        'strict_mode' => true,
    ],
]));
$container->instance('events', new Dispatcher($container));

Capsule::schema()->create('sequences', function (Blueprint $table): void {
    $table->id();
    $table->string('name', 100);
    $table->string('group_by', 250)->default('');
    $table->unsignedBigInteger('last_number');
    $table->timestamps();

    $table->unique(['name', 'group_by']);
});

$conn = Capsule::connection();

$conn->transaction(function (): void {
    assertSameString('1', sequence('sequence_number')->next(), 'ungrouped first number');
    assertSameString('2', sequence('sequence_number')->next(), 'ungrouped second number');

    assertSameString('C001', sequence('category_code', 'C', 3)->next(), 'prefix/minLength first number');
    assertSameString('C002', sequence('category_code', 'C', 3)->next(), 'prefix/minLength second number');

    $customer = new CustomerStub();

    $configA = SequenceConfig::create('CU', 3)->groupBy('branch', 'A');
    $configB = SequenceConfig::create('CU', 3)->groupBy('branch', 'B');

    assertSameString(
        'CU001',
        NextSequence::createForModel($customer, 'customer_code', $configA)->next(),
        'group A first number'
    );
    assertSameString(
        'CU002',
        NextSequence::createForModel($customer, 'customer_code', $configA)->next(),
        'group A second number'
    );
    assertSameString(
        'CU001',
        NextSequence::createForModel($customer, 'customer_code', $configB)->next(),
        'group B first number'
    );
});

echo "OK\n";
