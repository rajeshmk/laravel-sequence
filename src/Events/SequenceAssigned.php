<?php

declare(strict_types=1);

namespace Hatchyu\Sequence\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class SequenceAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $name,
        public int $rawNumber,
        public string $sequenceNumber,
        public ?string $groupByKey = null,
    ) {}
}
