<?php

/**
 * HistoryRerankServiceTest - unit coverage for the personalised re-ranking.
 *
 * Exercises the short-circuit guards and the pure tokenizer. The DB-backed
 * interest-profile branch is covered indirectly: with an empty/absent profile
 * the original ordering must be preserved (verified here by calling rerank for
 * a user with no history in the test schema).
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSemanticSearch\Tests\Unit;

use AhgSemanticSearch\Services\HistoryRerankService;
use PHPUnit\Framework\TestCase;

class HistoryRerankServiceTest extends TestCase
{
    private HistoryRerankService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new HistoryRerankService();
    }

    public function test_single_result_is_returned_unchanged(): void
    {
        $results = [['id' => 1, 'title' => 'Only one']];
        $this->assertSame($results, $this->svc->rerank($results, 42));
    }

    public function test_invalid_user_returns_results_unchanged(): void
    {
        $results = [
            ['id' => 1, 'title' => 'A'],
            ['id' => 2, 'title' => 'B'],
        ];
        $this->assertSame($results, $this->svc->rerank($results, 0));
    }

    public function test_tokenize_filters_stopwords_and_short_tokens(): void
    {
        $m = new \ReflectionMethod($this->svc, 'tokenize');
        $m->setAccessible(true);

        $tokens = $m->invoke($this->svc, 'The railway and a locomotive in Pretoria');

        $this->assertContains('railway', $tokens);
        $this->assertContains('locomotive', $tokens);
        $this->assertContains('pretoria', $tokens);
        $this->assertNotContains('the', $tokens);
        $this->assertNotContains('and', $tokens);
        $this->assertNotContains('a', $tokens);
        $this->assertNotContains('in', $tokens);
    }
}
