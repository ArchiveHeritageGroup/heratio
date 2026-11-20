<?php

/**
 * MediaCheckoutTest - heratio#1757 / #1758.
 *  - check-out locks the master to one user and offers the master and a sidecar;
 *  - another user cannot replace it, restore a version over it, or check it in;
 *  - check-in makes a new numbered version and keeps the record's metadata;
 *  - an administrator can check in or cancel someone else's check-out;
 *  - cancel releases the lock;
 *  - every step writes an audit row.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\FileVersionService;
use AhgCore\Services\MediaCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private int $io;

    private int $master;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_media_checkout') || ! Schema::hasTable('ahg_file_version')) {
            $this->markTestSkipped('Media check-out tables not migrated in this database.');
        }
        $this->root = sys_get_temp_dir().'/ahg-checkout-'.uniqid();
        mkdir($this->root.'/archive/r/mc', 0777, true);
        config(['heratio.uploads_path' => $this->root.'/archive', 'heratio.storage_path' => $this->root]);

        $this->io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $this->io, 'identifier' => 'MC-001', 'source_culture' => 'en', 'lft' => 5000100, 'rgt' => 5000101]);
        DB::table('information_object_i18n')->insert(['id' => $this->io, 'culture' => 'en', 'title' => 'Budget speech footage']);
        DB::table('slug')->insert(['object_id' => $this->io, 'slug' => 'mc-'.$this->io]);
        $ev = $this->objectRow('QubitEvent');
        DB::table('event')->insert(['id' => $ev, 'object_id' => $this->io, 'type_id' => 111, 'start_date' => '2026-02-12', 'source_culture' => 'en']);

        $this->path = $this->root.'/archive/r/mc/speech.mov';
        file_put_contents($this->path, 'MASTER v1');
        $this->master = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $this->master, 'object_id' => $this->io, 'usage_id' => 140, 'name' => 'speech.mov', 'path' => '/uploads/r/mc/', 'mime_type' => 'video/quicktime', 'byte_size' => 9]);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        AclService::forgetUser();
        parent::tearDown();
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(int $group): User
    {
        $id = $this->objectRow('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'mc-'.$id, 'email' => uniqid('mc-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }

    private function as(User $u): static
    {
        AclService::forgetUser();

        return $this->actingAs($u);
    }

    private function audit(string $action): int
    {
        return DB::table('security_audit_log')->where('object_id', $this->io)->where('action', $action)->count();
    }

    public function test_check_out_locks_the_file_and_offers_master_and_sidecar(): void
    {
        $editor = $this->user(101);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master), ['checkout_note' => 'Grade for broadcast'])
            ->assertRedirect(route('io.digitalobject.show', $this->master))->assertSessionHas('success');

        $lock = MediaCheckoutService::active($this->master);
        $this->assertNotNull($lock);
        $this->assertSame($editor->id, (int) $lock->user_id);
        $this->assertSame('checked_out', $lock->status);
        $this->assertSame('Grade for broadcast', $lock->note);
        $this->assertSame('1.0', $lock->version_at_checkout);
        $this->assertNotNull($lock->checked_out_at);
        $this->assertSame(1, $this->audit('media_checkout'));

        // A second check-out is refused while the first is open.
        $other = $this->user(101);
        $this->as($other)->post(route('io.digitalobject.checkout', $this->master))->assertSessionHas('error');
        $this->assertSame($editor->id, (int) MediaCheckoutService::active($this->master)->user_id);

        // The holder downloads the master and the sidecars; the other user may not.
        $res = $this->as($editor)->get(route('io.digitalobject.checkout.download', $this->master));
        $res->assertOk();
        $this->assertSame('MASTER v1', file_get_contents($res->baseResponse->getFile()->getPathname()));
        $json = $this->as($editor)->get(route('io.digitalobject.checkout.download', [$this->master, 'format' => 'json']))->assertOk()->json();
        $this->assertSame('MC-001', $json['dc:identifier']);
        $this->assertSame('Budget speech footage', $json['dc:title']['rdf:_1']['@value']);
        $this->assertSame(['2026-02-12'], $json['dc:date']['@list']);
        $this->assertSame('1.0', $json['xmpMM:VersionID']);
        $this->assertSame('urn:heratio:checkout:'.$lock->id, $json['xmpMM:InstanceID']);
        if (class_exists(\AhgMetadataExtraction\Xmp\XmpJsonLd::class)) {
            $xmp = $this->as($editor)->get(route('io.digitalobject.checkout.download', [$this->master, 'format' => 'xmp']))->assertOk()->getContent();
            $this->assertStringContainsString('Budget speech footage', $xmp);
            $back = \AhgMetadataExtraction\Xmp\XmpJsonLd::fromRdfXml($xmp, 'x');
            $this->assertSame('MC-001', $back['dc:identifier']);
            $this->assertSame('1.0', $back['xmpMM:VersionID']);
        }
        $this->as($other)->get(route('io.digitalobject.checkout.download', $this->master))->assertForbidden();

        // The edit page shows who has it; the holder gets the downloads and check-in.
        $this->as($editor)->get(route('io.digitalobject.show', $this->master))->assertOk()->assertSee(__('Sidecar (XMP)'))->assertSee(__('Check in'));
        $this->as($other)->get(route('io.digitalobject.show', $this->master))->assertOk()->assertSee($editor->username);
    }

    public function test_another_user_cannot_replace_restore_or_check_in(): void
    {
        $editor = $this->user(101);
        $other = $this->user(101);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));

        // Replace through the edit form.
        $this->as($other)->put(route('io.digitalobject.update', $this->master), ['replace_file' => UploadedFile::fake()->createWithContent('speech.mov', 'OTHER')])
            ->assertSessionHas('error');
        $this->assertSame('MASTER v1', file_get_contents($this->path));

        // Replace through the shared service (every replace and restore goes through it).
        $tmp = $this->root.'/other.mov';
        file_put_contents($tmp, 'OTHER');
        $master = DB::table('digital_object')->where('id', $this->master)->first();
        try {
            FileVersionService::replace($master, $this->path, $tmp);
            $this->fail('replace was not refused');
        } catch (\AhgCore\Services\MediaCheckedOutException) {
        }
        $this->assertSame('MASTER v1', file_get_contents($this->path));

        // Check-in and cancel by another (non-admin) user.
        $this->as($other)->post(route('io.digitalobject.checkin', $this->master), ['checkin_file' => UploadedFile::fake()->createWithContent('speech.mov', 'OTHER')])
            ->assertSessionHas('error');
        $this->as($other)->post(route('io.digitalobject.checkout.cancel', $this->master))->assertSessionHas('error');
        $this->assertSame('MASTER v1', file_get_contents($this->path));
        $this->assertNotNull(MediaCheckoutService::active($this->master));
        $this->assertSame(0, FileVersionService::list($this->master)->count());

        // The holder can still replace through the form.
        $this->as($editor)->put(route('io.digitalobject.update', $this->master), ['replace_file' => UploadedFile::fake()->createWithContent('speech.mov', 'OWNER')]);
        $this->assertSame('OWNER', file_get_contents($this->path));
    }

    public function test_check_in_makes_a_new_version_keeps_metadata_and_releases_the_lock(): void
    {
        $editor = $this->user(101);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $before = DB::table('information_object_i18n')->where('id', $this->io)->first();

        $this->as($editor)->post(route('io.digitalobject.checkin', $this->master), [
            'checkin_file' => UploadedFile::fake()->createWithContent('speech-graded.mov', 'MASTER v2 graded'),
            'checkin_note' => 'Colour graded',
            'version_type' => 'major',
        ])->assertSessionHas('success');

        $this->assertSame('MASTER v2 graded', file_get_contents($this->path));
        $versions = FileVersionService::list($this->master);
        $this->assertSame(['2.0', '1.0'], $versions->map(fn ($r) => $r->version_major.'.'.$r->version_minor)->all());
        $current = $versions->first();
        $this->assertSame('Checked in: Colour graded', $current->note);
        $this->assertSame($editor->id, (int) $current->created_by);
        $this->assertSame('MASTER v1', file_get_contents($versions->last()->stored_path));

        // Metadata is kept: the record, its identifier and the file's name and place.
        $this->assertSame('MC-001', DB::table('information_object')->where('id', $this->io)->value('identifier'));
        $this->assertEquals($before, DB::table('information_object_i18n')->where('id', $this->io)->first());
        $do = DB::table('digital_object')->where('id', $this->master)->first();
        $this->assertSame(['speech.mov', '/uploads/r/mc/', $this->io], [$do->name, $do->path, (int) $do->object_id]);
        $this->assertSame(hash('sha256', 'MASTER v2 graded'), $do->checksum);

        // The lock is released and records the version it made.
        $this->assertNull(MediaCheckoutService::active($this->master));
        $row = DB::table('ahg_media_checkout')->where('digital_object_id', $this->master)->first();
        $this->assertSame('checked_in', $row->status);
        $this->assertSame((int) $current->id, (int) $row->file_version_id);
        $this->assertSame(1, $this->audit('media_checkin'));

        // The next check-out starts from the new version.
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $this->assertSame('2.0', MediaCheckoutService::active($this->master)->version_at_checkout);
    }

    public function test_an_administrator_can_check_in_someone_elses_file(): void
    {
        $editor = $this->user(101);
        $admin = $this->user(100);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $this->as($admin)->get(route('admin.media-checkout'))->assertOk()->assertSee('speech.mov');

        $this->as($admin)->post(route('io.digitalobject.checkin', $this->master), [
            'checkin_file' => UploadedFile::fake()->createWithContent('speech.mov', 'ADMIN v2'),
        ])->assertSessionHas('success');
        $this->assertSame('ADMIN v2', file_get_contents($this->path));
        $this->assertNull(MediaCheckoutService::active($this->master));
        $this->assertSame((int) $admin->id, (int) DB::table('ahg_media_checkout')->where('digital_object_id', $this->master)->value('closed_by'));
    }

    public function test_cancel_releases_the_lock_without_a_version(): void
    {
        $editor = $this->user(101);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $this->as($editor)->post(route('io.digitalobject.checkout.cancel', $this->master), ['cancel_note' => 'Not needed'])->assertSessionHas('success');

        $this->assertNull(MediaCheckoutService::active($this->master));
        $row = DB::table('ahg_media_checkout')->where('digital_object_id', $this->master)->first();
        $this->assertSame(['cancelled', 'Not needed'], [$row->status, $row->close_note]);
        $this->assertSame('MASTER v1', file_get_contents($this->path));
        $this->assertSame(0, FileVersionService::list($this->master)->count());
        $this->assertSame(1, $this->audit('media_checkout_cancel'));

        // Released: another user can now check it out, and an admin can cancel theirs.
        $other = $this->user(101);
        $this->as($other)->post(route('io.digitalobject.checkout', $this->master))->assertSessionHas('success');
        $this->as($this->user(100))->post(route('io.digitalobject.checkout.cancel', $this->master))->assertSessionHas('success');
        $this->assertNull(MediaCheckoutService::active($this->master));
    }

    public function test_every_step_is_audited_and_logged_as_a_preservation_event(): void
    {
        $editor = $this->user(101);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $this->as($editor)->post(route('io.digitalobject.checkin', $this->master), ['checkin_file' => UploadedFile::fake()->createWithContent('speech.mov', 'v2')]);
        $this->as($editor)->post(route('io.digitalobject.checkout', $this->master));
        $this->as($editor)->post(route('io.digitalobject.checkout.cancel', $this->master));

        foreach (['media_checkout' => 2, 'media_checkin' => 1, 'media_checkout_cancel' => 1] as $action => $n) {
            $this->assertSame($n, $this->audit($action), $action);
        }
        $row = DB::table('security_audit_log')->where('object_id', $this->io)->where('action', 'media_checkin')->first();
        $this->assertSame((int) $editor->id, (int) $row->user_id);
        $this->assertSame('1.1', json_decode($row->details, true)['data']['version']);

        if (class_exists(\AhgPreservation\Services\PreservationService::class) && Schema::hasTable('preservation_event')) {
            $types = DB::table('preservation_event')->where('digital_object_id', $this->master)->pluck('event_type')->all();
            foreach (['checkout', 'checkin', 'replacement', 'checkout cancelled'] as $t) {
                $this->assertContains($t, $types);
            }
        }
    }
}
