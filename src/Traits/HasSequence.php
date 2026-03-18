<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Traits;

use Hatchyu\Sequence\Support\NextSequence;
use Hatchyu\Sequence\Support\SequenceColumnCollection;
use Hatchyu\Sequence\Support\SequenceConfig;

trait HasSequence
{
    public static function bootHasSequence(): void
    {
        static::creating(function (self $entity): void {
            foreach ($entity->sequenceColumns()->get() as $column => $config) {
                self::fillSequenceNumber($entity, $column, $config);
            }
        });
    }

    abstract protected function sequenceColumns(): SequenceColumnCollection;

    private static function fillSequenceNumber(self $entity, string $column, SequenceConfig $config): void
    {
        $currentValue = $entity->getAttribute($column);

        if ($currentValue !== null && $currentValue !== '') {
            return;
        }

        $sequence = NextSequence::createForModel($entity, $column, $config);

        // Assign sequence number to the required column
        $entity->{$column} = $sequence->next();
    }
}
