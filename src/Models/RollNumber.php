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
        'group_by',
        'last_number',
    ];

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        return config('roll-number.table', 'roll_numbers');
    }

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
