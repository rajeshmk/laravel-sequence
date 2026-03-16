<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RollNumberAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly int $rawNumber,
        public readonly string $sequenceNumber,
        public readonly ?string $groupByKey = null,
        public readonly ?Model $model = null,
    ) {}
}
