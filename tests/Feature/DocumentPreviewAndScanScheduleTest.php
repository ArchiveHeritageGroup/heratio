<?php

namespace Tests\Feature;

use AhgCore\Services\CronSchedulerService;
use AhgCore\Services\DigitalObjectService;
use Tests\TestCase;

/**
 * heratio#1750: office and PostScript files get a page to render a preview from.
 * heratio#1749: the watched-folder poll is a default schedule.
 * No database writes: previewSource and the schedule list are read-only.
 */
class DocumentPreviewAndScanScheduleTest extends TestCase
{
    private function preview(string $path, string $mime): ?array
    {
        $m = new \ReflectionMethod(DigitalObjectService::class, 'previewSource');
        $m->setAccessible(true);

        return $m->invoke(null, $path, $mime);
    }

    private function cleanup(?array $p): void
    {
        $m = new \ReflectionMethod(DigitalObjectService::class, 'removePreviewTmp');
        $m->setAccessible(true);
        $m->invoke(null, $p['tmp'] ?? null);
    }

    public function test_eps_and_illustrator_render_through_ghostscript(): void
    {
        if (trim((string) shell_exec('command -v gs')) === '') {
            $this->markTestSkipped('Ghostscript not installed');
        }
        $dir = sys_get_temp_dir().'/ahg-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $ai = $dir.'/master_logo.ai';
        file_put_contents($ai, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 200 100\n0.9 0.7 0.3 setrgbcolor 0 0 200 100 rectfill showpage\n");

        $p = $this->preview($ai, 'application/postscript');
        $this->assertNotNull($p);
        $this->assertStringStartsWith('%PDF', file_get_contents($p['path'], false, null, 0, 4));
        $this->cleanup($p);
        $this->assertDirectoryDoesNotExist($p['tmp']);

        @unlink($ai);
        @rmdir($dir);
    }

    public function test_plain_text_has_no_preview_route(): void
    {
        $this->assertNull($this->preview('/nonexistent/master_notes.txt', 'text/plain'));
    }

    public function test_scan_watch_is_a_default_schedule(): void
    {
        $row = collect((new CronSchedulerService)->getDefaultSchedules())->firstWhere('slug', 'scan-watch');
        $this->assertNotNull($row);
        $this->assertSame('ahg:scan-watch --once', $row['artisan_command']);
        $this->assertTrue((bool) $row['is_enabled']);
    }
}
