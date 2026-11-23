<?php

/**
 * CirculationStatsScreenTest - the circulation statistics screen and exports
 * over HTTP on MySQL (#1476): real ACL, branch scoping, and the queries under
 * ONLY_FULL_GROUP_BY. Everything runs inside a rolled-back transaction.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Services\AclService;
use AhgLibrary\Support\LibraryBranch;
use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CirculationStatsScreenTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/library-manage/reports/circulation';

    private const RANGE = '?period=custom&from=2026-03-01&to=2026-03-31';

    private int $branchA;

    private int $branchB;

    private string $slugB;

    private string $sqlMode;

    protected function setUp(): void
    {
        parent::setUp();
        AclService::forgetUser();
        LibraryBranch::forgetSchemaCache();

        $this->sqlMode = (string) DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        [$this->branchA] = $this->repository('a');
        [$this->branchB, $this->slugB] = $this->repository('b');
        LibraryBranch::forgetSchemaCache();

        $item = (int) DB::table('library_item')->insertGetId([
            'information_object_id' => 1, 'material_type' => 'monograph', 'call_number' => 'ZZ-STATS',
        ]);
        $patron = (int) DB::table('library_patron')->insertGetId([
            'card_number' => uniqid('ZZ'), 'patron_type' => 'adult', 'first_name' => 'Secret', 'last_name' => 'Borrower',
            'membership_start' => '2026-01-01', 'max_checkouts' => 5, 'max_renewals' => 2, 'max_holds' => 3,
            'borrowing_status' => 'active', 'total_fines_owed' => 0,
        ]);
        foreach ([$this->branchA, $this->branchB] as $i => $branch) {
            $copy = (int) DB::table('library_copy')->insertGetId([
                'library_item_id' => $item, 'copy_number' => $i + 1, 'barcode' => uniqid('ZZ'),
                'branch_id' => $branch, 'status' => 'available', 'acquisition_date' => '2026-01-01',
            ]);
            DB::table('library_checkout')->insert([
                'copy_id' => $copy, 'patron_id' => $patron, 'branch_id' => $branch,
                'checkout_date' => '2026-03-10 09:00:00', 'due_date' => '2026-03-20',
                'return_date' => '2026-03-15 09:00:00', 'renewed_count' => 0, 'status' => 'returned',
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::statement('SET SESSION sql_mode = ?', [$this->sqlMode]);
        AclService::forgetUser();
        LibraryBranch::forgetSchemaCache();
        parent::tearDown();
    }

    private function repository(string $tag): array
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitRepository', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $id, 'culture' => 'en', 'authorized_form_of_name' => 'ZZ Branch ' . $tag]);
        DB::table('repository')->insert(['id' => $id, 'source_culture' => 'en']);
        $slug = 'test-branch-' . $tag . '-' . uniqid();
        DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

        return [$id, $slug];
    }

    private function user(array $groups = []): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'stats-' . $id, 'email' => uniqid('stats-', true) . '@example.test',
            'password_hash' => Hash::make('secret'), 'active' => 1,
        ]);
        foreach ($groups as $g) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $g]);
        }
        Cache::forget("acl_groups_{$id}");
        Cache::forget("acl_repo_rules_{$id}");

        return User::findOrFail($id);
    }

    /** A non-administrator with the global update right. */
    private function staff(): User
    {
        $user = $this->user();
        $this->grant(['user_id' => $user->id, 'action' => 'update', 'grant_deny' => 1]);

        return $user;
    }

    private function grant(array $row): void
    {
        DB::table('acl_permission')->insert($row + ['created_at' => now(), 'updated_at' => now(), 'serial_number' => 0]);
        AclService::forgetUser();
        if (isset($row['user_id'])) {
            Cache::forget("acl_repo_rules_{$row['user_id']}");
        }
    }

    /** The rendered response is wrapped by middleware, so figures are read from the export. */
    private function csvFor(User $user): string
    {
        return $this->actingAs($user)->get(self::URL . '/export/csv' . self::RANGE)->assertOk()->getContent();
    }

    public function test_administrator_sees_the_report_with_real_figures(): void
    {
        $response = $this->actingAs($this->user([100]))->get(self::URL . self::RANGE);

        $response->assertOk()->assertSee('ZZ-STATS')->assertSee('ZZ Branch a')->assertDontSee('Borrower');
        $this->assertStringContainsString("\nIssues,2\n", $this->csvFor($this->user([100])));
    }

    public function test_the_default_view_needs_no_filters(): void
    {
        $this->actingAs($this->user([100]))->get(self::URL)->assertOk();
    }

    public function test_csv_and_pdf_exports_download(): void
    {
        $admin = $this->user([100]);

        $csv = $this->actingAs($admin)->get(self::URL . '/export/csv' . self::RANGE);
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ZZ-STATS', $csv->getContent());
        $this->assertStringNotContainsString('Borrower', $csv->getContent());

        $pdf = $this->actingAs($admin)->get(self::URL . '/export/pdf' . self::RANGE);
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_a_user_without_update_rights_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(self::URL)->assertForbidden();
        $this->actingAs($user)->get(self::URL . '/export/csv')->assertForbidden();
    }

    public function test_a_locked_operator_only_reports_on_their_branch(): void
    {
        $user = $this->staff();
        DB::table('library_staff_branch')->insert(['user_id' => $user->id, 'branch_id' => $this->branchA, 'all_branches' => 0, 'locked' => 1]);

        $response = $this->actingAs($user)->get(self::URL . self::RANGE);
        $response->assertOk();
        $this->assertStringContainsString("\nIssues,1\n", $this->csvFor($user));

        $this->actingAs($user)->get(self::URL . self::RANGE . '&branch=' . $this->branchB)->assertForbidden();
        $this->actingAs($user)->get(self::URL . '/export/csv' . self::RANGE . '&branch=' . $this->branchB)->assertForbidden();
    }

    public function test_a_repository_deny_rule_removes_that_branch(): void
    {
        $user = $this->staff();
        $this->grant([
            'user_id' => $user->id, 'action' => 'update', 'grant_deny' => 0,
            'constants' => json_encode(['repository' => $this->slugB]),
        ]);

        $response = $this->actingAs($user)->get(self::URL . self::RANGE);
        $response->assertOk();
        $this->assertStringContainsString("\nIssues,1\n", $this->csvFor($user));
        $response->assertSee('ZZ Branch a')->assertDontSee('ZZ Branch b');

        $this->actingAs($user)->get(self::URL . self::RANGE . '&branch=' . $this->branchB)->assertForbidden();
    }

    public function test_a_read_only_repository_deny_removes_that_branch(): void
    {
        $user = $this->staff();
        $this->grant([
            'user_id' => $user->id, 'action' => 'read', 'grant_deny' => 0,
            'constants' => json_encode(['repository' => $this->slugB]),
        ]);

        $this->assertStringContainsString("\nIssues,1\n", $this->csvFor($user));
        $this->actingAs($user)->get(self::URL . self::RANGE)->assertOk()->assertDontSee('ZZ Branch b');
        $this->actingAs($user)->get(self::URL . self::RANGE . '&branch=' . $this->branchB)->assertForbidden();
        $this->actingAs($user)->get(self::URL . '/export/pdf' . self::RANGE . '&branch=' . $this->branchB)->assertForbidden();
    }

    public function test_a_locked_operator_denied_their_own_branch_gets_nothing(): void
    {
        foreach (['read', 'update'] as $action) {
            $user = $this->staff();
            DB::table('library_staff_branch')->insert(['user_id' => $user->id, 'branch_id' => $this->branchB, 'all_branches' => 0, 'locked' => 1]);
            $this->grant([
                'user_id' => $user->id, 'action' => $action, 'grant_deny' => 0,
                'constants' => json_encode(['repository' => $this->slugB]),
            ]);

            $this->actingAs($user)->get(self::URL . self::RANGE)->assertForbidden();
            $this->actingAs($user)->get(self::URL . '/export/csv' . self::RANGE)->assertForbidden();
            $this->actingAs($user)->get(self::URL . '/export/pdf' . self::RANGE)->assertForbidden();
        }
    }

    public function test_a_locked_administrator_keeps_their_branch(): void
    {
        $admin = $this->user([100]);
        DB::table('library_staff_branch')->insert(['user_id' => $admin->id, 'branch_id' => $this->branchB, 'all_branches' => 0, 'locked' => 1]);
        $this->grant([
            'user_id' => $admin->id, 'action' => 'read', 'grant_deny' => 0,
            'constants' => json_encode(['repository' => $this->slugB]),
        ]);

        $this->assertStringContainsString("\nIssues,1\n", $this->csvFor($admin));
    }

    public function test_a_period_code_the_report_cannot_calculate_is_refused_not_a_500(): void
    {
        $admin = $this->user([100]);
        $row = ['taxonomy' => 'library_report_period', 'code' => 'weekly', 'label' => 'ZZ Weekly', 'is_active' => 1, 'sort_order' => 99];
        foreach (['taxonomy_label' => 'Library Report Period', 'taxonomy_section' => 'library'] as $column => $value) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('ahg_dropdown', $column)) {
                $row[$column] = $value;
            }
        }
        DB::table('ahg_dropdown')->insert($row);

        $this->actingAs($admin)->get(self::URL)->assertOk()->assertDontSee('ZZ Weekly');
        $this->actingAs($admin)->get(self::URL . '?period=weekly')->assertSessionHasErrors('period');
        $this->actingAs($admin)->get(self::URL . '/export/csv?period=weekly')->assertSessionHasErrors('period');
        $this->actingAs($admin)->get(self::URL . '/export/pdf?period=weekly')->assertSessionHasErrors('period');
    }

    public function test_bad_filters_are_rejected(): void
    {
        $admin = $this->user([100]);

        foreach ([
            '?period=nonsense',
            '?period=custom&from=2026-03-01',
            '?period=custom&from=2026-04-01&to=2026-03-01',
            '?period=custom&from=2015-01-01&to=2026-01-01',
            '?branch=abc',
        ] as $query) {
            $this->actingAs($admin)->get(self::URL . $query)->assertSessionHasErrors();
        }
    }
}
