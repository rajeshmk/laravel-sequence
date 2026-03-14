<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Support;

use Closure;
use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Illuminate\Database\Eloquent\Model;

final class RollNumberConfig
{
    private string $prefix = '';

    private int $minimumLength = 0;

    private int $rolloverLimit = 0;

    private array $groupByKeys = [];

    public function __construct(
        string $prefix = '',
        int $minimumLength = 0,
        private string $column = 'roll_number',
    ) {
        $this->setPrefix($prefix);
        $this->setMinimumLength($minimumLength);
    }

    public static function from(array $config): self
    {
        return new self(
            prefix: $config['prefix'] ?? '',
            minimumLength: (int) ($config['minimumLength'] ?? 0),
            column: $config['column'] ?? 'roll_number',
        );
    }

    public function prefix(string $prefix, int $minimumLength = 0): self
    {
        $this->setPrefix($prefix);
        $this->setMinimumLength($minimumLength);

        return $this;
    }

    public function column(): string
    {
        return $this->column;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function minimumLength(): int
    {
        return $this->minimumLength;
    }

    public function rolloverLimit(int $limit): self
    {
        $this->setRolloverLimit($limit);

        return $this;
    }

    public function isMaxLimitReached(int $lastNumber): bool
    {
        return $this->rolloverLimit > 0
            && $lastNumber >= $this->rolloverLimit;
    }

    public function belongsTo(Model ...$models): self
    {
        $this->groupBy(...$models);

        return $this;
    }

    public function groupBy(int|string|Model ...$groups): self
    {
        foreach ($groups as $group) {
            $this->addGroupKey($group);
        }

        return $this;
    }

    public function resolveGroupKeyUsing(): Closure
    {
        return fn () => implode('_', $this->groupByKeys);
    }

    public function groupByToken(): string
    {
        return $this->resolveGroupKeyUsing()();
    }

    private function addGroupKey(int|string|Model $group): void
    {
        if ($group instanceof Model) {
            $this->validateModel($group);

            $group = $group->getKey();
        }

        $this->groupByKeys[] = (string) $group;
    }

    private function setPrefix(string $prefix): void
    {
        $this->prefix = trim($prefix);
    }

    private function setMinimumLength(int $length): void
    {
        if ($length < 0) {
            throw RollNumberException::minimumLengthMustBeNonNegative();
        }

        $this->minimumLength = $length;
    }

    private function setRolloverLimit(int $limit): void
    {
        if ($limit < 0) {
            throw RollNumberException::rolloverLimitMustBeNonNegative();
        }

        $this->rolloverLimit = $limit;
    }

    private function validateModel(Model $model): void
    {
        if (! $model->exists) {
            throw RollNumberException::modelMustExist();
        }

        // Prevent potential issues with models that use non-string keys (e.g., composite keys).
        if (! is_string($model->getKeyName())) {
            throw RollNumberException::modelKeyMustBeString();
        }
    }
}
