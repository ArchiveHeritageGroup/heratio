<?php

/**
 * ResearchJournalService - journal builder for the research portal (#1105).
 *
 * Backs two modes against the same tables:
 *   - publication: an institutional scholarly journal (journal -> issues ->
 *     articles -> table of contents -> publish).
 *   - manuscript:  a single article workspace formatted toward an external
 *     target journal (reference style, abstract, keywords; target rules come
 *     from the #1107 target-journal directory when present).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class ResearchJournalService
{
    public const KIND_PUBLICATION = 'publication';
    public const KIND_MANUSCRIPT  = 'manuscript';

    /** Reference styles offered by the manuscript builder. */
    public const REFERENCE_STYLES = ['APA', 'Harvard', 'Vancouver', 'Chicago', 'MLA', 'IEEE'];

    // ── Journals ──────────────────────────────────────────────────────────

    public function listJournals(?string $kind = null): array
    {
        $q = DB::table('research_journal')->orderByDesc('updated_at');
        if ($kind !== null) {
            $q->where('kind', $kind);
        }

        return $q->get()->map(fn ($j) => (array) $j)->all();
    }

    public function getJournal(int $id): ?array
    {
        $row = DB::table('research_journal')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createJournal(array $data): int
    {
        return (int) DB::table('research_journal')->insertGetId($this->journalPayload($data, true));
    }

    public function updateJournal(int $id, array $data): bool
    {
        return DB::table('research_journal')->where('id', $id)->update($this->journalPayload($data, false)) >= 0;
    }

    public function deleteJournal(int $id): void
    {
        DB::table('research_journal_article')->where('journal_id', $id)->delete();
        DB::table('research_journal_issue')->where('journal_id', $id)->delete();
        DB::table('research_journal')->where('id', $id)->delete();
    }

    public function setJournalStatus(int $id, string $status): bool
    {
        return DB::table('research_journal')->where('id', $id)
            ->update(['status' => $status, 'updated_at' => Carbon::now()]) > 0;
    }

    private function journalPayload(array $d, bool $isNew): array
    {
        $p = [
            'kind'              => in_array(($d['kind'] ?? null), [self::KIND_PUBLICATION, self::KIND_MANUSCRIPT], true) ? $d['kind'] : self::KIND_PUBLICATION,
            'title'             => trim((string) ($d['title'] ?? 'Untitled journal')),
            'subtitle'          => $d['subtitle'] ?? null,
            'issn'              => $d['issn'] ?? null,
            'eissn'             => $d['eissn'] ?? null,
            'publisher'         => $d['publisher'] ?? null,
            'description'       => $d['description'] ?? null,
            'aims_scope'        => $d['aims_scope'] ?? null,
            'editor_name'       => $d['editor_name'] ?? null,
            'editor_email'      => $d['editor_email'] ?? null,
            'target_journal_id' => $d['target_journal_id'] ?? null,
            'doi'               => $d['doi'] ?? null,
            'updated_at'        => Carbon::now(),
        ];
        if ($isNew) {
            $p['researcher_id'] = $d['researcher_id'] ?? null;
            $p['status']        = $d['status'] ?? 'draft';
            $p['created_at']    = Carbon::now();
        } elseif (isset($d['status'])) {
            $p['status'] = $d['status'];
        }

        return $p;
    }

    // ── Issues ────────────────────────────────────────────────────────────

    public function listIssues(int $journalId): array
    {
        return DB::table('research_journal_issue')->where('journal_id', $journalId)
            ->orderBy('sort_order')->orderByDesc('issue_date')
            ->get()->map(fn ($i) => (array) $i)->all();
    }

    public function getIssue(int $id): ?array
    {
        $row = DB::table('research_journal_issue')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createIssue(int $journalId, array $data): int
    {
        return (int) DB::table('research_journal_issue')->insertGetId([
            'journal_id'  => $journalId,
            'volume'      => $data['volume'] ?? null,
            'number'      => $data['number'] ?? null,
            'title'       => $data['title'] ?? null,
            'issue_date'  => $data['issue_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'draft',
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    public function updateIssue(int $id, array $data): bool
    {
        return DB::table('research_journal_issue')->where('id', $id)->update([
            'volume'      => $data['volume'] ?? null,
            'number'      => $data['number'] ?? null,
            'title'       => $data['title'] ?? null,
            'issue_date'  => $data['issue_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'draft',
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'updated_at'  => Carbon::now(),
        ]) >= 0;
    }

    public function deleteIssue(int $id): void
    {
        // Unassign articles rather than delete them.
        DB::table('research_journal_article')->where('issue_id', $id)->update(['issue_id' => null]);
        DB::table('research_journal_issue')->where('id', $id)->delete();
    }

    // ── Articles ──────────────────────────────────────────────────────────

    public function listArticles(int $journalId, ?int $issueId = null): array
    {
        $q = DB::table('research_journal_article')->where('journal_id', $journalId)
            ->orderBy('sort_order')->orderBy('title');
        if ($issueId !== null) {
            $q->where('issue_id', $issueId);
        }

        return $q->get()->map(fn ($a) => (array) $a)->all();
    }

    public function getArticle(int $id): ?array
    {
        $row = DB::table('research_journal_article')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createArticle(int $journalId, array $data): int
    {
        $payload = $this->articlePayload($data);
        $payload['journal_id'] = $journalId;
        $payload['created_at'] = Carbon::now();

        return (int) DB::table('research_journal_article')->insertGetId($payload);
    }

    public function updateArticle(int $id, array $data): bool
    {
        return DB::table('research_journal_article')->where('id', $id)->update($this->articlePayload($data)) >= 0;
    }

    public function deleteArticle(int $id): void
    {
        DB::table('research_journal_article')->where('id', $id)->delete();
    }

    private function articlePayload(array $d): array
    {
        $markdown = (string) ($d['body_markdown'] ?? '');

        return [
            'issue_id'         => $d['issue_id'] ?? null,
            'title'            => trim((string) ($d['title'] ?? 'Untitled article')),
            'authors'          => $d['authors'] ?? null,
            'abstract'         => $d['abstract'] ?? null,
            'keywords'         => $d['keywords'] ?? null,
            'body_markdown'    => $markdown,
            'body_html'        => $this->renderMarkdown($markdown),
            'reference_style'  => $d['reference_style'] ?? null,
            'target_journal_id'=> $d['target_journal_id'] ?? null,
            'doi'              => $d['doi'] ?? null,
            'word_count'       => $this->wordCount($markdown),
            'status'           => $d['status'] ?? 'draft',
            'sort_order'       => (int) ($d['sort_order'] ?? 0),
            'updated_at'       => Carbon::now(),
        ];
    }

    // ── Table of contents ─────────────────────────────────────────────────

    /**
     * TOC for a published-style journal: issues (newest first) each with their
     * ordered articles. Used by the journal show / publish view.
     */
    public function tableOfContents(int $journalId): array
    {
        $toc = [];
        foreach ($this->listIssues($journalId) as $issue) {
            $issue['articles'] = $this->listArticles($journalId, (int) $issue['id']);
            $toc[] = $issue;
        }
        // Unassigned articles (manuscript drafts / not yet placed in an issue).
        $unassigned = DB::table('research_journal_article')
            ->where('journal_id', $journalId)->whereNull('issue_id')
            ->orderBy('sort_order')->orderBy('title')->get()->map(fn ($a) => (array) $a)->all();
        if ($unassigned) {
            $toc[] = ['id' => null, 'title' => 'Unassigned', 'volume' => null, 'number' => null,
                      'status' => 'draft', 'articles' => $unassigned];
        }

        return $toc;
    }

    // ── Manuscript formatting / validation (target journal = #1107) ─────────

    /**
     * Look up the external target-journal rules from the #1107 directory when it
     * exists. Returns null until that feature ships, so callers degrade
     * gracefully (manuscript builder still works on the captured style/limits).
     */
    public function targetJournal(?int $targetJournalId): ?array
    {
        if (! $targetJournalId || ! Schema::hasTable('research_target_journal')) {
            return null;
        }
        $row = DB::table('research_target_journal')->where('id', $targetJournalId)->first();

        return $row ? (array) $row : null;
    }

    /**
     * Validate a manuscript article against its target journal's rules (where
     * available) and basic completeness. Returns a list of human-readable
     * issues; empty = ready to assemble.
     */
    public function validateManuscript(array $article): array
    {
        $problems = [];
        if (trim((string) ($article['title'] ?? '')) === '') {
            $problems[] = 'Title is required.';
        }
        if (trim((string) ($article['abstract'] ?? '')) === '') {
            $problems[] = 'An abstract is required for submission.';
        }
        if (trim((string) ($article['authors'] ?? '')) === '') {
            $problems[] = 'At least one author must be listed.';
        }
        if (($article['word_count'] ?? 0) < 1) {
            $problems[] = 'The manuscript body is empty.';
        }

        $target = $this->targetJournal($article['target_journal_id'] ?? null);
        if ($target) {
            if (! empty($target['reference_style']) && ! empty($article['reference_style'])
                && strcasecmp($target['reference_style'], $article['reference_style']) !== 0) {
                $problems[] = "Reference style should be {$target['reference_style']} for this journal.";
            }
            if (! empty($target['max_words']) && ($article['word_count'] ?? 0) > (int) $target['max_words']) {
                $problems[] = "Manuscript exceeds the journal's {$target['max_words']}-word limit (currently {$article['word_count']}).";
            }
        }

        return $problems;
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    public function renderMarkdown(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return (string) $converter->convert($markdown);
    }

    public function wordCount(string $markdown): int
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->renderMarkdown($markdown))) ?? '');

        return $text === '' ? 0 : str_word_count($text);
    }
}
