<?php

namespace Ahg3dModel\Providers;

use Ahg3dModel\Services\ThreeDThumbnailService;
use Illuminate\Support\ServiceProvider;

class Ahg3dModelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThreeDThumbnailService::class, function () {
            return new ThreeDThumbnailService;
        });
        $this->app->singleton(\Ahg3dModel\Services\ModelMetadataExtractor::class);
        $this->app->singleton(\Ahg3dModel\Services\Model3dRegistry::class);
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-3d-model');

        // #1178 - research-grade 3D metadata + paradata. Additive columns on
        // object_3d_model (per decision). Idempotent: only runs the ALTER when a
        // sentinel column is missing. The whole probe + ALTER is wrapped in one
        // try/catch so a missing table / restricted DB user never fatals boot (CI).
        $this->ensureParadataColumns();
        $this->seedParadataDropdowns();

        // #1178 (Option A) - inject 3D technical metadata into the GLAM/DAM/sector
        // record show pages WITHOUT editing their locked blades. The shared,
        // unlocked partial ahg-core::partials._record-sidebar-extras is @included
        // by all of them; a View::composer feeds it $threeDModel (the registry
        // lazily creates/extracts the object_3d_model row for the record's 3D DO).
        \Illuminate\Support\Facades\View::composer(
            'ahg-3d-model::_metadata-panel',
            function ($view) {
                if (isset($view->getData()['threeDModel'])) {
                    return;
                }
                $oid = $view->getData()['objectId'] ?? null;
                if ($oid) {
                    $view->with('threeDModel', app(\Ahg3dModel\Services\Model3dRegistry::class)->ensureForObject((int) $oid));
                }
            }
        );

        // Commands are registered unconditionally so they can be invoked via
        // Artisan::call(...) from web requests (e.g. user-triggered TripoSR
        // generation), not only from the CLI.
        $this->commands([
            \Ahg3dModel\Commands\ThreeDDerivativesCommand::class,
            \Ahg3dModel\Commands\ThreeDMultiangleCommand::class,
            \Ahg3dModel\Commands\TriposrGenerateCommand::class,
            \Ahg3dModel\Commands\TriposrHealthCommand::class,
            \Ahg3dModel\Commands\TriposrPreloadCommand::class,
            \Ahg3dModel\Commands\ExtractMetadataCommand::class,
            \Ahg3dModel\Commands\PreserveCommand::class,
        ]);
    }

    /**
     * #1178 - add the 3D metadata/paradata columns to object_3d_model if absent.
     * Additive + nullable; sentinel-guarded so it runs at most once per install.
     */
    private function ensureParadataColumns(): void
    {
        try {
            $schema = \Illuminate\Support\Facades\Schema::class;
            if (! $schema::hasTable('object_3d_model')) {
                return;
            }
            // Sentinel: if the first paradata column exists, assume all do.
            if ($schema::hasColumn('object_3d_model', 'dimension_unit')) {
                return;
            }
            $schema::table('object_3d_model', function ($t) {
                // Technical / structural
                $t->decimal('real_width', 12, 4)->nullable();
                $t->decimal('real_height', 12, 4)->nullable();
                $t->decimal('real_depth', 12, 4)->nullable();
                $t->string('dimension_unit', 16)->nullable()->comment('dropdown model_3d_units');
                $t->string('scale_note', 64)->nullable();
                $t->string('coordinate_system', 16)->nullable()->comment('dropdown model_3d_coordinate_system');
                $t->string('bounding_box', 96)->nullable()->comment('auto: minX,minY,minZ maxX,maxY,maxZ (model units)');
                $t->string('format_version', 32)->nullable()->comment('auto: e.g. glTF 2.0');
                $t->string('compression', 24)->nullable()->comment('dropdown model_3d_compression');
                $t->boolean('is_lossless_master')->nullable();
                $t->string('pbr_maps', 128)->nullable()->comment('baseColor,normal,metalRough,occlusion,emissive');
                $t->string('texture_colorspace', 24)->nullable();
                $t->integer('lod_levels')->nullable();
                $t->boolean('is_watertight')->nullable();
                $t->boolean('has_rig')->nullable();
                // Capture & processing paradata
                $t->string('capture_method', 40)->nullable()->comment('dropdown model_3d_capture_method');
                $t->string('capture_device', 255)->nullable();
                $t->date('capture_date')->nullable();
                $t->string('capture_operator', 255)->nullable();
                $t->integer('source_count')->nullable()->comment('e.g. number of source photos');
                $t->string('point_density', 64)->nullable();
                $t->decimal('accuracy_mm', 8, 3)->nullable();
                $t->string('processing_software', 255)->nullable();
                $t->text('processing_notes')->nullable();
                $t->string('georeference', 255)->nullable();
                // Surrogate provenance
                $t->string('model_author', 255)->nullable();
                $t->text('derivation_note')->nullable();
                // Rights & access
                $t->string('model_license', 100)->nullable()->comment('dropdown model_3d_licence');
                $t->string('model_license_holder', 255)->nullable();
                $t->string('attribution', 500)->nullable();
                $t->string('alt_text', 500)->nullable();
            });
        } catch (\Throwable $e) {
            // Never fatal the boot over a schema probe (matches the CI-safety
            // pattern: probe + alter share one try/catch).
        }
    }

    /**
     * #1178 - seed the 3D metadata dropdown taxonomies (Dropdown Manager driven,
     * no ENUMs). Auto-seeds once: skipped if the first taxonomy already exists.
     */
    private function seedParadataDropdowns(): void
    {
        try {
            $db = \Illuminate\Support\Facades\DB::class;
            if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')) {
                return;
            }
            if ($db::table('ahg_dropdown')->where('taxonomy', 'model_3d_units')->exists()) {
                return;
            }
            $now = now();
            $sets = [
                ['model_3d_units', '3D Model Units', [
                    ['mm', 'Millimetres'], ['cm', 'Centimetres'], ['m', 'Metres'],
                    ['in', 'Inches'], ['ft', 'Feet'],
                ]],
                ['model_3d_coordinate_system', '3D Coordinate System', [
                    ['y_up', 'Y-up (glTF default)'], ['z_up', 'Z-up'],
                ]],
                ['model_3d_capture_method', '3D Capture Method', [
                    ['photogrammetry', 'Photogrammetry'], ['laser_scan', 'Laser scan'],
                    ['structured_light', 'Structured-light scan'], ['ct_scan', 'CT / volumetric scan'],
                    ['cad', 'CAD'], ['procedural', 'Procedural / generative'],
                    ['manual_modeling', 'Manual modelling'], ['other', 'Other'],
                ]],
                ['model_3d_compression', '3D Compression', [
                    ['none', 'None (uncompressed)'], ['draco', 'Draco'],
                    ['meshopt', 'meshopt'], ['ktx2', 'KTX2 textures'],
                ]],
                ['model_3d_licence', '3D Model Licence', [
                    ['cc0', 'CC0 (public domain dedication)'], ['cc_by', 'CC BY'],
                    ['cc_by_sa', 'CC BY-SA'], ['cc_by_nc', 'CC BY-NC'], ['cc_by_nd', 'CC BY-ND'],
                    ['public_domain', 'Public domain'], ['in_copyright', 'In copyright'],
                    ['all_rights_reserved', 'All rights reserved'],
                ]],
            ];
            $rows = [];
            foreach ($sets as [$tax, $label, $opts]) {
                $sort = 10;
                foreach ($opts as [$code, $optLabel]) {
                    $rows[] = [
                        'taxonomy' => $tax, 'taxonomy_label' => $label, 'taxonomy_section' => 'digital_media',
                        'code' => $code, 'label' => $optLabel, 'sort_order' => $sort,
                        'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $sort += 10;
                }
            }
            $db::table('ahg_dropdown')->insert($rows);
        } catch (\Throwable $e) {
            // Non-fatal: dropdowns can be added later via the Dropdown Manager.
        }
    }
}
