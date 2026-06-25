<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Services;

use AhgCore\Constants\TermId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sample content loader for sector site profiles (heratio#1331 slice 4).
 *
 * Creates a small set of representative, PUBLISHED records per sector so a
 * freshly-provisioned site (`bin/install --sector=X --with-sample`, or the admin
 * "Apply sector profile" UI) isn't empty. Every record is a full CTI
 * information_object (object + information_object + _i18n + slug + published
 * status), plus the sector's defining row where one exists (library_item /
 * museum_metadata) and a bundled sample image as a digital object for the
 * visual sectors (dam / gallery).
 *
 * Idempotent: each record is keyed by slug `sample-<sector>-<n>`; re-running
 * skips records that already exist. Jurisdiction-neutral content only.
 */
class SectorSampleService
{
    /** AtoM level-of-description term ids (taxonomy 34); resolved by name at runtime with these fallbacks. */
    private const LEVEL = ['fonds' => 236, 'collection' => 238, 'file' => 241, 'item' => 242];

    private const SAMPLES = [
        'archive' => [
            ['title' => 'Heritage Collection (Sample)', 'level' => 'fonds', 'identifier' => 'SAMPLE-ARC-001',
                'desc' => 'A sample archival fonds showing multi-level description. Replace or delete once you have loaded your own holdings.'],
            ['title' => 'Correspondence Series (Sample)', 'level' => 'file', 'identifier' => 'SAMPLE-ARC-002', 'parent' => 0,
                'desc' => 'A sample file within the collection, demonstrating the ISAD(G) hierarchy.'],
            ['title' => 'Founding Document (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-ARC-003', 'parent' => 1,
                'desc' => 'A sample item-level description nested under the correspondence series.'],
        ],
        'museum' => [
            ['title' => 'Ceramic Vessel (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-MUS-001',
                'desc' => 'A sample museum object catalogued with CCO-style descriptive metadata.',
                'museum' => ['object_type' => 'Ceramic vessel', 'classification' => 'Decorative arts', 'materials' => 'Earthenware, glaze',
                    'techniques' => 'Wheel-thrown, glazed', 'measurements' => 'H 24 cm', 'dimensions' => '24 x 15 x 15 cm',
                    'style_period' => 'Modern', 'cultural_context' => 'International', 'provenance' => 'Sample provenance record']],
            ['title' => 'Bronze Figurine (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-MUS-002',
                'desc' => 'A second sample museum object.',
                'museum' => ['object_type' => 'Figurine', 'classification' => 'Sculpture', 'materials' => 'Cast bronze',
                    'techniques' => 'Lost-wax casting', 'measurements' => 'H 31 cm', 'dimensions' => '31 x 12 x 10 cm',
                    'style_period' => 'Contemporary', 'cultural_context' => 'International', 'provenance' => 'Sample provenance record']],
            ['title' => 'Woven Textile (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-MUS-003',
                'desc' => 'A third sample museum object.',
                'museum' => ['object_type' => 'Textile', 'classification' => 'Decorative arts', 'materials' => 'Wool, cotton',
                    'techniques' => 'Hand-woven', 'measurements' => '180 x 120 cm', 'dimensions' => '180 x 120 cm',
                    'style_period' => 'Modern', 'cultural_context' => 'International', 'provenance' => 'Sample provenance record']],
        ],
        'gallery' => [
            ['title' => 'Untitled Landscape (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-GAL-001', 'media' => true,
                'desc' => 'A sample artwork with an attached image, demonstrating the gallery view.'],
            ['title' => 'Abstract Composition (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-GAL-002', 'media' => true,
                'desc' => 'A second sample artwork with an attached image.'],
        ],
        'library' => [
            ['title' => 'Introduction to Archival Science (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-LIB-001',
                'desc' => 'A sample library catalogue record.',
                'library' => ['material_type' => 'monograph', 'isbn' => '9780000000018', 'call_number' => '027 SAM',
                    'publisher' => 'Sample Press', 'publication_date' => '2020']],
            ['title' => 'Museum Studies: A Reader (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-LIB-002',
                'desc' => 'A second sample library catalogue record.',
                'library' => ['material_type' => 'monograph', 'isbn' => '9780000000025', 'call_number' => '069 SAM',
                    'publisher' => 'Sample Press', 'publication_date' => '2021']],
            ['title' => 'Digital Preservation Handbook (Sample)', 'level' => 'item', 'identifier' => 'SAMPLE-LIB-003',
                'desc' => 'A third sample library catalogue record.',
                'library' => ['material_type' => 'monograph', 'isbn' => '9780000000032', 'call_number' => '025.8 SAM',
                    'publisher' => 'Sample Press', 'publication_date' => '2022']],
        ],
        'dam' => [
            ['title' => 'Sample Image Asset', 'level' => 'item', 'identifier' => 'SAMPLE-DAM-001', 'media' => true,
                'desc' => 'A sample digital asset with a thumbnail, demonstrating the DAM grid.'],
            ['title' => 'Sample Photograph', 'level' => 'item', 'identifier' => 'SAMPLE-DAM-002', 'media' => true,
                'desc' => 'A second sample digital asset.'],
        ],
        'research' => [
            ['title' => 'Sample Research Record A', 'level' => 'item', 'identifier' => 'SAMPLE-RES-001',
                'desc' => 'A sample described record for the research portal to reference.'],
            ['title' => 'Sample Research Record B', 'level' => 'item', 'identifier' => 'SAMPLE-RES-002',
                'desc' => 'A second sample described record.'],
        ],
    ];

    /**
     * Load the sample set for $sector. Returns counts.
     *
     * @return array{sector:string,created:int,skipped:int,media:int,note?:string}
     */
    public function load(string $sector): array
    {
        $sector = strtolower(trim($sector));
        $defs = self::SAMPLES[$sector] ?? null;
        if ($defs === null) {
            return ['sector' => $sector, 'created' => 0, 'skipped' => 0, 'media' => 0, 'note' => 'no sample set defined for this sector'];
        }

        $root = $this->rootId();
        $created = $skipped = $media = 0;
        $ids = [];   // def index => io id (for parent refs)

        foreach ($defs as $i => $def) {
            $n = $i + 1;
            $slug = 'sample-'.$sector.'-'.$n;
            $existing = DB::table('slug')->where('slug', $slug)->value('object_id');
            if ($existing) {
                $ids[$i] = (int) $existing;
                $skipped++;
                continue;
            }

            $parentId = isset($def['parent']) ? ($ids[$def['parent']] ?? $root) : $root;
            $ioId = $this->createInformationObject($def, $parentId, $slug);
            $ids[$i] = $ioId;
            $created++;

            if (! empty($def['library']) && Schema::hasTable('library_item')) {
                $this->addLibraryItem($ioId, $def['library']);
            }
            if (! empty($def['museum']) && Schema::hasTable('museum_metadata')) {
                $this->addMuseumMetadata($ioId, $def['museum']);
            }
            if (! empty($def['media']) && $this->attachSampleImage($ioId, $sector, $n)) {
                $media++;
            }
        }

        // Records were inserted with lft/rgt=0; rebuild the nested set from
        // parent_id so the (still-authoritative) lft/rgt are correct, then
        // rebuild the closure to match. Both are best-effort + idempotent.
        if ($created > 0) {
            $this->rebuildHierarchy();
        }

        return ['sector' => $sector, 'created' => $created, 'skipped' => $skipped, 'media' => $media];
    }

    /** Full CTI create of one published information_object; returns its id. */
    private function createInformationObject(array $def, int $parentId, string $slug): int
    {
        $now = now();
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('information_object')->insert([
            'id' => $objectId,
            'source_culture' => 'en',
            'parent_id' => $parentId,
            'level_of_description_id' => $this->levelId($def['level'] ?? 'item'),
            'identifier' => $def['identifier'] ?? null,
            'lft' => 0,
            'rgt' => 0,
        ]);

        DB::table('information_object_i18n')->insert([
            'id' => $objectId,
            'culture' => 'en',
            'title' => $def['title'],
            'scope_and_content' => $def['desc'] ?? null,
        ]);

        DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug, 'serial_number' => 0]);

        DB::table('status')->insert([
            'object_id' => $objectId,
            'type_id' => TermId::STATUS_TYPE_PUBLICATION,
            'status_id' => TermId::PUBLICATION_STATUS_PUBLISHED,
            'serial_number' => 0,
        ]);

        // Dual-write the closure (regression-safe; lft/rgt rebuilt below).
        try {
            app(ClosureMaintenanceService::class)->addNode('information_object', $objectId, $parentId);
        } catch (\Throwable $e) {
            // closure infra not installed yet - non-fatal
        }

        return (int) $objectId;
    }

    private function addLibraryItem(int $ioId, array $d): void
    {
        if (DB::table('library_item')->where('information_object_id', $ioId)->exists()) {
            return;
        }
        DB::table('library_item')->insert([
            'information_object_id' => $ioId,
            'material_type' => ($d['material_type'] ?? '') ?: 'monograph',
            'isbn' => $d['isbn'] ?? null,
            'call_number' => $d['call_number'] ?? null,
            'publisher' => $d['publisher'] ?? null,
            'publication_date' => $d['publication_date'] ?? null,
            'frbr_override_type' => 'none',   // NOT NULL DEFAULT 'none' - never pass null
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addMuseumMetadata(int $ioId, array $d): void
    {
        if (DB::table('museum_metadata')->where('object_id', $ioId)->exists()) {
            return;
        }
        // Only known descriptive columns; object number is the IO identifier (not a column here).
        $allowed = ['object_type', 'classification', 'materials', 'techniques', 'measurements', 'dimensions', 'style_period', 'cultural_context', 'provenance'];
        $row = ['object_id' => $ioId];
        foreach ($allowed as $col) {
            if (isset($d[$col])) {
                $row[$col] = $d[$col];
            }
        }
        DB::table('museum_metadata')->insert($row);
    }

    /** Attach the bundled sample image as a digital object (master + derivatives). */
    private function attachSampleImage(int $ioId, string $sector, int $n): bool
    {
        $src = __DIR__.'/../../resources/sample-content/sample.jpg';
        if (! is_file($src)) {
            return false;
        }
        // DigitalObjectService::upload() MOVES the file, so hand it a throwaway copy.
        $tmpDir = storage_path('app/sample-tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $tmp = $tmpDir.'/sample-'.$sector.'-'.$n.'-'.uniqid().'.jpg';
        if (! @copy($src, $tmp)) {
            return false;
        }
        try {
            $file = new UploadedFile($tmp, 'sample-'.$sector.'-'.$n.'.jpg', 'image/jpeg', null, true);
            DigitalObjectService::upload($ioId, $file);

            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            @unlink($tmp);
        }
    }

    private function levelId(string $name): int
    {
        $name = strtolower($name);
        $fallback = self::LEVEL[$name] ?? self::LEVEL['item'];
        try {
            $id = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', 34)
                ->where('term_i18n.culture', 'en')
                ->whereRaw('LOWER(term_i18n.name) = ?', [$name])
                ->value('term.id');

            return $id ? (int) $id : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /** Root information_object id (parent_id IS NULL), AtoM convention id=1. */
    private function rootId(): int
    {
        $id = DB::table('information_object')->whereNull('parent_id')->orderBy('id')->value('id');

        return $id ? (int) $id : 1;
    }

    private function rebuildHierarchy(): void
    {
        try {
            Artisan::call('openric:rebuild-nested-set', ['--table' => 'information_object']);
        } catch (\Throwable $e) {
            // command not available (ahg-ric absent) - closure still maintained via addNode
        }
        try {
            Artisan::call('ahg:build-closure', ['--table' => 'information_object']);
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
