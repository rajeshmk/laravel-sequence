# Sequence (Laravel Package)

Generate sequential numbers (for example `INV-000001`) safely from the database. Supports optional grouping (separate counters per group keys), configurable prefixes and pad length, min/max ranges, and a convenient `HasSequence` Eloquent trait to auto-assign values on creation.

**Quick summary:** use the `sequence()` helper inside a DB transaction to generate numbers, or add the `HasSequence` trait to models to auto-assign a column on `creating`.

## Requirements

- PHP: `^8.3`
- Laravel: `^10 || ^11 || ^12`
- Database: uses row-level locking (`SELECT ... FOR UPDATE`) inside transactions to ensure safe concurrent increments.

### Development Requirements

For development and testing:
- [Pest](https://pestphp.com/) - PHP testing framework
- [Laravel Pint](https://github.com/laravel/pint) - Code formatter
- [Rector](https://getrector.org/) - Code refactoring tool
- [PHPStan](https://phpstan.org/) - Static analysis tool

## Installation

Install via Composer:

```bash
composer require hatchyu/laravel-sequence
```

Run your migrations (the package auto-loads its migrations via the service provider):

```bash
php artisan migrate
```

Optional: publish the config file if you want to customize table/connection/model:

```bash
php artisan vendor:publish --tag=config --provider="Hatchyu\\Sequence\\SequenceServiceProvider"
```

Optional: publish the migration if you want to customize the table name or columns:

```bash
php artisan vendor:publish --tag=sequence-migrations --provider="Hatchyu\\Sequence\\SequenceServiceProvider"
```

## Tables

The package creates a `sequences` table which stores the current `last_number` per `(name, group_by)` tuple. The `group_by` column is a string token generated from the configured `groupBy` values (multiple values/models are joined with `_`).

If you use a custom model via `config('sequence.model')`, it must extend Eloquent `Model` and be backed by a table that contains `name`, `group_by`, and `last_number` columns. The package writes those attributes via `forceFill()`, so `fillable` is not required.

If you publish and customize the migration, keep the unique index on `(name, group_by)` and a numeric `last_number` column — those are required for correctness under concurrency.

## Usage

Important: Sequence generation must run inside a DB transaction; the package will throw an exception if called outside one.
Use `->next()` to reserve and return the next value.

### 1) Simple sequential numbers

Generate an incrementing sequence ("1", "2", "3", ...):

```php
use Illuminate\Support\Facades\DB;

$value = DB::transaction(function () {
    return sequence('sequence_number')->next();
});

// returns "1", then "2", etc.
```

### 2) Prefix and pad length

Provide a prefix and a pad numeric length (padded with zeros):

```php
$value = DB::transaction(function () {
    return sequence('category_code', 'C', 3)->next(); // "C001"
});
```

You can combine any prefix string with an integer `padLength`.

### 3) Dynamic parts (e.g. year + sequence)

If you want codes like `202601`, `202602`, ... you can pass dynamic prefix values (for example `date('Y')`) and a suitable pad length:

```php
$value = DB::transaction(function () {
    return sequence('batch_code', date('Y'), 2)->next();
});
```

Note: reset behavior for prefixed dynamic values is controlled by grouping. If you want a separate counter per year, use grouping (see below).

### 4) Grouped sequences (per parent, per branch, etc.)

Sometimes you want separate counters per group of values (branch, year, tenant, etc.). The package supports grouping by multiple keys or models.

Example: reset sequence per branch and year:

```php
// When generating directly with multiple group keys
DB::transaction(function () use ($branchId, $year) {
    return sequence('customer_code')
        ->groupBy($branchId, $year)
        ->next();
});

// When used via HasSequence, configure grouping in SequenceConfig (example below)
```

#### Advanced grouping: custom group key resolver

If you need a custom token format (e.g., `tenantId:year:branch`), you can provide a resolver callback:

```php
use Hatchyu\Sequence\Support\SequenceConfig;

$sequence = sequence('invoice')
    ->config(fn (SequenceConfig $c) =>
        $c->resolveGroupKeyUsing(fn (array $keys) => implode(':', $keys))
          ->groupBy($tenantId, date('Y'), $branchId)
    )
    ->next();

// Example token: 12:2026:3
```

Notes:

- You can pass persisted Eloquent models inside `groupBy($modelA, $modelB)`.
- Models must exist in the database before being used for grouping.

### Example patterns (from real-world usage)

Simple sequence:

```php
DB::transaction(fn () => sequence('simple_sequence')->next());
// 1, 2, 3, ...
```

Prefixed with zero-padding:

```php
DB::transaction(fn () => sequence('prefixed_category_code', 'C', 3)->next());
// C001, C002, C003, ...
```

Year-prefixed numeric:

```php
DB::transaction(fn () => sequence('cv_number_prefixed_by_year', date('Y'), 2)->next());
// 202601, 202602, 202603, ...
```

Templated prefix:

```php
$prefix = 'JOB/' . date('Y') . '/';
DB::transaction(fn () => sequence('custom_job_code', $prefix, 2)->next());
// JOB/2026/01, JOB/2026/02, ...
```

Grouped by year:

```php
DB::transaction(fn () => sequence('batch_code_grouped_by_year', '', 2)->groupBy(date('Y'))->next());
// 2026[01, 02, ...], 2027[01, 02, ...]
```

Multiple grouping keys (tenant, branch, year):

```php
DB::transaction(function () {
    return sequence('invoice_tenant_branch_year_wise', '', 2)
        ->groupBy(1, 2, date('Y'))
        ->next();
});
```

### 5) Auto-assign on Eloquent models (`HasSequence`)

Add the `HasSequence` trait to your model and provide a `sequenceColumns()` method that returns a `SequenceColumnCollection`. This supports one or many columns. Example:

```php
use Illuminate\Database\Eloquent\Model;
use Hatchyu\Sequence\Traits\HasSequence;
use Hatchyu\Sequence\Support\SequenceConfig;
use Hatchyu\Sequence\Support\SequenceColumnCollection;

class CustomerProfile extends Model
{
    use HasSequence;

    protected function sequenceColumns(): SequenceColumnCollection
    {
        return SequenceColumnCollection::collection()
            ->column(
                'customer_code',
                SequenceConfig::create('CU', 3)
                    // optional: make sequence per-branch (or per branch+year, etc.)
                    ->groupBy($this->branch_id)
            );
    }
}
```

Behavior notes for trait usage:

- The sequence type name is derived from the model class + column name (used as the `name` key in `sequences`).
- Generation runs during the model `creating` hook — your create flow must be in a DB transaction, otherwise generation will throw.
- If the column already has a non-empty value, the trait will not overwrite it.

Multiple columns example:

```php
protected function sequenceColumns(): SequenceColumnCollection
{
    return SequenceColumnCollection::collection()
        ->column(
            'admission_number',
            SequenceConfig::create('ADM', 3)
        )
        ->column(
            'attendance_number',
            SequenceConfig::create()
                ->resolveGroupKeyUsing(function () {
                    return $this->tenantId() . '_' . $this->academic_year . '_' . $this->class_id;
                })
        );
}
```

## API reference

- Helper: `sequence(string $name, string $prefix = '', int $padLength = 0)` — returns a `NextSequence` instance.
- Call `->groupBy(...$keys)` on the returned object to scope the counter by multiple values or models.
- Call `->config(fn (SequenceConfig $config) => ...)` to customize the configuration (min/max range, grouping, prefix, etc.).
- `SequenceConfig::range(int $min, ?int $max = null)` — set min/max bounds.
- `SequenceConfig::bounded(int $min, int $max)` — range + throw on overflow.
- `SequenceConfig::cyclingRange(int $min, int $max)` — range + cycle on overflow.
- `SequenceConfig::cycle()` — wrap to min when max is reached.
- `SequenceConfig::throwOnOverflow()` — throw when max is reached (default).
- Call `->next(): string` to reserve and return the next sequence value.

Example:

```php
$next = sequence('orders', 'ORD-', 6)
    ->groupBy($customerId, date('Y'))
    ->next();
```

Example with config callback:

```php
use Hatchyu\Sequence\Support\SequenceConfig;

$next = sequence('range_test', '', 2)
    ->config(function (SequenceConfig $config) {
        $config->range(1, 7)
            ->groupBy(date('Y'));
    })
    ->next();
```

Note: `config()` is just a convenience. You can still chain `groupBy()` or other methods before/after it.

## Concurrency and transactions

- Always call generation inside `DB::transaction()`.
- The package uses `SELECT ... FOR UPDATE` to lock the row that stores `last_number` for a given `(name, group_by)`.
- Keep transactions short to reduce lock contention.
- If you configured a custom connection in `config/sequence.php`, make sure to use that same connection for the surrounding transaction.
- When using `HasSequence`, the package will use the model's connection by default (unless `sequence.connection` is configured).
- When using the `sequence()` helper, the package uses `sequence.connection` if set; otherwise it uses the default connection. Use `DB::connection('name')->transaction(...)` to match.
- Ensure your database engine supports row-level locking in transactions (e.g., InnoDB on MySQL).

## Range and overflow

The package supports min/max ranges via `SequenceConfig::range()`, `SequenceConfig::bounded()`, and `SequenceConfig::cyclingRange()`.

- `range($min, $max)` sets the allowed range (inclusive). The default overflow behavior is to throw a `SequenceOverflowException` when `max` is reached.
- `bounded($min, $max)` is a convenience wrapper that sets the range and keeps the default "fail" overflow behavior.
- `cyclingRange($min, $max)` wraps back to `min` when `max` is reached.
 
Notes:
- `min` is inclusive and can be `0`. `max` is inclusive and must be at least `1`.
- If the next number exceeds the pad length, it is returned as-is (no truncation).

Example (throw on overflow):

```php
DB::transaction(fn () => sequence('orders', 'ORD-', 4)
    ->config(fn ($c) => $c->bounded(1, 9999))
    ->next()
);
```

Example (cycle back to min):

```php
DB::transaction(fn () => sequence('sessions')
    ->config(fn ($c) => $c->cyclingRange(1, 10))
    ->next()
);
```

See the error handling section below for a `SequenceOverflowException` catch example. Consult the config API in `src/Support/SequenceConfig.php` for exact methods and options.

## Customization (Config)

After publishing the config file, you can customize:

- `table`: The name of the sequence counters table.
- `connection`: The database connection used by the sequence model (use the same connection for your surrounding transaction).
- `model`: The Eloquent model class for sequences. Must extend `Model` and use a table with `name`, `group_by`, and `last_number` columns.
- `strict_mode`: When enabled (default), validates name and group key lengths and throws clear exceptions before hitting DB errors.

## Events

The package dispatches a `Hatchyu\Sequence\Events\SequenceAssigned` event whenever a number is reserved:

```php
use Hatchyu\Sequence\Events\SequenceAssigned;
use Illuminate\Support\Facades\Event;

Event::listen(SequenceAssigned::class, function (SequenceAssigned $event) {
    // $event->name, $event->rawNumber, $event->sequenceNumber, $event->groupByKey
});
```

## Testing

The package includes comprehensive unit tests and concurrency regression tests.

### Unit Tests

Run the test suite with Pest:

```bash
./vendor/bin/pest
```

Key test files:
- `tests/Unit/SequenceConfigTest.php` - Tests configuration creation, validation, and grouping
- `tests/Unit/SequenceAssignedEventTest.php` - Tests event dispatching

Test coverage includes:
- Configuration creation with prefix and pad length
- Validation of negative pad length (throws exception)
- Validation of grouping by non-persisted models (throws exception)
- Event dispatching when sequence numbers are assigned

### Concurrency Testing

For concurrency testing, use the regression script that simulates parallel processes:

```bash
php scripts/regression_concurrency.php
```

This script:
- Creates multiple worker processes that generate sequence numbers simultaneously
- Verifies no duplicate numbers are generated
- Tests the concurrency safety of the package

### Testing Best Practices

- Always wrap sequence number generation in `DB::transaction()` in tests
- Use database fixtures for consistent test data
- Test both simple sequences and grouped sequences
- Verify event dispatching in integration tests

Example test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Hatchyu\Sequence\Events\SequenceAssigned;

uses(RefreshDatabase::class);

it('generates sequential sequence numbers', function () {
    $value1 = DB::transaction(fn() => sequence('test')->next());
    $value2 = DB::transaction(fn() => sequence('test')->next());

    expect($value1)->toBe('1');
    expect($value2)->toBe('2');
});

it('dispatches sequence number assigned event', function () {
    Event::fake();

    DB::transaction(fn() => sequence('test')->next());

    Event::assertDispatched(SequenceAssigned::class);
});
```

## Error handling & troubleshooting

The package throws `Hatchyu\Sequence\Exceptions\SequenceException` (a `RuntimeException`) and a few grouped subclasses with specific error codes:

- `SequenceValidationException` — invalid names or group-by tokens
  - `CODE_NAME_REQUIRED` (400) — sequence name is empty
  - `CODE_NAME_TOO_LONG` (401) — sequence name exceeds length limit
  - `CODE_GROUP_BY_TOKEN_TOO_LONG` (402) — group-by token exceeds length limit

- `SequenceTransactionException` — missing DB transaction
  - `CODE_TRANSACTION_NOT_INITIATED` (300) — no active transaction
  - `CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION` (301) — no active transaction on specific connection

- `SequenceConfigException` — invalid configuration values
  - `CODE_PAD_LENGTH_NEGATIVE` (100) — pad length is negative
  - `CODE_MIN_NEGATIVE` (101) — min value is negative
  - `CODE_MAX_TOO_SMALL` (102) — max value is less than 1
  - `CODE_MAX_LESS_THAN_MIN` (103) — max value is less than min value
  - `CODE_INVALID_MODEL_CLASS` (104) — configured model class is invalid

- `SequenceModelException` — invalid or unsaved models
  - `CODE_MODEL_KEY_MUST_BE_STRING` (200) — model key is not a string
  - `CODE_MODEL_MUST_BE_PERSISTED` (201) — model must be saved before grouping

- `SequenceOverflowException` — max reached while overflow strategy is `FAIL`
  - `CODE_SEQUENCE_OVERFLOW` (500) — sequence max reached

You can catch either the base class or a specific subclass depending on how granular you want the handling to be. Each exception includes a specific error code for programmatic handling.

Example:

```php
use Hatchyu\Sequence\Exceptions\SequenceException;
use Hatchyu\Sequence\Exceptions\SequenceOverflowException;
use Hatchyu\Sequence\Exceptions\SequenceTransactionException;

try {
    DB::transaction(fn () => sequence('orders')->next());
} catch (SequenceOverflowException $e) {
    // max reached and overflow strategy is FAIL
    throw $e;
} catch (SequenceTransactionException $e) {
    if ($e->getCode() === SequenceTransactionException::CODE_TRANSACTION_NOT_INITIATED) {
        // handle missing transaction specifically
    }
    throw $e;
} catch (SequenceException $e) {
    // handle any other sequence error
    throw $e;
}
```

### Common troubleshooting

- "Not in transaction" exception: ensure `sequence()` runs inside `DB::transaction()`.
- Duplicate numbers under concurrency: check that your DB supports `SELECT ... FOR UPDATE` on the used engine and that transactions are used.

## Development

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/SequenceConfigTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

Note: the current test suite focuses on unit-level behavior (config validation, exceptions, and event construction). If you need end-to-end verification of DB transactions and sequence generation patterns, add integration tests in your application or introduce a Laravel test harness.

### Regression Scripts

The `scripts/` directory contains regression testing scripts:

```bash
# Test first number generation
php scripts/regression_first_number.php

# Test concurrency with multiple processes (requires pcntl extension)
php scripts/regression_concurrency.php
```

### Code Quality

```bash
# Run Laravel Pint for code formatting
./vendor/bin/pint

# Run strict code formatting
./vendor/bin/pint --config=pint.strict.json

# Run Rector for code refactoring
./vendor/bin/rector process

# Run PHPStan for static analysis
./vendor/bin/phpstan analyse
```

## Contribution

Contributions are welcome — open issues or PRs.

## License

MIT. See `LICENSE`.
