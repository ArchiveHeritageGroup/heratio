<?php

/**
 * UiStringReviewTest - heratio#1445, native-speaker review of machine
 * translations: workbook round trip, placeholder check, preview + apply to
 * ui_string and the lang file, the hold rule, the fill lock and the
 * translator/admin gate on the review screen.
 *
 * Works on a temporary lang directory; the real lang/*.json are never written.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\UiStringReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class UiStringReviewTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private UiStringReviewService $review;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ui_string_review')) {
            $this->markTestSkipped('ui_string_review not migrated on this database.');
        }
        // The workflow makes no AI calls; anything that tries fails loudly.
        Http::preventStrayRequests();
        AclService::forgetUser();

        $this->dir = sys_get_temp_dir().'/uisr-'.uniqid();
        mkdir($this->dir);
        $en = ['Save' => 'Save', 'Hello :name' => 'Hello :name', 'Delete' => 'Delete', 'Held text' => 'Held text', 'PayPal' => 'PayPal'];
        file_put_contents($this->dir.'/en.json', json_encode($en));
        file_put_contents($this->dir.'/zz.json', json_encode(['Save' => 'Gcina', 'Hello :name' => 'Sawubona :name', 'Delete' => 'Susa', 'PayPal' => 'Imbuyiselo']));
        file_put_contents($this->dir.'/_meta.json', json_encode(['locales' => ['zz' => ['mt_batch_count' => 4]]]));
        $this->review = new UiStringReviewService($this->dir);
        $this->app->instance(UiStringReviewService::class, $this->review);
        Cache::put('ui_string_review_context', ['Save' => 'packages/x/view.blade.php +2'], 60);
    }

    protected function tearDown(): void
    {
        foreach (glob(($this->dir ?? '/nonexistent').'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir ?? '');
        parent::tearDown();
    }

    public function test_placeholders_must_match_the_english(): void
    {
        $this->assertNull(UiStringReviewService::placeholderProblem('Hello :name', 'Sawubona :name'));
        $this->assertStringContainsString(':name', (string) UiStringReviewService::placeholderProblem('Hello :name', 'Sawubona'));
        $this->assertNotNull(UiStringReviewService::placeholderProblem('Save', 'Gcina :count'));
    }

    public function test_export_writes_the_review_layout(): void
    {
        $path = $this->dir.'/out.xlsx';
        $this->review->writeWorkbook('zz', $this->review->exportRows('zz'), $path);
        $rows = IOFactory::load($path)->getSheet(0)->toArray(null, false, false, false);

        $this->assertSame(['id', 'english', 'zz_machine', 'zz_corrected', 'must_keep_unchanged', 'context', 'note'], $rows[0]);
        $byEn = array_column(array_slice($rows, 1), null, 1);
        $this->assertSame(substr(sha1('Save'), 0, 10), $byEn['Save'][0]);
        $this->assertSame('Gcina', $byEn['Save'][2]);
        $this->assertSame(':name', $byEn['Hello :name'][4]);
        $this->assertSame('packages/x/view.blade.php +2', $byEn['Save'][5]);
        $this->assertArrayNotHasKey('Held text', $byEn, 'a string with no translation is not exported');
    }

    public function test_import_previews_then_writes_ui_string_lang_file_and_review_log(): void
    {
        $sheet = $this->sheet([
            ['Save', ''],                     // blank: accepted as is
            ['Hello :name', 'Sawubona'],      // drops :name: refused
            ['Delete', 'Cima'],               // correction
            ['PayPal', 'PayPal'],             // correction back to the brand name
        ]);
        $preview = $this->review->preview('zz', $sheet);
        $byKey = array_column($preview['items'], null, 'key');
        $this->assertSame('accepted', $byKey['Save']['action']);
        $this->assertNotNull($byKey['Hello :name']['problem']);
        $this->assertSame('corrected', $byKey['Delete']['action']);
        $this->assertSame('Susa', $byKey['Delete']['machine']);
        $this->assertSame([], DB::table('ui_string_review')->where('culture', 'zz')->pluck('key')->all(), 'preview writes nothing');

        $done = $this->review->apply('zz', $preview['items'], 'Thandi M', 7);
        $this->assertSame(['accepted' => 1, 'corrected' => 2, 'refused' => 1], $done);

        $this->assertSame('Cima', DB::table('ui_string')->where('culture', 'zz')->where('key', 'Delete')->value('value'));
        $file = json_decode((string) file_get_contents($this->dir.'/zz.json'), true);
        $this->assertSame('Cima', $file['Delete']);
        $this->assertSame('PayPal', $file['PayPal']);
        $this->assertSame('Sawubona :name', $file['Hello :name'], 'a refused row leaves the old text');
        $row = DB::table('ui_string_review')->where('culture', 'zz')->where('key', 'Delete')->first();
        $this->assertSame('corrected', $row->status);
        $this->assertSame('Thandi M', $row->reviewer_name);
        $this->assertSame(7, (int) $row->reviewed_by_user_id);
        $this->assertNotNull($row->reviewed_at);
        $meta = json_decode((string) file_get_contents($this->dir.'/_meta.json'), true);
        $this->assertSame('Thandi M', $meta['locales']['zz']['reviewed_by']);
        $this->assertSame(3, $meta['locales']['zz']['reviewed_count']);
        $this->assertSame(4, $meta['locales']['zz']['mt_batch_count'], 'other meta kept');

        // The fill commands now skip these keys, so a reviewed PayPal stays PayPal.
        $locked = UiStringReviewService::lockedKeys('zz');
        $this->assertArrayHasKey('PayPal', $locked);
        $this->assertArrayNotHasKey('Hello :name', $locked);
        // A second import of the same sheet changes nothing.
        $this->assertSame([], array_values(array_filter($this->review->preview('zz', $sheet)['items'], fn ($i) => $i['problem'] === null)));
    }

    public function test_held_strings_stay_out_until_reviewed(): void
    {
        $held = $this->sheet([['Held text', '', 'Umbhalo obanjiwe']]);
        $n = $this->review->stageHeld('zz', $held, 'heratio-zz-review.xlsx');
        $this->assertSame(1, $n['staged']);

        $this->assertArrayNotHasKey('Held text', json_decode((string) file_get_contents($this->dir.'/zz.json'), true));
        $this->assertFalse(DB::table('ui_string')->where('culture', 'zz')->where('key', 'Held text')->exists());
        $this->assertArrayHasKey('Held text', UiStringReviewService::lockedKeys('zz'), 'the fill must not refill a held string');
        $queue = $this->review->queue('zz', true);
        $this->assertSame(['Held text'], array_column($queue, 'key'));
        $this->assertSame('Umbhalo obanjiwe', $queue[0]['machine']);

        $this->review->decide('zz', 'Held text', null, 'Thandi M', 7);
        $this->assertSame('Umbhalo obanjiwe', json_decode((string) file_get_contents($this->dir.'/zz.json'), true)['Held text']);
        $this->assertSame('Umbhalo obanjiwe', DB::table('ui_string')->where('culture', 'zz')->where('key', 'Held text')->value('value'));
        $this->assertSame([], $this->review->queue('zz', true));
    }

    public function test_review_screen_is_for_translators_and_admins_only(): void
    {
        $plain = $this->user();
        $this->actingAs($plain)->get(route('translation-review.index', ['locale' => 'zz']))->assertForbidden();
        $this->actingAs($plain)->post(route('translation-review.decide'), ['locale' => 'zz', 'key' => 'Save', 'decision' => 'accept'])->assertForbidden();

        $editor = $this->user(101);
        $this->actingAs($editor)->get(route('translation-review.step', ['locale' => 'zz']))->assertForbidden();

        $translator = $this->user(103);
        $this->actingAs($translator)->get(route('translation-review.index', ['locale' => 'zz']))->assertOk()->assertSee('Translation review');
        $this->actingAs($translator)->get(route('translation-review.step', ['locale' => 'zz']))->assertOk()->assertSee('Gcina');
        $this->actingAs($translator)->post(route('translation-review.decide'), [
            'locale' => 'zz', 'key' => 'Hello :name', 'decision' => 'correct', 'correction' => 'Molo',
        ])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($translator)->post(route('translation-review.decide'), [
            'locale' => 'zz', 'key' => 'Hello :name', 'decision' => 'correct', 'correction' => 'Molo :name',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('corrected', DB::table('ui_string_review')->where('culture', 'zz')->where('key', 'Hello :name')->value('status'));
        $this->assertSame((string) $translator->id, (string) DB::table('ui_string_review')->where('culture', 'zz')->where('key', 'Hello :name')->value('reviewed_by_user_id'));
    }

    public function test_workbook_upload_preview_and_apply_over_http(): void
    {
        $admin = $this->user(100);
        $file = new \Illuminate\Http\UploadedFile($this->sheet([['Delete', 'Cima']]), 'zz.xlsx', null, null, true);
        $this->withoutExceptionHandling()->actingAs($admin)->post(route('translation-review.upload'), [
            'locale' => 'zz', 'workbook' => $file, 'mode' => 'review', 'reviewer' => 'Thandi M', 'accept_blank' => '0',
        ])->assertOk()->assertSee('Cima')->assertSee('Susa');

        $token = basename((string) collect(glob(storage_path('app/translation-review/*.xlsx')))->sortByDesc(fn ($f) => filemtime($f))->first());
        $this->actingAs($admin)->post(route('translation-review.apply'), [
            'locale' => 'zz', 'token' => $token, 'reviewer' => 'Thandi M', 'accept_blank' => '0',
        ])->assertRedirect(route('translation-review.index', ['locale' => 'zz']));
        $this->assertSame('Cima', DB::table('ui_string')->where('culture', 'zz')->where('key', 'Delete')->value('value'));
        $this->assertFalse(DB::table('ui_string_review')->where('culture', 'zz')->where('key', 'Save')->exists(), 'accept_blank off: blanks stay unreviewed');
    }

    /** @param list<array{0:string,1:string,2?:string}> $rows english, corrected, machine */
    private function sheet(array $rows): string
    {
        $book = new Spreadsheet();
        $data = [['id', 'english', 'zz_machine', 'zz_corrected', 'must_keep_unchanged', 'note']];
        foreach ($rows as $r) {
            $data[] = [substr(sha1($r[0]), 0, 10), $r[0], $r[2] ?? '', $r[1], '', ''];
        }
        $book->getActiveSheet()->fromArray($data, null, 'A1', true);
        $path = $this->dir.'/'.uniqid('in-').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return $path;
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'uisr-'.$id, 'email' => uniqid('uisr-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }
}
