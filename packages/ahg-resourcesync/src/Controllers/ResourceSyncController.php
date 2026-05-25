<?php

/**
 * ResourceSyncController - ResourceSync 1.1 Source endpoint for Heratio
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

namespace AhgResourceSync\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ResourceSync 1.1 Source endpoint controller.
 *
 * Implements the four document types in the ResourceSync Source role:
 *   - SourceDescription (.well-known/resourcesync)
 *   - CapabilityList    (/resourcesync/capabilitylist.xml)
 *   - ResourceList      (/resourcesync/resourcelist.xml, paged via ?page=)
 *   - ChangeList        (/resourcesync/changelist.xml,    paged via ?page=)
 *
 * Responses are sitemap-formatted XML per the ResourceSync 1.1 specification
 * (NISO Z39.99-2017). Each document carries the ResourceSync `xmlns:rs`
 * extension namespace alongside the sitemap namespace, with rs:md / rs:ln
 * elements supplying the protocol metadata (capability, datetime, change).
 *
 * Publication-status filter mirrors ahg-oai exactly: `information_object` is
 * joined to `object` (for updated_at) and `status` (type_id=158, status_id=160
 * = published) and we exclude the root node by requiring a non-null, non-zero
 * parent_id. Tombstones come from `oai_deleted_record` — the same table the
 * OAI-PMH `php artisan oai:mark-deleted` worker populates — so ResourceSync
 * and OAI report the same deletion set.
 */
class ResourceSyncController extends Controller
{
    /**
     * Sitemap base namespace.
     */
    private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * ResourceSync extension namespace (used for rs:md, rs:ln).
     */
    private const NS_RS = 'http://www.openarchives.org/rs/terms/';

    /**
     * Capability URIs - normative values from the ResourceSync spec.
     */
    private const CAP_DESCRIPTION = 'description';
    private const CAP_CAPABILITYLIST = 'capabilitylist';
    private const CAP_RESOURCELIST = 'resourcelist';
    private const CAP_CHANGELIST = 'changelist';

    /**
     * Default page size when neither config nor settings provide one.
     * Matches the OAI fallback (PAGE_SIZE = 100) shape but lifted to 1000
     * because ResourceSync documents are much lighter per entry (no full
     * metadata body), so a larger page reduces aggregator round trips.
     */
    private const DEFAULT_PAGE_SIZE = 1000;

    /**
     * ChangeList horizon default — last 30 days of updates + tombstones.
     */
    private const DEFAULT_CHANGELIST_DAYS = 30;

    // ------------------------------------------------------------------
    // Public route handlers
    // ------------------------------------------------------------------

    /**
     * SourceDescription document — the well-known discovery file every
     * ResourceSync aggregator looks up first. Points to the CapabilityList.
     */
    public function sourceDescription(Request $request): Response
    {
        $capabilityListUrl = url('/resourcesync/capabilitylist.xml');

        $writer = $this->openWriter();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);
        $writer->writeAttribute('xmlns:rs', self::NS_RS);

        // <rs:ln rel="describedby"> back to the human-readable docs page
        // (optional but recommended by the spec). We point at the OAI docs
        // blade for now because it's the only operator-facing federation
        // landing page; aggregators ignore it.
        $writer->startElement('rs:ln');
        $writer->writeAttribute('rel', 'describedby');
        $writer->writeAttribute('href', url('/oai/docs'));
        $writer->endElement();

        // <rs:md capability="description"> declares the document type.
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_DESCRIPTION);
        $writer->endElement();

        // The one capability list this source exposes.
        $writer->startElement('url');
        $writer->writeElement('loc', $capabilityListUrl);
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_CAPABILITYLIST);
        $writer->endElement();
        $writer->endElement(); // </url>

        $writer->endElement(); // </urlset>

        return $this->finishResponse($writer);
    }

    /**
     * CapabilityList document — describes which ResourceSync capabilities
     * this source offers (ResourceList + ChangeList). Aggregators discover
     * the full inventory + changes from here.
     */
    public function capabilityList(Request $request): Response
    {
        $sourceDescriptionUrl = url('/.well-known/resourcesync');
        $resourceListUrl = url('/resourcesync/resourcelist.xml');
        $changeListUrl = url('/resourcesync/changelist.xml');

        $writer = $this->openWriter();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);
        $writer->writeAttribute('xmlns:rs', self::NS_RS);

        // rel="up" points back to the parent SourceDescription so an
        // aggregator that starts from a CapabilityList URL can still walk
        // up to find sibling sources.
        $writer->startElement('rs:ln');
        $writer->writeAttribute('rel', 'up');
        $writer->writeAttribute('href', $sourceDescriptionUrl);
        $writer->endElement();

        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_CAPABILITYLIST);
        $writer->endElement();

        // ResourceList entry.
        $writer->startElement('url');
        $writer->writeElement('loc', $resourceListUrl);
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_RESOURCELIST);
        $writer->endElement();
        $writer->endElement();

        // ChangeList entry.
        $writer->startElement('url');
        $writer->writeElement('loc', $changeListUrl);
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_CHANGELIST);
        $writer->endElement();
        $writer->endElement();

        $writer->endElement(); // </urlset>

        return $this->finishResponse($writer);
    }

    /**
     * ResourceList document — full inventory of published archival records.
     * Paginated via ?page=N (1-indexed). Each page advertises the next via
     * a sitemap-style <rs:ln rel="next"> link so aggregators can walk the
     * whole chain without guessing.
     *
     * Mirrors the OAI ListRecords filter exactly:
     *   - join object on updated_at
     *   - join status (type_id=158) on status_id=160 (published)
     *   - parent_id NOT NULL AND != 0 (excludes the synthetic root)
     */
    public function resourceList(Request $request): Response
    {
        $pageSize = $this->pageSize();
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $pageSize;

        $base = $this->publishedRecordQuery();
        $total = (clone $base)->count();
        $totalPages = max(1, (int) ceil($total / $pageSize));

        $records = $base->offset($offset)->limit($pageSize)->get();

        $writer = $this->openWriter();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);
        $writer->writeAttribute('xmlns:rs', self::NS_RS);

        // rel="up" points back to the CapabilityList.
        $writer->startElement('rs:ln');
        $writer->writeAttribute('rel', 'up');
        $writer->writeAttribute('href', url('/resourcesync/capabilitylist.xml'));
        $writer->endElement();

        // <rs:md capability="resourcelist" at="..."> — at is the document
        // timestamp; we use the request time because the inventory is
        // generated on-the-fly (no snapshot file).
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_RESOURCELIST);
        $writer->writeAttribute('at', $this->isoNow());
        $writer->endElement();

        // rel="next" / rel="prev" pagination links per sitemap convention
        // (ResourceSync inherits sitemap pagination semantics).
        if ($page < $totalPages) {
            $writer->startElement('rs:ln');
            $writer->writeAttribute('rel', 'next');
            $writer->writeAttribute('href', url('/resourcesync/resourcelist.xml?page='.($page + 1)));
            $writer->endElement();
        }
        if ($page > 1) {
            $writer->startElement('rs:ln');
            $writer->writeAttribute('rel', 'prev');
            $writer->writeAttribute('href', url('/resourcesync/resourcelist.xml?page='.($page - 1)));
            $writer->endElement();
        }

        foreach ($records as $record) {
            $this->writeUrlEntry($writer, $record, null);
        }

        $writer->endElement(); // </urlset>

        return $this->finishResponse($writer);
    }

    /**
     * ChangeList document — records updated or tombstoned within the
     * configured horizon (default 30 days). Each entry carries change=
     * "created"|"updated"|"deleted" so the aggregator can apply the right
     * action.
     *
     * Heratio doesn't track create vs update at the row level (object only
     * has created_at + updated_at, no version-history join here), so we
     * use a simple heuristic: rows whose created_at == updated_at are
     * "created", everything else is "updated". Tombstones are sourced from
     * the same oai_deleted_record table the OAI-PMH endpoint uses, keeping
     * the two endpoints' deletion sets in sync.
     */
    public function changeList(Request $request): Response
    {
        $pageSize = $this->pageSize();
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $pageSize;

        $horizonDays = (int) config('resourcesync.changelist_days', self::DEFAULT_CHANGELIST_DAYS);
        if ($horizonDays <= 0) {
            $horizonDays = self::DEFAULT_CHANGELIST_DAYS;
        }
        $cutoff = now()->subDays($horizonDays);

        // Live record changes within the horizon.
        $changesBase = $this->publishedRecordQuery()
            ->where('o.updated_at', '>=', $cutoff);
        $changesTotal = (clone $changesBase)->count();

        // Tombstones within the horizon (oai_deleted_record.deleted_at).
        $tombstonesTotal = $this->countTombstonesSince($cutoff);

        $grandTotal = $changesTotal + $tombstonesTotal;
        $totalPages = max(1, (int) ceil($grandTotal / $pageSize));

        // Single virtual list: [live changes ordered by updated_at] then
        // [tombstones ordered by deleted_at]. The offset walks across the
        // boundary so a harvester sees changes first, then deletes.
        $liveSlice = [];
        $tombSlice = [];
        if ($offset < $changesTotal) {
            $liveLimit = min($pageSize, $changesTotal - $offset);
            $liveSlice = $changesBase
                ->orderBy('o.updated_at')
                ->orderBy('io.id')
                ->offset($offset)
                ->limit($liveLimit)
                ->get();

            $remaining = $pageSize - $liveSlice->count();
            if ($remaining > 0 && $tombstonesTotal > 0) {
                $tombSlice = $this->getTombstonesSince($cutoff, 0, $remaining);
            }
        } else {
            $tombOffset = $offset - $changesTotal;
            $tombSlice = $this->getTombstonesSince($cutoff, $tombOffset, $pageSize);
        }

        $writer = $this->openWriter();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);
        $writer->writeAttribute('xmlns:rs', self::NS_RS);

        $writer->startElement('rs:ln');
        $writer->writeAttribute('rel', 'up');
        $writer->writeAttribute('href', url('/resourcesync/capabilitylist.xml'));
        $writer->endElement();

        // capability + from/until window so aggregators can confirm the
        // horizon covered. "from" is the cutoff, "until" is now.
        $writer->startElement('rs:md');
        $writer->writeAttribute('capability', self::CAP_CHANGELIST);
        $writer->writeAttribute('from', $this->isoDate($cutoff));
        $writer->writeAttribute('until', $this->isoNow());
        $writer->endElement();

        if ($page < $totalPages) {
            $writer->startElement('rs:ln');
            $writer->writeAttribute('rel', 'next');
            $writer->writeAttribute('href', url('/resourcesync/changelist.xml?page='.($page + 1)));
            $writer->endElement();
        }
        if ($page > 1) {
            $writer->startElement('rs:ln');
            $writer->writeAttribute('rel', 'prev');
            $writer->writeAttribute('href', url('/resourcesync/changelist.xml?page='.($page - 1)));
            $writer->endElement();
        }

        // Live changes — created vs updated heuristic.
        foreach ($liveSlice as $record) {
            $change = ($record->created_at && $record->updated_at && $record->created_at === $record->updated_at)
                ? 'created'
                : 'updated';
            $this->writeUrlEntry($writer, $record, $change);
        }

        // Tombstones — change="deleted". No slug (the IO is gone), so we
        // synthesise a stable loc using the OAI local identifier route.
        foreach ($tombSlice as $tomb) {
            $this->writeTombstoneEntry($writer, $tomb);
        }

        $writer->endElement(); // </urlset>

        return $this->finishResponse($writer);
    }

    // ------------------------------------------------------------------
    // Query helpers
    // ------------------------------------------------------------------

    /**
     * Base query for published archival records. Mirrors the OAI shape
     * (status type_id=158, status_id=160, non-root parent_id) so the two
     * federation surfaces stay in sync.
     */
    private function publishedRecordQuery()
    {
        return DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('st.status_id', '=', 160)
            ->whereNotNull('io.parent_id')
            ->where('io.parent_id', '!=', 0)
            ->select(
                'io.id',
                'io.oai_local_identifier',
                'io.identifier',
                'o.created_at',
                'o.updated_at',
                'slug.slug'
            )
            ->orderBy('io.id');
    }

    /**
     * Page of tombstones whose deleted_at falls within the horizon window.
     * Returns an array of {oai_local_identifier, deleted_at} rows.
     */
    private function getTombstonesSince($cutoff, int $offset, int $limit): array
    {
        if (! Schema::hasTable('oai_deleted_record')) {
            return [];
        }

        return DB::table('oai_deleted_record')
            ->where('deleted_at', '>=', $cutoff)
            ->orderBy('deleted_at')
            ->orderBy('oai_local_identifier')
            ->offset($offset)
            ->limit($limit)
            ->select('oai_local_identifier', 'deleted_at')
            ->get()
            ->all();
    }

    /**
     * Count tombstones within the horizon window.
     */
    private function countTombstonesSince($cutoff): int
    {
        if (! Schema::hasTable('oai_deleted_record')) {
            return 0;
        }

        return (int) DB::table('oai_deleted_record')
            ->where('deleted_at', '>=', $cutoff)
            ->count();
    }

    // ------------------------------------------------------------------
    // XML emission helpers
    // ------------------------------------------------------------------

    /**
     * Write a sitemap <url> entry for a live record. Optional change
     * attribute (used by ChangeList only — null on ResourceList).
     */
    private function writeUrlEntry(\XMLWriter $writer, object $record, ?string $change): void
    {
        $loc = $this->locFor($record);

        $writer->startElement('url');
        $writer->writeElement('loc', $loc);
        if (! empty($record->updated_at)) {
            $writer->writeElement('lastmod', $this->isoDate($record->updated_at));
        }
        $writer->startElement('rs:md');
        if ($change !== null) {
            $writer->writeAttribute('change', $change);
        }
        $writer->writeAttribute('datetime', $this->isoDate($record->updated_at ?? null));
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Write a sitemap <url> entry for a tombstone. change="deleted",
     * loc points at the stable archival-record route by slug if available
     * (gone-but-cited URL) or by oai-local-identifier as fallback.
     */
    private function writeTombstoneEntry(\XMLWriter $writer, object $tomb): void
    {
        // We don't keep the slug after a hard delete, so fall back to a
        // synthetic URL keyed by oai_local_identifier. Aggregators only
        // need a stable identifier here, not a live URL.
        $loc = url('/informationobject/by-oai/'.((int) $tomb->oai_local_identifier));

        $writer->startElement('url');
        $writer->writeElement('loc', $loc);
        $writer->writeElement('lastmod', $this->isoDate($tomb->deleted_at));
        $writer->startElement('rs:md');
        $writer->writeAttribute('change', 'deleted');
        $writer->writeAttribute('datetime', $this->isoDate($tomb->deleted_at));
        $writer->endElement();
        $writer->endElement();
    }

    /**
     * Resolve the canonical archival-record URL for a record. Uses the
     * informationobject.show route when the slug is present; falls back
     * to the /{slug} root path so old slug-only links keep working.
     */
    private function locFor(object $record): string
    {
        if (! empty($record->slug)) {
            // route() returns the configured app URL + slug; equivalent
            // to url('/'.$slug) but goes through the registered route name
            // so any future move of the show page picks it up.
            try {
                return route('informationobject.show', ['slug' => $record->slug]);
            } catch (\Throwable $e) {
                return url('/'.$record->slug);
            }
        }

        // Pre-slug records (rare) — point at an OAI-id route. Same shape
        // as the tombstone fallback.
        return url('/informationobject/by-oai/'.((int) $record->oai_local_identifier));
    }

    /**
     * Resolve the per-document page size. Honours the OAI resumption-
     * token limit when set so operators only have to tune one knob, then
     * falls back to the package config.
     */
    private function pageSize(): int
    {
        // Match OAI's per-request setting lookup so changing the limit in
        // the settings UI flows through to ResourceSync too.
        $oaiLimit = null;
        try {
            $row = DB::table('setting as s')
                ->join('setting_i18n as si', 'si.id', '=', 's.id')
                ->where('s.scope', 'oai')
                ->where('s.name', 'resumption_token_limit')
                ->where('si.culture', 'en')
                ->value('si.value');
            if ($row !== null && $row !== '') {
                $oaiLimit = (int) $row;
            }
        } catch (\Throwable $e) {
            // setting / setting_i18n may not exist in tests / fresh installs.
            $oaiLimit = null;
        }

        if ($oaiLimit !== null && $oaiLimit > 0) {
            return $oaiLimit;
        }

        $cfg = (int) config('resourcesync.page_size', self::DEFAULT_PAGE_SIZE);

        return $cfg > 0 ? $cfg : self::DEFAULT_PAGE_SIZE;
    }

    /**
     * Open a new XMLWriter buffered into memory, with declaration and 2-
     * space indent for human-readable output.
     */
    private function openWriter(): \XMLWriter
    {
        $w = new \XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        return $w;
    }

    /**
     * Finish the current document and return an HTTP response with the
     * correct XML content type. ResourceSync uses application/xml per
     * the spec (sitemap profile).
     */
    private function finishResponse(\XMLWriter $writer): Response
    {
        $writer->endDocument();
        $xml = $writer->outputMemory(true);

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * ISO 8601 UTC timestamp for the current moment.
     */
    private function isoNow(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * ISO 8601 UTC timestamp for an arbitrary input (MySQL datetime,
     * Carbon, or null). Returns the current moment when input is empty.
     */
    private function isoDate($date): string
    {
        if ($date === null || $date === '') {
            return $this->isoNow();
        }
        if ($date instanceof \DateTimeInterface) {
            return gmdate('Y-m-d\TH:i:s\Z', $date->getTimestamp());
        }
        $ts = strtotime((string) $date);
        if ($ts === false) {
            return $this->isoNow();
        }

        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }
}
