<?php

namespace VocoLabs\RollNumber\Traits;

use Illuminate\Support\Str;

trait HasRollNumber
{
    public static function bootHasRollNumber()
    {
        static::creating(function (self $entity) {
            self::appendRollNumber($entity);
        });
    }

    protected function rollNumberConfig(): string|array
    {
        return [
            'column' => 'roll_number',
            'prefix' => '',
        ];
    }

    private static function appendRollNumber(self $entity)
    {
        $config = $entity->rollNumberConfig();

        $column = is_string($config) ? $config : $config['column'];

        $classNameSnake = str_replace('\\', '', Str::snake(get_class($entity)));

        $rollNumber = roll_number($classNameSnake.':'.Str::snake($column))
            ->prefix($config['prefix'] ?? '');

        if (method_exists($entity, 'getRollGroupModelName')) {
            $rollNumber->groupBy(
                $entity->getRollGroupModelName(),
                $entity->getRollGroupModelId(),
            );
        }

        // Assign roll number to the required column
        $entity->$column = $rollNumber->get();
    }
}
