<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Traits;

use Hatchyu\RollNumber\Support\NextRollNumber;
use Hatchyu\RollNumber\Support\RollNumberConfig;
use Hatchyu\RollNumber\Support\SequenceColumnCollection;

trait HasRollNumber
{
    public static function bootHasRollNumber(): void
    {
        static::creating(function (self $entity): void {
            foreach ($entity->sequenceColumns()->get() as $column => $config) {
                self::fillSequenceNumber($entity, $column, $config);
            }
        });
    }

    abstract protected function sequenceColumns(): SequenceColumnCollection;

    private static function fillSequenceNumber(self $entity, string $column, RollNumberConfig $config): void
    {
        $currentValue = $entity->getAttribute($column);

        if ($currentValue !== null && $currentValue !== '') {
            return;
        }

        $rollNumber = NextRollNumber::createForModel($entity, $column, $config);

        // Assign roll number to the required column
        $entity->{$column} = $rollNumber->next();
    }
}
