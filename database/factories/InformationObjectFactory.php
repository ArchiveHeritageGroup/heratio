<?php

namespace Database\Factories;

use AhgCore\Models\BaseObject;
use AhgCore\Models\InformationObject;
use AhgCore\Models\InformationObjectI18n;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for InformationObject (Archival Descriptions)
 *
 * Creates object + information_object + information_object_i18n rows.
 */
class InformationObjectFactory extends Factory
{
    protected $model = InformationObject::class;

    public function definition(): array
    {
        return [
            'identifier' => fake()->optional()->bothify('??-###-####'),
            'level_of_description_id' => null,
            'parent_id' => InformationObject::ROOT_ID,
            'repository_id' => null,
            'source_culture' => 'en',
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (InformationObject $io) {
                if (! $io->id) {
                    $object = BaseObject::create([
                        'class_name' => 'QubitInformationObject',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $io->id = $object->id;
                }
            })
            ->afterCreating(function (InformationObject $io) {
                if (! $io->i18n()->where('culture', 'en')->exists()) {
                    InformationObjectI18n::create([
                        'id' => $io->id,
                        'culture' => 'en',
                        'title' => fake()->sentence(4),
                    ]);
                }
            });
    }

    public function withI18n(array $data): static
    {
        return $this->afterCreating(function (InformationObject $io) use ($data) {
            $io->i18n()->where('culture', 'en')->update($data);
        });
    }

    // Real term IDs from taxonomy 34 (Levels of description)
    const LEVEL_COLLECTION = 238;
    const LEVEL_SERIES = 239;
    const LEVEL_FILE = 241;
    const LEVEL_ITEM = 242;

    public function collection(): static
    {
        return $this->state(fn () => ['level_of_description_id' => self::LEVEL_COLLECTION]);
    }

    public function series(): static
    {
        return $this->state(fn () => ['level_of_description_id' => self::LEVEL_SERIES]);
    }

    public function file(): static
    {
        return $this->state(fn () => ['level_of_description_id' => self::LEVEL_FILE]);
    }

    public function item(): static
    {
        return $this->state(fn () => ['level_of_description_id' => self::LEVEL_ITEM]);
    }
}
