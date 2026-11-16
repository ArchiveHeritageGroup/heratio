<?php

/**
 * IcipRecordGateTest - #1731.
 *
 * A "community permission required" restriction set on a parent with
 * applies_to_descendants must block a guest from the child record on every
 * sector (one shared IcipAccessService::enforce()), while an administrator
 * passes; the record's cultural notice and TK label are returned for display
 * and rendered by the shared partial.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgIcip\Services\IcipAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IcipRecordGateTest extends TestCase
{
    use DatabaseTransactions;

    private int $parentId;

    private int $childId;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        foreach (['information_object', 'icip_access_restriction', 'icip_cultural_notice', 'icip_tk_label', 'icip_community'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t not present in this install.");
            }
        }

        $this->parentId = $this->io(900000001, 900000004);
        $this->childId = $this->io(900000002, 900000003);

        $community = (int) DB::table('icip_community')->insertGetId([
            'name' => 'Test community '.uniqid(), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('icip_access_restriction')->insert([
            'information_object_id' => $this->parentId, 'restriction_type' => 'community_permission_required',
            'community_id' => $community, 'applies_to_descendants' => 1, 'override_security_clearance' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('icip_cultural_notice')->insert([
            'information_object_id' => $this->childId,
            'notice_type_id' => (int) DB::table('icip_cultural_notice_type')->where('display_public', 1)->value('id'),
            'community_id' => $community, 'custom_text' => 'Test notice text', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('icip_tk_label')->insert([
            'information_object_id' => $this->childId, 'label_type_id' => (int) DB::table('icip_tk_label_type')->value('id'),
            'community_id' => $community, 'applied_by' => 'community', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function io(int $lft, int $rgt): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $rgt, 'parent_id' => 1]);

        return $id;
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'icip-admin-'.$id, 'email' => uniqid('icip-', true).'@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_guest_is_blocked_by_an_inherited_restriction(): void
    {
        try {
            app(IcipAccessService::class)->enforce($this->childId);
            $this->fail('Guest was not blocked.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_admin_passes_and_sees_the_staff_note(): void
    {
        $this->actingAs($this->admin());
        $decision = app(IcipAccessService::class)->enforce($this->childId);
        $this->assertFalse($decision['allowed']);

        $html = view('icip::partials.record-notices', ['ioId' => $this->childId])->render();
        $this->assertStringContainsString('Test notice text', $html);
        $this->assertStringContainsString('Restricted by a cultural protocol', $html);
        $this->assertStringContainsString('icip-tk-label', $html);
    }

    public function test_403_page_names_the_cultural_protocol(): void
    {
        $e = new HttpException(403, 'ICIP restriction: Community Permission Required');
        $html = view('errors.403', ['exception' => $e])->render();
        $this->assertStringContainsString('restricted by a cultural protocol', $html);
        $this->assertStringNotContainsString('you must log in', $html);
    }

    public function test_unrestricted_record_renders_nothing(): void
    {
        Auth::logout();
        $other = $this->io(900000010, 900000011);
        $this->assertSame([], app(IcipAccessService::class)->displayLabels($other)->all());
        $this->assertSame('', trim(view('icip::partials.record-notices', ['ioId' => $other])->render()));
    }
}
