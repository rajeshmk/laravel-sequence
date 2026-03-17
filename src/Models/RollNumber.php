<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

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

    #[Override]
    public function getTable(): string
    {
        return $this->table ?? config('roll-number.table', 'roll_numbers');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
