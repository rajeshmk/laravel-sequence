<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class RollNumberAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public int $rawNumber,
        public string $sequenceNumber,
        public ?string $groupByKey = null,
        public ?Model $model = null,
    ) {}
}
