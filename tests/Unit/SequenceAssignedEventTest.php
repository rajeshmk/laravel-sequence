<?php

declare(strict_types=1);
use Hatchyu\Sequence\Events\SequenceAssigned;

it('can construct SequenceAssigned event', function () {
    $event = new SequenceAssigned(
        name: 'orders',
        rawNumber: 12,
        sequenceNumber: 'ORD-00012',
        groupByKey: 'tenant_1',
    );

    expect($event->name)->toBe('orders')
        ->and($event->rawNumber)->toBe(12)
        ->and($event->sequenceNumber)->toBe('ORD-00012')
        ->and($event->groupByKey)->toBe('tenant_1')
    ;
});
