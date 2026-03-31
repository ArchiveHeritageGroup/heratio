<?php

namespace Database\Factories;

use AhgCore\Models\QubitAccession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for QubitAccession (Accessions)
 */
class AccessionFactory extends Factory
{
    protected $model = QubitAccession::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1000, 999999),
            'identifier' => $this->faker->unique()->bothify('ACC-####-####'),
            'title' => $this->faker->sentence(4),
            'date' => $this->faker->date(),
            'created_at' => now(),
            'updated_at' => now(),
            '来源' => $this->faker->optional()->name(),
            '取得方式' => $this->faker->randomElement(['gift', 'purchase', 'deposit', 'transfer', 'donation']),
            '取得描述' => $this->faker->optional()->paragraph(),
            '遗赠' => null,
            '版税' => null,
            '版权状态' => $this->faker->randomElement(['unknown', 'public_domain', 'copyright', 'orphaned']),
            '版权到期日期' => null,
            '版权持有人' => null,
            '物理描述' => $this->faker->optional()->sentence(),
            '物理描述媒介' => null,
            'condition' => $this->faker->randomElement(['good', 'fair', 'poor', 'excellent']),
            '说明' => $this->faker->optional()->paragraph(),
            '位置' => $this->faker->optional()->word(),
            'storage_location' => $this->faker->optional()->word(),
            '陈列位置' => null,
            '接收参考' => null,
            '接收日期' => null,
            '所需设备' => null,
            '评价备注' => null,
            'appraisal_note' => null,
            'appraisal_date' => null,
            'appraisal_by' => null,
            '收集政策' => null,
            '收集政策_note' => null,
            '归档_note' => null,
            '相关收藏' => null,
            'related_material' => null,
            '外部机构' => null,
            'external_documents' => null,
            'processing_notes' => null,
            'processing_priority' => null,
            'processing_date' => null,
            'processing_by' => null,
            'repository_id' => $this->faker->optional()->numberBetween(1, 100),
            'donor_id' => $this->faker->optional()->numberBetween(1, 100),
            '資源类型' => null,
            'scope_and_content' => null,
            'rights_act' => null,
            'rights_note' => null,
            '版税_note' => null,
            'disclosure' => null,
            'update_trigger' => now(),
        ];
    }

    public function gift(): static
    {
        return $this->state(fn (array $attributes) => [
            '取得方式' => 'gift',
            '遗赠' => 0,
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            '取得方式' => 'purchase',
            '版税' => $this->faker->optional()->numberBetween(100, 10000),
        ]);
    }

    public function donation(): static
    {
        return $this->state(fn (array $attributes) => [
            '取得方式' => 'donation',
            '来源' => $this->faker->name(),
        ]);
    }
}
