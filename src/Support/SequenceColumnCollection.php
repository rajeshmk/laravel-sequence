<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Support;

final class SequenceColumnCollection
{
    /**
     * @var array<string, SequenceConfig>
     */
    private array $columns = [];

    /**
     * Private constructor.
     */
    private function __construct() {}

    public static function collection(): static
    {
        return new self();
    }

    public function column(string $column, SequenceConfig $config): self
    {
        $this->columns[$column] = $config;

        return $this;
    }

    /**
     * @return array<string, SequenceConfig>
     */
    public function get(): array
    {
        return $this->columns;
    }

    public function isEmpty(): bool
    {
        return count($this->columns) === 0;
    }
}
