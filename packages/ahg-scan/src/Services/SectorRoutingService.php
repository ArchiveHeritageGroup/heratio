<?php

/**
 * SectorRoutingService — Heratio ahg-scan (P3)
 *
 * Writes sector-specific metadata from a parsed heratioScan sidecar into
 * the appropriate Heratio tables:
 *   archive → information_object (already handled by IngestService)
 *   library → library_item + library_item_creator + library_item_subject + library_copy
 *   gallery → museum_metadata + gallery_artwork + gallery_artist (via creation event) + gallery_valuation
 *   museum  → museum_object + museum_metadata + (optional) spectrum_object_entry + spectrum_acquisition
 *
 * Also handles:
 *   - Authority auto-creation (creators) gated by ingest_session.output_create_authorities
 *   - Spectrum workflow auto-entry gated by ingest_session.spectrum_auto_enter
 *   - Controlled-vocab resolution (lookup-only for v1 — missing terms recorded as warnings)
 *   - DAM augmentation merge into dam_iptc_metadata (sidecar wins over ExifTool)
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SectorRoutingService
{
    /**
     * Creation event type — links an IO to an actor as creator.
     * Confirmed in EsReindexCommand via `->where('type_id', 111)`.
     */
    protected const EVENT_TYPE_CREATION = 111;

    /**
     * description_status_id for reserved/draft authorities (open Q#8).
     */
    protected const STATUS_AUTHORITY_DRAFT = 232;

    /**
     * Route a freshly-ingested IO + DO into sector-specific tables based on
     * the sidecar profile. Safe to call when no sidecar is present — becomes
     * a no-op except for archive sector (which is already fully handled by
     * IngestService::ingestFile).
     *
     * @param array  $parsed  SidecarParser output (see SidecarParser::parse docblock)
     * @return string[]       Non-fatal warning messages for operators
     */
    public function route(int $ioId, int $doId, ?array $parsed, object $session): array
    {
        $warnings = [];
        $sector = $parsed['sector'] ?? $session->sector ?? 'archive';

        // 1. DAM augmentation (all sectors)
        if (!empty($parsed['dam_augmentation']) && is_array($parsed['dam_augmentation'])) {
            $this->mergeDamAugmentation((int) $ioId, $parsed['dam_augmentation']);
        }

        // 2. Sector profile
        if (empty($parsed['sector_profile']['data'])) {
            return $warnings; // no profile → nothing to route
        }
        $profile = $parsed['sector_profile']['data'];

        try {
            switch ($sector) {
                case 'library':
                    $this->routeLibrary($ioId, $profile, $session, $warnings);
                    break;
                case 'gallery':
                    $this->routeGallery($ioId, $doId, $profile, $session, $warnings);
                    break;
                case 'museum':
                    $this->routeMuseum($ioId, $profile, $session, $warnings);
                    break;
                // 'archive' → covered by IngestService; nothing sector-specific to add
            }
        } catch (\Throwable $e) {
            Log::warning("[ahg-scan] sector routing ({$sector}) failed for IO {$ioId}: " . $e->getMessage());
            $warnings[] = "Sector routing failure: " . $e->getMessage();
        }

        return $warnings;
    }

    // ----------------------------------------------------------------
    // Library
    // ----------------------------------------------------------------

    protected function routeLibrary(int $ioId, array $profile, object $session, array &$warnings): void
    {
        $existing = DB::table('library_item')->where('information_object_id', $ioId)->exists();
        if ($existing) {
            return; // scanner never clobbers existing library metadata on re-ingest
        }

        DB::table('library_item')->insert(array_filter([
            'information_object_id' => $ioId,
            'material_type' => $profile['materialType'] ?? 'monograph',
            'subtitle' => $this->flat($profile['subtitle'] ?? null),
            'isbn' => $this->flat($profile['isbn'] ?? null),
            'issn' => $this->flat($profile['issn'] ?? null),
            'edition' => $this->flat($profile['edition'] ?? null),
            'publisher' => $this->flat($profile['publisher'] ?? null),
            'publication_place' => $this->flat($profile['placeOfPublication'] ?? null),
            'publication_date' => $this->flat($profile['yearOfPublication'] ?? null),
            'pagination' => $this->flat($profile['pagination'] ?? null),
            'dimensions' => $this->flat($profile['dimensions'] ?? null),
            'series_title' => $this->flat($profile['seriesTitle'] ?? null),
            'language' => $this->flat($profile['language'] ?? null),
            'call_number' => $this->flat($profile['callNumber'] ?? null),
            'lccn' => $this->flat($profile['lccn'] ?? null),
            'oclc_number' => $this->flat($profile['oclc'] ?? null),
            'doi' => $this->flat($profile['doi'] ?? null),
        ], fn($v) => $v !== null && $v !== ''));

        $libraryItemId = (int) DB::getPdo()->lastInsertId();

        // Creators
        foreach ($this->iterate($profile['creators']['creator'] ?? $profile['creator'] ?? null) as $sortOrder => $c) {
            $name = $this->creatorName($c);
            if (!$name) { continue; }
            DB::table('library_item_creator')->insert([
                'library_item_id' => $libraryItemId,
                'name' => $name,
                'role' => $this->attr($c, 'role') ?: 'author',
                'sort_order' => $sortOrder,
                'authority_uri' => $this->attr($c, 'uri') ?: null,
                'created_at' => now(),
            ]);
            $this->touchLibraryCreator($name);
        }

        // Subjects
        foreach ($this->iterate($profile['subjects']['subject'] ?? $profile['subject'] ?? null) as $s) {
            $heading = $this->flat(is_array($s) ? ($s['value'] ?? '') : $s);
            if (!$heading) { continue; }
            $uri = $this->attr($s, 'uri');
            $vocab = $this->attr($s, 'vocab');
            DB::table('library_item_subject')->insert([
                'library_item_id' => $libraryItemId,
                'heading' => $heading,
                'source' => $vocab,
                'uri' => $uri,
                'lcsh_id' => $vocab === 'lcsh' ? $uri : null,
                'created_at' => now(),
            ]);
            $this->touchLibrarySubject($heading);
            if ($vocab && !$this->vocabKnown($vocab)) {
                $warnings[] = "Unknown vocabulary '{$vocab}' on library subject '{$heading}'";
            }
        }

        // Holdings
        foreach ($this->iterate($profile['holdings']['copy'] ?? $profile['copy'] ?? null) as $copyIdx => $copy) {
            $attrs = is_array($copy) ? ($copy['@attributes'] ?? $copy) : [];
            DB::table('library_copy')->insert(array_filter([
                'library_item_id' => $libraryItemId,
                'copy_number' => $copyIdx + 1,
                'barcode' => $attrs['barcode'] ?? null,
                'shelf_location' => $attrs['location'] ?? null,
                'call_number_suffix' => $attrs['callNumber'] ?? null,
                'status' => $attrs['status'] ?? 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ], fn($v) => $v !== null && $v !== ''));
        }
    }

    protected function touchLibraryCreator(string $name): void
    {
        $id = DB::table('library_creator')->where('name', $name)->value('id');
        if ($id) {
            DB::table('library_creator')->where('id', $id)->update([
                'work_count' => DB::raw('COALESCE(work_count,0)+1'),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('library_creator')->insert([
                'name' => $name, 'work_count' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function touchLibrarySubject(string $name): void
    {
        $id = DB::table('library_subject')->where('name', $name)->value('id');
        if ($id) {
            DB::table('library_subject')->where('id', $id)->update([
                'item_count' => DB::raw('COALESCE(item_count,0)+1'),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('library_subject')->insert([
                'name' => $name, 'item_count' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Gallery
    // ----------------------------------------------------------------

    protected function routeGallery(int $ioId, int $doId, array $profile, object $session, array &$warnings): void
    {
        // 1. Link the digital object into gallery_artwork (minimal bridge row).
        DB::table('gallery_artwork')->insertOrIgnore([
            'digital_object_id' => $doId,
            'object_id' => $ioId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Descriptive metadata → museum_metadata (shared descriptive table
        //    for both gallery + museum sectors, has the fields we need).
        $this->upsertMuseumMetadata($ioId, $profile, $warnings, 'gallery');

        // 3. Artist(s): find-or-create actor + gallery_artist + creation event.
        foreach ($this->iterate($profile['artist'] ?? null) as $artistNode) {
            $name = $this->creatorName($artistNode);
            if (!$name) { continue; }
            $actorId = $this->findOrCreateActor($name, $this->attr($artistNode, 'uri'), (bool) ($session->output_create_authorities ?? 1));
            if (!$actorId) {
                $warnings[] = "Artist '{$name}' not found and auto-create disabled";
                continue;
            }
            $this->upsertGalleryArtist($actorId, $name, $artistNode);
            $this->linkCreator($ioId, $actorId);
        }

        // 4. Valuation (optional).
        $val = $profile['valuation'] ?? null;
        if (is_array($val) && !empty($val['@attributes']['amount'] ?? null)) {
            $a = $val['@attributes'];
            DB::table('gallery_valuation')->insert([
                'object_id' => $ioId,
                'valuation_type' => $a['type'] ?? 'insurance',
                'value_amount' => $a['amount'],
                'currency' => $a['currency'] ?? 'ZAR',
                'valuation_date' => !empty($a['date']) ? $a['date'] : now()->toDateString(),
                'is_current' => 1,
                'created_at' => now(),
            ]);
        }
    }

    protected function upsertGalleryArtist(int $actorId, string $displayName, $artistNode): void
    {
        $existing = DB::table('gallery_artist')->where('actor_id', $actorId)->first();
        $row = [
            'actor_id' => $actorId,
            'display_name' => $displayName,
            'artist_type' => 'individual',
        ];
        if (is_array($artistNode)) {
            $row = array_filter(array_merge($row, [
                'medium_specialty' => $this->child($artistNode, 'mediumSpecialty'),
                'movement_style' => $this->child($artistNode, 'movementStyle'),
                'nationality' => $this->child($artistNode, 'nationality'),
            ]), fn($v) => $v !== null);
        }
        if ($existing) {
            DB::table('gallery_artist')->where('id', $existing->id)->update($row);
        } else {
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table('gallery_artist')->insert($row);
        }
    }

    // ----------------------------------------------------------------
    // Museum
    // ----------------------------------------------------------------

    protected function routeMuseum(int $ioId, array $profile, object $session, array &$warnings): void
    {
        $existing = DB::table('museum_object')->where('object_id', $ioId)->first();
        $objectNumber = $this->flat($profile['objectNumber'] ?? null);
        $accessionNumber = $this->flat($profile['accessionNumber'] ?? null);

        $museumObjectRow = array_filter([
            'object_id' => $ioId,
            'accession_number' => $accessionNumber,
            'identifier' => $this->flat($profile['identifier'] ?? null),
            'object_number' => $objectNumber,
            'title' => $this->flat($profile['title'] ?? null),
        ], fn($v) => $v !== null && $v !== '');

        if ($existing) {
            DB::table('museum_object')->where('id', $existing->id)->update(array_merge($museumObjectRow, ['updated_at' => now()]));
        } else {
            $museumObjectRow['created_at'] = now();
            $museumObjectRow['updated_at'] = now();
            DB::table('museum_object')->insert($museumObjectRow);
        }

        $this->upsertMuseumMetadata($ioId, $profile, $warnings, 'museum');

        // Spectrum workflow — opt-in per session (plan §12 Q#7).
        if ((int) ($session->spectrum_auto_enter ?? 0) === 1) {
            $this->enterSpectrumWorkflow($ioId, $profile, $session);
        }

        // Darwin Core natural-history block: currently stored as JSON in
        // museum_metadata.physical_appearance when present. Full taxonomy
        // resolution is out of P3 scope (plan P7).
        if (!empty($profile['darwinCore'])) {
            DB::table('museum_metadata')->where('object_id', $ioId)->update([
                'physical_appearance' => json_encode($profile['darwinCore'], JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    protected function enterSpectrumWorkflow(int $ioId, array $profile, object $session): void
    {
        $spectrum = $profile['spectrum'] ?? null;
        if (!is_array($spectrum)) { return; }

        $entryNumber = 'scan-' . $ioId;
        $exists = DB::table('spectrum_object_entry')->where('object_id', $ioId)->exists();
        if (!$exists) {
            DB::table('spectrum_object_entry')->insert([
                'object_id' => $ioId,
                'entry_number' => $entryNumber,
                'entry_date' => $this->child($spectrum, 'entryDate') ?: now()->toDateString(),
                'entry_method' => $this->child($spectrum, 'acquisitionMethod') ?: 'scan-ingest',
                'entry_reason' => 'Auto-enter from scanner ingest session ' . $session->id,
                'received_by' => 'heratio-scan',
                'workflow_state' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $accession = $this->child($spectrum, 'acquisitionMethod');
        if ($accession) {
            $exists = DB::table('spectrum_acquisition')->where('object_id', $ioId)->exists();
            if (!$exists) {
                DB::table('spectrum_acquisition')->insert([
                    'object_id' => $ioId,
                    'acquisition_number' => 'scan-' . $ioId,
                    'acquisition_date' => $this->child($spectrum, 'acquisitionDate') ?: now()->toDateString(),
                    'acquisition_method' => $accession,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    // ----------------------------------------------------------------
    // Shared: museum_metadata upsert (used by gallery + museum)
    // ----------------------------------------------------------------

    protected function upsertMuseumMetadata(int $ioId, array $profile, array &$warnings, string $sector): void
    {
        $row = array_filter([
            'object_id' => $ioId,
            'work_type' => $this->childLabel($profile['workType'] ?? $profile['objectType'] ?? null, 80),
            'object_type' => $this->childLabel($profile['objectType'] ?? null, 255),
            'classification' => $this->childLabel($profile['classification'] ?? null, 255),
            'materials' => $this->listChildren($profile['materials']['material'] ?? null),
            'techniques' => $this->listChildren($profile['techniques']['technique'] ?? null),
            'measurements' => $this->measurementsString($profile['measurements']['measurement'] ?? $profile['dimensions']['dimension'] ?? null),
            'dimensions' => $this->flat($profile['dimensions'] ?? null),
            'creation_date_earliest' => $this->normalizeDate($this->attr($profile['creationDate'] ?? null, 'start')),
            'creation_date_latest' => $this->normalizeDate($this->attr($profile['creationDate'] ?? null, 'end')),
            'inscription' => $this->flat($profile['inscription'] ?? null),
            'condition_notes' => $this->child($profile['spectrum'] ?? null, 'conditionNotes'),
            'provenance' => $this->provenanceString($profile['provenance']['entry'] ?? null),
            'style_period' => $this->childLabel($profile['movement'] ?? $profile['periodOrStyle'] ?? null, 255),
            'cultural_context' => $this->childLabel($profile['culturalAffiliation'] ?? null, 255),
            'current_location' => $this->child($profile['spectrum'] ?? null, 'currentLocation')
                ?? $this->flat($profile['physicalLocation'] ?? null),
        ], fn($v) => $v !== null && $v !== '');
        if (empty($row)) { return; }

        $existing = DB::table('museum_metadata')->where('object_id', $ioId)->exists();
        if ($existing) {
            DB::table('museum_metadata')->where('object_id', $ioId)->update($row);
        } else {
            DB::table('museum_metadata')->insert($row);
        }
    }

    // ----------------------------------------------------------------
    // DAM augmentation (all sectors): merge into dam_iptc_metadata
    // ----------------------------------------------------------------

    protected function mergeDamAugmentation(int $ioId, array $dam): void
    {
        $map = [
            'usageRights' => 'rights_usage_terms',
            'colourProfile' => 'color_space',
            'captureDevice' => 'camera_model',
            'captureSoftware' => 'camera_make', // imperfect; no dedicated column
            'captureDate' => 'date_created',
        ];
        $update = [];
        foreach ($map as $sidecarKey => $dbCol) {
            $v = $this->flat($dam[$sidecarKey] ?? null);
            if ($v !== null && $v !== '') {
                if ($dbCol === 'date_created') {
                    $v = $this->normalizeDate($v);
                    if (!$v) { continue; }
                }
                $update[$dbCol] = $v;
            }
        }
        if (empty($update)) { return; }

        $exists = DB::table('dam_iptc_metadata')->where('object_id', $ioId)->exists();
        if ($exists) {
            DB::table('dam_iptc_metadata')->where('object_id', $ioId)->update(array_merge($update, ['updated_at' => now()]));
        } else {
            DB::table('dam_iptc_metadata')->insert(array_merge($update, [
                'object_id' => $ioId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    // ----------------------------------------------------------------
    // Authority auto-create
    // ----------------------------------------------------------------

    /**
     * Find-or-create an actor with the given name. Honours
     * ingest_session.output_create_authorities — if disabled, returns null
     * when not found so the caller can skip/warn.
     */
    protected function findOrCreateActor(string $name, ?string $uri, bool $canCreate): ?int
    {
        // 1. Existing by name
        $existing = DB::table('actor_i18n')
            ->where('authorized_form_of_name', $name)
            ->where('culture', 'en')
            ->value('id');
        if ($existing) { return (int) $existing; }

        if (!$canCreate) {
            return null;
        }

        // 2. Auto-create — actor at 'reserved' status (plan §12 Q#8)
        return DB::transaction(function () use ($name, $uri) {
            $now = now();
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitActor',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('actor')->insert([
                'id' => $objectId,
                'description_status_id' => self::STATUS_AUTHORITY_DRAFT,
                'description_identifier' => $uri,
                'parent_id' => 3, // ROOT actor
                'source_culture' => 'en',
            ]);
            DB::table('actor_i18n')->insert([
                'id' => $objectId,
                'culture' => 'en',
                'authorized_form_of_name' => $name,
            ]);
            // Slug
            $base = Str::slug($name) ?: 'actor-' . $objectId;
            $slug = $base;
            $n = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $n++;
            }
            DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);
            return $objectId;
        });
    }

    protected function linkCreator(int $ioId, int $actorId): void
    {
        // De-dupe: don't create a second creation event for the same pair.
        $exists = DB::table('event')
            ->where('object_id', $ioId)
            ->where('actor_id', $actorId)
            ->where('type_id', self::EVENT_TYPE_CREATION)
            ->exists();
        if ($exists) { return; }

        // Class-table inheritance: event.id must match the parent object.id.
        $now = now();
        $eventObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event')->insert([
            'id' => $eventObjectId,
            'type_id' => self::EVENT_TYPE_CREATION,
            'object_id' => $ioId,
            'actor_id' => $actorId,
            'source_culture' => 'en',
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers — node-walkers over the SidecarParser::nodeToArray output
    // ----------------------------------------------------------------

    protected function flat($v): ?string
    {
        if ($v === null || $v === '') { return null; }
        if (is_array($v)) {
            if (isset($v['value'])) { return (string) $v['value']; }
            if (array_is_list($v)) { return implode('; ', array_map('strval', $v)); }
            return null;
        }
        return (string) $v;
    }

    protected function attr($node, string $attr): ?string
    {
        if (!is_array($node)) { return null; }
        return $node['@attributes'][$attr] ?? null;
    }

    protected function child($node, string $key): ?string
    {
        if (!is_array($node)) { return null; }
        return $this->flat($node[$key] ?? null);
    }

    protected function childLabel($node, int $maxLen): ?string
    {
        $v = $this->flat($node);
        return $v ? mb_substr($v, 0, $maxLen) : null;
    }

    /**
     * Yield each item from either a scalar, associative array, or list of items.
     */
    protected function iterate($raw): iterable
    {
        if ($raw === null) { return; }
        if (is_string($raw) || (is_array($raw) && !array_is_list($raw))) {
            yield 0 => $raw;
            return;
        }
        foreach ((array) $raw as $i => $item) {
            yield $i => $item;
        }
    }

    protected function creatorName($node): ?string
    {
        if (is_string($node)) { return trim($node) ?: null; }
        if (!is_array($node)) { return null; }
        // Text content of the element (if any).
        if (!empty($node['value']) && is_string($node['value'])) {
            return trim($node['value']);
        }
        // Element-children named displayName.
        if (!empty($node['displayName']) && is_string($node['displayName'])) {
            return trim($node['displayName']);
        }
        // Attribute-only form: <artist displayName="..." vocab="ulan"/>
        $attr = $this->attr($node, 'displayName');
        return $attr ? trim($attr) : null;
    }

    protected function listChildren($raw): ?string
    {
        if ($raw === null) { return null; }
        $out = [];
        foreach ($this->iterate($raw) as $item) {
            $v = $this->flat($item);
            if ($v) { $out[] = $v; }
        }
        return $out ? implode('; ', $out) : null;
    }

    protected function measurementsString($raw): ?string
    {
        if ($raw === null) { return null; }
        $out = [];
        foreach ($this->iterate($raw) as $m) {
            $type = $this->attr($m, 'type');
            $value = $this->attr($m, 'value');
            $unit = $this->attr($m, 'unit');
            if ($value !== null) {
                $out[] = trim(($type ? "$type: " : '') . $value . ($unit ? " $unit" : ''));
            }
        }
        return $out ? implode(' × ', $out) : null;
    }

    protected function provenanceString($raw): ?string
    {
        if ($raw === null) { return null; }
        $out = [];
        foreach ($this->iterate($raw) as $entry) {
            $date = $this->attr($entry, 'date') ?: '';
            $owner = $this->attr($entry, 'owner') ?: '';
            $acq = $this->attr($entry, 'acquisition') ?: '';
            $parts = array_filter([$date, $owner, $acq]);
            if ($parts) { $out[] = implode(' — ', $parts); }
        }
        return $out ? implode("\n", $out) : null;
    }

    protected function normalizeDate(?string $raw): ?string
    {
        if (!$raw) { return null; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) { return $raw; }
        if (preg_match('/^(\d{4})$/', $raw)) { return "{$raw}-01-01"; }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    protected function vocabKnown(string $vocab): bool
    {
        return in_array(strtolower($vocab), [
            'aat', 'ulan', 'tgn', 'lcsh', 'lcnaf', 'mesh',
            'iconclass', 'nomenclature', 'itis', 'gbif', 'dwc',
        ], true);
    }
}
