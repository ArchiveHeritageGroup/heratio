<?php

/**
 * LanguageTranscriptionService - community TRANSCRIPTION / CORRECTION /
 * TRANSLATION contributions for the language-revival corpus (north-star
 * heratio#1208: a culture you can talk to - corpus-grounded history + language
 * revival).
 *
 * This is the next slice on top of the read-only language-revival corpus
 * (LanguageCorpusService) and its community glossary. Where the glossary lets a
 * speaker add a word, this lets a speaker work on the TEXT of an item:
 *
 *   - resolveItem()        - read READ-ONLY context for a PUBLISHED catalogue
 *                            item (title, slug, its in-culture text snapshot) so
 *                            a contributor knows what they are transcribing /
 *                            correcting / translating.
 *   - approvedForItem()    - the APPROVED community contributions on one item
 *                            (public read).
 *   - approvedForCulture() - the APPROVED community contributions across a whole
 *                            culture (for the language page).
 *   - contribute()         - lodge a new contribution; it lands 'pending' and is
 *                            NOT shown publicly until an admin approves it.
 *   - moderationQueue()    - admin queue of contributions in one moderation
 *                            state (mirrors the glossary flow exactly).
 *   - moderationCounts()   - per-state counts for the admin chips.
 *   - moderate()           - approve / reject one contribution.
 *
 * It writes to ONE additive, soft-keyed table only:
 * language_transcription_contribution. It never ALTERs or writes any existing
 * table, and it reads the catalogue strictly READ-ONLY behind Schema::hasTable
 * probes. The "published" gate is the same as the rest of Heratio: a record is
 * published when its row in the status table (type_id = 158) carries
 * status_id = 160; the catalogue root (id = 1) is never surfaced.
 *
 * Framing is deliberately respectful and jurisdiction-neutral: a heritage
 * language is living and belongs to its community of speakers. A contributor is
 * credited only where they consent to be named; otherwise the contribution is
 * shown anonymously.
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

class LanguageTranscriptionService
{
    /** The single table this service writes to. */
    public const TABLE = 'language_transcription_contribution';

    /** Catalogue root id, never surfaced as a real record. */
    protected const ROOT_ID = 1;

    /** Publication-status type_id and the "published" status_id in the status table. */
    protected const PUBLICATION_TYPE_ID = 158;

    protected const PUBLISHED_STATUS_ID = 160;

    /** Hard caps so the public read-only surface stays cheap + bounded. */
    protected const MAX_PER_ITEM = 100;

    protected const MAX_PER_CULTURE = 200;

    protected const MAX_QUEUE = 300;

    /** Longest a contribution body we store (defensive cap; column is MEDIUMTEXT). */
    protected const MAX_BODY_CHARS = 60000;

    /**
     * Canonical contribution kinds. VARCHAR-backed (Dropdown-Manager idiom),
     * never an ENUM, so the set can grow without a schema change.
     *
     * @var array<string,array{label:string,icon:string,blurb:string}>
     */
    public const CONTRIBUTION_TYPES = [
        'transcription' => [
            'label' => 'Transcription',
            'icon'  => 'fa-keyboard',
            'blurb' => 'Type out the text of this item in its own language.',
        ],
        'correction' => [
            'label' => 'Correction',
            'icon'  => 'fa-pen-to-square',
            'blurb' => 'Correct an existing transcription, scan or machine reading.',
        ],
        'translation' => [
            'label' => 'Translation',
            'icon'  => 'fa-language',
            'blurb' => 'Offer a translation of this item into another language.',
        ],
        'note' => [
            'label' => 'Note',
            'icon'  => 'fa-comment-dots',
            'blurb' => 'Add context only a speaker of the language would know.',
        ],
    ];

    /**
     * Canonical moderation states. VARCHAR-backed, never an ENUM. Only 'approved'
     * contributions are shown on the public surface. Mirrors the glossary flow.
     *
     * @var array<string,array{label:string,level:string}>
     */
    public const MODERATION_STATUSES = [
        'pending'  => ['label' => 'Pending review', 'level' => 'secondary'],
        'approved' => ['label' => 'Approved', 'level' => 'success'],
        'rejected' => ['label' => 'Not published', 'level' => 'light'],
    ];

    /**
     * Respectful, jurisdiction-neutral framing surfaced wherever contributions are
     * shown or invited. A heritage language is living and owned by its community.
     */
    public const DISCLAIMER = 'Heritage and endangered languages are living languages that belong to the communities '
        .'who speak them. Transcriptions, corrections and translations contributed here are reviewed before they '
        .'appear and are shared as a community resource. You are credited for your contribution only where you ask '
        .'to be named; otherwise it appears anonymously. The collection holds these contributions on behalf of the '
        .'community, not as a claim of authority over the language.';

    /** Shared culture helpers live on the corpus service - reuse, do not duplicate. */
    protected LanguageCorpusService $corpus;

    public function __construct(?LanguageCorpusService $corpus = null)
    {
        $this->corpus = $corpus ?? new LanguageCorpusService;
    }

    // ---------------------------------------------------------------------
    // Table availability + culture helpers (reuse the corpus service)
    // ---------------------------------------------------------------------

    /**
     * Is the contributions table present? All read/write paths gate on this so a
     * fresh (un-booted) install never fatals.
     */
    public function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            Log::info('[language-transcription] table probe failed: '.$e->getMessage());

            return false;
        }
    }

    /** Lower-cased base subtag, or null when the culture code is unusable. */
    public function sanitiseCulture(?string $culture): ?string
    {
        return $this->corpus->sanitiseCulture($culture);
    }

    /** Human label for a culture code. */
    public function label(string $code): string
    {
        return $this->corpus->label($code);
    }

    /** Normalise a contribution-type code, falling back to 'transcription'. */
    public function sanitiseType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return array_key_exists($type, self::CONTRIBUTION_TYPES) ? $type : 'transcription';
    }

    // ---------------------------------------------------------------------
    // Read-only item context from the catalogue
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
     * Resolve READ-ONLY context for a PUBLISHED catalogue item so a contributor
     * knows what they are working on: its title, slug (for a link back), the
     * culture its description is written in, and a snapshot of its in-culture text
     * (scope_and_content) as the thing being transcribed / corrected / translated.
     *
     * Returns null when the item is missing, unpublished, or the schema is absent
     * (the controller renders a clear "not available" state). Never throws.
     *
     * @return array{id:int,title:string,slug:?string,culture:?string,culture_label:?string,text:?string}|null
     */
    public function resolveItem(int $itemId): ?array
    {
        if ($itemId <= self::ROOT_ID
            || ! Schema::hasTable('information_object_i18n')
            || ! Schema::hasTable('status')) {
            return null;
        }

        try {
            $q = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->where('i.id', $itemId)
                ->select(['i.id as id', 'i.title as title', 'i.culture as culture', 'i.scope_and_content as scope']);

            if (Schema::hasTable('slug')) {
                $q->leftJoin('slug as sl', 'sl.object_id', '=', 'i.id')
                    ->addSelect('sl.slug as slug');
            }

            // An IO can have rows in several cultures; prefer the one with a real
            // title, then the longest text, so the contributor sees the richest
            // in-language description.
            $row = $q->orderByRaw("CASE WHEN TRIM(COALESCE(i.title,'')) <> '' THEN 0 ELSE 1 END")
                ->orderByDesc(DB::raw('CHAR_LENGTH(COALESCE(i.scope_and_content,""))'))
                ->first();
        } catch (\Throwable $e) {
            Log::info('[language-transcription] resolveItem failed for '.$itemId.': '.$e->getMessage());

            return null;
        }

        if ($row === null) {
            return null;
        }

        $culture = isset($row->culture) ? $this->corpus->sanitiseCulture((string) $row->culture) : null;
        $text = trim((string) ($row->scope ?? ''));

        return [
            'id' => (int) $row->id,
            'title' => trim((string) ($row->title ?? '')) !== '' ? (string) $row->title : (__('Untitled record')),
            'slug' => isset($row->slug) && $row->slug !== null ? (string) $row->slug : null,
            'culture' => $culture,
            'culture_label' => $culture !== null ? $this->corpus->label($culture) : null,
            'text' => $text !== '' ? (mb_strlen($text) > 4000 ? mb_substr($text, 0, 4000).'...' : $text) : null,
        ];
    }

    // ---------------------------------------------------------------------
    // Public reads (APPROVED only)
    // ---------------------------------------------------------------------

    /**
     * APPROVED community contributions on one catalogue item, newest first. Public
     * read. Read-only over the new table; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function approvedForItem(int $itemId): array
    {
        if ($itemId <= 0 || ! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('item_ref', $itemId)
                ->where('moderation_status', 'approved')
                ->orderByDesc('id')
                ->limit(self::MAX_PER_ITEM)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-transcription] approvedForItem failed for '.$itemId.': '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorate'], $rows->all());
    }

    /**
     * APPROVED community contributions across a whole culture, newest first.
     * Public read for the language page. Read-only; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function approvedForCulture(string $culture): array
    {
        $base = $this->corpus->sanitiseCulture($culture);
        if ($base === null || ! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where('culture', $base)
                ->where('moderation_status', 'approved')
                ->orderByDesc('id')
                ->limit(self::MAX_PER_CULTURE)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-transcription] approvedForCulture failed for '.$base.': '.$e->getMessage());

            return [];
        }

        return array_map([$this, 'decorate'], $rows->all());
    }

    // ---------------------------------------------------------------------
    // Contribution (the one write path)
    // ---------------------------------------------------------------------

    /**
     * Lodge a new community contribution. It lands as 'pending' and is NOT shown
     * publicly until an admin approves it. Writes one row to the new table only.
     * Returns the new id, or null on failure (never throws).
     *
     * The contribution is only accepted against a PUBLISHED item: an unresolved /
     * unpublished item_ref is rejected so the public surface can never be flooded
     * with orphan rows. The culture is taken from the item where the contributor
     * did not pin one explicitly.
     *
     * @param  array<string,mixed>  $data
     */
    public function contribute(array $data, ?int $userId = null): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $itemId = (int) ($data['item_ref'] ?? 0);
        $item = $itemId > 0 ? $this->resolveItem($itemId) : null;
        if ($item === null) {
            // Only accept contributions against a real, published item.
            return null;
        }

        $type = $this->sanitiseType($data['contribution_type'] ?? null);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        // Prefer an explicitly chosen culture, else the item's own culture, else
        // bail - we never store a contribution with no language context.
        $culture = $this->corpus->sanitiseCulture((string) ($data['culture'] ?? ''))
            ?? ($item['culture'] ?? null);
        if ($culture === null) {
            return null;
        }

        $name = $this->clipText($data['contributor_name'] ?? null, 255);
        $consent = ! empty($data['credit_consent']) && $name !== null;

        $now = now();
        $payload = [
            'item_ref' => $item['id'],
            'culture' => $culture,
            'contribution_type' => $type,
            'body' => mb_substr($body, 0, self::MAX_BODY_CHARS),
            'source' => $this->clipText($data['source'] ?? null, 512),
            'contributed_by' => $userId,
            'contributor_name' => $consent ? $name : null,
            'credit_consent' => $consent ? 1 : 0,
            'moderation_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            return (int) DB::table(self::TABLE)->insertGetId($payload);
        } catch (\Throwable $e) {
            Log::warning('[language-transcription] contribute failed for item '.$item['id'].': '.$e->getMessage());

            return null;
        }
    }

    // ---------------------------------------------------------------------
    // Admin moderation (mirrors the glossary flow)
    // ---------------------------------------------------------------------

    /**
     * Admin moderation queue: contributions in one moderation state (default
     * 'pending'), newest first. Read-only over the new table; never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    public function moderationQueue(string $status = 'pending'): array
    {
        if (! $this->available()) {
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
                ->limit(self::MAX_QUEUE)
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-transcription] moderationQueue read failed: '.$e->getMessage());

            return [];
        }

        // Attach a light item label (read-only) so the moderator has context.
        $out = array_map([$this, 'decorate'], $rows->all());
        foreach ($out as &$row) {
            $row['item'] = $this->itemLabel((int) ($row['item_ref'] ?? 0));
        }
        unset($row);

        return $out;
    }

    /**
     * Counts of contributions per moderation state (for the admin chips).
     *
     * @return array<string,int>
     */
    public function moderationCounts(): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->select('moderation_status', DB::raw('COUNT(*) as c'))
                ->groupBy('moderation_status')
                ->get();
        } catch (\Throwable $e) {
            Log::info('[language-transcription] moderationCounts failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->moderation_status] = (int) $r->c;
        }

        return $out;
    }

    /**
     * Set the moderation state of one contribution. Returns true on success.
     */
    public function moderate(int $id, string $status, ?int $moderatorId = null): bool
    {
        if (! $this->available() || $id <= 0) {
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
            Log::warning('[language-transcription] moderate failed for '.$id.': '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Read-only, cheap label for an item (title + slug) for the moderation table.
     * Returns a soft array even when the item is gone, so the queue never breaks.
     *
     * @return array{id:int,title:string,slug:?string}
     */
    protected function itemLabel(int $itemId): array
    {
        $fallback = ['id' => $itemId, 'title' => '#'.$itemId, 'slug' => null];
        if ($itemId <= 0 || ! Schema::hasTable('information_object_i18n')) {
            return $fallback;
        }

        try {
            $q = DB::table('information_object_i18n as i')
                ->where('i.id', $itemId)
                ->whereRaw("TRIM(COALESCE(i.title,'')) <> ''")
                ->select(['i.id as id', 'i.title as title']);

            if (Schema::hasTable('slug')) {
                $q->leftJoin('slug as sl', 'sl.object_id', '=', 'i.id')
                    ->addSelect('sl.slug as slug');
            }

            $row = $q->orderByRaw("CASE WHEN TRIM(COALESCE(i.title,'')) <> '' THEN 0 ELSE 1 END")
                ->first();
        } catch (\Throwable $e) {
            return $fallback;
        }

        if ($row === null) {
            return $fallback;
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'slug' => isset($row->slug) && $row->slug !== null ? (string) $row->slug : null,
        ];
    }

    /**
     * Decorate a raw contribution row into a view-friendly array, with display
     * metadata for its type and moderation state.
     *
     * @return array<string,mixed>
     */
    protected function decorate(object $row): array
    {
        $status = (string) ($row->moderation_status ?? 'pending');
        $statusMeta = self::MODERATION_STATUSES[$status] ?? ['label' => ucfirst($status), 'level' => 'secondary'];

        $type = (string) ($row->contribution_type ?? 'transcription');
        $typeMeta = self::CONTRIBUTION_TYPES[$type] ?? self::CONTRIBUTION_TYPES['transcription'];

        $consent = ! empty($row->credit_consent);
        $name = $row->contributor_name !== null ? (string) $row->contributor_name : null;

        return [
            'id' => (int) $row->id,
            'item_ref' => $row->item_ref !== null ? (int) $row->item_ref : null,
            'culture' => (string) ($row->culture ?? ''),
            'culture_label' => $this->corpus->label((string) ($row->culture ?? '')),
            'contribution_type' => $type,
            'type_meta' => ['key' => $type] + $typeMeta,
            'body' => (string) ($row->body ?? ''),
            'source' => $row->source !== null ? (string) $row->source : null,
            'moderation_status' => $status,
            'status_meta' => ['key' => $status] + $statusMeta,
            // Only surface a name where the contributor consented to be credited.
            'credit_consent' => $consent,
            'contributor_name' => ($consent && $name !== null && $name !== '') ? $name : null,
            'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
        ];
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
