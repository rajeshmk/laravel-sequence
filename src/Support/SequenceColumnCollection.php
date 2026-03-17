<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Support;

final class SequenceColumnCollection
{
    /**
     * @var array<string, RollNumberConfig>
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

    public function column(string $column, RollNumberConfig $config): self
    {
        $this->columns[$column] = $config;

        return $this;
    }

    /**
     * @return array<string, RollNumberConfig>
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
