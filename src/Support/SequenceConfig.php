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

    private const int GROUP_BY_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private array $groupByKeys = [];

    private ?string $groupByToken = null;

    private int $min = 1;

    private ?int $max = null;

    private OverflowStrategy $overflowStrategy = OverflowStrategy::FAIL;

    private Closure|string|null $format = null;

    private int $step = 1;

    private function __construct(
        private string $prefix,
        private int $padLength,
    ) {
        $this->prefix($prefix);
        $this->padLength($padLength);
    }

    public static function create(): self
    {
        return new self('', 0);
    }

    public function prefix(string $prefix): self
    {
        $this->setPrefix($prefix);

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function format(Closure|string $format): self
    {
        $this->setFormat($format);

        return $this;
    }

    public function getFormat(): Closure|string|null
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

    public function padLength(int $length): self
    {
        $this->setPadLength($length);

        return $this;
    }

    public function getPadLength(): int
    {
        return $this->padLength;
    }

    public function step(int $step): self
    {
        $this->setStep($step);

        return $this;
    }

    public function getStep(): int
    {
        return $this->step;
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

    public function groupByYear(): self
    {
        return $this->groupBy(date('Y'));
    }

    public function groupByMonth(): self
    {
        return $this->groupBy(date('Ym'));
    }

    public function groupByDay(): self
    {
        return $this->groupBy(date('Ymd'));
    }

    public function groupByToken(): string
    {
        if (! $this->groupByToken) {
            if ($this->groupByKeys === []) {
                $this->groupByToken = '';
            } else {
                $this->groupByToken = json_encode($this->groupByKeys, self::GROUP_BY_JSON_FLAGS) ?: '';
            }
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
            $group = $this->validatedModelKey($group);
        }

        $this->groupByKeys[] = (string) $group;
        $this->groupByToken = null;
    }

    private function validatedModelKey(Model $model): int|string
    {
        $this->validateModel($model);

        $key = $model->getKey();
        if (! is_int($key) && ! is_string($key)) {
            throw SequenceModelException::modelKeyMustBeString();
        }

        return $key;
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

    private function setFormat(Closure|string $format): void
    {
        if ($format instanceof Closure) {
            $this->format = $format;

            return;
        }

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

    private function setStep(int $step): void
    {
        if ($step < 1) {
            throw SequenceConfigException::stepMustBeAtLeastOne();
        }

        $this->step = $step;
    }

    private function validateModel(Model $model): void
    {
        if (! $model->exists) {
            throw SequenceModelException::modelMustExist();
        }

        // Eloquent does not natively support composite primary keys. Guard early so
        // groupBy($model) never tries to resolve an array key name/value.
        if (! is_string($model->getKeyName())) {
            throw SequenceModelException::modelKeyMustBeString();
        }
    }
}
