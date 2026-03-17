<?php

declare(strict_types=1);

use Hatchyu\Sequence\Models\Sequence;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;

require __DIR__ . '/../vendor/autoload.php';

// Define base_path helper for standalone script
if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return __DIR__ . '/../' . ltrim($path, '/');
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

class TestContainer extends Container
{
    public function basePath($path = '')
    {
        return __DIR__ . '/../' . ltrim($path, '/');
    }
}

if (! function_exists('pcntl_fork')) {
    echo "SKIP: pcntl extension not available\n";

    exit(0);
}

$dbFile = __DIR__ . '/test_concurrency.db';
@unlink($dbFile);
touch($dbFile);

bootstrapContainerAndDb($dbFile, true);

// Pre-seed the counter row to avoid concurrent first-insert contention in SQLite.
Capsule::table('sequences')->insert([
    'name' => 'concurrency_test',
    'group_by' => '',
    'last_number' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);

$iterations = 100;
$workers = 5;
$pids = [];

echo "Starting concurrency test with {$workers} workers, {$iterations} iterations each...\n";

for ($i = 1; $i <= $workers; $i++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Unable to fork process');
    }

    if ($pid === 0) {
        bootstrapContainerAndDb($dbFile, false);
        $conn = Capsule::connection();

        for ($j = 0; $j < $iterations; $j++) {
            $attempt = 0;
            $maxAttempts = 10;

            while (true) {
                try {
                    $conn->transaction(function () use ($i, $j): void {
                        $value = sequence('concurrency_test')->next();

                        Capsule::table('sequence_results')->insert([
                            'worker_id' => $i,
                            'iteration' => $j,
                            'value' => $value,
                        ]);
                    });
                    break;
                } catch (QueryException $e) {
                    if (str_contains($e->getMessage(), 'database is locked') && $attempt < $maxAttempts) {
                        $attempt++;
                        usleep(50000 * $attempt);
                        continue;
                    }

                    throw $e;
                }
            }
        }

        exit(0);
    }

    $pids[] = $pid;
}

foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
    if ($status !== 0) {
        throw new RuntimeException("Worker process {$pid} failed with status {$status}");
    }
}

bootstrapContainerAndDb($dbFile, false);

$results = Capsule::table('sequence_results')->pluck('value')->all();
$total = count($results);
$unique = count(array_unique($results));
$expectedTotal = $workers * $iterations;

if ($total !== $expectedTotal) {
    throw new RuntimeException("Unexpected result count: {$total}, expected {$expectedTotal}");
}

if ($unique !== $total) {
    $duplicates = array_diff_assoc($results, array_unique($results));

    throw new RuntimeException("Duplicate sequence numbers detected. Total: {$total}, Unique: {$unique}. Duplicates: " . implode(', ', array_unique($duplicates)));
}

// Test grouped concurrency
echo "Testing grouped sequences...\n";
$groupedResults = [];
for ($i = 0; $i < 50; $i++) {
    Capsule::connection()->transaction(function () use (&$groupedResults, $i): void {
        $groupId = $i % 3; // 3 different groups
        $value = sequence('grouped_test')->groupBy($groupId)->next();

        $groupedResults[] = ['group' => $groupId, 'value' => $value];
    });
}

// Verify grouped sequences are correct
$groupedByGroup = [];
foreach ($groupedResults as $result) {
    $groupedByGroup[$result['group']][] = $result['value'];
}

foreach ($groupedByGroup as $groupId => $values) {
    $uniqueValues = array_unique($values);
    if (count($uniqueValues) !== count($values)) {
        throw new RuntimeException("Duplicate values found in group {$groupId}");
    }

    // Check that values are sequential within each group
    $numericValues = array_map(fn ($v) => (int) $v, $values);
    sort($numericValues);
    for ($i = 0; $i < count($numericValues); $i++) {
        if ($numericValues[$i] !== $i + 1) {
            throw new RuntimeException("Non-sequential values in group {$groupId}: " . implode(', ', $numericValues));
        }
    }
}

echo "✓ Concurrency test passed! Generated {$total} unique sequential numbers.\n";
echo "✓ Grouped sequences test passed! Each group maintains separate counters.\n";

@unlink($dbFile);

function bootstrapContainerAndDb(string $dbFile, bool $createSchema): void
{
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $dbFile,
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // Improve SQLite concurrency handling for multi-process tests.
    $sqlite = $capsule->getConnection();
    $sqlite->statement('PRAGMA journal_mode=WAL');
    $sqlite->statement('PRAGMA busy_timeout=5000');

    $container = new TestContainer();
    Container::setInstance($container);
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

    if (! $createSchema) {
        return;
    }

    Capsule::schema()->create('sequences', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 100);
        $table->string('group_by', 250)->default('');
        $table->unsignedBigInteger('last_number');
        $table->timestamps();

        $table->unique(['name', 'group_by']);
    });

    Capsule::schema()->create('sequence_results', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('worker_id');
        $table->unsignedInteger('iteration');
        $table->string('value', 255);
        $table->timestamps();
    });
}
