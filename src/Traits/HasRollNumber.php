<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Traits;

use Hatchyu\RollNumber\Support\NextRollNumber;
use Hatchyu\RollNumber\Support\RollNumberConfig;

trait HasRollNumber
{
    public static function bootHasRollNumber(): void
    {
        static::creating(function (self $entity): void {
            self::assignRollNumber($entity);
        });
    }

    abstract protected function rollNumberConfig(): RollNumberConfig;

    private static function assignRollNumber(self $entity): void
    {
        $config = $entity->rollNumberConfig();

        $column = $config->column();

        $currentValue = $entity->getAttribute($column);
        if ($currentValue !== null && $currentValue !== '') {
            return;
        }

        $rollNumber = NextRollNumber::createForModel($entity, $config);

        // Assign roll number to the required column
        $entity->{$column} = $rollNumber->next();
    }
}
