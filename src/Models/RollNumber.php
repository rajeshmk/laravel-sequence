<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Models;

use Illuminate\Database\Eloquent\Model;

class RollNumber extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'grouping_type',
        'grouping_id',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }

    protected string $table = '';

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        return config('roll-number.table', 'roll_numbers');
    }
}
