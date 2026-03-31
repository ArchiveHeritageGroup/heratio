<?php

namespace Database\Factories;

use AhgCore\Models\QubitEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for QubitEvent (Creation, Accumulation, Maintenance events)
 */
class EventFactory extends Factory
{
    protected $model = QubitEvent::class;

    public function definition(): array
    {
        $eventTypes = [
            101 => 'creation',
            102 => 'accumulation',
            103 => 'maintenance',
            104 => 'publication',
            105 => 'modification',
        ];

        return [
            'id' => $this->faker->unique()->numberBetween(1000, 999999),
            'object_id' => null, // Set via withObject()
            'object_class' => 'QubitInformationObject',
            'actor_id' => null, // Set via withActor()
            'type_id' => $this->faker->randomElement(array_keys($eventTypes)),
            'date' => $this->faker->optional()->date(),
            'start_date' => $this->faker->optional()->date(),
            'end_date' => $this->faker->optional()->date(),
            'description' => $this->faker->optional()->sentence(),
            '更新觸發' => now(),
        ];
    }

    public function withObject(int $objectId, string $class = 'QubitInformationObject'): static
    {
        return $this->state(fn (array $attributes) => [
            'object_id' => $objectId,
            'object_class' => $class,
        ]);
    }

    public function withActor(int $actorId): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => $actorId,
        ]);
    }

    public function creation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_id' => 101,
        ]);
    }

    public function accumulation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_id' => 102,
        ]);
    }
}
