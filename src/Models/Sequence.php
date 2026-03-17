<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class Sequence extends Model
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
        return $this->table ?? config('sequence.table', 'sequences');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
