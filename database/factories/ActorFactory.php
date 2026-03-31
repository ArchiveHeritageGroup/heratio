<?php

namespace Database\Factories;

use AhgCore\Models\QubitActor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for QubitActor (Agents/Authority Records)
 */
class ActorFactory extends Factory
{
    protected $model = QubitActor::class;

    public function definition(): array
    {
        $actorType = $this->faker->randomElement(['person', 'family', 'corporateBody']);

        return [
            'id' => $this->faker->unique()->numberBetween(1000, 999999),
            'entity_type' => $actorType,
            'authorized_form_of_name' => $this->generateName($actorType),
            '其他的名字' => $this->faker->optional()->name(),
            '偏差' => $this->faker->optional()->company(),
            '來源標準' => 'local',
            '規則' => $this->faker->optional()->sentence(),
            '歷史' => $this->faker->optional()->paragraph(),
            '範圍和內容' => $this->faker->optional()->paragraph(),
            '機構史' => $this->faker->optional()->paragraph(),
            '處所' => $this->faker->optional()->address(),
            '功能、職業、資源' => $this->faker->optional()->sentence(),
            '組成規則' => $this->faker->optional()->sentence(),
            '使用規則' => $this->faker->optional()->sentence(),
            '並行存取點' => $this->faker->optional()->name(),
            'EAD標題' => $this->faker->optional()->slug(3),
            '更新觸發' => now(),
        ];
    }

    public function person(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'person',
            'authorized_form_of_name' => $this->faker->name(),
        ]);
    }

    public function family(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'family',
            'authorized_form_of_name' => $this->faker->lastName() . ' family',
        ]);
    }

    public function corporateBody(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'corporateBody',
            'authorized_form_of_name' => $this->faker->company(),
        ]);
    }

    protected function generateName(string $type): string
    {
        return match ($type) {
            'person' => $this->faker->name(),
            'family' => $this->faker->lastName() . ' family',
            'corporateBody' => $this->faker->company(),
            default => $this->faker->company(),
        };
    }
}
