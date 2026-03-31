<?php

namespace Database\Factories;

use AhgCore\Models\QubitInformationObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for QubitInformationObject (Archival Descriptions/Records)
 */
class InformationObjectFactory extends Factory
{
    protected $model = QubitInformationObject::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1000, 999999),
            'identifier' => $this->faker->optional()->bothify('??-###-####'),
            'level_of_description_id' => $this->faker->randomElement([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]),
            'parent_id' => null,
            'repository_id' => $this->faker->optional()->numberBetween(1, 100),
            'accession_id' => $this->faker->optional()->numberBetween(1, 1000),
            'title' => $this->faker->sentence(4),
            '歷史' => $this->faker->optional()->paragraph(),
            '檔案材料內容' => $this->faker->optional()->paragraph(),
            '系統排列' => $this->faker->optional()->sentence(),
            '存取條件' => $this->faker->optional()->sentence(),
            '利用條件' => $this->faker->optional()->sentence(),
            '語文文種' => $this->faker->optional()->word(),
            '物理媒介' => $this->faker->optional()->word(),
            '裝訂' => $this->faker->optional()->word(),
            '備註' => $this->faker->optional()->sentence(),
            'rights_holder' => $this->faker->optional()->name(),
            '版權狀態' => $this->faker->randomElement(['public', 'copyright', 'unknown']),
            '獲取方式' => $this->faker->optional()->word(),
            '創建日期' => $this->faker->optional()->date(),
            '更新觸發' => now(),
        ];
    }

    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    public function collection(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_description_id' => 1, // Collection
            'title' => 'Collection: ' . $this->faker->words(3, true),
        ]);
    }

    public function series(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_description_id' => 2, // Series
            'title' => 'Series: ' . $this->faker->words(3, true),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_description_id' => 3, // File
            'title' => $this->faker->words(3, true) . ' file',
        ]);
    }

    public function item(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_description_id' => 4, // Item
            'title' => $this->faker->words(3, true) . ' item',
        ]);
    }
}
