<?php

/**
 * RedactionRectTest - a stored redaction rectangle has two key shapes, and
 * every consumer has to read both.
 *
 * privacy_visual_redaction.coordinates is written by the editor as
 * left/top/width/height, but rows exist as x/y/width/height - five of each on
 * heratio-dev. Only the editor's own loader ever handled both. The three
 * consumers that matter did not, and each failed differently:
 *
 *   - the public overlay read coords.top / coords.left, got undefined for an
 *     x/y row, multiplied it to NaN, and the browser dropped the top and left
 *     declarations while keeping width and height. The mask drew at the right
 *     size in the wrong place, which is worse than not drawing at all: the
 *     page looks redacted;
 *   - RedactionRenderService, which burns masks into derivative files, read
 *     'top' and 'left' with no fallback and defaulted both to 0, so the mask
 *     was baked into the top-left corner of the file;
 *   - the audit snapshot diffed two rows describing the same rectangle as
 *     different, in a method whose whole purpose is a stable shape.
 *
 * All four now go through PrivacyService::redactionRect(). Pure static, no DB,
 * no container, so it runs in CI.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgInformationObjectManage\Tests\Unit;

use AhgInformationObjectManage\Services\PrivacyService;
use PHPUnit\Framework\TestCase;

class RedactionRectTest extends TestCase
{
    public function test_it_reads_the_shape_the_editor_writes(): void
    {
        $this->assertSame(
            ['left' => 0.25, 'top' => 0.5, 'width' => 0.1, 'height' => 0.2],
            PrivacyService::redactionRect('{"left":0.25,"top":0.5,"width":0.1,"height":0.2}')
        );
    }

    public function test_it_reads_the_x_y_shape_that_also_exists_in_the_data(): void
    {
        $this->assertSame(
            ['left' => 0.25, 'top' => 0.5, 'width' => 0.1, 'height' => 0.2],
            PrivacyService::redactionRect('{"x":0.25,"y":0.5,"width":0.1,"height":0.2}')
        );
    }

    /**
     * The regression itself: the two shapes describe the same rectangle, so
     * every consumer must place them identically. Before this reader existed,
     * one rendered at its true position and the other at the origin.
     */
    public function test_the_two_shapes_describe_the_same_rectangle(): void
    {
        $this->assertSame(
            PrivacyService::redactionRect('{"left":0.3,"top":0.4,"width":0.5,"height":0.6}'),
            PrivacyService::redactionRect('{"x":0.3,"y":0.4,"width":0.5,"height":0.6}')
        );
    }

    public function test_left_and_top_win_when_a_row_carries_both(): void
    {
        $rect = PrivacyService::redactionRect('{"left":0.1,"x":0.9,"top":0.2,"y":0.8,"width":0.3,"height":0.4}');

        $this->assertSame(0.1, $rect['left']);
        $this->assertSame(0.2, $rect['top']);
    }

    public function test_it_accepts_an_already_decoded_array(): void
    {
        $this->assertSame(
            ['left' => 1.0, 'top' => 2.0, 'width' => 3.0, 'height' => 4.0],
            PrivacyService::redactionRect(['x' => 1, 'y' => 2, 'w' => 3, 'h' => 4])
        );
    }

    /**
     * A zero-size rect is what a cataloguer leaves behind by clicking without
     * dragging. Both renderers skip width or height <= 0, so this has to come
     * back as zero rather than as a default the caller would then draw.
     */
    public function test_missing_or_unparseable_coordinates_are_zero_not_a_default_box(): void
    {
        foreach (['{}', 'not json at all', '', 'null', '[]'] as $input) {
            $this->assertSame(
                ['left' => 0.0, 'top' => 0.0, 'width' => 0.0, 'height' => 0.0],
                PrivacyService::redactionRect($input),
                'Input '.var_export($input, true).' should give a zero rect, which both renderers skip.'
            );
        }
    }

    public function test_string_numbers_are_cast(): void
    {
        $rect = PrivacyService::redactionRect('{"left":"0.25","top":"0.5","width":"0.1","height":"0.2"}');

        $this->assertSame(0.25, $rect['left']);
        $this->assertIsFloat($rect['height']);
    }
}
