<?php

/**
 * LanguageCorpusService - language-revival corpus surface (north-star
 * heratio#1208: a culture you can talk to - corpus-grounded history + language
 * revival).
 *
 * Some of the languages a collection holds are heritage or endangered languages,
 * living and owned by the communities that speak them. This service turns the
 * collection into a READ-ONLY revival resource for a chosen language / culture:
 *
 *   - languages()        - every culture present in the catalogue, with how much
 *                          of the collection is described IN it and how many
 *                          terms / place-names carry a label in it, richest
 *                          first (Afrikaans ordered immediately before Dutch).
 *   - describedRecords() - PUBLISHED records whose description is written IN the
 *                          chosen culture (the holdings in that language).
 *   - terms()            - controlled-vocabulary terms / place-names that carry a
 *                          label in the chosen culture (a starting word-list).
 *   - transcriptions()   - PUBLISHED records that carry transcription-style prose
 *                          in the chosen culture (full-text in the language).
 *   - glossary()         - the APPROVED community glossary for the culture.
 *   - contribute()       - lodge a new community glossary entry (lands 'pending').
 *   - translateSnippet() - OPTIONAL on-demand machine translation of a snippet,
 *                          via the existing LlmService abstraction (the AHG
 *                          gateway), clearly labelled as machine translation and
 *                          NOT an official / authoritative translation.
 *
 * Everything about the catalogue is read READ-ONLY behind Schema::hasTable
 * probes. The ONLY table this service writes to is the additive, soft-keyed
 * language_revival_glossary. It never ALTERs or writes any existing table.
 *
 * The published-records gate mirrors the rest of Heratio: a record is
 * "published" when its row in the status table (type_id = 158 publication
 * status) carries status_id = 160 (published); the catalogue root (id = 1) is
 * never surfaced.
 *
 * Framing is deliberately respectful and jurisdiction-neutral: a heritage
 * language is living and belongs to its community of speakers. This surface
 * gathers what the collection holds in or about a language as a resource for
 * that community - it does not assert ownership of the language, and any machine
 * translation it offers is clearly marked as unofficial.
 *
 * Every read/write path is Schema::hasTable-guarded and wrapped so a missing
 * table degrades to an empty result rather than a 500.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LanguageCorpusService
{
    /** The single table this service writes to. */
    public const TABLE = 'language_revival_glossary';

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /** Places taxonomy id (for place-names) and Subjects taxonomy id. */
    protected const TAXONOMY_PLACES = 42;

    protected const TAXONOMY_SUBJECTS = 35;

    /** Hard caps so the public read-only surface stays cheap + bounded. */
    protected const MAX_RECORDS = 60;

    protected const MAX_TERMS = 120;

    protected const MAX_TRANSCRIPTIONS = 40;

    protected const MAX_GLOSSARY = 500;

    /**
     * Cap for the in-language id set used to scope another surface (the #1208
     * chatbot). Far larger than MAX_RECORDS - a scoped conversation should see the
     * whole corpus of a language, not just the first public page - but still
     * bounded so the set stays cheap to build and to match against.
     */
    protected const MAX_SCOPE_IDS = 20000;

    /** Snippet cap for the optional gateway translation. */
    protected const MAX_SNIPPET_CHARS = 1200;

    /**
     * Canonical moderation states for a community glossary entry. VARCHAR-backed
     * (Dropdown-Manager idiom), never an ENUM. Only 'approved' entries are shown
     * on the public surface.
     *
     * @var array<string,array{label:string,level:string}>
     */
    public const MODERATION_STATUSES = [
        'pending'  => ['label' => 'Pending review', 'level' => 'secondary'],
        'approved' => ['label' => 'Approved', 'level' => 'success'],
        'rejected' => ['label' => 'Not published', 'level' => 'light'],
    ];

    /**
     * The standing label for any machine translation offered on this surface.
     * Required wherever translated text is shown.
     */
    public const MT_LABEL = 'Machine translation via the AHG gateway - not an official or authoritative translation.';

    /**
     * Respectful, jurisdiction-neutral framing surfaced wherever the corpus is
     * shown. A heritage language is living and owned by its community of speakers.
     */
    public const DISCLAIMER = 'Heritage and endangered languages are living languages that belong to the communities '
        .'who speak them. This page gathers what the collection holds in or about a language - records described in '
        .'it, place-names and subject terms that carry a label in it, and any transcriptions - as a resource for '
        .'speakers, learners and researchers. It does not claim authority over the language itself. The community '
        .'glossary is contributed by people, reviewed before it appears, and is a shared starting point, not a '
        .'definitive dictionary.';

    /**
     * Human labels for language CODES, keyed on the lower-cased base subtag. Kept
     * in step with the core LanguageCoverageService so this surface and the
     * coverage analytic read the same names. Unknown codes fall back to the
     * upper-cased code. Afrikaans is first-class and is ordered before Dutch.
     *
     * @var array<string,string>
     */
    protected const LANGUAGE_LABELS = [
        'en'  => 'English',
        'af'  => 'Afrikaans',
        'fr'  => 'Français',
        'nl'  => 'Nederlands',
        'pt'  => 'Português',
        'es'  => 'Español',
        'de'  => 'Deutsch',
        'it'  => 'Italiano',
        'zu'  => 'isiZulu',
        'xh'  => 'isiXhosa',
        'st'  => 'Sesotho',
        'tn'  => 'Setswana',
        'nso' => 'Sepedi',
        'ts'  => 'Xitsonga',
        'ss'  => 'siSwati',
        've'  => 'Tshivenda',
        'nr'  => 'isiNdebele',
        'sn'  => 'chiShona',
        'sw'  => 'Kiswahili',
        'ar'  => 'العربية',
        'zh'  => '中文',
        'ja'  => '日本語',
        'ru'  => 'Русский',
        'pl'  => 'Polski',
        'cy'  => 'Cymraeg',
        'hr'  => 'Hrvatski',
        'bs'  => 'Bosanski',
        'mk'  => 'Македонски',
    ];

    // ---------------------------------------------------------------------
    // Glossary table availability
    // ---------------------------------------------------------------------

    /**
     * Is the glossary table present? Glossary read/write paths gate on this so a
     * fresh (un-booted) install never fatals. Note: the read-only corpus surfaces
     * do NOT depend on this - they degrade per-source.
     */
    public function glossaryAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[language-corpus] glossary table probe failed: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Culture helpers
    // ---------------------------------------------------------------------

    /**
     * Normalise a culture / language code to its lower-cased base subtag, e.g.
     * "pt_BR" -> "pt", "de-CH" -> "de". Defensive: returns '' for blanks.
     */
    public function normaliseLang(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return '';
        }
        // Split on the first separator (- or _ or @) so a regional or script
        // variant collapses onto its base language.
        $base = preg_split('/[-_@]/', $code)[0] ?? $code;

        return $base !== '' ? $base : $code;
    }

    /** Human label for a culture code, falling back to the upper-cased code. */
    public function label(string $code): string
    {
        $base = $this->normaliseLang($code);

        return self::LANGUAGE_LABELS[$base] ?? strtoupper($base !== '' ? $base : $code);
    }

    /**
     * Validate that a requested culture is non-empty and contains only the
     * characters a culture code may use. Returns the lower-cased, base subtag, or
     * null when the input is unusable.
     */
    public function sanitiseCulture(?string $culture): ?string
    {
        $culture = strtolower(trim((string) $culture));
        if ($culture === '' || ! preg_match('/^[a-z]{2,3}([-_@][a-z0-9]+)*$/', $culture)) {
            return null;
        }

        return $this->normaliseLang($culture);
    }

    // ---------------------------------------------------------------------
    // Language directory
    // ---------------------------------------------------------------------

    /** Reusable subquery: object_ids of every published, non-root record. */
    protected function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::PUBLICATION_TYPE_ID)
            ->where('status_id', self::PUBLISHED_STATUS_ID)
            ->where('object_id', '>', self::ROOT_ID);
    }

    /**
     * Every culture present in the catalogue, with how many PUBLISHED records are
     * described IN it and how many controlled-vocabulary terms carry a label in
     * it. Richest first (by described-record count, then term count). English is
     * kept (it is a real holding language) but the directory leads with the
     * fuller multilingual picture; Afrikaans is ordered immediately before Dutch.
     *
     * @return array<int,array{code:string,label:string,records:int,terms:int}>
     */
    public function languages(): array
    {
        $records = $this->describedCountsByCulture();
        $terms = $this->termCountsByCulture();

        $codes = array_unique(array_merge(array_keys($records), array_keys($terms)));

        $out = [];
        foreach ($codes as $code) {
            $base = $this->normaliseLang((string) $code);
            if ($base === '') {
                continue;
            }
            // Merge regional variants onto the base code.
            if (! isset($out[$base])) {
                $out[$base] = ['code' => $base, 'label' => $this->label($base), 'records' => 0, 'terms' => 0];
            }
            $out[$base]['records'] += (int) ($records[$code] ?? 0);
            $out[$base]['terms'] += (int) ($terms[$code] ?? 0);
        }

        $list = array_values($out);
        usort($list, function ($a, $b) {
            if ($a['records'] !== $b['records']) {
                return $b['records'] <=> $a['records'];
            }
            if ($a['terms'] !== $b['terms']) {
                return $b['terms'] <=> $a['terms'];
            }

            return strcmp($a['label'], $b['label']);
        });

        return $this->afBeforeNl($list);
    }

    /**
     * Count of PUBLISHED records described in each culture (real, non-blank title).
     *
     * @return array<string,int> culture => count
     */
    protected function describedCountsByCulture(): array
    {
        if (! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return [];
        }
        try {
            $rows = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereNotNull('i.culture')
                ->whereRaw("TRIM(i.culture) <> ''")
                ->whereRaw("TRIM(COALESCE(i.title,'')) <> ''")
                ->groupBy('i.culture')
                ->select(['i.culture as culture', DB::raw('COUNT(*) as c')])
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->culture] = (int) $r->c;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::info('[language-corpus] describedCountsByCulture failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Count of controlled-vocabulary terms carrying a real label in each culture.
     *
     * @return array<string,int> culture => count
     */
    protected function termCountsByCulture(): array
    {
        if (! Schema::hasTable('term_i18n')) {
            return [];
        }
        try {
            $rows = DB::table('term_i18n')
                ->whereNotNull('culture')
                ->whereRaw("TRIM(culture) <> ''")
                ->whereRaw("TRIM(COALESCE(name,'')) <> ''")
                ->groupBy('culture')
                ->select(['culture', DB::raw('COUNT(*) as c')])
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->culture] = (int) $r->c;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::info('[language-corpus] termCountsByCulture failed: '.$e->getMessage());

            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Read-only corpus sources for one culture
    // ---------------------------------------------------------------------

    /**
     * PUBLISHED records whose description is written IN the chosen culture (the
     * holdings in that language). Matches the base subtag so regional variants
     * count too. Read-only; never throws.
     *
     * @return array<int,array{id:int,title:string,slug:?string,snippet:?string,culture:string}>
     */
    public function describedRecords(string $culture): array
    {
        $base = $this->sanitiseCulture($culture);
        if ($base === null || ! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return [];
        }

        try {
            $q = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereRaw('LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(i.culture,"-",1),"_",1),"@",1)) = ?', [$base])
                ->whereRaw("TRIM(COALESCE(i.title,'')) <> ''")
                ->select(['i.id as id', 'i.title as title', 'i.scope_and_content as scope', 'i.culture as culture'])
                ->orderBy('i.title')
                ->limit(self::MAX_RECORDS);

            if (Schema::hasTable('slug')) {
                $q->leftJoin('slug as sl', 'sl.object_id', '=', 'i.id')
                    ->addSelect('sl.slug as slug');
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] describedRecords failed for '.$base.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'slug' => isset($r->slug) && $r->slug !== null ? (string) $r->slug : null,
                'snippet' => $this->clipSnippet($r->scope ?? null),
                'culture' => (string) $r->culture,
            ];
        }

        return $out;
    }

    /**
     * The information_object ids of every PUBLISHED, non-root record whose
     * description is written IN the chosen culture(s) - the SAME "holdings in this
     * language" definition as describedRecords(), returned as a flat id list for
     * callers that need to scope another surface (e.g. the #1208 chatbot, which
     * post-filters its retrieval hits to this set so a scoped conversation stays
     * grounded in the language's corpus). Read-only; never throws; returns [] when
     * the required tables are absent or nothing is published in the culture(s).
     *
     * #1208 multi-culture: accepts a single culture string OR an array of culture
     * codes. With several cultures the result is the UNION of their in-language id
     * sets (a record described in ANY of the selected languages is in scope).
     * Unknown / malformed codes are dropped; an empty / all-unusable selection
     * returns []. The single-string overload is preserved byte-for-byte.
     *
     * Bounded by MAX_SCOPE_IDS so the set stays cheap to build and to match
     * against, while being far larger than the public-page MAX_RECORDS cap so a
     * scoped chat sees the real corpus, not just the first page of it. The cap is
     * applied to the UNION across all selected cultures.
     *
     * @param  string|array<int,string>  $culture
     * @return array<int,int>
     */
    public function describedRecordIds($culture): array
    {
        // Normalise the input into a de-duplicated list of usable base subtags.
        $cultures = is_array($culture) ? $culture : [$culture];
        $bases = [];
        foreach ($cultures as $c) {
            $base = $this->sanitiseCulture(is_string($c) ? $c : null);
            if ($base !== null) {
                $bases[$base] = true;
            }
        }
        $bases = array_keys($bases);

        if (empty($bases) || ! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return [];
        }

        // Build a WHERE ... IN (?, ?, ...) over the normalised base subtag so a
        // single query unions all selected languages. Regional variants collapse
        // onto their base, matching describedRecords()/the directory.
        $placeholders = implode(',', array_fill(0, count($bases), '?'));

        try {
            $rows = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereRaw(
                    'LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(i.culture,"-",1),"_",1),"@",1)) IN ('.$placeholders.')',
                    $bases
                )
                ->whereRaw("TRIM(COALESCE(i.title,'')) <> ''")
                ->distinct()
                ->limit(self::MAX_SCOPE_IDS)
                ->pluck('i.id');
        } catch (\Throwable $e) {
            Log::info('[language-corpus] describedRecordIds failed for '.implode(',', $bases).': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $id) {
            $out[(int) $id] = true; // de-dupe ids across cultures
        }

        return array_keys($out);
    }

    /**
     * #1208 multi-culture: the language directory as a compact options list for a
     * scope selector (the chatbot multi-select). Each entry is the base subtag, its
     * human label and how many published records are described in it - directory
     * order (richest first, Afrikaans before Dutch). Languages with no described
     * records are dropped: there is nothing in-corpus to scope a conversation to.
     * Read-only; never throws; returns [] on any failure.
     *
     * @return array<int,array{code:string,label:string,records:int}>
     */
    public function availableCultures(): array
    {
        try {
            $languages = $this->languages();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] availableCultures failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($languages as $lang) {
            $code = (string) ($lang['code'] ?? '');
            if ($code === '' || (int) ($lang['records'] ?? 0) <= 0) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => (string) ($lang['label'] ?? strtoupper($code)),
                'records' => (int) ($lang['records'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Controlled-vocabulary terms (place-names + subject terms) that carry a label
     * in the chosen culture - a starting word-list drawn from the catalogue's own
     * authorities. Read-only; never throws.
     *
     * @return array<int,array{id:int,name:string,kind:string,slug:?string}>
     */
    public function terms(string $culture): array
    {
        $base = $this->sanitiseCulture($culture);
        if ($base === null || ! Schema::hasTable('term_i18n') || ! Schema::hasTable('term')) {
            return [];
        }

        try {
            $q = DB::table('term_i18n as ti')
                ->join('term as t', 't.id', '=', 'ti.id')
                ->whereRaw('LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(ti.culture,"-",1),"_",1),"@",1)) = ?', [$base])
                ->whereRaw("TRIM(COALESCE(ti.name,'')) <> ''")
                ->whereIn('t.taxonomy_id', [self::TAXONOMY_PLACES, self::TAXONOMY_SUBJECTS])
                ->select(['ti.id as id', 'ti.name as name', 't.taxonomy_id as taxonomy_id'])
                ->orderBy('ti.name')
                ->limit(self::MAX_TERMS);

            if (Schema::hasTable('slug')) {
                $q->leftJoin('slug as sl', 'sl.object_id', '=', 'ti.id')
                    ->addSelect('sl.slug as slug');
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] terms failed for '.$base.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'kind' => ((int) $r->taxonomy_id === self::TAXONOMY_PLACES) ? 'place' : 'subject',
                'slug' => isset($r->slug) && $r->slug !== null ? (string) $r->slug : null,
            ];
        }

        return $out;
    }

    /**
     * PUBLISHED records that carry transcription-style full-text prose in the
     * chosen culture. We treat the scope_and_content field of an in-culture
     * description as the transcription / full-text surface (it is where a
     * transcription or extended text is held in this catalogue). Read-only; never
     * throws.
     *
     * @return array<int,array{id:int,title:string,slug:?string,text:string,culture:string}>
     */
    public function transcriptions(string $culture): array
    {
        $base = $this->sanitiseCulture($culture);
        if ($base === null || ! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return [];
        }

        try {
            $q = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereRaw('LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(i.culture,"-",1),"_",1),"@",1)) = ?', [$base])
                ->whereRaw("CHAR_LENGTH(TRIM(COALESCE(i.scope_and_content,''))) >= 120")
                ->select(['i.id as id', 'i.title as title', 'i.scope_and_content as scope', 'i.culture as culture'])
                ->orderByDesc(DB::raw('CHAR_LENGTH(i.scope_and_content)'))
                ->limit(self::MAX_TRANSCRIPTIONS);

            if (Schema::hasTable('slug')) {
                $q->leftJoin('slug as sl', 'sl.object_id', '=', 'i.id')
                    ->addSelect('sl.slug as slug');
            }

            $rows = $q->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] transcriptions failed for '.$base.': '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $text = trim((string) ($r->scope ?? ''));
            $out[] = [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'slug' => isset($r->slug) && $r->slug !== null ? (string) $r->slug : null,
                'text' => mb_strlen($text) > 1500 ? mb_substr($text, 0, 1500).'...' : $text,
                'culture' => (string) $r->culture,
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Community glossary (the one table this slice writes to)
    // ---------------------------------------------------------------------

    /**
     * The APPROVED community glossary for one culture (public read). Alphabetical
     * by term. Read-only over the glossary table; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function glossary(string $culture): array
    {
        $base = $this->sanitiseCulture($culture);
        if ($base === null || ! $this->glossaryAvailable()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('culture', $base)
                ->where('moderation_status', 'approved')
                ->orderBy('term')
                ->limit(self::MAX_GLOSSARY)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] glossary read failed for '.$base.': '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorateGlossary'], $rows->all());
    }

    /**
     * Lodge a new community glossary entry. It lands as 'pending' and is NOT shown
     * publicly until an admin approves it. Writes one row to the new table only.
     * Returns the new id, or null on failure (never throws).
     *
     * @param  array<string,mixed>  $data
     */
    public function contribute(array $data, ?int $userId = null): ?int
    {
        if (! $this->glossaryAvailable()) {
            return null;
        }

        $base = $this->sanitiseCulture((string) ($data['culture'] ?? ''));
        $term = trim((string) ($data['term'] ?? ''));
        $meaning = trim((string) ($data['meaning'] ?? ''));
        if ($base === null || $term === '' || $meaning === '') {
            return null;
        }

        $now = now();
        $payload = [
            'culture' => $base,
            'term' => mb_substr($term, 0, 512),
            'meaning' => $meaning,
            'usage_example' => $this->clipText($data['usage_example'] ?? null),
            'source' => $this->clipText($data['source'] ?? null, 512),
            'moderation_status' => 'pending',
            'contributed_by' => $userId,
            'contributor_name' => $this->clipText($data['contributor_name'] ?? null, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            return (int) DB::table(self::TABLE)->insertGetId($payload);
        } catch (\Throwable $e) {
            Log::warning('[language-corpus] contribute failed for '.$base.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Admin moderation queue: glossary entries in one moderation state (default
     * 'pending'), newest first. Read-only over the glossary table; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function moderationQueue(string $status = 'pending'): array
    {
        if (! $this->glossaryAvailable()) {
            return [];
        }

        $status = strtolower(trim($status));
        if (! array_key_exists($status, self::MODERATION_STATUSES)) {
            $status = 'pending';
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('moderation_status', $status)
                ->orderByDesc('id')
                ->limit(self::MAX_GLOSSARY)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] moderationQueue read failed: '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorateGlossary'], $rows->all());
    }

    /**
     * Counts of glossary entries per moderation state (for the admin chips).
     *
     * @return array<string,int>
     */
    public function moderationCounts(): array
    {
        if (! $this->glossaryAvailable()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select('moderation_status', DB::raw('COUNT(*) as c'))
                ->groupBy('moderation_status')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-corpus] moderationCounts failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->moderation_status] = (int) $r->c;
        }

        return $out;
    }

    /**
     * Set the moderation state of one glossary entry. Returns true on success.
     */
    public function moderate(int $id, string $status, ?int $moderatorId = null): bool
    {
        if (! $this->glossaryAvailable() || $id <= 0) {
            return false;
        }

        $status = strtolower(trim($status));
        if (! array_key_exists($status, self::MODERATION_STATUSES)) {
            return false;
        }

        try {
            return DB::table(self::TABLE)->where('id', $id)->update([
                'moderation_status' => $status,
                'moderated_by' => $moderatorId,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]) >= 0;
        } catch (\Throwable $e) {
            Log::warning('[language-corpus] moderate failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Optional gateway translation
    // ---------------------------------------------------------------------

    /**
     * OPTIONAL on-demand machine translation of a snippet, via the existing
     * LlmService abstraction (which routes through the AHG gateway, never a node
     * port). The result is ALWAYS to be presented with self::MT_LABEL: it is
     * machine translation and NOT an official / authoritative translation of a
     * heritage language. Returns null when translation is unavailable / disabled /
     * failed (the caller degrades gracefully). Never throws.
     *
     * @param  string  $text         the snippet to translate
     * @param  string  $targetLang   the target language code (e.g. 'en')
     * @param  string  $sourceLang   the source language code (the heritage culture)
     * @return array{text:string,label:string}|null
     */
    public function translateSnippet(string $text, string $targetLang, string $sourceLang = ''): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $text = mb_substr($text, 0, self::MAX_SNIPPET_CHARS);

        $target = $this->normaliseLang($targetLang);
        if ($target === '') {
            $target = 'en';
        }
        $source = $this->normaliseLang($sourceLang);

        // The translate abstraction lives in the (locked) ahg-ai-services package.
        // We reach it only through the public LlmService::translate() interface and
        // only if that class is actually present, so this surface degrades cleanly
        // when the AI package is not installed. We NEVER call a node port directly.
        $class = '\\AhgAiServices\\Services\\LlmService';
        if (! class_exists($class)) {
            return null;
        }

        try {
            $svc = app($class);
            $out = $svc->translate($text, $target, $source);
            $out = is_string($out) ? trim($out) : '';
            if ($out === '') {
                return null;
            }

            return ['text' => $out, 'label' => self::MT_LABEL];
        } catch (\Throwable $e) {
            // Quota, timeout, disabled feature, anything - soft-fail. The caller
            // shows the original text with a "translation unavailable" note.
            Log::info('[language-corpus] gateway translate unavailable: '.$e->getMessage());

            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Reorder a language list so 'af' (Afrikaans) sits immediately before 'nl'
     * (Dutch) - Afrikaans is first-class and leads its Dutch sibling. Mirrors the
     * convention in the core LanguageCoverageService.
     *
     * @param  array<int,array{code:string}>  $rows
     * @return array<int,array{code:string}>
     */
    protected function afBeforeNl(array $rows): array
    {
        $codes = array_column($rows, 'code');
        if (! in_array('af', $codes, true) || ! in_array('nl', $codes, true)) {
            return $rows;
        }

        $afRow = null;
        $rest = [];
        foreach ($rows as $row) {
            if ($row['code'] === 'af' && $afRow === null) {
                $afRow = $row;

                continue;
            }
            $rest[] = $row;
        }

        $out = [];
        foreach ($rest as $row) {
            if ($row['code'] === 'nl' && $afRow !== null) {
                $out[] = $afRow;
                $afRow = null;
            }
            $out[] = $row;
        }
        if ($afRow !== null) {
            $out[] = $afRow;
        }

        return $out;
    }

    /**
     * Decorate a raw glossary row into a view-friendly array, with display
     * metadata for its moderation state.
     *
     * @return array<string,mixed>
     */
    protected function decorateGlossary(object $row): array
    {
        $status = (string) ($row->moderation_status ?? 'pending');
        $meta = self::MODERATION_STATUSES[$status] ?? ['label' => ucfirst($status), 'level' => 'secondary'];

        return [
            'id' => (int) $row->id,
            'culture' => (string) ($row->culture ?? ''),
            'culture_label' => $this->label((string) ($row->culture ?? '')),
            'term' => (string) ($row->term ?? ''),
            'meaning' => (string) ($row->meaning ?? ''),
            'usage_example' => $row->usage_example !== null ? (string) $row->usage_example : null,
            'source' => $row->source !== null ? (string) $row->source : null,
            'moderation_status' => $status,
            'status_meta' => ['key' => $status] + $meta,
            'contributor_name' => $row->contributor_name !== null ? (string) $row->contributor_name : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
        ];
    }

    /** Short plain-text snippet of a description field. */
    protected function clipSnippet($value): ?string
    {
        $value = trim(strip_tags((string) ($value ?? '')));
        if ($value === '') {
            return null;
        }

        return mb_strlen($value) > 220 ? mb_substr($value, 0, 220).'...' : $value;
    }

    /** Trim a long value, returning null for blanks. Optional max length. */
    protected function clipText($value, int $max = 0): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return ($max > 0 && mb_strlen($value) > $max) ? mb_substr($value, 0, $max) : $value;
    }
}
