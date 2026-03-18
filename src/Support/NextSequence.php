<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Support;

use Closure;
use Hatchyu\Sequence\Enums\OverflowStrategy;
use Hatchyu\Sequence\Events\SequenceAssigned;
use Hatchyu\Sequence\Exceptions\SequenceConfigException;
use Hatchyu\Sequence\Exceptions\SequenceOverflowException;
use Hatchyu\Sequence\Exceptions\SequenceTransactionException;
use Hatchyu\Sequence\Exceptions\SequenceValidationException;
use Hatchyu\Sequence\Models\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

use function event;

final readonly class NextSequence
{
    private const int NAME_MAX_LENGTH = 100;

    private const int GROUP_BY_MAX_LENGTH = 250;

    /**
     * Private constructor.
     *
     * Sequence generation must be executed from within a DB transaction.
     */
    private function __construct(
        private string $name,
        private SequenceConfig $config,
        private ?string $connectionName = null,
    ) {
        $this->ensureName($name);
        $this->ensureDbTransaction();
    }

    public static function create(string $name, string $prefix = '', int $padLength = 0): static
    {
        $config = SequenceConfig::create($prefix, $padLength);

        return new self(trim($name), $config, null);
    }

    public static function createForModel(Model $model, string $column, SequenceConfig $config): static
    {
        $name = Str::snake($model->getTable()) . '.' . Str::snake($column);

        return new self($name, $config, $model->getConnectionName());
    }

    public function groupBy(int|string|Model ...$groups): self
    {
        $this->config->groupBy(...$groups);

        return $this;
    }

    public function config(Closure $callback): self
    {
        $callback($this->config);

        return $this;
    }

    public function next(): string
    {
        $sequence = $this->getNextNumber();

        $sequenceNumber = $this->withPrefix($sequence->last_number);

        $this->dispatchSequenceAssignedEvent($sequenceNumber, $sequence);

        return $sequenceNumber;
    }

    // -------------------------------------------------------------------------
    // Private functions
    // -------------------------------------------------------------------------

    private function ensureName(string $name): void
    {
        if ($name === '') {
            throw SequenceValidationException::nameRequired();
        }

        if ($this->isStrictModeEnabled() && strlen($name) > self::NAME_MAX_LENGTH) {
            throw SequenceValidationException::nameTooLong(self::NAME_MAX_LENGTH);
        }
    }

    private function ensureDbTransaction(): void
    {
        $connection = $this->sequenceModel()->getConnection();
        if ($connection->transactionLevel() < 1) {
            $connectionName = $connection->getName();
            if (is_string($connectionName) && $connectionName !== '') {
                throw SequenceTransactionException::transactionNotInitiatedOnConnection($connectionName);
            }

            throw SequenceTransactionException::transactionNotInitiated();
        }
    }

    private function ensureGroupByTokenLength(): void
    {
        if (! $this->isStrictModeEnabled()) {
            return;
        }

        $token = $this->config->groupByToken();
        if (strlen($token) > self::GROUP_BY_MAX_LENGTH) {
            throw SequenceValidationException::groupByTokenTooLong(self::GROUP_BY_MAX_LENGTH);
        }
    }

    private function isStrictModeEnabled(): bool
    {
        return (bool) config('sequence.strict_mode', true);
    }

    private function getNextNumber(): Model
    {
        $sequence = $this->getCurrentSequence();

        if ($sequence->wasRecentlyCreated) {
            return $sequence;
        }

        $lastNumber = $this->calculateNextNumber($sequence);

        $sequence->forceFill(['last_number' => $lastNumber]);
        $sequence->save();

        return $sequence;
    }

    private function getCurrentSequence(): Model
    {
        $this->ensureGroupByTokenLength();

        $sequence = $this->selectForUpdate();
        if ($sequence !== null) {
            return $sequence;
        }

        try {
            return $this->createFirstNumber();
        } catch (QueryException $exception) {
            // This can happen under concurrency: two transactions both observe "no row"
            // and race to create the first one. The UNIQUE index ensures correctness.
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $sequence = $this->selectForUpdate();
            if ($sequence === null) {
                throw $exception;
            }
        }

        return $sequence;
    }

    private function calculateNextNumber(Model $sequence): int
    {
        $last = (int) $sequence->last_number;

        $min = $this->config->getMin();
        $max = $this->config->getMax();

        // First allocation (or corrupted state)
        if ($last < $min) {
            return $min;
        }

        // Unbounded sequence → simple increment
        if ($max === null) {
            return $last + 1;
        }

        // Within bounds → increment
        if ($last < $max) {
            return $last + 1;
        }

        // Overflow handling
        return match ($this->config->getOverflowStrategy()) {
            OverflowStrategy::CYCLE => $min,

            OverflowStrategy::FAIL => throw SequenceOverflowException::limitReached($sequence->name, $max),
        };
    }

    private function selectForUpdate(): ?Model
    {
        return $this->sequenceQuery()
            ->where('name', $this->name)
            ->where('group_by', $this->config->groupByToken())
            ->lockForUpdate()
            ->first()
        ;
    }

    private function createFirstNumber(): Model
    {
        $sequence = $this->sequenceModel();
        $sequence->forceFill([
            'name' => $this->name,
            'group_by' => $this->config->groupByToken(),
            'last_number' => $this->config->getMin(),
        ]);
        $sequence->save();

        return $sequence;
    }

    private function withPrefix(int $number): string
    {
        return $this->config->getPrefix() . $this->paddedNumber($number);
    }

    private function paddedNumber(int $number): string
    {
        $padLength = $this->config->padLength();

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

    private function dispatchSequenceAssignedEvent(string $sequenceNumber, Model $sequence): void
    {
        // Dispatch an event so consumers can react when a sequence number is assigned.
        event(new SequenceAssigned(
            name: $sequence->name,
            rawNumber: $sequence->last_number,
            sequenceNumber: $sequenceNumber,
            groupByKey: $sequence->group_by,
        ));
    }

    /**
     * @return class-string<Model>
     */
    private function sequenceModelClass(): string
    {
        $class = config('sequence.model', Sequence::class);

        if (! is_string($class) || $class === '' || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw SequenceConfigException::invalidModelClass((string) $class);
        }

        return $class;
    }

    private function sequenceModel(): Model
    {
        $modelClass = $this->sequenceModelClass();

        /** @var Model $model */
        $model = new $modelClass();

        $connection = config('sequence.connection');
        if (is_string($connection) && $connection !== '') {
            $model->setConnection($connection);
        } elseif (is_string($this->connectionName) && $this->connectionName !== '') {
            $model->setConnection($this->connectionName);
        }

        return $model;
    }

    private function sequenceQuery()
    {
        return $this->sequenceModel()->newQuery();
    }
}
