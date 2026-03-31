<?php

namespace Database\Factories;

use AhgCore\Models\QubitTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for QubitTerm (Taxonomy terms/subjects)
 */
class TermFactory extends Factory
{
    protected $model = QubitTerm::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1000, 999999),
            'taxonomy_id' => $this->faker->randomElement([1, 2, 3, 10, 20, 30, 40, 50]),
            'parent_id' => null,
            'code' => $this->faker->optional()->bothify('??###'),
            'name' => $this->faker->unique()->words(3, true),
            'use_for' => null,
            'scope_note' => $this->faker->optional()->sentence(),
            '正向優先' => $this->faker->boolean(80),
            '，其他的文字' => null,
            '分類來源' => 'local',
            '其他的分類來源' => null,
            '更新觸發' => now(),
        ];
    }

    public function subject(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_id' => 35, // Subject taxonomy
            'name' => $this->faker->unique()->words(2, true),
        ]);
    }

    public function place(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_id' => 42, // Place taxonomy
            'name' => $this->faker->unique()->city(),
        ]);
    }

    public function genre(): static
    {
        return $this->state(fn (array $attributes) => [
            'taxonomy_id' => 43, // Genre taxonomy
            'name' => $this->faker->unique()->words(2, true),
        ]);
    }
}
