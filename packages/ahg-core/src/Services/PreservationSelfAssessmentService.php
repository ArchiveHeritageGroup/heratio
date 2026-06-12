<?php

/**
 * PreservationSelfAssessmentService - Heratio ahg-core
 *
 * heratio#1244 (maturity self-assessment slice): the HUMAN-ENTERED, organisational
 * digital-preservation maturity self-assessment. It is the deliberate counterpart to
 * PreservationMaturityService, which COMPUTES a maturity reading from concrete records
 * in the running instance. This service stores what the institution says about ITSELF
 * when it rates its own practice, section by section, against a recognised
 * international maturity model:
 *
 *   - NDSA Levels of Digital Preservation - five functional areas (storage, integrity,
 *     control, metadata, content), rated on the shared 0..4 scale.
 *   - DPC Rapid Assessment Model (DPC RAM) - eleven sections (organisational +
 *     service-capability), each rated 0 (Minimal awareness) .. 4 (Optimised).
 *
 * Both models are widely used and jurisdiction-neutral - they make NO country
 * assumptions and are seeded here as data, not hardcoded into the views.
 *
 * Scope of writes: ONLY the two NEW side tables preservation_self_assessment and
 * preservation_self_assessment_rating. No AtoM/Qubit base table is ever written, no
 * ALTER is run, and no AI call is made. The enumerated values (model + level labels)
 * come exclusively from the Dropdown Manager (ahg_dropdown groups assessment_model +
 * maturity_level) - never an ENUM, never a hardcoded option list.
 *
 * Resilient by design: every public method is schema-guarded and wrapped so a missing
 * table (fresh / mid-migration install) makes reads return empty and writes safe
 * no-ops rather than throwing. The surrounding admin UI degrades to a calm empty state
 * and never 500s.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreservationSelfAssessmentService
{
    /** The two new tables this service owns. */
    public const RUN_TABLE = 'preservation_self_assessment';

    public const RATING_TABLE = 'preservation_self_assessment_rating';

    /** Dropdown Manager taxonomies. */
    public const MODEL_TAXONOMY = 'assessment_model';

    public const LEVEL_TAXONOMY = 'maturity_level';

    /** Lowest / highest rateable maturity level (shared 0..4 scale). */
    public const MIN_LEVEL = 0;

    public const MAX_LEVEL = 4;

    /** Fallback model when the dropdown taxonomy is unreadable. */
    private const FALLBACK_MODEL = 'dpc_ram';

    /**
     * Built-in fallback level labels (used only when the maturity_level dropdown
     * group is unreadable). The DPC RAM wording, which also reads correctly for the
     * NDSA "Level 1..4" framing.
     */
    private const FALLBACK_LEVEL_LABELS = [
        0 => 'Minimal awareness',
        1 => 'Awareness',
        2 => 'Basic',
        3 => 'Managed',
        4 => 'Optimised',
    ];

    // =====================================================================
    // Model definitions (the recognised international maturity grids, as data)
    // =====================================================================

    /**
     * The section catalogue for every supported model. Each section carries a
     * stable key, a name, a short description, and a per-level descriptor map
     * (0..4) so the rating form can show the institution what each level means.
     *
     * NDSA Levels of Digital Preservation v2.0 - five functional areas. The shared
     * 0..4 scale maps "Not yet" (0) plus NDSA Levels 1..4.
     *
     * DPC Rapid Assessment Model (DPC RAM) - eleven sections, three organisational
     * + eight service-capability, each on the 0..4 maturity scale.
     *
     * @return array<string, array{name:string, sections:array<int, array{key:string,name:string,description:string,levels:array<int,string>}>}>
     */
    public function models(): array
    {
        return [
            'ndsa' => [
                'name' => 'NDSA Levels of Digital Preservation',
                'note' => 'The NDSA Levels of Digital Preservation (v2.0) describe digital-preservation practice across five functional areas, each progressing through Level 1 (Know your content) to Level 4 (Repair your content). They are a widely used, jurisdiction-neutral self-assessment grid. Here the institution self-rates each area on a 0 (not yet) to 4 scale.',
                'sections' => [
                    [
                        'key' => 'ndsa_storage',
                        'name' => 'Storage',
                        'description' => 'Multiple copies of content held with geographic and provider or system diversity.',
                        'levels' => [
                            0 => 'No additional preservation copies beyond the primary store.',
                            1 => 'At least two complete copies of content are held.',
                            2 => 'Three or more copies, with copies in geographically separated locations.',
                            3 => 'Copies span different storage providers or system types (provider diversity).',
                            4 => 'Replication is managed and verified on an ongoing schedule.',
                        ],
                    ],
                    [
                        'key' => 'ndsa_integrity',
                        'name' => 'Integrity (fixity)',
                        'description' => 'Checksums recorded, fixity verified on a cadence, and content protected from change.',
                        'levels' => [
                            0 => 'No fixity information (checksums) recorded.',
                            1 => 'Fixity (e.g. SHA-256) recorded on ingest for content.',
                            2 => 'Fixity verified on a schedule and content write-protected.',
                            3 => 'Corruption is detected and fixity activity is logged / audited.',
                            4 => 'Corrupted content can be repaired or replaced from a known-good copy.',
                        ],
                    ],
                    [
                        'key' => 'ndsa_control',
                        'name' => 'Control (security)',
                        'description' => 'Who can read or change content is restricted, and actions are logged.',
                        'levels' => [
                            0 => 'No defined controls over who may read or change content.',
                            1 => 'Authority to read and change content is identified.',
                            2 => 'Access to content is restricted (access controls in place).',
                            3 => 'Logs are maintained of who did what (audit trail).',
                            4 => 'Audit logs are periodically reviewed and acted upon.',
                        ],
                    ],
                    [
                        'key' => 'ndsa_metadata',
                        'name' => 'Metadata',
                        'description' => 'Descriptive, administrative, technical and preservation (PREMIS) metadata.',
                        'levels' => [
                            0 => 'No inventory or descriptive metadata held.',
                            1 => 'Minimal inventory metadata captured.',
                            2 => 'Administrative, transformative and preservation metadata stored.',
                            3 => 'Standard technical and descriptive metadata stored.',
                            4 => 'Standard preservation (PREMIS) metadata stored.',
                        ],
                    ],
                    [
                        'key' => 'ndsa_content',
                        'name' => 'Content (file formats)',
                        'description' => 'Format identification (PRONOM/PUID), diversity and obsolescence monitoring.',
                        'levels' => [
                            0 => 'File formats held are not known.',
                            1 => 'The file formats held are known (basic identification).',
                            2 => 'Formats identified to a standard (PRONOM/PUID); format range limited.',
                            3 => 'Format obsolescence is monitored against a risk registry.',
                            4 => 'Action is taken (migration/normalisation) to keep content renderable.',
                        ],
                    ],
                ],
            ],
            'dpc_ram' => [
                'name' => 'DPC Rapid Assessment Model (DPC RAM)',
                'note' => 'The DPC Rapid Assessment Model (DPC RAM), published by the Digital Preservation Coalition, lets an organisation rate its digital-preservation capability across eleven sections - three organisational and eight service-capability - on a five-point scale from 0 (Minimal awareness) to 4 (Optimised). It is an internationally used, jurisdiction-neutral model.',
                'sections' => [
                    [
                        'key' => 'dpc_organisational_viability',
                        'name' => 'Organisational viability',
                        'description' => 'Organisational commitment, mandate and sustainability for digital preservation.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_policy_strategy',
                        'name' => 'Policy and strategy',
                        'description' => 'Digital-preservation policy, strategy and supporting documentation.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_legal_basis',
                        'name' => 'Legal basis',
                        'description' => 'Rights, permissions and the legal basis to preserve and provide access.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_it_capability',
                        'name' => 'IT capability',
                        'description' => 'Technical infrastructure and IT skills to support digital preservation.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_continuous_improvement',
                        'name' => 'Continuous improvement',
                        'description' => 'Monitoring, community engagement and ongoing improvement of practice.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_acquisition_transfer',
                        'name' => 'Acquisition, transfer and ingest',
                        'description' => 'Acquiring content and transferring it into the preservation environment.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_bitstream_preservation',
                        'name' => 'Bitstream preservation',
                        'description' => 'Storage, replication, integrity and security of the stored bitstreams.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_content_preservation',
                        'name' => 'Content preservation',
                        'description' => 'Keeping content usable over time (format management, migration, emulation).',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_metadata_management',
                        'name' => 'Metadata management',
                        'description' => 'Capturing and managing the metadata needed to preserve and find content.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_discovery_access',
                        'name' => 'Discovery and access',
                        'description' => 'Enabling content to be found and accessed appropriately over time.',
                        'levels' => $this->ramLevels(),
                    ],
                    [
                        'key' => 'dpc_metadata_use_reuse',
                        'name' => 'Reuse',
                        'description' => 'Supporting appropriate reuse of preserved content and its metadata.',
                        'levels' => $this->ramLevels(),
                    ],
                ],
            ],
        ];
    }

    /** The generic DPC RAM 0..4 maturity scale descriptors (shared by every section). */
    private function ramLevels(): array
    {
        return [
            0 => 'Minimal awareness - the capability is not yet addressed.',
            1 => 'Awareness - the need is recognised and being explored.',
            2 => 'Basic - basic, partial measures are in place.',
            3 => 'Managed - the capability is managed and consistently applied.',
            4 => 'Optimised - the capability is optimised and continually improved.',
        ];
    }

    /** Is a model code one we know how to render section descriptors for? */
    public function isKnownModel(string $model): bool
    {
        return array_key_exists($model, $this->models());
    }

    /**
     * The section catalogue for one model, or an empty array for an unknown model.
     *
     * @return array<int, array{key:string,name:string,description:string,levels:array<int,string>}>
     */
    public function sectionsFor(string $model): array
    {
        $models = $this->models();

        return $models[$model]['sections'] ?? [];
    }

    /** A model's display name + framing note. */
    public function modelMeta(string $model): array
    {
        $models = $this->models();

        return [
            'name' => $models[$model]['name'] ?? $model,
            'note' => $models[$model]['note'] ?? '',
        ];
    }

    // =====================================================================
    // Dropdown-backed enumerations
    // =====================================================================

    /**
     * Selectable assessment models, read live from the Dropdown Manager group
     * assessment_model, filtered to those we actually have a section catalogue for.
     * Falls back to the built-in model list when the dropdown table is unreadable so
     * the start form always has at least the known models.
     *
     * @return array<int, array{code:string,label:string}>
     */
    public function modelOptions(): array
    {
        $known = $this->models();
        $out = [];

        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', self::MODEL_TAXONOMY)
                    ->where('is_active', 1)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['code', 'label']);
                foreach ($rows as $r) {
                    $code = (string) $r->code;
                    if (! isset($known[$code])) {
                        continue; // only offer models we can render
                    }
                    $out[] = ['code' => $code, 'label' => (string) $r->label];
                }
            }
        } catch (\Throwable $e) {
            // fall through to built-in below
        }

        if (! empty($out)) {
            return $out;
        }

        // Built-in fallback (dropdown table missing / not seeded yet).
        foreach ($known as $code => $def) {
            $out[] = ['code' => $code, 'label' => (string) ($def['name'] ?? $code)];
        }

        return $out;
    }

    /**
     * The 0..4 maturity-level labels, read live from the Dropdown Manager group
     * maturity_level (keyed by the numeric level). Falls back to the built-in labels
     * when the dropdown group is unreadable.
     *
     * @return array<int, string>
     */
    public function levelLabels(): array
    {
        try {
            if (Schema::hasTable('ahg_dropdown')) {
                $rows = DB::table('ahg_dropdown')
                    ->where('taxonomy', self::LEVEL_TAXONOMY)
                    ->where('is_active', 1)
                    ->get(['code', 'label']);
                $labels = [];
                foreach ($rows as $r) {
                    if (! is_numeric($r->code)) {
                        continue;
                    }
                    $lvl = (int) $r->code;
                    if ($lvl >= self::MIN_LEVEL && $lvl <= self::MAX_LEVEL) {
                        $labels[$lvl] = (string) $r->label;
                    }
                }
                if (! empty($labels)) {
                    // backfill any gaps from the built-in labels
                    return $labels + self::FALLBACK_LEVEL_LABELS;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return self::FALLBACK_LEVEL_LABELS;
    }

    /** Human label for a single level, dropdown-driven with a safe fallback. */
    public function levelLabel(int $level): string
    {
        $labels = $this->levelLabels();

        return $labels[$level] ?? (self::FALLBACK_LEVEL_LABELS[$level] ?? (string) $level);
    }

    // =====================================================================
    // Availability
    // =====================================================================

    /** True only when both new tables exist - the feature is installed. */
    public function isAvailable(): bool
    {
        try {
            return Schema::hasTable(self::RUN_TABLE) && Schema::hasTable(self::RATING_TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // Run CRUD (writes confined to the two new tables)
    // =====================================================================

    /**
     * Create a new assessment run for the given model and seed an empty (level-0)
     * rating row for every section in that model, so the rating form is fully
     * pre-populated. Returns the new run id, or null when unavailable / bad model.
     */
    public function createRun(string $model, array $meta = []): ?int
    {
        $model = trim($model);
        if (! $this->isAvailable() || ! $this->isKnownModel($model)) {
            return null;
        }

        try {
            $now = now();
            $assessmentDate = $this->sanitiseDate($meta['assessment_date'] ?? null) ?? $now->toDateString();

            $runId = (int) DB::table(self::RUN_TABLE)->insertGetId([
                'model' => $model,
                'title' => $this->clip($meta['title'] ?? null, 255),
                'assessor' => $this->clip($meta['assessor'] ?? null, 255),
                'assessor_user_id' => isset($meta['assessor_user_id']) && (int) $meta['assessor_user_id'] > 0 ? (int) $meta['assessor_user_id'] : null,
                'assessment_date' => $assessmentDate,
                'status' => 'draft',
                'notes' => $this->clip($meta['notes'] ?? null, 65535),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Seed an empty rating per section (idempotent via the unique key).
            foreach ($this->sectionsFor($model) as $section) {
                DB::table(self::RATING_TABLE)->insertOrIgnore([
                    'assessment_id' => $runId,
                    'section_key' => (string) $section['key'],
                    'level' => 0,
                    'evidence' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $runId;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation self-assessment createRun failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Persist the ratings + run metadata for an existing run. $ratings is keyed by
     * section_key => ['level' => int, 'evidence' => ?string]. Only sections that
     * belong to the run's model are written; the level is clamped to 0..4. Returns
     * true on a successful save.
     */
    public function saveRun(int $runId, array $ratings, array $meta = []): bool
    {
        if ($runId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        try {
            $run = DB::table(self::RUN_TABLE)->where('id', $runId)->first();
            if (! $run) {
                return false;
            }
            $model = (string) $run->model;
            $validKeys = array_map(fn ($s) => $s['key'], $this->sectionsFor($model));
            $now = now();

            foreach ($ratings as $sectionKey => $val) {
                $sectionKey = (string) $sectionKey;
                if (! in_array($sectionKey, $validKeys, true)) {
                    continue; // ignore anything not in this model
                }
                $level = (int) ($val['level'] ?? 0);
                $level = max(self::MIN_LEVEL, min(self::MAX_LEVEL, $level));
                $evidence = $this->clip($val['evidence'] ?? null, 65535);

                DB::table(self::RATING_TABLE)->updateOrInsert(
                    ['assessment_id' => $runId, 'section_key' => $sectionKey],
                    [
                        'level' => $level,
                        'evidence' => $evidence,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            // Update run metadata + status.
            $update = ['updated_at' => $now];
            if (array_key_exists('title', $meta)) {
                $update['title'] = $this->clip($meta['title'], 255);
            }
            if (array_key_exists('assessor', $meta)) {
                $update['assessor'] = $this->clip($meta['assessor'], 255);
            }
            if (array_key_exists('assessment_date', $meta)) {
                $update['assessment_date'] = $this->sanitiseDate($meta['assessment_date']);
            }
            if (array_key_exists('notes', $meta)) {
                $update['notes'] = $this->clip($meta['notes'], 65535);
            }
            if (array_key_exists('status', $meta)) {
                $status = trim((string) $meta['status']);
                $update['status'] = in_array($status, ['draft', 'complete'], true) ? $status : 'draft';
            }
            DB::table(self::RUN_TABLE)->where('id', $runId)->update($update);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation self-assessment saveRun failed: '.$e->getMessage());

            return false;
        }
    }

    /** Delete a run and its ratings. Confined to the two new tables. */
    public function deleteRun(int $runId): bool
    {
        if ($runId <= 0 || ! $this->isAvailable()) {
            return false;
        }

        try {
            DB::table(self::RATING_TABLE)->where('assessment_id', $runId)->delete();

            return DB::table(self::RUN_TABLE)->where('id', $runId)->delete() > 0;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation self-assessment deleteRun failed: '.$e->getMessage());

            return false;
        }
    }

    // =====================================================================
    // Reads
    // =====================================================================

    /**
     * All assessment runs, newest first, each with its overall (average) level and a
     * tiny per-section level map for sparkline/progress display. Returns an empty
     * array when unavailable.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listRuns(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $runs = DB::table(self::RUN_TABLE)
                ->orderByDesc('assessment_date')
                ->orderByDesc('id')
                ->get();
            if ($runs->isEmpty()) {
                return [];
            }

            $ids = $runs->pluck('id')->all();
            $ratings = DB::table(self::RATING_TABLE)
                ->whereIn('assessment_id', $ids)
                ->get()
                ->groupBy('assessment_id');

            $out = [];
            foreach ($runs as $run) {
                $rows = $ratings->get($run->id, collect());
                $levels = $rows->map(fn ($r) => (int) $r->level)->all();
                $out[] = [
                    'id' => (int) $run->id,
                    'model' => (string) $run->model,
                    'model_name' => $this->modelMeta((string) $run->model)['name'],
                    'title' => $run->title !== null ? (string) $run->title : null,
                    'assessor' => $run->assessor !== null ? (string) $run->assessor : null,
                    'assessment_date' => $run->assessment_date !== null ? (string) $run->assessment_date : null,
                    'status' => (string) $run->status,
                    'section_count' => count($levels),
                    'overall' => $this->average($levels),
                    'created_at' => $run->created_at !== null ? (string) $run->created_at : null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation self-assessment listRuns failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * One run fully hydrated for the rating form / profile: the run metadata, the
     * model's section catalogue, and the saved level + evidence per section.
     *
     * @return array<string,mixed>|null
     */
    public function getRun(int $runId): ?array
    {
        if ($runId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        try {
            $run = DB::table(self::RUN_TABLE)->where('id', $runId)->first();
            if (! $run) {
                return null;
            }

            $model = (string) $run->model;
            $sections = $this->sectionsFor($model);
            $saved = DB::table(self::RATING_TABLE)
                ->where('assessment_id', $runId)
                ->get()
                ->keyBy('section_key');

            $levelLabels = $this->levelLabels();
            $built = [];
            $levels = [];
            foreach ($sections as $section) {
                $key = (string) $section['key'];
                $row = $saved->get($key);
                $level = $row ? max(self::MIN_LEVEL, min(self::MAX_LEVEL, (int) $row->level)) : 0;
                $levels[] = $level;
                $built[] = [
                    'key' => $key,
                    'name' => (string) $section['name'],
                    'description' => (string) $section['description'],
                    'level_descriptors' => $section['levels'] ?? [],
                    'level' => $level,
                    'level_label' => $levelLabels[$level] ?? (string) $level,
                    'evidence' => $row && $row->evidence !== null ? (string) $row->evidence : '',
                ];
            }

            return [
                'id' => (int) $run->id,
                'model' => $model,
                'model_name' => $this->modelMeta($model)['name'],
                'model_note' => $this->modelMeta($model)['note'],
                'title' => $run->title !== null ? (string) $run->title : null,
                'assessor' => $run->assessor !== null ? (string) $run->assessor : null,
                'assessor_user_id' => $run->assessor_user_id !== null ? (int) $run->assessor_user_id : null,
                'assessment_date' => $run->assessment_date !== null ? (string) $run->assessment_date : null,
                'status' => (string) $run->status,
                'notes' => $run->notes !== null ? (string) $run->notes : null,
                'sections' => $built,
                'overall' => $this->average($levels),
                'max_level' => self::MAX_LEVEL,
                'created_at' => $run->created_at !== null ? (string) $run->created_at : null,
                'updated_at' => $run->updated_at !== null ? (string) $run->updated_at : null,
            ];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] preservation self-assessment getRun failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * The export shape of one run: a self-contained, machine-readable snapshot of the
     * assessment (model, metadata, every section with its self-rated level + label +
     * evidence, and the overall average). Returns null for an unknown / missing run.
     *
     * @return array<string,mixed>|null
     */
    public function exportRun(int $runId): ?array
    {
        $run = $this->getRun($runId);
        if ($run === null) {
            return null;
        }

        $sections = array_map(fn ($s) => [
            'section_key' => $s['key'],
            'section_name' => $s['name'],
            'level' => $s['level'],
            'level_label' => $s['level_label'],
            'evidence' => $s['evidence'],
        ], $run['sections']);

        return [
            'schema' => 'heratio.preservation_self_assessment.v1',
            'generated_at' => now()->toIso8601String(),
            'assessment' => [
                'id' => $run['id'],
                'model' => $run['model'],
                'model_name' => $run['model_name'],
                'title' => $run['title'],
                'assessor' => $run['assessor'],
                'assessment_date' => $run['assessment_date'],
                'status' => $run['status'],
                'notes' => $run['notes'],
                'overall_level' => $run['overall'],
                'max_level' => $run['max_level'],
                'sections' => $sections,
            ],
        ];
    }

    /**
     * History series for the progress view: for each model, the chronological list of
     * runs with their overall average, so a trend can be drawn. Returns an empty array
     * when unavailable.
     *
     * @return array<string, array<int, array{id:int,date:?string,overall:float,status:string}>>
     */
    public function history(): array
    {
        $runs = $this->listRuns();
        $byModel = [];
        foreach ($runs as $r) {
            $byModel[$r['model']][] = [
                'id' => $r['id'],
                'date' => $r['assessment_date'],
                'overall' => $r['overall'],
                'status' => $r['status'],
            ];
        }
        // listRuns is newest-first; reverse each series to chronological order.
        foreach ($byModel as $model => $series) {
            $byModel[$model] = array_reverse($series);
        }

        return $byModel;
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** Mean of a level list, rounded to one decimal; 0.0 for an empty list. */
    private function average(array $levels): float
    {
        $levels = array_map('intval', $levels);
        if (empty($levels)) {
            return 0.0;
        }

        return round(array_sum($levels) / count($levels), 1);
    }

    /** Trim + length-clip a free-text field; null stays null / empty becomes null. */
    private function clip($value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /** Validate a Y-m-d date string; null/invalid becomes null. */
    private function sanitiseDate($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        if ($d && $d->format('Y-m-d') === $value) {
            return $value;
        }

        return null;
    }
}
