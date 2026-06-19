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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgExhibition\Console\Commands\LostPlaceGatherCommand::class,
            ]);
        }

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
                    'spotlight' => 'TINYINT NULL DEFAULT 0',   // #1174 spotlight mode: 0 off, 1 light on approach, 2 always-on
                    'display_case' => 'TINYINT(1) NULL DEFAULT 0',   // show this item inside a glass display case on a plinth
                    'on_floor' => 'TINYINT(1) NULL DEFAULT 0',   // stand the 3D model directly on the floor (no pedestal)
                    'view_x' => 'DECIMAL(6,5) NULL',   // curator-set viewing spot (room-local fraction) the tour/walk stands at
                    'view_y' => 'DECIMAL(6,5) NULL',
                    // heratio#1155 federated twin foundation: a placement may reference a REMOTE
                    // object borrowed from a peer institution's scene.json export instead of a
                    // local information_object_id. Read-only; attribution + media URLs live in
                    // remote_payload. No FK (the object is owned by the peer).
                    'remote_peer_id' => 'INT NULL',                 // federation_peer.id the object came from (nullable: ad-hoc by URL)
                    'remote_ref' => 'VARCHAR(255) NULL',            // the peer's object reference (e.g. its information_object_id)
                    'remote_payload' => 'JSON NULL',                // normalised stop: {title,description,image_url,model_url,model_format,kind,record_url,peer_name}
                ];
                foreach ($placementCols as $col => $ddl) {
                    if (! Schema::hasColumn('ahg_exhibition_placement', $col)) {
                        DB::statement("ALTER TABLE ahg_exhibition_placement ADD COLUMN {$col} {$ddl}");
                    }
                }
                // #1155: a borrowed remote object has NO local IO, so relax the historically
                // NOT NULL information_object_id to allow NULL (once; idempotent). The FK still
                // permits NULL, so local placements are unaffected.
                $ioCol = DB::selectOne("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ahg_exhibition_placement' AND column_name = 'information_object_id'");
                if ($ioCol && strtoupper((string) $ioCol->IS_NULLABLE) === 'NO') {
                    DB::statement('ALTER TABLE ahg_exhibition_placement MODIFY information_object_id INT NULL');
                }
            }

            if (Schema::hasTable('ahg_exhibition_space')) {
                $spaceCols = [
                    'floorplan_image_path' => 'VARCHAR(500) NULL',
                    'ceiling_image_path' => 'VARCHAR(500) NULL',
                    'wall_image_path' => 'VARCHAR(500) NULL',
                    'wall_images_json' => 'JSON NULL',   // #wall-pictures: per-edge wall images {edgeIndex: path}; wall_image_path is the all-walls default
                    'floor_image_path' => 'VARCHAR(500) NULL',   // decorative floor picture stretched over the room floor (overrides marble)
                    'floor_grout' => 'TINYINT(1) NULL DEFAULT 0',   // overlay a grout grid on the uploaded floor image
                    'floor_tile_m' => 'DECIMAL(5,2) NULL DEFAULT 2.00',   // floor tile size in metres (marble tiles + floor-image grout); configurable per room
                    'floor_grout_mm' => 'DECIMAL(5,1) NULL DEFAULT 8.0',   // grout-line width in millimetres on the floor-image grout grid
                    'wall_color' => 'VARCHAR(9) NULL',   // all-walls paint colour (#hex) used when no wall image
                    'wall_colors_json' => 'JSON NULL',   // per-edge paint colours {edgeIndex: '#hex'}; wall_color is the all-walls default
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
                    'scan_shell_path' => 'VARCHAR(500) NULL',          // heratio#1156: photoreal capture shell (glTF/GLB/OBJ/STL/PLY) rendered as the room backdrop
                    'scan_shell_scale' => 'DECIMAL(8,3) NULL DEFAULT 1.000',   // heratio#1156: uniform scale applied to the scan shell (fit to room metres)
                    'scan_embed_url' => 'VARCHAR(500) NULL',           // heratio#1156: 360/Matterport embed URL (opened in an overlay; licensing handled by the host)
                    'sensor_token' => 'VARCHAR(64) NULL',              // heratio#1188: per-space token a real IoT sensor/gateway uses to POST readings
                    'ric_activity_id' => 'BIGINT NULL',                // heratio#1195: the RiC Activity entity this space is published as (graph integration)
                    'intro_text' => 'TEXT NULL',                       // heratio#1186: AI-generated exhibition introduction (held on the main/first room only)
                    'room_blurb' => 'TEXT NULL',                       // heratio#1186: AI-generated short blurb for THIS room
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
            // Graffiti pinned flat to a wall: yaw (radians) of the wall it was tagged on (null = old billboard style).
            if (Schema::hasTable('ahg_exhibition_annotation') && ! Schema::hasColumn('ahg_exhibition_annotation', 'yaw')) {
                DB::statement('ALTER TABLE ahg_exhibition_annotation ADD COLUMN yaw DOUBLE NULL');
            }

            // heratio#1188: when a conservation alert was escalated to staff (throttle marker).
            if (Schema::hasTable('ahg_exhibition_alert') && ! Schema::hasColumn('ahg_exhibition_alert', 'notified_at')) {
                DB::statement('ALTER TABLE ahg_exhibition_alert ADD COLUMN notified_at TIMESTAMP NULL');
            }

            // Furniture & fittings: placeable props per room (bench/pedestal/case/planter/table/railing).
            if (! Schema::hasTable('ahg_exhibition_furniture')) {
                DB::statement("CREATE TABLE ahg_exhibition_furniture (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    exhibition_space_id INT NOT NULL,
                    kind VARCHAR(32) NOT NULL,
                    pos_x DOUBLE NOT NULL DEFAULT 0.5,
                    pos_y DOUBLE NOT NULL DEFAULT 0.5,
                    rotation_deg DOUBLE NOT NULL DEFAULT 0,
                    scale DOUBLE NOT NULL DEFAULT 1,
                    segments INT NOT NULL DEFAULT 2,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    INDEX idx_room (exhibition_space_id)
                )");
            }
            // Rope-railing extendability: pole count (rope spans between consecutive poles).
            if (Schema::hasTable('ahg_exhibition_furniture') && ! Schema::hasColumn('ahg_exhibition_furniture', 'segments')) {
                DB::statement('ALTER TABLE ahg_exhibition_furniture ADD COLUMN segments INT NOT NULL DEFAULT 2');
            }
            // Rope-railing per-pole layout: explicit pole offsets [{x,z}] in metres relative to the railing centre
            // (null = fall back to evenly-spaced `segments`).
            if (Schema::hasTable('ahg_exhibition_furniture') && ! Schema::hasColumn('ahg_exhibition_furniture', 'pole_json')) {
                DB::statement('ALTER TABLE ahg_exhibition_furniture ADD COLUMN pole_json JSON NULL');
            }
            // Custom furniture library: uploaded 3D models / images, reusable across all rooms.
            if (! Schema::hasTable('ahg_exhibition_furniture_asset')) {
                DB::statement("CREATE TABLE ahg_exhibition_furniture_asset (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    label VARCHAR(120) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    ext VARCHAR(8) NOT NULL,
                    asset_kind VARCHAR(8) NOT NULL DEFAULT 'model',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL
                )");
            }
            // Furniture-library description (captured on upload).
            if (Schema::hasTable('ahg_exhibition_furniture_asset') && ! Schema::hasColumn('ahg_exhibition_furniture_asset', 'description')) {
                DB::statement('ALTER TABLE ahg_exhibition_furniture_asset ADD COLUMN description TEXT NULL');
            }
            // A placed furniture row can reference an uploaded asset (denormalised so the walkthrough payload is self-contained).
            foreach (['asset_path' => 'VARCHAR(500) NULL', 'asset_ext' => 'VARCHAR(8) NULL'] as $fcol => $fddl) {
                if (Schema::hasTable('ahg_exhibition_furniture') && ! Schema::hasColumn('ahg_exhibition_furniture', $fcol)) {
                    DB::statement("ALTER TABLE ahg_exhibition_furniture ADD COLUMN {$fcol} {$fddl}");
                }
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
            // heratio#1187 - per-object dwell within a visit (attention heatmap). Banked
            // server-side from the presence beat exactly the way per-room dwell is, so we
            // can shade individual objects by how long visitors actually lingered on them.
            foreach (['cur_object' => 'INT NULL', 'object_entered_at' => 'DATETIME NULL', 'object_seconds_json' => 'JSON NULL'] as $vcol => $vddl) {
                if (Schema::hasTable('ahg_exhibition_visit') && ! Schema::hasColumn('ahg_exhibition_visit', $vcol)) {
                    DB::statement("ALTER TABLE ahg_exhibition_visit ADD COLUMN {$vcol} {$vddl}");
                }
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
            // heratio#1188 - conservation threshold alerts raised on sensor ingest.
            if (! Schema::hasTable('ahg_exhibition_alert')) {
                DB::statement("CREATE TABLE ahg_exhibition_alert (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    exhibition_space_id INT NOT NULL,
                    metric VARCHAR(24) NOT NULL,
                    value DECIMAL(10,3) NULL,
                    threshold VARCHAR(64) NULL,
                    severity VARCHAR(12) NOT NULL DEFAULT 'warning',
                    message VARCHAR(255) NULL,
                    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    INDEX idx_space_created (exhibition_space_id, created_at)
                )");
            }
            // heratio#1192 - live virtual openings: a scheduled, ticketed event hosted
            // in an exhibition space's walkthrough. FIRST SLICE = scheduling + capacity
            // RSVP/ticketing + a public event page that links into the existing
            // walkthrough at event time. Live multi-user spatial presence is #1150.
            if (! Schema::hasTable('ahg_exhibition_event')) {
                DB::statement("CREATE TABLE ahg_exhibition_event (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    exhibition_space_id INT NOT NULL,
                    public_token VARCHAR(32) NOT NULL,
                    title VARCHAR(200) NOT NULL,
                    host_name VARCHAR(160) NULL,
                    description TEXT NULL,
                    starts_at DATETIME NOT NULL,
                    duration_minutes INT NOT NULL DEFAULT 60,
                    capacity INT NOT NULL DEFAULT 50,
                    status VARCHAR(16) NOT NULL DEFAULT 'scheduled',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_event_token (public_token),
                    INDEX idx_space_start (exhibition_space_id, starts_at)
                )");
            }
            // heratio#1192 - one RSVP / ticket row per attendee for an event.
            if (! Schema::hasTable('ahg_exhibition_event_rsvp')) {
                DB::statement("CREATE TABLE ahg_exhibition_event_rsvp (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    ticket_code VARCHAR(32) NOT NULL,
                    name VARCHAR(160) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    party_size INT NOT NULL DEFAULT 1,
                    status VARCHAR(16) NOT NULL DEFAULT 'confirmed',
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY uq_ticket (ticket_code),
                    UNIQUE KEY uq_event_email (event_id, email),
                    INDEX idx_event (event_id)
                )");
            }

            // heratio#1192 slice 2b - PAID ticketing. Additive, idempotent: a price +
            // currency on the event, and the paid amount + timestamp on the RSVP. All
            // NULLable so existing FREE events (no price set) behave exactly as before.
            $eventPriceCols = [
                'price' => 'DECIMAL(10,2) NULL',
                'currency' => 'VARCHAR(3) NULL',
            ];
            foreach ($eventPriceCols as $col => $ddl) {
                if (Schema::hasTable('ahg_exhibition_event') && ! Schema::hasColumn('ahg_exhibition_event', $col)) {
                    DB::statement("ALTER TABLE ahg_exhibition_event ADD COLUMN {$col} {$ddl}");
                }
            }
            $rsvpPaidCols = [
                'amount_paid' => 'DECIMAL(10,2) NULL',
                'paid_at' => 'DATETIME NULL',
            ];
            foreach ($rsvpPaidCols as $col => $ddl) {
                if (Schema::hasTable('ahg_exhibition_event_rsvp') && ! Schema::hasColumn('ahg_exhibition_event_rsvp', $col)) {
                    DB::statement("ALTER TABLE ahg_exhibition_event_rsvp ADD COLUMN {$col} {$ddl}");
                }
            }

            // heratio#1206 - "walk through what no longer exists": associate a catalogue
            // record about a lost / destroyed / no-longer-extant place with a walkable
            // exhibition-space digital twin that serves as its virtual RECONSTRUCTION.
            // A reconstruction IS an exhibition space; this table is the link only.
            // FIRST SLICE = the association + a public gallery of reconstructions.
            if (! Schema::hasTable('ahg_lost_place_reconstruction')) {
                DB::statement('CREATE TABLE ahg_lost_place_reconstruction (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    information_object_id INT NOT NULL,
                    exhibition_space_id BIGINT UNSIGNED NOT NULL,
                    note TEXT NULL,
                    created_at DATETIME NULL,
                    INDEX idx_io (information_object_id),
                    INDEX idx_space (exhibition_space_id)
                )');
            }

            // heratio#1219 - "reconstruction assembly montage": a lost structure
            // rebuilding itself on screen before the visitor walks into its 3D twin.
            // Each reconstruction carries a default montage style; each stage is a
            // rebuild layer (Assembly stacks them, Time-lapse cross-fades them).
            if (Schema::hasTable('ahg_lost_place_reconstruction')
                && ! Schema::hasColumn('ahg_lost_place_reconstruction', 'montage_style')) {
                DB::statement("ALTER TABLE ahg_lost_place_reconstruction ADD COLUMN montage_style VARCHAR(20) NOT NULL DEFAULT 'assembly'");
            }

            if (! Schema::hasTable('ahg_reconstruction_stage')) {
                DB::statement('CREATE TABLE ahg_reconstruction_stage (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    reconstruction_id INT NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    caption VARCHAR(255) NULL,
                    body TEXT NULL,
                    date_display VARCHAR(64) NULL,
                    image_path VARCHAR(1024) NULL,
                    image_url VARCHAR(1024) NULL,
                    opacity DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    INDEX idx_recon_order (reconstruction_id, sort_order)
                )');
            }

            // heratio#1206 - optional AI "evidence layer" annotator: a nullable JSON
            // column holding the curator-confirmed structured provenance metadata for
            // a stage (date estimate, evidence type, confidence, source credibility).
            // Additive + optional - the montage works unchanged when it is null.
            if (Schema::hasTable('ahg_reconstruction_stage')
                && ! Schema::hasColumn('ahg_reconstruction_stage', 'metadata')) {
                DB::statement('ALTER TABLE ahg_reconstruction_stage ADD COLUMN metadata JSON NULL');
            }

            // Seed the montage-style dropdown so the admin select reads from the
            // Dropdown Manager (no hardcoded option list). INSERT IGNORE keeps it
            // idempotent across boots.
            if (Schema::hasTable('ahg_dropdown')
                && ! DB::table('ahg_dropdown')->where('taxonomy', 'reconstruction_montage_style')->exists()) {
                $now = now();
                DB::table('ahg_dropdown')->insertOrIgnore([
                    [
                        'taxonomy' => 'reconstruction_montage_style',
                        'taxonomy_label' => 'Reconstruction Montage Style',
                        'taxonomy_section' => 'exhibition',
                        'code' => 'assembly',
                        'label' => 'Assembly (fragments accrete into the whole)',
                        'color' => '#0d6efd',
                        'icon' => 'layer-group',
                        'sort_order' => 10,
                        'is_default' => 1,
                        'is_active' => 1,
                        'created_at' => $now,
                    ],
                    [
                        'taxonomy' => 'reconstruction_montage_style',
                        'taxonomy_label' => 'Reconstruction Montage Style',
                        'taxonomy_section' => 'exhibition',
                        'code' => 'timelapse',
                        'label' => 'Time-lapse (dated states cross-fade)',
                        'color' => '#198754',
                        'icon' => 'clock-history',
                        'sort_order' => 20,
                        'is_default' => 0,
                        'is_active' => 1,
                        'created_at' => $now,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal: builder simply stays unavailable until the columns exist.
        }
    }
}
