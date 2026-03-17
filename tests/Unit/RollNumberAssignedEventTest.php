<?php

declare(strict_types=1);
use Hatchyu\RollNumber\Events\RollNumberAssigned;

it('can construct RollNumberAssigned event', function () {
    $event = new RollNumberAssigned(
        name: 'orders',
        rawNumber: 12,
        sequenceNumber: 'ORD-00012',
        groupByKey: 'tenant_1',
        model: null
    );

    expect($event->name)->toBe('orders')
        ->and($event->rawNumber)->toBe(12)
        ->and($event->sequenceNumber)->toBe('ORD-00012')
        ->and($event->groupByKey)->toBe('tenant_1')
        ->and($event->model)->toBeNull()
    ;
});
