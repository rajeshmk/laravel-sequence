# Roll Number (Laravel Package)

Generate sequential “roll numbers” (e.g. `INV-000001`) safely from the database, with optional grouping (separate counters per model/id) and a simple Eloquent trait for auto-assigning roll numbers on model creation.

## Requirements

- PHP: `^8.1 || ^8.2`
- Laravel: `^9 || ^10 || ^11 || ^12`
- Database: uses a `LAST_INSERT_ID(...)`-based `UPDATE` for atomic increments (MySQL/MariaDB compatible).

## Installation

Install via Composer:

```bash
composer require vocolabs/roll-number
```

Run migrations:

```bash
php artisan migrate
```

The package auto-loads its migrations when running in console (`VocoLabs\RollNumber\RollNumberServiceProvider`).

## How it works (tables)

The migration creates:

- `roll_types`: stores a roll-number “type” (by `name`) and an optional `grouping_model`
- `roll_numbers`: stores the current `next_number` per `(type_id, grouping_id)` pair

## Usage

### 1) Generate a roll number manually

Use the global helper `roll_number($name)` (autoloaded from `helper-functions/voco-helpers.php`).

Important: generation **must** be executed inside a DB transaction (the package checks this and throws if you’re not in one).

```php
use Illuminate\Support\Facades\DB;

$roll = DB::transaction(function () {
    return roll_number('invoice:number')
        ->prefix('INV-', 6) // prefix + zero-padding
        ->get();
});

// "INV-000001"
```

### 2) Grouped counters (separate sequence per model/id)

If you want separate counters per “group” (e.g. per `Company`, per `Store`, per `Tenant`), call `groupBy()`:

```php
use Illuminate\Support\Facades\DB;
use App\Models\Company;

$roll = DB::transaction(function () use ($company) {
    return roll_number('invoice:number')
        ->groupBy(Company::class, $company->getKey())
        ->prefix('INV-', 6)
        ->get();
});
```

This stores `grouping_model` on the type and uses `grouping_id` to keep independent sequences.

### 3) Rollover limit

You can set an optional rollover limit:

```php
use Illuminate\Support\Facades\DB;

$roll = DB::transaction(function () {
    return roll_number('token:number')
        ->rolloverLimit(9999)
        ->get();
});
```

### 4) Auto-assign roll numbers on Eloquent models

Add `HasRollNumber` to your model to auto-fill a column on `creating`:

```php
use Illuminate\Database\Eloquent\Model;
use VocoLabs\RollNumber\Traits\HasRollNumber;

class Invoice extends Model
{
    use HasRollNumber;

    protected function rollNumberConfig(): string|array
    {
        return [
            'column' => 'roll_number',
            'prefix' => 'INV-',
        ];
    }
}
```

Notes:

- The default column is `roll_number`.
- The roll type name is derived from the model + column name.
- Roll number generation still happens inside the model `creating` hook, so your create flow should run inside a transaction if you rely on roll numbers (recommended).

### 5) Model-based grouping via trait

If your roll numbers must be grouped by a model/id, use `HasModelBasedRollNumber` and implement the required methods:

```php
use Illuminate\Database\Eloquent\Model;
use VocoLabs\RollNumber\Traits\HasModelBasedRollNumber;
use App\Models\Company;

class Invoice extends Model
{
    use HasModelBasedRollNumber;

    protected function getRollGroupModelName(): string
    {
        return Company::class;
    }

    protected function getRollGroupModelId(): int|string
    {
        return $this->company_id;
    }
}
```

## API reference

The roll number generator is `VocoLabs\RollNumber\Support\NextRollNumber`:

- `roll_number(string $name)`
- `->prefix(string $prefix, int $zeroPadding = 0)`
- `->groupBy(string $modelClass, int|string $id)`
- `->rolloverLimit(int $limit)`
- `->get(): string`

## License

MIT. See `LICENSE`.

