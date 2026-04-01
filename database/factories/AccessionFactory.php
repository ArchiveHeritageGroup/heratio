<?php

namespace Database\Factories;

use AhgCore\Models\Accession;
use AhgCore\Models\AccessionI18n;
use AhgCore\Models\BaseObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Accession
 *
 * Creates object + accession + accession_i18n rows.
 */
class AccessionFactory extends Factory
{
    protected $model = Accession::class;

    public function definition(): array
    {
        return [
            'identifier' => fake()->unique()->bothify('ACC-####-####'),
            'date' => fake()->date(),
            'source_culture' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Accession $accession) {
                if (! $accession->id) {
                    $object = BaseObject::create([
                        'class_name' => 'QubitAccession',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $accession->id = $object->id;
                }
            })
            ->afterCreating(function (Accession $accession) {
                if (! $accession->i18n()->where('culture', 'en')->exists()) {
                    AccessionI18n::create([
                        'id' => $accession->id,
                        'culture' => 'en',
                        'title' => fake()->sentence(4),
                    ]);
                }
            });
    }

    public function withI18n(array $data): static
    {
        return $this->afterCreating(function (Accession $accession) use ($data) {
            $accession->i18n()->where('culture', 'en')->update($data);
        });
    }

    public function gift(): static
    {
        return $this->state(fn () => ['acquisition_type_id' => null]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => ['acquisition_type_id' => null]);
    }

    public function donation(): static
    {
        return $this->state(fn () => ['acquisition_type_id' => null]);
    }
}
