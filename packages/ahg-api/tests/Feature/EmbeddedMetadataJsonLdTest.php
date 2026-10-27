<?php

/**
 * EmbeddedMetadataJsonLdTest - GET /api/v2/digital-object/{id}/embedded-metadata:
 *
 *  - with Accept: application/ld+json it returns the file's XMP as JSON-LD
 *    (heratio#1557), with GPS withheld while a GPS finding is open;
 *  - without it, the flat response is unchanged.
 *
 * The endpoint is for keys whose owner can read the catalogue (staff); a
 * public-catalogue key is refused before it gets here (ApiAuthenticate).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Services\DisclosureGate;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmbeddedMetadataJsonLdTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('imagejpeg') || trim((string) shell_exec('command -v exiftool')) === '') {
            $this->markTestSkipped('GD and exiftool are needed to build the test image.');
        }
        $this->dir = sys_get_temp_dir().'/heratio-xmp-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
        config()->set('heratio.uploads_path', $this->dir);

        $user = app(UserService::class)->create(['username' => 'xmp'.bin2hex(random_bytes(4)), 'email' => 'xmp'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'XMP reader', 'active' => 1, 'groups' => []]);
        DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => \AhgCore\Models\AclGroup::EDITOR_ID]);
        Cache::forget("acl_groups_{$user}");
        $this->key = app(\AhgApi\Services\ApiKeyService::class)->issue($user, 'xmp test', ['read'])['api_key'];
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            exec('rm -rf '.escapeshellarg($this->dir));
        }
        parent::tearDown();
    }

    /** A record with one image whose XMP has a title in two languages and GPS. */
    private function record(int $statusId): int
    {
        $now = now();
        $io = DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $io,
            'type_id' => DisclosureGate::STATUS_TYPE_PUBLICATION, 'status_id' => $statusId]);

        $do = DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $io, 'path' => "/uploads/r/{$io}/", 'name' => 'photo.jpg',
            'mime_type' => 'image/jpeg', 'usage_id' => 166, 'byte_size' => 1]);
        DB::table('digital_object_metadata')->insert(['digital_object_id' => $do, 'title' => 'Flat title',
            'raw_metadata' => json_encode(['xmp' => ['dc:title' => 'Flat title']])]);

        @mkdir("{$this->dir}/r/{$io}", 0775, true);
        $file = "{$this->dir}/r/{$io}/photo.jpg";
        $img = imagecreatetruecolor(8, 8);
        imagejpeg($img, $file);
        exec('exiftool -q -overwrite_original -XMP-dc:Title="Wild mushroom" -XMP-dc:Title-af="Wilde sampioen" -XMP-dc:Subject=fungi -XMP-dc:Subject=macro '
            .'-XMP-exif:GPSLatitude="33.92 S" '.escapeshellarg($file));

        return $do;
    }

    private function fetchMeta(int $do, bool $ld = false)
    {
        return $this->withHeaders(['X-API-Key' => $this->key] + ($ld ? ['Accept' => 'application/ld+json'] : []))
            ->get("/api/v2/digital-object/{$do}/embedded-metadata");
    }

    public function test_accept_ld_json_returns_the_xmp_as_json_ld(): void
    {
        $do = $this->record(DisclosureGate::STATUS_PUBLISHED);
        $res = $this->fetchMeta($do, true);

        $res->assertOk();
        $this->assertStringContainsString('application/ld+json', $res->headers->get('Content-Type'));
        $doc = $res->json();
        $this->assertSame("urn:heratio:digital-object:{$do}", $doc['@id']);
        $this->assertSame('http://purl.org/dc/elements/1.1/', $doc['@context']['dc']);
        $this->assertSame('rdf:Alt', $doc['dc:title']['@type']);
        $titles = array_column(array_filter($doc['dc:title'], 'is_array'), '@value', '@language');
        $this->assertSame('Wilde sampioen', $titles['af'] ?? null);
        $this->assertSame(['fungi', 'macro'], $doc['dc:subject']['@set']);
        $this->assertArrayHasKey('exif:GPSLatitude', $doc, 'no open finding: GPS stays');
    }

    public function test_gps_is_withheld_from_the_json_ld_while_a_finding_is_open(): void
    {
        $do = $this->record(DisclosureGate::STATUS_PUBLISHED);
        DB::table('ahg_pii_finding_embedded')->insert(['digital_object_id' => $do, 'pii_type' => 'gps_coordinate',
            'source_table' => 'digital_object_metadata', 'source_field' => 'gps_latitude', 'resolution_status' => 'pending', 'scanned_at' => now()]);

        $doc = $this->fetchMeta($do, true)->assertOk()->json();
        $this->assertSame([], array_values(array_filter(array_keys($doc), fn ($k) => stripos($k, ':GPS') !== false)));
        $this->assertSame(['fungi', 'macro'], $doc['dc:subject']['@set'], 'the rest stays');
    }

    public function test_without_ld_json_the_flat_response_is_unchanged(): void
    {
        $do = $this->record(DisclosureGate::STATUS_PUBLISHED);
        $res = $this->fetchMeta($do)->assertOk();

        $this->assertStringContainsString('application/json', $res->headers->get('Content-Type'));
        $this->assertSame('Flat title', $res->json('data.embedded_metadata.xmp')['dc:title']);
    }
}
