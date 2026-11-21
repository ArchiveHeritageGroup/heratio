<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1272 - image search. One row per master image digital object that
 * the image embedder has seen, so the pending sweep (ahg:image-embed) only
 * sends new or changed images to the AI gateway. The vectors themselves live
 * in Qdrant (ahg_discovery_image_collection); this table is the ledger.
 *
 * status is internal pipeline state (embedded / skipped / failed), not a
 * user-facing enumeration, so it is a plain VARCHAR with no dropdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_image_embedding')) {
            Schema::create('ahg_image_embedding', function (Blueprint $t) {
                $t->integer('digital_object_id')->primary();
                $t->integer('information_object_id')->index();
                $t->string('model', 100);
                $t->unsignedSmallInteger('dimensions')->nullable();
                $t->string('status', 16)->index();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->string('error', 255)->nullable();
                $t->string('checksum', 128)->nullable();
                $t->dateTime('embedded_at')->nullable();
                $t->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('ahg_settings')) {
            return;
        }
        foreach ([
            'ahg_discovery_image_text_model' => ['nomic-embed-text', 'Text embedder for "describe the picture" searches. It must share the image model\'s vector space (nomic-embed-text pairs with nomic-embed-vision-v1.5). Empty sends the text to the gateway image embedder\'s /embed/text route instead, for a CLIP-style model that has both towers.'],
            'ahg_discovery_image_text_prefix' => ['search_query: ', 'Prefix the text embedder expects in front of a search query.'],
            'ahg_discovery_image_max_mb' => ['25', 'Largest image, in MB, sent to the embedder. Bigger files are skipped; the reference copy is used when there is one.'],
        ] as $key => [$value, $desc]) {
            if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'discovery'];
                if (Schema::hasColumn('ahg_settings', 'description')) {
                    $row['description'] = $desc;
                }
                DB::table('ahg_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_image_embedding');
    }
};
