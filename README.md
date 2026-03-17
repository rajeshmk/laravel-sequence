# Roll Number (Laravel Package)

Generate sequential “roll numbers” (for example `INV-000001`) safely from the database. Supports optional grouping (separate counters per group keys), configurable prefixes and minimum length, rollover limits, and a convenient `HasRollNumber` Eloquent trait to auto-assign values on creation.

**Quick summary:** use the `roll_number()` helper inside a DB transaction to generate numbers, or add the `HasRollNumber` trait to models to auto-assign a column on `creating`.

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
composer require hatchyu/laravel-roll-number
```

Run your migrations (the package auto-loads its migrations via the service provider):

```bash
php artisan migrate
```

Optional: publish the config file if you want to customize table/connection/model:

```bash
php artisan vendor:publish --tag=config --provider="Hatchyu\\RollNumber\\RollNumberServiceProvider"
```

Optional: publish the migration if you want to customize the table name or columns:

```bash
php artisan vendor:publish --tag=roll-number-migrations --provider="Hatchyu\\RollNumber\\RollNumberServiceProvider"
```

## Tables

The package creates a `roll_numbers` table which stores the current `last_number` per `(name, group_by)` tuple. The `group_by` column is a string token generated from the configured `groupBy` values (multiple values/models are joined with `_`).

If you use a custom model via `config('roll-number.model')`, it must extend Eloquent `Model` and be backed by a table that contains `name`, `group_by`, and `last_number` columns. The package writes those attributes via `forceFill()`, so `fillable` is not required.

## Usage

Important: roll number generation must run inside a DB transaction; the package will throw an exception if called outside one.
Use `->next()` to reserve and return the next value.

### 1) Simple sequential numbers

Generate an incrementing sequence ("1", "2", "3", ...):

```php
use Illuminate\Support\Facades\DB;

$value = DB::transaction(function () {
    return roll_number('sequence_number')->next();
});

// returns "1", then "2", etc.
```

### 2) Prefix and minimum length

Provide a prefix and a minimum numeric length (padded with zeros):

```php
$value = DB::transaction(function () {
    return roll_number('category_code', 'C', 3)->next(); // "C001"
});
```

You can combine any prefix string with an integer `minimumLength`.

### 3) Dynamic parts (e.g. year + sequence)

If you want codes like `202601`, `202602`, ... you can pass dynamic prefix values (for example `date('Y')`) and a suitable minimum length:

```php
$value = DB::transaction(function () {
    return roll_number('batch_code', date('Y'), 2)->next();
});
```

Note: rollover behavior for prefixed dynamic values is controlled by grouping. If you want a separate counter per year, use grouping (see below).

### 4) Grouped sequences (per parent, per branch, etc.)

Sometimes you want separate counters per group of values (branch, year, tenant, etc.). The package supports grouping by multiple keys or models.

Example: reset sequence per branch and year:

```php
// When generating directly with multiple group keys
DB::transaction(function () use ($branchId, $year) {
    return roll_number('customer_code')
        ->groupBy($branchId, $year)
        ->next();
});

// When used via HasRollNumber, configure grouping in RollNumberConfig (example below)
```

#### Advanced grouping: custom group key resolver

If you need a custom token format (e.g., `tenantId:year:branch`), you can provide a resolver callback:

```php
use Hatchyu\RollNumber\Support\RollNumberConfig;

$roll = roll_number('invoice')
    ->config(fn (RollNumberConfig $c) =>
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
DB::transaction(fn () => roll_number('simple_sequence')->next());
// 1, 2, 3, ...
```

Prefixed with zero-padding:

```php
DB::transaction(fn () => roll_number('prefixed_category_code', 'C', 3)->next());
// C001, C002, C003, ...
```

Year-prefixed numeric:

```php
DB::transaction(fn () => roll_number('cv_number_prefixed_by_year', date('Y'), 2)->next());
// 202601, 202602, 202603, ...
```

Templated prefix:

```php
$prefix = 'JOB/' . date('Y') . '/';
DB::transaction(fn () => roll_number('custom_job_code', $prefix, 2)->next());
// JOB/2026/01, JOB/2026/02, ...
```

Grouped by year:

```php
DB::transaction(fn () => roll_number('batch_code_grouped_by_year', '', 2)->groupBy(date('Y'))->next());
// 2026[01, 02, ...], 2027[01, 02, ...]
```

Multiple grouping keys (tenant, branch, year):

```php
DB::transaction(function () {
    return roll_number('invoice_tenant_branch_year_wise', '', 2)
        ->groupBy(1, 2, date('Y'))
        ->next();
});
```

### 5) Auto-assign on Eloquent models (`HasRollNumber`)

Add the `HasRollNumber` trait to your model and provide a `sequenceColumns()` method that returns a `SequenceColumnCollection`. This supports one or many columns. Example:

```php
use Illuminate\Database\Eloquent\Model;
use Hatchyu\RollNumber\Traits\HasRollNumber;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Hatchyu\RollNumber\Support\SequenceColumnCollection;

class CustomerProfile extends Model
{
    use HasRollNumber;

    protected function sequenceColumns(): SequenceColumnCollection
    {
        return SequenceColumnCollection::collection()
            ->column(
                'customer_code',
                RollNumberConfig::create('CU', 3)
                    // optional: make sequence per-branch (or per branch+year, etc.)
                    ->groupBy($this->branch_id)
            );
    }
}
```

Behavior notes for trait usage:

- The roll type name is derived from the model class + column name (used as the `name` key in `roll_numbers`).
- Generation runs during the model `creating` hook — your create flow must be in a DB transaction, otherwise generation will throw.
- If the column already has a non-empty value, the trait will not overwrite it.

Multiple columns example:

```php
protected function sequenceColumns(): SequenceColumnCollection
{
    return SequenceColumnCollection::collection()
        ->column(
            'admission_number',
            RollNumberConfig::create('ADM', 3)
        )
        ->column(
            'roll_number',
            RollNumberConfig::create()
                ->resolveGroupKeyUsing(function () {
                    return $this->tenantId() . '_' . $this->academic_year . '_' . $this->class_id;
                })
        );
}
```

## API reference

- Helper: `roll_number(string $name, string $prefix = '', int $minimumLength = 0)` — returns a `NextRollNumber` instance.
- Call `->groupBy(...$keys)` on the returned object to scope the counter by multiple values or models.
- Call `->config(fn (RollNumberConfig $config) => ...)` to customize the configuration (rollover limit, grouping, prefix, etc.).
- Call `->next(): string` to reserve and return the next roll value.

Example:

```php
$next = roll_number('orders', 'ORD-', 6)
    ->groupBy($customerId, date('Y'))
    ->next();
```

Example with config callback:

```php
use Hatchyu\RollNumber\Support\RollNumberConfig;

$next = roll_number('rollover_test', '', 2)
    ->config(function (RollNumberConfig $config) {
        $config->rolloverLimit(7)
            ->groupBy(date('Y'));
    })
    ->next();
```

Note: `config()` is just a convenience. You can still chain `groupBy()` or other methods before/after it.

## Concurrency and transactions

- Always call generation inside `DB::transaction()`.
- The package uses `SELECT ... FOR UPDATE` to lock the row that stores `last_number` for a given `(name, group_by)`.
- Keep transactions short to reduce lock contention.
- If you configured a custom connection in `config/roll-number.php`, make sure to use that same connection for the surrounding transaction.
- Ensure your database engine supports row-level locking in transactions (e.g., InnoDB on MySQL).

## Rollover and limits

The package supports rolling over or setting limits via `RollNumberConfig` (see `->rolloverLimit()` on the config). When the limit is reached, the next number resets to `1`. Consult the config API in `src/Support/RollNumberConfig.php` for exact methods and options.

## Customization (Config)

After publishing the config file, you can customize:

- `table`: The name of the roll number counters table.
- `connection`: The database connection used by the roll number model (use the same connection for your surrounding transaction).
- `model`: The Eloquent model class for roll numbers. Must extend `Model` and use a table with `name`, `group_by`, and `last_number` columns.
- `strict_mode`: When enabled (default), validates name and group key lengths and throws clear exceptions before hitting DB errors.

## Events

The package dispatches a `Hatchyu\RollNumber\Events\RollNumberAssigned` event whenever a number is reserved:

```php
use Hatchyu\RollNumber\Events\RollNumberAssigned;
use Illuminate\Support\Facades\Event;

Event::listen(RollNumberAssigned::class, function (RollNumberAssigned $event) {
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
- `tests/Unit/RollNumberConfigTest.php` - Tests configuration creation, validation, and grouping
- `tests/Unit/RollNumberAssignedEventTest.php` - Tests event dispatching

Test coverage includes:
- Configuration creation with prefix and minimum length
- Validation of negative minimum length (throws exception)
- Validation of grouping by non-persisted models (throws exception)
- Event dispatching when roll numbers are assigned

### Concurrency Testing

For concurrency testing, use the regression script that simulates parallel processes:

```bash
php scripts/regression_concurrency.php
```

This script:
- Creates multiple worker processes that generate roll numbers simultaneously
- Verifies no duplicate numbers are generated
- Tests the concurrency safety of the package

### Testing Best Practices

- Always wrap roll number generation in `DB::transaction()` in tests
- Use database fixtures for consistent test data
- Test both simple sequences and grouped sequences
- Verify event dispatching in integration tests

Example test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Hatchyu\RollNumber\Events\RollNumberAssigned;

uses(RefreshDatabase::class);

it('generates sequential roll numbers', function () {
    $value1 = DB::transaction(fn() => roll_number('test')->next());
    $value2 = DB::transaction(fn() => roll_number('test')->next());

    expect($value1)->toBe('1');
    expect($value2)->toBe('2');
});

it('dispatches roll number assigned event', function () {
    Event::fake();

    DB::transaction(fn() => roll_number('test')->next());

    Event::assertDispatched(RollNumberAssigned::class);
});
```

## Error handling & troubleshooting

The package throws `Hatchyu\RollNumber\Exceptions\RollNumberException` (a `RuntimeException`) and a few grouped subclasses with specific error codes:

- `RollNumberValidationException` — invalid names or group-by tokens
  - `CODE_NAME_REQUIRED` (400) — roll number name is empty
  - `CODE_NAME_TOO_LONG` (401) — roll number name exceeds length limit
  - `CODE_GROUP_BY_TOKEN_TOO_LONG` (402) — group-by token exceeds length limit

- `RollNumberTransactionException` — missing DB transaction
  - `CODE_TRANSACTION_NOT_INITIATED` (300) — no active transaction
  - `CODE_TRANSACTION_NOT_INITIATED_ON_CONNECTION` (301) — no active transaction on specific connection

- `RollNumberConfigException` — invalid configuration values
  - `CODE_MIN_LENGTH_NEGATIVE` (100) — minimum length is negative
  - `CODE_ROLLOVER_LIMIT_NEGATIVE` (101) — rollover limit is negative
  - `CODE_INVALID_MODEL_CLASS` (102) — configured model class is invalid

- `RollNumberModelException` — invalid or unsaved models
  - `CODE_MODEL_KEY_MUST_BE_STRING` (200) — model key is not a string
  - `CODE_MODEL_MUST_BE_PERSISTED` (201) — model must be saved before grouping

You can catch either the base class or a specific subclass depending on how granular you want the handling to be. Each exception includes a specific error code for programmatic handling.

Example:

```php
use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Hatchyu\RollNumber\Exceptions\RollNumberTransactionException;

try {
    DB::transaction(fn () => roll_number('orders')->next());
} catch (RollNumberTransactionException $e) {
    if ($e->getCode() === RollNumberTransactionException::CODE_TRANSACTION_NOT_INITIATED) {
        // handle missing transaction specifically
    }
    throw $e;
} catch (RollNumberException $e) {
    // handle any other roll-number error
    throw $e;
}
```

### Common troubleshooting

- "Not in transaction" exception: ensure `roll_number()` runs inside `DB::transaction()`.
- Duplicate numbers under concurrency: check that your DB supports `SELECT ... FOR UPDATE` on the used engine and that transactions are used.

## Development

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/RollNumberConfigTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

Note: the current test suite focuses on unit-level behavior (config validation, exceptions, and event construction). If you need end-to-end verification of DB transactions and roll-number generation patterns, add integration tests in your application or introduce a Laravel test harness.

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
