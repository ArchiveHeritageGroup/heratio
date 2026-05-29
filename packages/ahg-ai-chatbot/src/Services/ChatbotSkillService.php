<?php

/**
 * ChatbotSkillService - intent detection + task skills for the chatbot.
 *
 * Sits in front of the RAG pipeline. Given a user message it:
 *   1. detectIntent()  - keyword (and optionally LLM-assisted) intent match
 *   2. handle()        - dispatch to the matched skill, returning a structured
 *                        reply, OR null to signal "no skill - fall back to RAG".
 *
 * Skills implemented (issue #1095):
 *   - renew_loan        -> LibraryCirculationService::renew()
 *   - submit_ill_request -> LibraryIllService::patronCreate()
 *   - check_item_status  -> LibraryOpacService::getAvailability() (via lookup)
 *
 * All skills are null-safe: when the patron cannot be resolved from the
 * authenticated user (no login, or no matching library_patron row) the skill
 * returns a friendly "please sign in / link your library card" message rather
 * than erroring. The library package is called read-only-where-possible and
 * NEVER edited from here.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiChatbot\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ChatbotSkillService
{
    /**
     * Keyword triggers per intent. Matched case-insensitively against the
     * message. Order matters only for tie-breaking; the highest hit-count
     * intent wins.
     *
     * @var array<string,array<int,string>>
     */
    protected array $intentKeywords = [
        'renew_loan' => [
            'renew', 'renewal', 'extend my loan', 'extend the loan', 'keep the book longer',
        ],
        'submit_ill_request' => [
            'interlibrary loan', 'inter-library loan', 'ill request', 'request a book from another',
            'borrow from another library', 'document delivery', 'request an article',
        ],
        'check_item_status' => [
            'is it available', 'availability', 'in stock', 'on the shelf', 'can i borrow',
            'is the book available', 'how many copies', 'checked out', 'available copies',
        ],
    ];

    /**
     * Detect the user's intent.
     *
     * @return array{intent: ?string, score: int, confidence: float}
     */
    public function detectIntent(string $message): array
    {
        $haystack = mb_strtolower(trim($message));
        if ($haystack === '') {
            return ['intent' => null, 'score' => 0, 'confidence' => 0.0];
        }

        $best = null;
        $bestScore = 0;
        foreach ($this->intentKeywords as $intent => $phrases) {
            $score = 0;
            foreach ($phrases as $phrase) {
                if (str_contains($haystack, $phrase)) {
                    // Multi-word phrases are stronger signals than single words.
                    $score += str_contains($phrase, ' ') ? 2 : 1;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }

        return [
            'intent'     => $bestScore > 0 ? $best : null,
            'score'      => $bestScore,
            'confidence' => $bestScore > 0 ? min(1.0, $bestScore / 3) : 0.0,
        ];
    }

    /**
     * Handle a message if it maps to a skill.
     *
     * @return array|null  Structured reply array, or null to fall back to RAG.
     *                     Shape: {handled, intent, reply, data?}
     */
    public function handle(string $message, ?int $userId = null): ?array
    {
        $detected = $this->detectIntent($message);
        $intent = $detected['intent'];

        if ($intent === null) {
            return null; // -> RAG fallback
        }

        return match ($intent) {
            'renew_loan'         => $this->renewLoan($message, $userId),
            'submit_ill_request' => $this->submitIllRequest($message, $userId),
            'check_item_status'  => $this->checkItemStatus($message, $userId),
            default              => null,
        };
    }

    // ── Skills ──────────────────────────────────────────────────────────

    /**
     * renew_loan - renew the patron's active loan(s). If a barcode / call
     * number is present in the message we target that loan; otherwise we renew
     * the single active loan if there is exactly one, or list the loans for the
     * patron to choose.
     */
    protected function renewLoan(string $message, ?int $userId): array
    {
        $patron = $this->resolvePatron($userId);
        if ($patron === null) {
            return $this->signInReply('renew_loan',
                'To renew a loan I need to know who you are. Please sign in with the account linked to your library membership, then ask again.');
        }

        try {
            $patronSvc = app(\AhgLibrary\Services\LibraryPatronService::class);
            $loans = $patronSvc->getActiveLoans((int) $patron->id);
        } catch (\Throwable $e) {
            Log::warning('[chatbot-skill] renew_loan loan lookup failed: '.$e->getMessage());

            return $this->errorReply('renew_loan', 'I could not reach the circulation system just now. Please try again shortly.');
        }

        if (empty($loans)) {
            return $this->reply('renew_loan', 'You have no active loans to renew.');
        }

        // Try to target a specific loan by barcode / call number / title hint.
        $target = $this->matchLoan($loans, $message);

        if ($target === null && count($loans) > 1) {
            $list = array_map(
                fn ($l) => '- '.($l->title ?? '[Untitled]').' (due '.($l->due_date ?? '?').')',
                $loans
            );

            return $this->reply('renew_loan',
                "You have ".count($loans)." active loans. Which would you like to renew?\n".implode("\n", $list));
        }

        $target = $target ?? $loans[0];

        try {
            $circ = app(\AhgLibrary\Services\LibraryCirculationService::class);
            $ok = $circ->renew((int) $target->id);
        } catch (\Throwable $e) {
            Log::warning('[chatbot-skill] renew_loan renew() failed: '.$e->getMessage());

            return $this->errorReply('renew_loan', 'Renewal could not be completed. Please try again or contact the library desk.');
        }

        if (! $ok) {
            return $this->reply('renew_loan',
                'I could not renew "'.($target->title ?? 'that item').'". It may have reached its renewal limit or there may be a hold queued by another patron. A librarian can help - reply "talk to a librarian" if you would like.',
                ['checkout_id' => (int) $target->id, 'renewed' => false]);
        }

        // Re-read to report the new due date.
        $newDue = DB::table('library_checkout')->where('id', $target->id)->value('due_date');

        return $this->reply('renew_loan',
            'Renewed "'.($target->title ?? 'your item').'".'.($newDue ? ' The new due date is '.$newDue.'.' : ''),
            ['checkout_id' => (int) $target->id, 'renewed' => true, 'due_date' => $newDue]);
    }

    /**
     * submit_ill_request - lodge an interlibrary-loan request for the patron.
     * We extract a best-effort title from the message; the librarian completes
     * the bibliographic detail on the staff side.
     */
    protected function submitIllRequest(string $message, ?int $userId): array
    {
        $patron = $this->resolvePatron($userId);
        if ($patron === null) {
            return $this->signInReply('submit_ill_request',
                'To submit an interlibrary-loan request I need your library account. Please sign in with the account linked to your membership and ask again.');
        }

        $title = $this->extractTitle($message);
        if ($title === '') {
            return $this->reply('submit_ill_request',
                'I can lodge an interlibrary-loan request for you. What is the title (and author, if you have it) of the item you need?');
        }

        try {
            $ill = app(\AhgLibrary\Services\LibraryIllService::class);
            $id = $ill->patronCreate((int) $patron->id, [
                'title'          => $title,
                'requester_note' => 'Submitted via chatbot assistant.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[chatbot-skill] submit_ill_request create failed: '.$e->getMessage());

            return $this->errorReply('submit_ill_request', 'I could not submit the request just now. Please try again shortly.');
        }

        if ($id <= 0) {
            return $this->errorReply('submit_ill_request', 'The interlibrary-loan service is not available right now. Please try again later.');
        }

        return $this->reply('submit_ill_request',
            'Your interlibrary-loan request for "'.$title.'" has been submitted. The library will be in touch about availability and any fees.',
            ['ill_request_id' => $id, 'title' => $title]);
    }

    /**
     * check_item_status - look up an item by title / ISBN / call number and
     * report copy availability. No authentication required (public OPAC data).
     */
    protected function checkItemStatus(string $message, ?int $userId): array
    {
        $term = $this->extractTitle($message);
        if ($term === '') {
            return $this->reply('check_item_status',
                'Which item would you like me to check? Give me a title, ISBN, or call number.');
        }

        try {
            $item = $this->findItem($term);
        } catch (\Throwable $e) {
            Log::warning('[chatbot-skill] check_item_status lookup failed: '.$e->getMessage());

            return $this->errorReply('check_item_status', 'I could not reach the catalogue just now. Please try again shortly.');
        }

        if ($item === null) {
            return $this->reply('check_item_status',
                'I could not find a library item matching "'.$term.'". Try the exact title, ISBN, or call number.');
        }

        try {
            $opac = app(\AhgLibrary\Services\LibraryOpacService::class);
            $avail = $opac->getAvailability((int) $item->id);
        } catch (\Throwable $e) {
            Log::warning('[chatbot-skill] check_item_status availability failed: '.$e->getMessage());

            return $this->errorReply('check_item_status', 'I found the item but could not read its availability just now.');
        }

        $title = $item->title ?? $term;
        $available = (int) ($avail['available'] ?? 0);
        $total = (int) ($avail['total'] ?? 0);

        $msg = $available > 0
            ? sprintf('"%s" - %d of %d copies are available to borrow now.', $title, $available, $total)
            : sprintf('"%s" - all %d copies are currently out. You can place a hold to join the queue.', $title, $total);

        return $this->reply('check_item_status', $msg, [
            'item_id'      => (int) $item->id,
            'availability' => $avail,
        ]);
    }

    // ── Resolution helpers ───────────────────────────────────────────────

    /**
     * Resolve the library_patron row for an authenticated user by matching the
     * user's email to library_patron.email. Returns null when unauthenticated,
     * when no patron table exists, or when no row matches.
     */
    protected function resolvePatron(?int $userId): ?object
    {
        if ($userId === null) {
            return null;
        }

        try {
            if (! Schema::hasTable('library_patron') || ! Schema::hasTable('users')) {
                return null;
            }

            $email = DB::table('users')->where('id', $userId)->value('email');
            if (! $email) {
                return null;
            }

            return DB::table('library_patron')
                ->where('email', $email)
                ->first();
        } catch (\Throwable $e) {
            Log::debug('[chatbot-skill] resolvePatron failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Pick the loan a renew request refers to by matching barcode / call number
     * / title tokens found in the message. Returns null when ambiguous.
     *
     * @param array $loans  rows from LibraryPatronService::getActiveLoans()
     */
    protected function matchLoan(array $loans, string $message): ?object
    {
        $hay = mb_strtolower($message);
        foreach ($loans as $loan) {
            foreach (['barcode', 'call_number', 'isbn'] as $field) {
                $val = isset($loan->$field) ? mb_strtolower((string) $loan->$field) : '';
                if ($val !== '' && mb_strlen($val) >= 3 && str_contains($hay, $val)) {
                    return $loan;
                }
            }
            $title = isset($loan->title) ? mb_strtolower((string) $loan->title) : '';
            if ($title !== '' && mb_strlen($title) >= 4 && str_contains($hay, $title)) {
                return $loan;
            }
        }

        return null;
    }

    /**
     * Find a single library_item by ISBN / call number / title LIKE.
     */
    protected function findItem(string $term): ?object
    {
        if (! Schema::hasTable('library_item')) {
            return null;
        }

        $culture = app()->getLocale();
        $like = '%'.$term.'%';

        return DB::table('library_item as li')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->where(function ($w) use ($term, $like) {
                $w->where('li.isbn', $term)
                    ->orWhere('li.call_number', $term)
                    ->orWhere('i18n.title', 'LIKE', $like);
            })
            ->select('li.id', 'li.isbn', 'li.call_number', 'i18n.title')
            ->orderBy('i18n.title')
            ->first();
    }

    /**
     * Best-effort title extraction from a free-text message: prefer quoted
     * text, else strip the trigger phrasing and keep the remainder.
     */
    protected function extractTitle(string $message): string
    {
        // Quoted segment wins.
        if (preg_match('/["\x{201C}\x{201D}\x{2018}\x{2019}\']([^"\x{201C}\x{201D}\x{2018}\x{2019}\']{2,})["\x{201C}\x{201D}\x{2018}\x{2019}\']/u', $message, $m)) {
            return trim($m[1]);
        }

        // Strip common lead-ins.
        $stripped = preg_replace(
            '/\b(is|can i borrow|do you have|how many copies of|availability of|check( the)?|status of|request|interlibrary loan( for)?|ill( request)?( for)?|borrow|renew( my| the)?( loan)?( of)?)\b/iu',
            ' ',
            $message
        );
        $stripped = preg_replace('/\b(available|in stock|on the shelf|please|the book|a book|for me|from another library)\b/iu', ' ', (string) $stripped);
        $stripped = trim(preg_replace('/[?\.\!]+/', ' ', (string) $stripped));
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped));

        // Avoid returning a single noise word.
        return mb_strlen($stripped) >= 3 ? $stripped : '';
    }

    // ── Reply shapers ────────────────────────────────────────────────────

    protected function reply(string $intent, string $text, array $data = []): array
    {
        return [
            'handled' => true,
            'intent'  => $intent,
            'reply'   => $text,
            'data'    => $data,
        ];
    }

    protected function signInReply(string $intent, string $text): array
    {
        $r = $this->reply($intent, $text);
        $r['needs_auth'] = true;

        return $r;
    }

    protected function errorReply(string $intent, string $text): array
    {
        $r = $this->reply($intent, $text);
        $r['error'] = true;

        return $r;
    }
}
