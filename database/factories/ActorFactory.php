<?php

namespace Database\Factories;

use AhgCore\Models\Actor;
use AhgCore\Models\ActorI18n;
use AhgCore\Models\BaseObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Actor (Authority Records)
 *
 * Creates object + actor + actor_i18n rows matching AtoM's class table inheritance.
 * Entity type IDs: 131 = Corporate body, 132 = Person, 133 = Family
 */
class ActorFactory extends Factory
{
    protected $model = Actor::class;

    public function definition(): array
    {
        return [
            'entity_type_id' => 132, // Person
            'source_culture' => 'en',
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Actor $actor) {
                if (! $actor->id) {
                    $object = BaseObject::create([
                        'class_name' => 'QubitActor',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $actor->id = $object->id;
                }
            })
            ->afterCreating(function (Actor $actor) {
                if (! $actor->i18n()->where('culture', 'en')->exists()) {
                    ActorI18n::create([
                        'id' => $actor->id,
                        'culture' => 'en',
                        'authorized_form_of_name' => fake()->name(),
                    ]);
                }
            });
    }

    /**
     * Set i18n attributes (authorized_form_of_name, history, etc.)
     */
    public function withI18n(array $data): static
    {
        return $this->afterCreating(function (Actor $actor) use ($data) {
            $actor->i18n()->where('culture', 'en')->update($data);
        });
    }

    public function person(): static
    {
        return $this->state(fn () => ['entity_type_id' => 132]);
    }

    public function family(): static
    {
        return $this->state(fn () => ['entity_type_id' => 133]);
    }

    public function corporateBody(): static
    {
        return $this->state(fn () => ['entity_type_id' => 131]);
    }
}
