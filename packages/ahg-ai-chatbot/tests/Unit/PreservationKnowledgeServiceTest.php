<?php

/**
 * PreservationKnowledgeServiceTest - unit coverage for the deterministic
 * digital-preservation retrieval layer (issue #1243).
 *
 * Pure unit test: indexes the real curated corpus under docs/ and asserts the
 * retrieval returns relevant, correctly-cited passages for sample preservation
 * questions. No database, no LLM, no embedding, no network - the layer is
 * file-based and deterministic by design.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Tests\Unit;

use AhgAiChatbot\Services\PreservationKnowledgeService;
use PHPUnit\Framework\TestCase;

class PreservationKnowledgeServiceTest extends TestCase
{
    private PreservationKnowledgeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        // Point at the real repo base path (5 levels up from this test file:
        // tests/Unit -> tests -> ahg-ai-chatbot -> packages -> repo root).
        $this->svc = new PreservationKnowledgeService(dirname(__DIR__, 4));
    }

    // ── Corpus indexing ───────────────────────────────────────────────

    public function test_corpus_is_discovered_and_indexed(): void
    {
        $this->assertNotEmpty($this->svc->corpusFiles(), 'curated corpus files should be found');
        $this->assertGreaterThan(10, count($this->svc->getIndex()), 'corpus should split into many sections');
    }

    // ── Domain gating ─────────────────────────────────────────────────

    public function test_detects_preservation_questions(): void
    {
        $this->assertTrue($this->svc->looksLikePreservationQuestion('what is fixity?'));
        $this->assertTrue($this->svc->looksLikePreservationQuestion('explain PREMIS metadata'));
        $this->assertTrue($this->svc->looksLikePreservationQuestion('what is a WARC file'));
        $this->assertFalse($this->svc->looksLikePreservationQuestion('who is the donor of this fonds'));
    }

    // ── Retrieval relevance + citation ────────────────────────────────

    public function test_fixity_query_returns_fixity_passage_with_citation(): void
    {
        $hits = $this->svc->retrieve('what is fixity', 3);
        $this->assertNotEmpty($hits);
        $top = $hits[0];
        $this->assertStringContainsStringIgnoringCase('fixity', $top['source']);
        $this->assertStringContainsStringIgnoringCase('checksum', $top['excerpt'] . ' ' . $top['heading']);
        $this->assertGreaterThan(0.0, $top['score']);
        // Citation must point at a real curated doc path + anchor.
        $this->assertMatchesRegularExpression('#^docs/(reference|help)/.+\.md\#.+$#', $top['source']);
    }

    public function test_fixity_frequency_query_matches_fixity_corpus(): void
    {
        $hits = $this->svc->retrieve('how often should I run fixity checks', 3);
        $this->assertNotEmpty($hits);
        $sources = implode(' ', array_column($hits, 'source'));
        $this->assertStringContainsStringIgnoringCase('fixity', $sources);
    }

    public function test_warc_query_returns_web_archiving_passage(): void
    {
        $hits = $this->svc->retrieve('what is WARC web archiving', 3);
        $this->assertNotEmpty($hits);
        $sources = implode(' ', array_column($hits, 'source'));
        $this->assertStringContainsStringIgnoringCase('warc', $sources . implode(' ', array_column($hits, 'excerpt')));
    }

    public function test_premis_query_returns_premis_passage(): void
    {
        $hits = $this->svc->retrieve('explain PREMIS preservation metadata events', 3);
        $this->assertNotEmpty($hits);
        $sources = implode(' ', array_column($hits, 'source'));
        $this->assertStringContainsStringIgnoringCase('premis', $sources);
    }

    // ── Context block ─────────────────────────────────────────────────

    public function test_context_block_contains_source_tags(): void
    {
        $block = $this->svc->buildContextBlock('what is fixity', 2);
        $this->assertStringContainsString('PRESERVATION KNOWLEDGE', $block);
        $this->assertStringContainsString('(source: docs/', $block);
    }

    public function test_non_preservation_query_returns_no_passages_in_block(): void
    {
        // A query with no preservation terms at all should yield an empty block.
        $block = $this->svc->buildContextBlock('xyzzy plugh frobnicate', 3);
        $this->assertSame('', $block);
    }

    // ── Verbatim guarantee ────────────────────────────────────────────

    public function test_excerpt_is_verbatim_from_the_source_doc(): void
    {
        $hits = $this->svc->retrieve('what is fixity', 1);
        $this->assertNotEmpty($hits);
        [$file] = explode('#', $hits[0]['source']);
        $raw = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
        // Take the first sentence fragment of the excerpt and assert it appears
        // (whitespace-normalised) in the source - no paraphrase, no fabrication.
        $fragment = trim(explode('.', $hits[0]['excerpt'])[0]);
        $needle = preg_replace('/\s+/', ' ', $fragment);
        $haystack = preg_replace('/\s+/', ' ', $raw);
        $this->assertStringContainsString($needle, $haystack);
    }
}
