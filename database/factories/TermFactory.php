<?php

namespace Database\Factories;

use AhgCore\Models\BaseObject;
use AhgCore\Models\Term;
use AhgCore\Models\TermI18n;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Term (Taxonomy terms)
 *
 * Creates object + term + term_i18n rows matching AtoM's class table inheritance.
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'taxonomy_id' => 35, // Subject
            'parent_id' => null,
            'code' => null,
            'source_culture' => 'en',
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Term $term) {
                if (! $term->id) {
                    $object = BaseObject::create([
                        'class_name' => 'QubitTerm',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $term->id = $object->id;
                }
            })
            ->afterCreating(function (Term $term) {
                if (! $term->i18n()->where('culture', 'en')->exists()) {
                    TermI18n::create([
                        'id' => $term->id,
                        'culture' => 'en',
                        'name' => fake()->unique()->words(3, true),
                    ]);
                }
            });
    }

    public function withI18n(array $data): static
    {
        return $this->afterCreating(function (Term $term) use ($data) {
            $term->i18n()->where('culture', 'en')->update($data);
        });
    }

    public function subject(): static
    {
        return $this->state(fn () => ['taxonomy_id' => 35]);
    }

    public function place(): static
    {
        return $this->state(fn () => ['taxonomy_id' => 42]);
    }

    public function genre(): static
    {
        return $this->state(fn () => ['taxonomy_id' => 43]);
    }
}
