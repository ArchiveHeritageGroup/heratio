<?php

namespace AhgIiifCollection\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GHSA-pfhq-87fh-gqfp: the IIIF routes must refuse a published record under
 * an active embargo to visitors, as every other surface does, and still serve
 * an unrestricted published record.
 */
class IiifRestrictedRecordTest extends TestCase
{
    use DatabaseTransactions;

    /** Two published records with an image master, seeded in this transaction. */
    private function records(): array
    {
        foreach (['embargo', 'digital_object', 'information_object', 'status'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("{$table} table not present in this install.");
            }
        }
        $now = now();
        $out = [];
        foreach (['a', 'b'] as $n) {
            $id = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);
            DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => "GHSA test record {$n}"]);
            $slug = "ghsa-pfhq-test-{$n}-{$id}";
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);
            DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => 160]);
            $doId = DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('digital_object')->insert(['id' => $doId, 'object_id' => $id, 'usage_id' => 140,
                'path' => "/uploads/r/ghsa-test/{$id}/", 'name' => "page-{$n}.jpg", 'mime_type' => 'image/jpeg']);
            $out[] = (object) ['id' => $id, 'slug' => $slug];
        }

        return $out;
    }

    public function test_embargoed_record_is_refused_and_an_open_one_is_served(): void
    {
        [$embargoed, $open] = $this->records();
        DB::table('embargo')->insert([
            'object_id' => $embargoed->id, 'embargo_type' => 'full', 'start_date' => now()->subDay()->toDateString(),
            'status' => 'active', 'is_active' => 1, 'auto_release' => 0, 'notification_sent' => 0,
        ]);

        $this->get('/iiif-manifest/'.$embargoed->slug)->assertStatus(404);
        $this->get('/iiif-manifest/'.$embargoed->slug.'/search?q=a')->assertStatus(404);
        $this->get('/iiif-manifest/'.$embargoed->slug.'/canvas/1/annotations')->assertStatus(404);
        $this->get('/iiif-viewer/'.$embargoed->slug)->assertStatus(404);

        $master = DB::table('digital_object')->where('object_id', $embargoed->id)->whereNull('parent_id')->first(['path', 'name']);
        $identifier = str_replace('/', '_SL_', trim($master->path, '/').'/'.$master->name);
        $this->getJson('/privacy/iiif-check?identifier='.rawurlencode($identifier))->assertJson(['allowed' => false]);

        $this->get('/iiif-manifest/'.$open->slug)->assertStatus(200);
    }
}
