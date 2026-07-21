<?php

/**
 * Seed the controlled vocabularies the archaeology module depends on.
 *
 * NMMZ stored period, material and dating method as free-text varchars, which
 * cannot be browsed, faceted or reconciled - the same weakness eHive shows,
 * where "Found at" is a keyword rather than a relationship. This module binds
 * those fields to taxonomy terms instead, so the vocabularies must exist.
 *
 * Terms rather than a private lookup table, because a term is the thing the
 * rest of Heratio already knows how to enrich:
 *   - term_protocol attaches ICIP/TK Labels to a term, so "Human remains",
 *     "Burial site" and "Rock art site" can be gated by TermProtocolGate across
 *     display, OAI, RiC, export and portable bundles
 *   - getty_vocabulary_link reconciles a term to AAT
 *   - term_i18n translates it into the SA languages
 *
 * Only rows are written. No table structure is created or altered on any base
 * AtoM table; cataloguers can add, edit and delete these terms afterwards like
 * any other vocabulary.
 *
 * Idempotent: re-running inserts only missing terms and never edits an existing
 * one, so local additions and relabelling survive.
 *
 * Term sets are pitched at southern African archaeology because that is the
 * first deployment (Wits), but nothing in the schema is country-specific.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArchaeology\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchaeologySeedVocabulariesCommand extends Command
{
    protected $signature = 'ahg:archaeology-seed-vocabularies
                            {--dry-run : Report what would be created without writing}
                            {--repair-tree : Only rebuild nested-set and closure entries for existing terms}';

    protected $description = 'Seed archaeology vocabularies (period, material, method, object type) as taxonomy terms';

    /** AtoM root ids: taxonomies hang off 30, top-level terms off 110. */
    private const TAXONOMY_ROOT_ID = 30;
    private const TERM_ROOT_ID = 110;

    /**
     * Vocabulary name => terms.
     *
     * Southern African chronology follows the conventional Stone Age / Iron Age
     * framework. The "Undetermined" entries are deliberate: an unknown value
     * must be recordable, otherwise cataloguers guess and the data degrades
     * silently.
     */
    private const VOCABULARIES = [
        'Archaeological Period' => [
            'Earlier Stone Age', 'Middle Stone Age', 'Later Stone Age',
            'Early Iron Age', 'Middle Iron Age', 'Late Iron Age',
            'Historical Period', 'Colonial Period', 'Modern',
            'Multi-period', 'Undetermined',
        ],

        'Archaeological Site Type' => [
            'Open-air site', 'Rock shelter', 'Cave', 'Shell midden', 'Kraal',
            'Settlement', 'Stone-walled settlement', 'Burial site', 'Rock art site',
            'Quarry', 'Mine', 'Smelting site', 'Industrial site', 'Battlefield',
            'Shipwreck', 'Historical building', 'Artefact scatter', 'Undetermined',
        ],

        // How the material came out of the ground. Provenance reliability
        // depends on this far more than on who catalogued it.
        'Recovery Method' => [
            'Controlled excavation', 'Test excavation', 'Surface collection',
            'Systematic survey', 'Sieving', 'Auger sampling', 'Coring',
            'Salvage or rescue', 'Chance find', 'Donation', 'Purchase',
            'Confiscation', 'Unknown',
        ],

        'Dating Method' => [
            'Radiocarbon (C14)', 'Calibrated radiocarbon', 'Optically stimulated luminescence (OSL)',
            'Thermoluminescence (TL)', 'Uranium-series', 'Electron spin resonance (ESR)',
            'Palaeomagnetic', 'Dendrochronology', 'Typological', 'Stratigraphic',
            'Associated finds', 'Historical record', 'Undated',
        ],

        // Deep enough for beads, glass, ceramics and lithics. The existing
        // Material (CCO) taxonomy has 11 terms, which is a gallery-scale list
        // rather than an archaeological one.
        'Archaeological Material' => [
            'Ceramic', 'Earthenware', 'Stoneware', 'Porcelain', 'Daga or clay',
            'Glass', 'Glass bead', 'Faience',
            'Stone', 'Quartz', 'Quartzite', 'Chert', 'Hornfels', 'Dolerite',
            'Silcrete', 'Chalcedony', 'Agate', 'Sandstone', 'Soapstone',
            'Bone', 'Ivory', 'Horn', 'Antler', 'Tooth',
            'Shell', 'Ostrich eggshell', 'Marine shell', 'Freshwater shell',
            'Iron', 'Copper', 'Bronze', 'Brass', 'Gold', 'Silver', 'Lead', 'Tin',
            'Slag', 'Ochre', 'Charcoal', 'Wood', 'Fibre', 'Textile', 'Leather',
            'Resin', 'Composite', 'Undetermined',
        ],

        'Archaeological Object Type' => [
            'Bead', 'Pendant', 'Bangle', 'Ring', 'Ornament',
            'Vessel', 'Sherd', 'Rim sherd', 'Body sherd', 'Base sherd', 'Decorated sherd',
            'Flake', 'Blade', 'Bladelet', 'Core', 'Scraper', 'Point', 'Adze',
            'Hammerstone', 'Grindstone', 'Upper grindstone', 'Lower grindstone',
            'Hand axe', 'Cleaver', 'Debitage', 'Manuport',
            'Spindle whorl', 'Loom weight', 'Crucible', 'Tuyere', 'Mould',
            'Figurine', 'Pipe', 'Button', 'Buckle', 'Nail', 'Coin', 'Bottle',
            'Faunal remains', 'Botanical remains', 'Human remains',
            'Sample', 'Unidentified',
        ],

        'Archaeological Condition' => [
            'Complete', 'Near complete', 'Fragmentary', 'Heavily fragmented',
            'Eroded', 'Weathered', 'Burnt', 'Corroded', 'Conserved', 'Unassessed',
        ],

        'Site Protection Status' => [
            'National heritage site', 'Provincial heritage site', 'Protected area',
            'Formally recorded', 'Proposed for protection', 'Unprotected',
            'Destroyed', 'Unassessed',
        ],
    ];

    /**
     * Terms that should be reviewed for a community protocol once seeded.
     * Flagged in the output rather than auto-restricted: assigning a TK Label
     * is a community decision, not something a seeder gets to make.
     */
    private const PROTOCOL_REVIEW = [
        'Human remains', 'Burial site', 'Rock art site',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (['object', 'taxonomy', 'taxonomy_i18n', 'term', 'term_i18n'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} is missing.");

                return self::FAILURE;
            }
        }

        if ($this->option('repair-tree')) {
            $fixed = $this->repairTree();
            $this->info($fixed > 0
                ? "Placed {$fixed} terms in the nested set and closure tree."
                : 'Nothing to repair - every term already has lft/rgt and a closure entry.');

            return self::SUCCESS;
        }

        $culture = 'en';
        $newTaxonomies = 0;
        $newTerms = 0;

        foreach (self::VOCABULARIES as $name => $terms) {
            $taxonomyId = $this->findTaxonomy($name, $culture);

            if ($taxonomyId === null) {
                if ($dryRun) {
                    $this->line(sprintf('  %-34s would create + %d terms', $name, count($terms)));
                    $newTaxonomies++;
                    $newTerms += count($terms);
                    continue;
                }
                $taxonomyId = $this->createTaxonomy($name, $culture);
                $newTaxonomies++;
            }

            $existing = array_flip(
                DB::table('term')
                    ->join('term_i18n', 'term_i18n.id', '=', 'term.id')
                    ->where('term.taxonomy_id', $taxonomyId)
                    ->where('term_i18n.culture', $culture)
                    ->pluck('term_i18n.name')
                    ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                    ->all()
            );

            $added = 0;
            foreach ($terms as $termName) {
                if (isset($existing[mb_strtolower($termName)])) {
                    continue;
                }
                if (! $dryRun) {
                    $this->createTerm($taxonomyId, $termName, $culture);
                }
                $added++;
            }

            $newTerms += $added;
            if ($added > 0) {
                $this->line(sprintf('  %-34s +%d terms', $name, $added));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d taxonomies and %d terms.',
            $dryRun ? 'Would create' : 'Created',
            $newTaxonomies,
            $newTerms
        ));

        if ($dryRun) {
            $this->comment('Dry run - nothing written.');

            return self::SUCCESS;
        }

        // Terms are inserted without lft/rgt above and placed in one batch here,
        // rather than shifting the whole tree once per term.
        $placed = $this->repairTree();
        if ($placed > 0) {
            $this->line("  placed {$placed} terms in the nested set and closure tree");
        }

        $this->newLine();
        $this->warn('Review these terms for a community protocol (term_protocol / TK Labels):');
        foreach (self::PROTOCOL_REVIEW as $term) {
            $this->line('  - '.$term);
        }
        $this->comment('Assigning a label is a community decision, so none was applied automatically.');

        return self::SUCCESS;
    }

    /**
     * Place this module's terms in the nested set and the closure tree.
     *
     * A term needs both: `term.lft` still drives ordering in places
     * (DamService, genre access points), and #1333 added `term_closure`
     * alongside it as a dual-write. A term with neither sorts unpredictably and
     * is invisible to closure-based ancestor queries.
     *
     * Terms are appended as children of the term root in one contiguous block,
     * so the tree is shifted once rather than once per term.
     *
     * @return int number of terms placed
     */
    private function repairTree(): int
    {
        $culture = 'en';

        $ids = DB::table('term')
            ->join('taxonomy_i18n', function ($j) use ($culture) {
                $j->on('taxonomy_i18n.id', '=', 'term.taxonomy_id')
                  ->where('taxonomy_i18n.culture', '=', $culture);
            })
            ->whereIn('taxonomy_i18n.name', array_keys(self::VOCABULARIES))
            ->whereNull('term.lft')
            ->orderBy('term.id')
            ->pluck('term.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($ids)) {
            return 0;
        }

        $count = count($ids);

        DB::transaction(function () use ($ids, $count) {
            $root = DB::table('term')->where('id', self::TERM_ROOT_ID)->first(['lft', 'rgt']);
            if (! $root || $root->rgt === null) {
                // No usable root nested set; leave lft/rgt alone rather than
                // invent values that would corrupt the tree.
                return;
            }

            $insertAt = (int) $root->rgt;
            $width = 2 * $count;

            // Open a gap at the root's right edge for the whole block.
            DB::table('term')->where('rgt', '>=', $insertAt)->increment('rgt', $width);
            DB::table('term')->where('lft', '>', $insertAt)->increment('lft', $width);

            foreach ($ids as $i => $id) {
                $lft = $insertAt + (2 * $i);
                DB::table('term')->where('id', $id)->update([
                    'lft' => $lft,
                    'rgt' => $lft + 1,
                ]);
            }
        });

        // Closure is maintained by the shared service so this module does not
        // encode the table's shape. addNode() is idempotent.
        if (class_exists(\AhgCore\Services\ClosureMaintenanceService::class)) {
            $closure = app(\AhgCore\Services\ClosureMaintenanceService::class);
            foreach ($ids as $id) {
                $closure->addNode('term', $id, self::TERM_ROOT_ID);
            }
        }

        return $count;
    }

    private function findTaxonomy(string $name, string $culture): ?int
    {
        $id = DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy_i18n.id', '=', 'taxonomy.id')
            ->where('taxonomy_i18n.culture', $culture)
            ->whereRaw('LOWER(taxonomy_i18n.name) = ?', [mb_strtolower($name)])
            ->value('taxonomy.id');

        return $id ? (int) $id : null;
    }

    /**
     * Taxonomies and terms are `object` subclasses, the same as every other
     * AtoM-derived entity in Heratio.
     */
    private function createTaxonomy(string $name, string $culture): int
    {
        return DB::transaction(function () use ($name, $culture) {
            $id = DB::table('object')->insertGetId([
                'class_name'    => 'QubitTaxonomy',
                'created_at'    => now(),
                'updated_at'    => now(),
                'serial_number' => 0,
            ]);

            DB::table('taxonomy')->insert([
                'id'             => $id,
                'usage'          => null,
                'parent_id'      => self::TAXONOMY_ROOT_ID,
                'source_culture' => $culture,
            ]);

            DB::table('taxonomy_i18n')->insert([
                'id'      => $id,
                'culture' => $culture,
                'name'    => $name,
            ]);

            return $id;
        });
    }

    private function createTerm(int $taxonomyId, string $name, string $culture): int
    {
        return DB::transaction(function () use ($taxonomyId, $name, $culture) {
            $id = DB::table('object')->insertGetId([
                'class_name'    => 'QubitTerm',
                'created_at'    => now(),
                'updated_at'    => now(),
                'serial_number' => 0,
            ]);

            DB::table('term')->insert([
                'id'             => $id,
                'taxonomy_id'    => $taxonomyId,
                'code'           => null,
                'parent_id'      => self::TERM_ROOT_ID,
                'source_culture' => $culture,
            ]);

            DB::table('term_i18n')->insert([
                'id'      => $id,
                'culture' => $culture,
                'name'    => $name,
            ]);

            return $id;
        });
    }
}
