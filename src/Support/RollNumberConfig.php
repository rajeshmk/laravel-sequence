<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Support;

use Closure;
use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Illuminate\Database\Eloquent\Model;

final class RollNumberConfig
{
    private array $groupByKeys = [];

    private ?Closure $groupKeyResolver = null;

    private ?string $groupByToken = null;

    private function __construct(
        private string $prefix,
        private int $minimumLength,
        private int $rolloverLimit,
    ) {
        $this->prefix($prefix, $minimumLength);
        $this->rolloverLimit($rolloverLimit);
    }

    public static function create(
        string $prefix = '',
        int $minimumLength = 0,
        int $rolloverLimit = 0
    ): self {
        return new self($prefix, $minimumLength, $rolloverLimit);
    }

    public function prefix(string $prefix, int $minimumLength = 0): self
    {
        $this->setPrefix($prefix);
        $this->setMinimumLength($minimumLength);

        return $this;
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

    public function resolveGroupKeyUsing(Closure $callback): self
    {
        $this->groupKeyResolver = $callback;
        $this->groupByToken = null;

        return $this;
    }

    public function getGroupKeyResolver(): Closure
    {
        if ($this->groupKeyResolver) {
            return $this->groupKeyResolver;
        }

        return fn (array $keys): string => implode('_', $keys);
    }

    public function groupByToken(): string
    {
        if (! $this->groupByToken) {
            $callback = $this->getGroupKeyResolver();

            $this->groupByToken = $callback($this->groupByKeys);
        }

        return $this->groupByToken;
    }

    private function addGroupKey(int|string|Model $group): void
    {
        if ($group instanceof Model) {
            $this->validateModel($group);

            $group = $group->getKey();
        }

        $this->groupByKeys[] = (string) $group;
        $this->groupByToken = null;
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
