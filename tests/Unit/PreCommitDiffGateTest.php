<?php

/**
 * PreCommitDiffGateTest - pre-commit gate 3 (#108) lets a markdown list in a
 * JS template literal through (#1506) and still catches a pasted diff, run
 * with the hook's own awk program.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PreCommitDiffGateTest extends TestCase
{
    private function flags(string $source): bool
    {
        $hook = file_get_contents(__DIR__.'/../../hooks/pre-commit');
        $this->assertSame(1, preg_match("/if awk '\n(.*?)\n\s*' \"\\\$f\"/s", $hook, $m), 'gate 3 awk program found');
        $dir = sys_get_temp_dir();
        file_put_contents($prog = tempnam($dir, 'g3'), $m[1]);
        file_put_contents($file = tempnam($dir, 'src'), $source);
        exec('awk -f '.escapeshellarg($prog).' '.escapeshellarg($file).' 2>&1', $out, $code);
        unlink($prog);
        unlink($file);

        return $code !== 0;
    }

    public function test_bullet_list_in_a_template_literal_passes(): void
    {
        $this->assertFalse($this->flags("const prompt = `\n- first bullet\n- second bullet\n- third bullet\n`;\n"));
    }

    public function test_pasted_diff_is_still_caught_after_a_template_and_after_a_commented_backtick(): void
    {
        $this->assertTrue($this->flags("const p = `\n- a\n`;\n-  old();\n-  older();\n-  oldest();\n"));
        $this->assertTrue($this->flags("// a stray ` here\n-  old();\n-  older();\n-  oldest();\n"));
    }
}
