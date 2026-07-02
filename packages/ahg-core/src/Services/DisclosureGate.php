<?php

/**
 * DisclosureGate - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgCore\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single, fail-closed enforcement point for "may this information object be
 * disclosed to a public / ungated consumer?". Centralises the four gates that
 * were previously scattered and partially applied across OAI, the REST/GraphQL
 * APIs, CIDOC/SPARQL, Europeana and the portable export (#1389 / #1384):
 *
 *   - Publication status   — status row type_id=158 / status_id=160.
 *   - ICIP / TK protocols  — icip_access_restriction (direct + applies_to_descendants subtree).
 *   - ODRL access policy   — research_rights_policy 'use' prohibition.
 *   - PII redaction (files)— privacy_visual_redaction present ⇒ raw derivative unsafe.
 *
 * Everything routes through here so a surface can never silently ship
 * protocol-restricted, gated, unpublished or redacted content. Fail-closed:
 * on any doubt the record is excluded.
 *
 * The restricted-id set (ICIP ∪ ODRL) is small and bounded, so it is resolved
 * once and memoised for the request; callers keep (or gain) the cheap
 * publication join and simply add `whereNotIn` against this set.
 */
class DisclosureGate
{
    /** Publication-status taxonomy (AtoM parity). */
    public const STATUS_TYPE_PUBLICATION = 158;
    public const STATUS_PUBLISHED = 160;

    /** @var int[]|null Memoised ICIP∪ODRL restricted IO ids for this request. */
    private ?array $restricted = null;

    /** @var int[]|null Memoised ICIP/TK-restricted IO ids (direct + subtree). */
    private ?array $icip = null;

    /** @var int[]|null Memoised ODRL 'use'-prohibited IO ids. */
    private ?array $odrl = null;

    /** @var array<int,bool>|null Memoised IO ids carrying redaction regions. */
    private ?array $redacted = null;

    /**
     * IO ids restricted by ICIP/TK cultural protocol — direct restrictions plus
     * the descendants of any restriction flagged applies_to_descendants. Memoised.
     *
     * @return int[]
     */
    public function icipRestrictedIds(): array
    {
        if ($this->icip !== null) {
            return $this->icip;
        }
        $ids = [];
        if (Schema::hasTable('icip_access_restriction')) {
            foreach (DB::table('icip_access_restriction')->pluck('information_object_id') as $id) {
                $ids[(int) $id] = true;
            }
            if (Schema::hasTable('information_object')) {
                $subtree = DB::table('information_object as io')
                    ->join('icip_access_restriction as r', 'r.applies_to_descendants', '=', DB::raw('1'))
                    ->join('information_object as anc', 'anc.id', '=', 'r.information_object_id')
                    ->whereColumn('io.lft', '>=', 'anc.lft')
                    ->whereColumn('io.lft', '<=', 'anc.rgt')
                    ->pluck('io.id');
                foreach ($subtree as $id) {
                    $ids[(int) $id] = true;
                }
            }
        }

        return $this->icip = array_keys($ids);
    }

    /**
     * IO ids carrying an ODRL 'use' prohibition. Records are keyed as
     * 'archival_description' in policies; the legacy alias is accepted too.
     *
     * @return int[]
     */
    public function odrlRestrictedIds(): array
    {
        if ($this->odrl !== null) {
            return $this->odrl;
        }
        $ids = [];
        if (Schema::hasTable('research_rights_policy')) {
            foreach (DB::table('research_rights_policy')
                ->whereIn('target_type', ['archival_description', 'information_object'])
                ->where('action_type', 'use')
                ->where('policy_type', 'prohibition')
                ->pluck('target_id') as $id) {
                $ids[(int) $id] = true;
            }
        }

        return $this->odrl = array_keys($ids);
    }

    /**
     * IO ids that must be excluded from any public disclosure for confidentiality
     * reasons beyond publication status: ICIP/TK restriction unioned with ODRL
     * 'use' prohibitions. Memoised per request.
     *
     * @return int[]
     */
    public function restrictedIds(): array
    {
        if ($this->restricted !== null) {
            return $this->restricted;
        }
        $ids = array_flip($this->icipRestrictedIds());
        foreach ($this->odrlRestrictedIds() as $id) {
            $ids[$id] = true;
        }

        return $this->restricted = array_keys($ids);
    }

    /**
     * Add the confidentiality exclusion to a query builder against the given
     * information-object id column. Does NOT add the publication join — callers
     * keep their existing published INNER join; this layers ICIP/ODRL on top.
     * Use wherePublished() as well if the query has no publication gate yet.
     */
    public function excludeRestricted(Builder $query, string $idColumn = 'id'): Builder
    {
        $restricted = $this->restrictedIds();
        if (! empty($restricted)) {
            $query->whereNotIn($idColumn, $restricted);
        }

        return $query;
    }

    /**
     * Add the publication-status gate (published only) to a query builder.
     * `$ioTable` is the aliased information_object table the id lives on.
     */
    public function wherePublished(Builder $query, string $ioTable = 'information_object'): Builder
    {
        if (! Schema::hasTable('status')) {
            return $query;
        }

        return $query->whereExists(function ($q) use ($ioTable) {
            $q->select(DB::raw(1))->from('status')
                ->whereColumn('status.object_id', $ioTable.'.id')
                ->where('status.type_id', self::STATUS_TYPE_PUBLICATION)
                ->where('status.status_id', self::STATUS_PUBLISHED);
        });
    }

    /**
     * Is this single record publicly disclosable? Published AND not
     * ICIP/ODRL-restricted. Fail-closed (unknown id ⇒ not published ⇒ false).
     */
    public function allows(int $ioId): bool
    {
        if ($ioId <= 1) {
            return false; // synthetic root / invalid
        }
        if (in_array($ioId, $this->restrictedIds(), true)) {
            return false;
        }
        if (! Schema::hasTable('status')) {
            return false;
        }

        return DB::table('status')
            ->where('object_id', $ioId)
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->exists();
    }

    /**
     * Filter a set of IO ids down to the publicly-disclosable ones.
     *
     * @param  int[]  $ioIds
     * @return int[]
     */
    public function filterIds(array $ioIds): array
    {
        $ioIds = array_values(array_unique(array_map('intval', $ioIds)));
        if (empty($ioIds)) {
            return [];
        }

        $restricted = array_flip($this->restrictedIds());
        $ioIds = array_filter($ioIds, fn ($id) => $id > 1 && ! isset($restricted[$id]));
        if (empty($ioIds)) {
            return [];
        }

        if (! Schema::hasTable('status')) {
            return []; // fail-closed: cannot prove published
        }

        $published = array_flip(DB::table('status')
            ->whereIn('object_id', $ioIds)
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->pluck('object_id')->map('intval')->all());

        return array_values(array_filter($ioIds, fn ($id) => isset($published[$id])));
    }

    /**
     * Does this record carry PII visual-redaction regions? If so its raw
     * derivatives must not be served — callers should withhold the object or
     * serve the redacted rendition. Memoised.
     */
    public function hasRedactions(int $ioId): bool
    {
        $this->loadRedacted();

        return isset($this->redacted[$ioId]);
    }

    /**
     * All IO ids carrying PII visual-redaction regions — for query surfaces that
     * withhold digital objects of redacted records. Memoised.
     *
     * @return int[]
     */
    public function redactedIds(): array
    {
        $this->loadRedacted();

        return array_keys($this->redacted);
    }

    private function loadRedacted(): void
    {
        if ($this->redacted !== null) {
            return;
        }
        $this->redacted = [];
        if (Schema::hasTable('privacy_visual_redaction')) {
            $this->redacted = array_fill_keys(
                DB::table('privacy_visual_redaction')->distinct()->pluck('object_id')->map('intval')->all(),
                true
            );
        }
    }
}
