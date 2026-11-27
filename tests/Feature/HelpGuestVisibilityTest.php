<?php

namespace Tests\Feature;

use AhgHelp\Services\HelpArticleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Visitors who are not signed in see only guides to the public screens. The old rule hid two
 * category names the help import never uses, so guests could read every staff article.
 */
class HelpGuestVisibilityTest extends TestCase
{
    private function article(string $slug): void
    {
        DB::table('help_article')->updateOrInsert(['slug' => $slug], [
            'title' => 'T '.$slug, 'category' => 'User Guides', 'body_markdown' => 'x',
            'body_html' => '<p>x</p>', 'body_text' => 'x', 'is_published' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('help_article')->whereIn('slug', ['search-user-guide', 'zz-staff-settings-guide'])
            ->where('title', 'like', 'T %')->delete();
        parent::tearDown();
    }

    public function test_guest_sees_public_guide_but_not_staff_guide(): void
    {
        if (DB::table('help_article')->where('slug', 'search-user-guide')->exists()) {
            $this->markTestSkipped('real search-user-guide present; test needs its own fixture');
        }
        $this->article('search-user-guide');
        $this->article('zz-staff-settings-guide');

        $this->assertNotNull(HelpArticleService::getBySlug('search-user-guide'));
        $this->assertNull(HelpArticleService::getBySlug('zz-staff-settings-guide'));
        $this->assertTrue(HelpArticleService::isStaffOnly('zz-staff-settings-guide'));

        $this->get('/help/article/zz-staff-settings-guide')->assertRedirect();
        $this->get('/help/article/search-user-guide')->assertOk();
    }
}
