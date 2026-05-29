<?php

/**
 * QueryExpansionServiceTest - unit coverage for the parsing + merge logic.
 *
 * Exercises the pure helpers (term parsing, merge) and the empty-query
 * short-circuit. The LLM and thesaurus integration branches are not invoked
 * here so no Laravel container or DB is required.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\QueryExpansionService;
use PHPUnit\Framework\TestCase;

class QueryExpansionServiceTest extends TestCase
{
    private QueryExpansionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new QueryExpansionService();
    }

    public function test_empty_query_returns_base(): void
    {
        $r = $this->svc->expand('   ');
        $this->assertSame('', $r['original_query']);
        $this->assertSame('', $r['expanded_query']);
        $this->assertSame('none', $r['source']);
        $this->assertSame([], $r['terms']);
    }

    public function test_parse_terms_drops_query_words_and_noise(): void
    {
        $m = new \ReflectionMethod($this->svc, 'parseTerms');
        $m->setAccessible(true);

        $raw = "railway, locomotive, train, railway, ab";
        $terms = $m->invoke($this->svc, $raw, 'railway history');

        // 'railway' is in the query -> dropped; 'ab' too short -> dropped.
        $this->assertContains('locomotive', $terms);
        $this->assertContains('train', $terms);
        $this->assertNotContains('railway', $terms);
        $this->assertNotContains('ab', $terms);
    }

    public function test_parse_terms_handles_numbered_list(): void
    {
        $m = new \ReflectionMethod($this->svc, 'parseTerms');
        $m->setAccessible(true);

        $raw = "Here are terms: steam engine, coal, signalling";
        $terms = $m->invoke($this->svc, $raw, 'trains');

        $this->assertContains('steam engine', $terms);
        $this->assertContains('coal', $terms);
        $this->assertContains('signalling', $terms);
    }

    public function test_merge_terms_appends_to_query(): void
    {
        $m = new \ReflectionMethod($this->svc, 'mergeTerms');
        $m->setAccessible(true);

        $merged = $m->invoke($this->svc, 'railway', ['locomotive', 'train']);
        $this->assertSame('railway locomotive train', $merged);
    }

    public function test_merge_terms_with_no_terms_returns_query(): void
    {
        $m = new \ReflectionMethod($this->svc, 'mergeTerms');
        $m->setAccessible(true);

        $this->assertSame('railway', $m->invoke($this->svc, 'railway', []));
    }
}
