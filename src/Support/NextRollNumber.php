<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Support;

use Hatchyu\RollNumber\Events\RollNumberAssigned;
use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Hatchyu\RollNumber\Models\RollNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

use function event;

final class NextRollNumber
{
    /**
     * Private constructor.
     *
     * Roll number generation must be executed from within a DB transaction.
     */
    private function __construct(
        private string $name,
        private RollNumberConfig $config,
    ) {
        $this->ensureName($name);
        $this->ensureDbTransaction();
    }

    public static function create(string $name, string $prefix = '', int $minimumLength = 0): static
    {
        $config = new RollNumberConfig(
            prefix: $prefix,
            minimumLength: $minimumLength
        );

        return new self(trim($name), $config);
    }

    public static function createForModel(Model $model, RollNumberConfig $config): static
    {
        $name = Str::snake($model->getTable()) . '.'
            . Str::snake($config->column());

        return new self($name, $config);
    }

    public function groupBy(int|string|Model ...$groups): self
    {
        $this->config->groupBy(...$groups);

        return $this;
    }

    public function nextWithMeta(): array
    {
        $number = $this->getNextNumber();

        $meta = $number->only(['last_number', 'name', 'group_by']);

        $meta['last_number'] = $this->withPrefix($meta['last_number']);

        // Dispatch an event so consumers can react when a roll number is assigned.
        event(new RollNumberAssigned(
            $this->name,
            $meta['last_number'],
            $this->config->groupByToken(),
        ));

        return $meta;
    }

    public function next(): string
    {
        return $this->nextWithMeta()['last_number'];
    }

    // -------------------------------------------------------------------------
    // Private functions
    // -------------------------------------------------------------------------

    private function ensureName(string $name): void
    {
        if ($name === '') {
            throw RollNumberException::nameRequired();
        }
    }

    private function ensureDbTransaction(): void
    {
        if (RollNumber::query()->getConnection()->transactionLevel() < 1) {
            throw RollNumberException::transactionNotInitiated();
        }
    }

    private function getNextNumber(): RollNumber
    {
        $rollNumber = $this->getCurrentRollNumber();

        if ($rollNumber->wasRecentlyCreated) {
            return $rollNumber;
        }

        $lastNumber = $this->calculateNextNumber($rollNumber);

        $rollNumber->update(['last_number' => $lastNumber]);

        return $rollNumber;
    }

    private function getCurrentRollNumber(): RollNumber
    {
        $rollNumber = $this->selectForUpdate();
        if ($rollNumber !== null) {
            return $rollNumber;
        }

        try {
            return $this->createFirstNumber();
        } catch (QueryException $exception) {
            // This can happen under concurrency: two transactions both observe "no row"
            // and race to create the first one. The UNIQUE index ensures correctness.
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $rollNumber = $this->selectForUpdate();
            if ($rollNumber === null) {
                throw $exception;
            }
        }

        return $rollNumber;
    }

    private function calculateNextNumber(RollNumber $rollNumber): int
    {
        $lastNumber = $rollNumber->last_number;

        if ($this->config->isMaxLimitReached($lastNumber)) {
            return 1;
        }

        return $lastNumber + 1;
    }

    private function selectForUpdate(): ?RollNumber
    {
        return RollNumber::query()
            ->where('name', $this->name)
            ->where('group_by', $this->config->groupByToken())
            ->lockForUpdate()
            ->first()
        ;
    }

    private function createFirstNumber(): RollNumber
    {
        return RollNumber::create([
            'name' => $this->name,
            'group_by' => $this->config->groupByToken(),
            'last_number' => 1,
        ]);
    }

    private function withPrefix(int $number): string
    {
        return $this->config->getPrefix() . $this->paddedNumber($number);
    }

    private function paddedNumber(int $number): string
    {
        $padLength = $this->config->minimumLength();

        return $padLength > 0
            ? str_pad((string) $number, $padLength, '0', STR_PAD_LEFT)
            : (string) $number;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        // Cross-driver best-effort detection.
        // - SQLSTATE[23000] is "integrity constraint violation" (MySQL, SQLite, etc.)
        // - Postgres: "duplicate key value violates unique constraint"
        return $code === '23000'
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'unique violation');
    }
}
