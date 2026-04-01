<?php

namespace Database\Factories;

use AhgCore\Models\BaseObject;
use AhgCore\Models\Event;
use AhgCore\Models\EventI18n;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Event (Creation, Accumulation, etc.)
 *
 * Creates object + event + event_i18n rows.
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'type_id' => Event::CREATION_ID,
            'object_id' => null,
            'actor_id' => null,
            'start_date' => fake()->optional()->date(),
            'end_date' => null,
            'source_culture' => 'en',
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Event $event) {
                if (! $event->id) {
                    $object = BaseObject::create([
                        'class_name' => 'QubitEvent',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $event->id = $object->id;
                }
            })
            ->afterCreating(function (Event $event) {
                EventI18n::create([
                    'id' => $event->id,
                    'culture' => 'en',
                ]);
            });
    }

    public function withObject(int $objectId): static
    {
        return $this->state(fn () => ['object_id' => $objectId]);
    }

    public function withActor(int $actorId): static
    {
        return $this->state(fn () => ['actor_id' => $actorId]);
    }

    public function creation(): static
    {
        return $this->state(fn () => ['type_id' => Event::CREATION_ID]);
    }

    public function accumulation(): static
    {
        return $this->state(fn () => ['type_id' => Event::ACCUMULATION_ID]);
    }
}
