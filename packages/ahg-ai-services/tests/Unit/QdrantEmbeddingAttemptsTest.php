<?php

/**
 * QdrantEmbeddingAttemptsTest - ahg:qdrant-index retries a long record on shorter
 * openings of its text (27 Nov 2026). The gateway refuses text longer than the
 * embedding model's context ("the input length exceeds the context length"), so
 * records with long descriptions were never indexed.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgAiServices\Commands\QdrantIndexCommand;
use PHPUnit\Framework\TestCase;

class QdrantEmbeddingAttemptsTest extends TestCase
{
    public function test_short_text_is_tried_once(): void
    {
        $this->assertSame(['General Smuts funeral'], QdrantIndexCommand::embeddingAttempts('General Smuts funeral'));
    }

    public function test_long_text_gets_shorter_whole_word_openings(): void
    {
        $text = trim(str_repeat('procession ', 400));
        $a = QdrantIndexCommand::embeddingAttempts($text);
        $this->assertSame($text, $a[0]);
        $this->assertCount(4, $a);
        foreach ([1 => 1000, 2 => 600, 3 => 300] as $i => $max) {
            $this->assertLessThanOrEqual($max, mb_strlen($a[$i]));
            $this->assertStringEndsWith('procession', $a[$i], 'cut at a word boundary');
        }
    }
}
