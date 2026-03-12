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
        public string $name,
        public string $value,
        public string $groupingType = '',
        public string|int|null $groupingId = null,
        public ?Model $model = null,
    ) {
    }
}
