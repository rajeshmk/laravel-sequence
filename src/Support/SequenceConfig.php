<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Support;

use Closure;
use Hatchyu\Sequence\Enums\OverflowStrategy;
use Hatchyu\Sequence\Exceptions\SequenceConfigException;
use Hatchyu\Sequence\Exceptions\SequenceModelException;
use Illuminate\Database\Eloquent\Model;

final class SequenceConfig
{
    private const string FORMAT_PLACEHOLDER = '?';

    private array $groupByKeys = [];

    private ?Closure $groupKeyResolver = null;

    private ?string $groupByToken = null;

    private int $min = 1;

    private ?int $max = null;

    private OverflowStrategy $overflowStrategy = OverflowStrategy::FAIL;

    private ?string $format = null;

    private function __construct(
        private string $prefix,
        private int $padLength,
    ) {
        $this->prefix($prefix, $padLength);
    }

    public static function create(
        string $prefix = '',
        int $padLength = 0,
    ): self {
        return new self($prefix, $padLength);
    }

    public function prefix(string $prefix, int $padLength = 0): self
    {
        $this->setPrefix($prefix);
        $this->setPadLength($padLength);

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function format(string $format): self
    {
        $this->setFormat($format);

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function range(int $min, ?int $max = null): self
    {
        return $this->setRange($min, $max);
    }

    public function bounded(int $min, int $max): self
    {
        return $this->range($min, $max)
            ->throwOnOverflow()
        ;
    }

    public function cyclingRange(int $min, int $max): self
    {
        return $this->range($min, $max)
            ->cycle()
        ;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function cycle(): self
    {
        return $this->setOverflowStrategy(OverflowStrategy::CYCLE);
    }

    public function throwOnOverflow(): self
    {
        return $this->setOverflowStrategy(OverflowStrategy::FAIL);
    }

    public function getOverflowStrategy(): OverflowStrategy
    {
        return $this->overflowStrategy;
    }

    public function padLength(): int
    {
        return $this->padLength;
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
        if ($this->groupKeyResolver instanceof Closure) {
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

    // -------------------------------------------------------------------------
    // Private functions
    // -------------------------------------------------------------------------

    private function setOverflowStrategy(OverflowStrategy $strategy): self
    {
        $this->overflowStrategy = $strategy;

        return $this;
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

    private function setPadLength(int $length): void
    {
        if ($length < 0) {
            throw SequenceConfigException::padLengthMustBeNonNegative();
        }

        $this->padLength = $length;
    }

    private function setFormat(string $format): void
    {
        $format = trim($format);

        if (! str_contains($format, self::FORMAT_PLACEHOLDER)) {
            throw SequenceConfigException::formatPlaceholderMissing();
        }

        $this->format = $format;
    }

    private function setRange(int $min, ?int $max = null): self
    {
        if ($min < 0) {
            throw SequenceConfigException::minMustBeNonNegative();
        }

        if ($max !== null && $max < 1) {
            throw SequenceConfigException::maxMustBeAtLeastOne();
        }

        if ($max !== null && $max < $min) {
            throw SequenceConfigException::maxMustBeGreaterOrEqualMin($min);
        }

        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    private function validateModel(Model $model): void
    {
        if (! $model->exists) {
            throw SequenceModelException::modelMustExist();
        }

        // Prevent potential issues with models that use non-string keys (e.g., composite keys).
        if (! is_string($model->getKeyName())) {
            throw SequenceModelException::modelKeyMustBeString();
        }
    }
}
