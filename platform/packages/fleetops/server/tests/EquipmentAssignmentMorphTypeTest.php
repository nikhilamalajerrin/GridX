<?php

use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\Equipment;
use GridX\FleetOps\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\Relation;

test('equipment assignment type aliases normalize to model classes', function () {
    $equipment = new Equipment();

    $equipment->equipable_type = 'fleet-ops:vehicle';
    expect($equipment->equipable_type)->toBe(Vehicle::class);

    $equipment->equipable_type = 'fleet-ops:driver';
    expect($equipment->equipable_type)->toBe(Driver::class);
});

test('equipment assignment type preserves null and existing model classes', function () {
    $equipment = new Equipment();

    $equipment->equipable_type = null;
    expect($equipment->equipable_type)->toBeNull();

    $equipment->equipable_type = Vehicle::class;
    expect($equipment->equipable_type)->toBe(Vehicle::class);
});

test('legacy equipment assignment aliases are registered for morph reads', function () {
    new Equipment();

    expect(Relation::getMorphedModel('fleet-ops:vehicle'))
        ->toBe(Vehicle::class)
        ->and(Relation::getMorphedModel('fleet-ops:driver'))
        ->toBe(Driver::class);
});
