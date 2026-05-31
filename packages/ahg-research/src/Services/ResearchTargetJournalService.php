<?php

/**
 * ResearchTargetJournalService - target-journal directory (#1107).
 *
 * Journals to publish TO, each with its subject scope and submission rules.
 * The directory core is jurisdiction-neutral; the DHET accredited list is the
 * South-African accreditation MODULE (accreditation_market='ZA') seeded here as
 * a starter set — other markets seed from DOAJ / Scopus / Web of Science.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResearchTargetJournalService
{
    public const REFERENCE_STYLES = ['APA', 'Harvard', 'Vancouver', 'Chicago', 'MLA', 'IEEE'];

    // ── CRUD ────────────────────────────────────────────────────────────────

    public function list(array $filters = []): array
    {
        $q = DB::table('research_target_journal')->orderBy('title');
        if (! empty($filters['market'])) {
            $q->where('accreditation_market', $filters['market']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)->orWhere('subject_scope', 'like', $term)
                  ->orWhere('publisher', 'like', $term)->orWhere('accreditation', 'like', $term);
            });
        }
        if (! empty($filters['accreditation'])) {
            $q->where('accreditation', 'like', '%'.$filters['accreditation'].'%');
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    public function get(int $id): ?array
    {
        $row = DB::table('research_target_journal')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function create(array $data): int
    {
        return (int) DB::table('research_target_journal')->insertGetId($this->payload($data, true));
    }

    public function update(int $id, array $data): bool
    {
        return DB::table('research_target_journal')->where('id', $id)->update($this->payload($data, false)) >= 0;
    }

    public function delete(int $id): void
    {
        DB::table('research_target_journal')->where('id', $id)->delete();
    }

    private function payload(array $d, bool $isNew): array
    {
        $p = [
            'title'                => trim((string) ($d['title'] ?? 'Untitled journal')),
            'subtitle'             => $d['subtitle'] ?? null,
            'issn'                 => $d['issn'] ?: null,
            'eissn'                => $d['eissn'] ?? null,
            'publisher'            => $d['publisher'] ?? null,
            'homepage_url'         => $d['homepage_url'] ?? null,
            'submission_url'       => $d['submission_url'] ?? null,
            'languages'            => $d['languages'] ?? null,
            'subject_scope'        => $d['subject_scope'] ?? null,
            'article_types'        => $d['article_types'] ?? null,
            'accreditation'        => $d['accreditation'] ?? null,
            'accreditation_market' => $d['accreditation_market'] ?? null,
            'reference_style'      => $d['reference_style'] ?? null,
            'structure_notes'      => $d['structure_notes'] ?? null,
            'max_words'            => isset($d['max_words']) && $d['max_words'] !== '' ? (int) $d['max_words'] : null,
            'abstract_max_words'   => isset($d['abstract_max_words']) && $d['abstract_max_words'] !== '' ? (int) $d['abstract_max_words'] : null,
            'peer_review'          => $d['peer_review'] ?? null,
            'open_access'          => ! empty($d['open_access']) ? 1 : 0,
            'apc_amount'           => $d['apc_amount'] ?? null,
            'turnaround'           => $d['turnaround'] ?? null,
            'notes'                => $d['notes'] ?? null,
            'updated_at'           => Carbon::now(),
        ];
        if ($isNew) {
            $p['status']     = $d['status'] ?? 'active';
            $p['created_at'] = Carbon::now();
        } elseif (isset($d['status'])) {
            $p['status'] = $d['status'];
        }

        return $p;
    }

    /**
     * Best-fit suggestions for a manuscript: score directory journals by how many
     * of the manuscript's subject terms appear in their scope. Returns the top N.
     */
    public function suggestForScope(string $text, int $limit = 5): array
    {
        $text = strtolower($text);
        $terms = array_unique(array_filter(preg_split('/[^a-z]+/', $text) ?: [], fn ($t) => strlen($t) >= 4));
        if (! $terms) {
            return [];
        }
        $scored = [];
        foreach ($this->list(['market' => null]) as $j) {
            $hay = strtolower(($j['subject_scope'] ?? '').' '.($j['title'] ?? ''));
            $score = 0;
            foreach ($terms as $t) {
                if (str_contains($hay, $t)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $j['match_score'] = $score;
                $scored[] = $j;
            }
        }
        usort($scored, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        return array_slice($scored, 0, $limit);
    }

    // ── DHET starter seed (SA accreditation module) ─────────────────────────

    /**
     * Upsert the curated DHET-accredited starter set, keyed by title (idempotent).
     * Returns the number of rows inserted or updated.
     *
     * This is a representative STARTER set of DHET-accredited South African
     * journals (with emphasis on GLAM / LIS / archives / heritage and a few
     * multidisciplinary titles), not the full ~700-title list. ISSNs and exact
     * numeric submission limits are left to be completed/verified per journal;
     * scope, publisher, indexing and reference style reflect each journal's
     * public profile. Operators extend the directory via the admin UI.
     */
    public function seedDhetStarter(): int
    {
        $n = 0;
        foreach ($this->dhetStarter() as $row) {
            $row['accreditation_market'] = 'ZA';
            $row['status'] = 'active';
            $row['updated_at'] = Carbon::now();
            $existing = DB::table('research_target_journal')->where('title', $row['title'])->first();
            if ($existing) {
                DB::table('research_target_journal')->where('id', $existing->id)->update($row);
            } else {
                $row['created_at'] = Carbon::now();
                DB::table('research_target_journal')->insert($row);
            }
            $n++;
        }

        return $n;
    }

    private function dhetStarter(): array
    {
        $h = 'Harvard';
        return [
            ['title' => 'South African Journal of Libraries and Information Science', 'publisher' => 'LIASA',
             'subject_scope' => 'Library and information science: collections, services, information behaviour, knowledge management, archives and records.',
             'article_types' => 'research, review', 'accreditation' => 'DHET, Scopus, DOAJ, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English',
             'notes' => 'Verify current author guidelines and word limits on the journal site.'],

            ['title' => 'Mousaion: South African Journal of Information Studies', 'publisher' => 'Unisa Press',
             'subject_scope' => 'Information studies, library science, archival and records studies, knowledge organisation.',
             'article_types' => 'research, review', 'accreditation' => 'DHET, IBSS, Scopus, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 0, 'languages' => 'English'],

            ['title' => 'Innovation: Journal of Appropriate Librarianship and Information Work in Southern Africa', 'publisher' => 'University of KwaZulu-Natal',
             'subject_scope' => 'Librarianship and information work in Southern Africa; practice-oriented LIS research.',
             'article_types' => 'research, practice', 'accreditation' => 'DHET, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 0, 'languages' => 'English'],

            ['title' => 'Journal of the South African Society of Archivists', 'publisher' => 'South African Society of Archivists',
             'subject_scope' => 'Archives, records management, recordkeeping, preservation and access in Southern Africa.',
             'article_types' => 'research, review', 'accreditation' => 'DHET, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English'],

            ['title' => 'ESARBICA Journal', 'publisher' => 'Eastern and Southern Africa Regional Branch of the ICA',
             'subject_scope' => 'Archives and records management across Eastern and Southern Africa.',
             'article_types' => 'research', 'accreditation' => 'DHET, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English'],

            ['title' => 'South African Journal of Cultural History', 'publisher' => 'South African Society for Cultural History',
             'subject_scope' => 'Cultural history, material culture, heritage, museums and built environment.',
             'article_types' => 'research', 'accreditation' => 'DHET, Sabinet',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 0, 'languages' => 'English, Afrikaans'],

            ['title' => 'Historia', 'publisher' => 'Historical Association of South Africa',
             'subject_scope' => 'Historical studies with a focus on South and Southern Africa.',
             'article_types' => 'research, review', 'accreditation' => 'DHET, Scopus, Sabinet',
             'reference_style' => 'Chicago', 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English, Afrikaans'],

            ['title' => 'South African Journal of Science', 'publisher' => 'Academy of Science of South Africa (ASSAf)',
             'subject_scope' => 'Multidisciplinary science, including digital humanities and heritage science.',
             'article_types' => 'research, review, commentary', 'accreditation' => 'DHET, Scopus, Web of Science, DOAJ',
             'reference_style' => 'Vancouver', 'peer_review' => 'single-blind', 'open_access' => 1, 'languages' => 'English'],

            ['title' => 'South African Journal of Information Management', 'publisher' => 'AOSIS',
             'subject_scope' => 'Information and knowledge management, information systems, digital information services.',
             'article_types' => 'research, review', 'accreditation' => 'DHET, Scopus, DOAJ',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 1, 'apc_amount' => 'APC applies', 'languages' => 'English'],

            ['title' => 'South African Computer Journal', 'publisher' => 'South African Institute of Computer Scientists and Information Technologists',
             'subject_scope' => 'Computer science and information technology, including digital curation and data management.',
             'article_types' => 'research', 'accreditation' => 'DHET, Scopus, DOAJ',
             'reference_style' => 'IEEE', 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English'],

            ['title' => 'Education as Change', 'publisher' => 'Unisa Press',
             'subject_scope' => 'Education research, teaching and learning, information literacy.',
             'article_types' => 'research', 'accreditation' => 'DHET, Scopus, DOAJ',
             'reference_style' => 'APA', 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English'],

            ['title' => 'Literator: Journal of Literary Criticism, Comparative Linguistics and Literary Studies', 'publisher' => 'AOSIS',
             'subject_scope' => 'Literary studies, comparative linguistics, language and literature in Southern Africa.',
             'article_types' => 'research', 'accreditation' => 'DHET, Scopus, DOAJ',
             'reference_style' => $h, 'peer_review' => 'double-blind', 'open_access' => 1, 'languages' => 'English, Afrikaans'],
        ];
    }
}
