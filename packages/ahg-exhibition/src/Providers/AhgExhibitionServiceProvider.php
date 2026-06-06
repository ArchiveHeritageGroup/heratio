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
                    'spotlight' => 'TINYINT(1) NULL DEFAULT 0',   // #1174: dim surroundings + spotlight this object on approach
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
                    'guided_tour_json' => 'JSON NULL',   // heratio#guided-tour: authored audio tour (route + narration)
                    'floor_level' => 'INT NOT NULL DEFAULT 0',   // heratio#1169: numeric building level (0 = ground). NB distinct from the legacy text `floor` label.
                    'is_outdoor' => 'TINYINT(1) NOT NULL DEFAULT 0',   // heratio#1170: open-air space (sky + grass, no walls)
                    'stairs_json' => 'JSON NULL',            // heratio#1169: stair links [{x,z,from_floor,to_floor}]
                    'windows_json' => 'JSON NULL',           // heratio#1172: windows per wall [{wall,pos,width,sill,height}]
                    'bld_group' => 'VARCHAR(40) NULL',       // heratio#1143: plan grouping - rooms sharing a snapped wall move as one unit
                    'bld_locked' => 'TINYINT(1) NULL DEFAULT 0',   // heratio#1143: room "done" lock (walls aligned flush; no move/resize)
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

            // heratio#1150 - multi-user presence: live co-visitors + docent state per building.
            if (! Schema::hasTable('ahg_exhibition_presence')) {
                DB::statement("CREATE TABLE ahg_exhibition_presence (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    building_id VARCHAR(64) NOT NULL,
                    session_token VARCHAR(64) NOT NULL,
                    display_name VARCHAR(120) NULL,
                    role VARCHAR(16) NOT NULL DEFAULT 'visitor',
                    color VARCHAR(9) NULL,
                    room_id INT NULL,
                    pos_x DOUBLE NULL, pos_y DOUBLE NULL, pos_z DOUBLE NULL, yaw DOUBLE NULL,
                    tour_active TINYINT(1) NOT NULL DEFAULT 0,
                    focus_object_id INT NULL,
                    docent_msg VARCHAR(280) NULL,
                    last_seen DATETIME NOT NULL,
                    UNIQUE KEY uq_building_token (building_id, session_token),
                    INDEX idx_building_seen (building_id, last_seen)
                )");
            }

            // heratio#1165 - wall graffiti / annotations placed in the walkthrough.
            if (! Schema::hasTable('ahg_exhibition_annotation')) {
                DB::statement("CREATE TABLE ahg_exhibition_annotation (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    building_id VARCHAR(64) NOT NULL,
                    room_id INT NULL,
                    pos_x DOUBLE NOT NULL, pos_y DOUBLE NOT NULL, pos_z DOUBLE NOT NULL,
                    text VARCHAR(160) NOT NULL,
                    color VARCHAR(9) NULL,
                    author VARCHAR(40) NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_building (building_id)
                )");
            }

            // heratio#1173 - automatic visitor analytics: one row per walkthrough session.
            if (! Schema::hasTable('ahg_exhibition_visit')) {
                DB::statement("CREATE TABLE ahg_exhibition_visit (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    building_id VARCHAR(64) NOT NULL,
                    session_token VARCHAR(64) NOT NULL,
                    device VARCHAR(16) NULL,
                    cur_room INT NULL,
                    room_entered_at DATETIME NULL,
                    room_seconds_json JSON NULL,
                    started_at DATETIME NOT NULL,
                    last_seen DATETIME NOT NULL,
                    UNIQUE KEY uq_visit (building_id, session_token),
                    INDEX idx_building_started (building_id, started_at)
                )");
            }
            // heratio#1173 - per-object / tour / door events within a visit.
            if (! Schema::hasTable('ahg_exhibition_visit_event')) {
                DB::statement("CREATE TABLE ahg_exhibition_visit_event (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    building_id VARCHAR(64) NOT NULL,
                    session_token VARCHAR(64) NOT NULL,
                    type VARCHAR(16) NOT NULL,
                    room_id INT NULL,
                    object_id INT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_building_type (building_id, type, created_at)
                )");
            }
        } catch (\Throwable $e) {
            // Non-fatal: builder simply stays unavailable until the columns exist.
        }
    }
}
