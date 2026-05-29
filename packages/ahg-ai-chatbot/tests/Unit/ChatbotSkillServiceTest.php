<?php

/**
 * ChatbotSkillServiceTest - unit coverage for chatbot task-skill routing.
 *
 * Pure unit test: exercises intent detection, title extraction, and the
 * null-safe (unauthenticated) skill paths, none of which touch the database
 * or the LLM. The authenticated DB-backed branches are covered by the
 * application's feature suite where a live schema is available.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Tests\Unit;

use AhgAiChatbot\Services\ChatbotSkillService;
use PHPUnit\Framework\TestCase;

class ChatbotSkillServiceTest extends TestCase
{
    private ChatbotSkillService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ChatbotSkillService();
    }

    // ── Intent detection ──────────────────────────────────────────────

    public function test_detects_renew_loan_intent(): void
    {
        $r = $this->svc->detectIntent('Can you renew my loan please?');
        $this->assertSame('renew_loan', $r['intent']);
        $this->assertGreaterThan(0, $r['score']);
    }

    public function test_detects_ill_intent(): void
    {
        $r = $this->svc->detectIntent('I want to make an interlibrary loan request for a book');
        $this->assertSame('submit_ill_request', $r['intent']);
    }

    public function test_detects_check_item_status_intent(): void
    {
        $r = $this->svc->detectIntent('Is the book available right now?');
        $this->assertSame('check_item_status', $r['intent']);
    }

    public function test_no_intent_for_general_question(): void
    {
        $r = $this->svc->detectIntent('Tell me about the history of the railway fonds');
        $this->assertNull($r['intent']);
        $this->assertSame(0.0, $r['confidence']);
    }

    public function test_empty_message_has_no_intent(): void
    {
        $r = $this->svc->detectIntent('   ');
        $this->assertNull($r['intent']);
    }

    // ── handle() returns null for RAG fallback ────────────────────────

    public function test_handle_returns_null_when_no_skill_matches(): void
    {
        $this->assertNull($this->svc->handle('What is provenance?', null));
    }

    // ── Null-safe (unauthenticated) skill paths ───────────────────────

    public function test_renew_loan_without_auth_asks_to_sign_in(): void
    {
        $result = $this->svc->handle('please renew my loan', null);
        $this->assertIsArray($result);
        $this->assertTrue($result['handled']);
        $this->assertSame('renew_loan', $result['intent']);
        $this->assertTrue($result['needs_auth'] ?? false);
        $this->assertStringContainsStringIgnoringCase('sign in', $result['reply']);
    }

    public function test_ill_request_without_auth_asks_to_sign_in(): void
    {
        $result = $this->svc->handle('I need an interlibrary loan for "Quiet Streets"', null);
        $this->assertIsArray($result);
        $this->assertSame('submit_ill_request', $result['intent']);
        $this->assertTrue($result['needs_auth'] ?? false);
    }

    // ── Title extraction ──────────────────────────────────────────────

    public function test_extract_title_prefers_quoted_text(): void
    {
        $method = new \ReflectionMethod($this->svc, 'extractTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->svc, 'Is "The Lighthouse Keepers" available?');
        $this->assertSame('The Lighthouse Keepers', $title);
    }

    public function test_extract_title_strips_lead_in_phrasing(): void
    {
        $method = new \ReflectionMethod($this->svc, 'extractTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->svc, 'check the status of Mapungubwe Gold');
        $this->assertStringContainsStringIgnoringCase('mapungubwe', $title);
    }
}
