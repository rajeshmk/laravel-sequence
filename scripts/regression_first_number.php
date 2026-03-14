<?php

declare(strict_types=1);

use Hatchyu\RollNumber\Support\NextRollNumber;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/../vendor/autoload.php';

final class Branch {}

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

Capsule::schema()->create('roll_numbers', function (Blueprint $table): void {
    $table->id();
    $table->string('name', 100);
    $table->string('grouping_type', 250)->default('');
    $table->string('grouping_id', 100)->default('');
    $table->unsignedBigInteger('last_number');
    $table->timestamps();

    $table->unique(['name', 'grouping_type', 'grouping_id']);
});

$conn = Capsule::connection();

$conn->transaction(function (): void {
    assertSameString('1', roll_number('sequence_number')->get(), 'ungrouped first number');
    assertSameString('2', roll_number('sequence_number')->get(), 'ungrouped second number');

    assertSameString('C001', roll_number('category_code', 'C', 3)->get(), 'prefix/minLength first number');
    assertSameString('C002', roll_number('category_code', 'C', 3)->get(), 'prefix/minLength second number');

    $customer = new CustomerStub();

    $configA = RollNumberConfig::from([
        'column' => 'customer_code',
        'prefix' => 'CU',
        'minimumLength' => 3,
    ])->groupBy(Branch::class, 'A');

    $configB = RollNumberConfig::from([
        'column' => 'customer_code',
        'prefix' => 'CU',
        'minimumLength' => 3,
    ])->groupBy(Branch::class, 'B');

    assertSameString('CU001', NextRollNumber::createForModel($customer, $configA)->get(), 'group A first number');
    assertSameString('CU002', NextRollNumber::createForModel($customer, $configA)->get(), 'group A second number');
    assertSameString('CU001', NextRollNumber::createForModel($customer, $configB)->get(), 'group B first number');
});

echo "OK\n";
