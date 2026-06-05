<?php

namespace AhgExhibition\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgExhibitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-exhibition');

        $this->migrateSpatialColumns();
    }

    /**
     * heratio#1138 - digital-twin spatial layer. Idempotently add the
     * builder/walkthrough columns to existing installs. Probe + ALTER are wrapped
     * in one outer try (reference_ci_schema_hastable) so a missing base table
     * during CI bootstrap never fatals the provider boot.
     */
    private function migrateSpatialColumns(): void
    {
        try {
            if (Schema::hasTable('ahg_exhibition_placement')) {
                $placementCols = [
                    'pos_x' => 'DECIMAL(6,5) NULL',
                    'pos_y' => 'DECIMAL(6,5) NULL',
                    'rotation_deg' => 'DECIMAL(6,2) NULL DEFAULT 0',
                    'scale' => 'DECIMAL(6,3) NULL DEFAULT 1',
                    'z_order' => 'INT NULL DEFAULT 0',
                    'wall_or_zone' => 'VARCHAR(100) NULL',
                    'label_visible' => 'TINYINT(1) NULL DEFAULT 1',
                    'model_tilt_x' => 'DECIMAL(6,2) NULL',
                    'model_tilt_z' => 'DECIMAL(6,2) NULL',
                    'wall_u' => 'DECIMAL(6,5) NULL',
                    'wall_v' => 'DECIMAL(6,5) NULL',
                ];
                foreach ($placementCols as $col => $ddl) {
                    if (! Schema::hasColumn('ahg_exhibition_placement', $col)) {
                        DB::statement("ALTER TABLE ahg_exhibition_placement ADD COLUMN {$col} {$ddl}");
                    }
                }
            }

            if (Schema::hasTable('ahg_exhibition_space')) {
                $spaceCols = [
                    'floorplan_image_path' => 'VARCHAR(500) NULL',
                    'ceiling_image_path' => 'VARCHAR(500) NULL',
                    'wall_image_path' => 'VARCHAR(500) NULL',
                    'floorplan_width_m' => 'DECIMAL(8,2) NULL',
                    'floorplan_height_m' => 'DECIMAL(8,2) NULL',
                    'walls_json' => 'JSON NULL',
                    'walkthrough_path_json' => 'JSON NULL',
                    'room_w' => 'DECIMAL(6,2) NULL',
                    'room_d' => 'DECIMAL(6,2) NULL',
                    'room_h' => 'DECIMAL(6,2) NULL',
                    'building_id' => 'VARCHAR(64) NULL',
                    'building_seq' => 'INT NULL DEFAULT 0',
                    'bld_x' => 'DECIMAL(8,2) NULL',
                    'bld_y' => 'DECIMAL(8,2) NULL',
                    'bld_rot' => 'DECIMAL(6,2) NULL DEFAULT 0',
                    'building_plan_image' => 'VARCHAR(500) NULL',
                    'building_plan_x' => 'DECIMAL(8,2) NULL',
                    'building_plan_y' => 'DECIMAL(8,2) NULL',
                    'building_plan_w' => 'DECIMAL(8,2) NULL',
                    'building_plan_h' => 'DECIMAL(8,2) NULL',
                    'doors_json' => 'JSON NULL',
                    'shape_json' => 'JSON NULL',
                ];
                foreach ($spaceCols as $col => $ddl) {
                    if (! Schema::hasColumn('ahg_exhibition_space', $col)) {
                        DB::statement("ALTER TABLE ahg_exhibition_space ADD COLUMN {$col} {$ddl}");
                    }
                }
            }

            // heratio#1146 - live data link: sensor / occupancy readings per space.
            if (! Schema::hasTable('ahg_exhibition_reading')) {
                DB::statement('CREATE TABLE ahg_exhibition_reading (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    exhibition_space_id INT NOT NULL,
                    metric VARCHAR(32) NOT NULL,
                    value DECIMAL(10,2) NOT NULL,
                    recorded_at DATETIME NOT NULL,
                    INDEX idx_space_metric_time (exhibition_space_id, metric, recorded_at)
                )');
            }
        } catch (\Throwable $e) {
            // Non-fatal: builder simply stays unavailable until the columns exist.
        }
    }
}
