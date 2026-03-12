<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Support;

use Hatchyu\RollNumber\Exceptions\RollNumberException;
use Illuminate\Database\Eloquent\Model;

final class RollNumberConfig
{
    private string $parentClass = '';

    private string $parentId = '';

    private string $prefix = '';

    private int $minimumLength = 0;

    private int $rolloverLimit = 0;

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

    public function belongsTo(Model $model): self
    {
        if (! $model->exists) {
            throw RollNumberException::modelMustExist();
        }

        return $this->groupBy(get_class($model), $model->getKey());
    }

    public function groupBy(string $parentClass, int|string $id): self
    {
        if (! class_exists($parentClass)) {
            throw RollNumberException::classNotFound($parentClass);
        }

        $this->parentClass = $parentClass;
        $this->parentId = (string) $id;

        return $this;
    }

    public function parentClass(): string
    {
        return $this->parentClass;
    }

    public function parentId(): string
    {
        return $this->parentId;
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
}
