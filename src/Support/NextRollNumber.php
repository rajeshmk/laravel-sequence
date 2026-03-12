<?php

namespace VocoLabs\RollNumber\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Vocolabs\Contracts\Database\DriverException;
use VocoLabs\RollNumber\Models\RollNumber;
use VocoLabs\RollNumber\Models\RollType;

// @TODO - use custom exception class instead of `RuntimeException`
final class NextRollNumber
{
    private string $name;

    private string $prefix = '';

    private int $zeroPadding = 0;

    private string $modelName;

    private int|string $modelId;

    private int $rolloverLimit;

    /**
     * PRIVATE constructor
     */
    private function __construct(string $name)
    {
        if (trim($name) === '') {
            throw new RuntimeException(__('Name required for the roll number type.'));
        }

        $this->name = trim($name);
    }

    /**
     * THIS FUNCTION MUST BE CALLED FROM WITHIN A "DB TRANSACTION"
     */
    public static function create(string $name): static
    {
        return static::newInstance(trim($name));
    }

    public function prefix(string $prefix, int $zeroPadding = 0): self
    {
        $this->prefix = $prefix;
        $this->zeroPadding = $zeroPadding;

        return $this;
    }

    public function groupBy(string $model, int|string $id): self
    {
        if (! class_exists($model)) {
            throw new RuntimeException(__('Class `'.$model.'` not found.'));
        }

        $this->modelName = $model;
        $this->modelId = $id;

        return $this;
    }

    public function rolloverLimit(int $limit): self
    {
        if ($limit > 0) {
            $this->rolloverLimit = $limit;
        }

        return $this;
    }

    public function get(): string
    {
        return $this->withPrefix($this->getNextNumber());
    }

    public function groupingModel(): ?string
    {
        return $this->modelName ?? null;
    }

    public function groupingId(): int|string|null
    {
        return $this->modelId ?? null;
    }

    // -------------------------------------------------------------------------
    // Private functions
    // -------------------------------------------------------------------------

    private static function newInstance(string $name): static
    {
        return new static($name);
    }

    private function getNextNumber(): int
    {
        // Get PDO instance
        $connection = DB::getPdo();

        if ($connection instanceof \PDO && true !== $connection->inTransaction()) {
            throw new DriverException(__('Database transaction not yet initiated.'));
        }

        $type = RollType::where('name', $this->name)->where('grouping_model', $this->groupingModel())->first();

        if (null === $type) {
            $type = $this->createRollType();

            return $this->createRollNumber($type);
        }

        $number = RollNumber::where('type_id', $type->id)->where('grouping_id', $this->groupingId())->first();

        if (null === $number) {
            return $this->createRollNumber($type);
        }

        $maxLimit = $this->rolloverLimit ?? null;

        $sql = 'UPDATE `'.DB::getTablePrefix().'roll_numbers`'
            .' SET `updated_at` = ?,'
            .' `next_number` = CASE'
            .' WHEN (? IS NULL OR ? > `next_number`)'
            .'   THEN (LAST_INSERT_ID(`next_number`) + 1)'
            .' ELSE (LAST_INSERT_ID(`next_number`) - `next_number` + 1)'
            .' END'
            .' WHERE `type_id` = ? AND `grouping_id` '
            .($this->groupingId() === null ? ' IS NULL ' : ' = ?');

        $queryParams = [
            date('Y-m-d H:i:s'),
            $maxLimit,
            $maxLimit,
            $type->id,
        ];

        if ($this->groupingId() !== null) {
            $queryParams[] = $this->groupingId();
        }

        DB::statement($sql, $queryParams);

        // Get roll number as LAST_INSERT_ID()
        $nextNumber = DB::getPdo()->lastInsertId();

        if (empty($nextNumber)) {
            throw new RuntimeException(__('Could not generate roll number.'));
        }

        return $nextNumber;
    }

    private function createRollType(): RollType
    {
        $type = new RollType();
        $type->name = $this->name;
        $type->grouping_model = $this->groupingModel();

        $type->save();

        return $type;
    }

    private function createRollNumber(RollType $type): int
    {
        if ($this->groupingId() && ! $type->grouping_model) {
            throw new RuntimeException(__('Model class should be specified in order to generate model based roll number.'));
        }

        $number = new RollNumber();
        $number->type_id = $type->id;
        $number->grouping_id = $this->groupingId();
        $number->next_number = 2;

        $number->save();

        return 1;
    }

    private function withPrefix(int $number): string
    {
        return $this->prefix.str_pad($number, $this->zeroPadding, '0', STR_PAD_LEFT);
    }
}
