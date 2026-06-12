<?php

/**
 * PeerSearchResult (ahg-sharepoint copy) — common result shape returned by
 * every PeerConnector.
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
 *
 * -----------------------------------------------------------------------------
 * INERT SCAFFOLD — issue #1221, step 1 (non-destructive).
 *
 * Byte-equivalent copy of the general federation value object, placed here so
 * the inert SharePoint connector scaffold is self-contained. The live result
 * shape remains the one in ahg-federation. See MIGRATION.md.
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

final class PeerSearchResult
{
    public function __construct(
        /** @var string Stable identifier within the source (e.g. SP item id, AtoM slug). */
        public readonly string $sourceId,

        /** @var string Human-readable title. Required. */
        public readonly string $title,

        /** @var ?string One-paragraph summary or first 280 chars of body/scope. */
        public readonly ?string $snippet,

        /** @var string Click-through URL. SHOULD be absolute. */
        public readonly string $url,

        /** @var string peerTypeKey of the producing connector — used for badge dispatch. */
        public readonly string $peerType,

        /** @var string Human-readable source label (e.g. "Active in SharePoint"). */
        public readonly string $sourceBadge,

        /** @var float Relevance score (0.0-1.0). Connector responsible for normalisation. */
        public readonly float $score,

        /** @var ?string Optional dedupe key — when two results share this, dedupe wins by score. */
        public readonly ?string $dedupeKey = null,

        /** @var ?string ISO-8601 date string for the result's primary date (modified/created/event). */
        public readonly ?string $date = null,

        /** @var array<string,mixed> Arbitrary extra fields the renderer may surface. */
        public readonly array $extras = [],
    ) {
    }

    /**
     * Convenience: serialize to a flat array for the federation_search_cache
     * JSON column and the JSON API response.
     */
    public function toArray(): array
    {
        return [
            'source_id'    => $this->sourceId,
            'title'        => $this->title,
            'snippet'      => $this->snippet,
            'url'          => $this->url,
            'peer_type'    => $this->peerType,
            'source_badge' => $this->sourceBadge,
            'score'        => $this->score,
            'dedupe_key'   => $this->dedupeKey,
            'date'         => $this->date,
            'extras'       => $this->extras,
        ];
    }
}
