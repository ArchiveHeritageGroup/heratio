<?php

/**
 * CaaisProfileService - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgAccessionManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * CAAIS 1.0 (Canadian Archival Accession Information Standard, Canadian Council
 * of Archives, 15 May 2019) as a pluggable profile over the core accession
 * model (heratio#1514).
 *
 * The core accession stays jurisdiction-neutral. This profile only adds side
 * tables keyed on accession.id (database/install_caais.sql) and reads the core
 * record when it exports. It is switched on per institution with the
 * ahg_settings key `accession_caais_enabled`. Two parts are useful to every
 * institution and so are always on: the repository link (CAAIS 1.1, which is
 * also the multi-repository request from 2020) and the creation/revision log
 * (CAAIS 7.2), written on every create and save.
 *
 * Every enumerated value is an ahg_dropdown code (seed_dropdowns.sql).
 */
class CaaisProfileService
{
    public const SETTING = 'accession_caais_enabled';

    /** Last table in install_caais.sql - present only when the whole file ran. */
    public const SENTINEL_TABLE = 'accession_caais_revision';

    /** The two events CAAIS 5.1 makes mandatory (codes in caais_event_type). */
    public const EVENT_PHYSICAL = 'physical_transfer';

    public const EVENT_LEGAL = 'legal_transfer';

    /** Form key => ahg_dropdown taxonomy. */
    public const TAXONOMIES = [
        'extent_type' => 'caais_extent_type',
        'unit' => 'caais_extent_unit',
        'content_type' => 'caais_content_type',
        'carrier_type' => 'caais_carrier_type',
        'confidentiality' => 'caais_source_confidentiality',
        'language' => 'caais_language',
        'requirement_type' => 'caais_preservation_type',
        'event_type' => 'caais_event_type',
        'revision_type' => 'caais_revision_type',
    ];

    /** Upper bound on rows per repeatable element in one save. */
    private const MAX_ROWS = 100;

    /**
     * The crosswalk: export key => [CAAIS element, Heratio source]. Published
     * inside every export so a receiving system can map without this code.
     */
    public const CROSSWALK = [
        'repository' => ['1.1', 'accession_caais.repository_id -> repository authorised name'],
        'identifiers' => ['1.2', 'accession.identifier (Accession number) + other_name alternative identifiers'],
        'accession_title' => ['1.3', 'accession_i18n.title'],
        'acquisition_method' => ['1.5', 'accession.acquisition_type_id (term)'],
        'status' => ['1.7', 'accession.processing_status_id (term)'],
        'source_of_material' => ['2.1', 'donor relation + contact_information; 2.1.6 from accession_caais_source'],
        'preliminary_custodial_history' => ['2.2', 'accession_i18n.archival_history'],
        'date_of_material' => ['3.1', 'event rows on the accession (display date or start/end)'],
        'extent_statement' => ['3.2', 'accession_caais_extent; accession_i18n.received_extent_units as a note when none'],
        'preliminary_scope_and_content' => ['3.3', 'accession_i18n.scope_and_content'],
        'language_of_material' => ['3.4', 'accession_caais_language'],
        'storage_location' => ['4.1', 'accession_i18n.location_information'],
        'rights' => ['4.2', 'rights linked by relation'],
        'preservation_requirements' => ['4.3', 'accession_caais_preservation; accession_i18n.physical_characteristics when none'],
        'appraisal' => ['4.4', 'accession_i18n.appraisal'],
        'events' => ['5.1', 'accession_caais_event + core accession_event'],
        'general_note' => ['6.1', 'accession_i18n.processing_notes'],
        'rules_or_conventions' => ['7.1', 'accession_caais.rules_or_conventions'],
        'date_of_creation_or_revision' => ['7.2', 'accession_caais_revision'],
        'language_of_accession_record' => ['7.3', 'accession.source_culture'],
    ];

    private ?array $choiceCache = null;

    /** Labels of inactive codes still held by old rows. */
    private array $retired = [];

    public function __construct(private ?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    public function installed(): bool
    {
        // Schema::hasTable goes through the per-release SchemaExistenceCache,
        // so this is not an information_schema query per request once present.
        return Schema::hasTable(self::SENTINEL_TABLE);
    }

    public function enabled(): bool
    {
        if (! $this->installed()) {
            return false;
        }
        $v = DB::table('ahg_settings')->where('setting_key', self::SETTING)->value('setting_value');

        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Active dropdown options per form key, code => label, in sort order.
     *
     * @return array<string, array<string, string>>
     */
    public function choices(): array
    {
        if ($this->choiceCache !== null) {
            return $this->choiceCache;
        }
        $rows = DB::table('ahg_dropdown')
            ->whereIn('taxonomy', array_values(self::TAXONOMIES))
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('label')
            ->get(['taxonomy', 'code', 'label']);

        $out = array_fill_keys(array_keys(self::TAXONOMIES), []);
        $byTaxonomy = array_flip(self::TAXONOMIES);
        foreach ($rows as $r) {
            $out[$byTaxonomy[$r->taxonomy]][$r->code] = $r->label;
        }

        return $this->choiceCache = $out;
    }

    /** Label for a stored code; a retired term's label, or the raw code. */
    public function label(string $key, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        $active = $this->choices()[$key][$code] ?? null;
        if ($active !== null) {
            return $active;
        }
        if (! array_key_exists($key.':'.$code, $this->retired)) {
            $this->retired[$key.':'.$code] = DB::table('ahg_dropdown')
                ->where('taxonomy', self::TAXONOMIES[$key])->where('code', $code)->value('label');
        }

        return $this->retired[$key.':'.$code] ?? $code;
    }

    /** Repositories for the 1.1 picker: id => authorised name. */
    public function repositoryOptions(): array
    {
        return DB::table('repository')
            ->join('actor_i18n', function ($j) {
                $j->on('repository.id', '=', 'actor_i18n.id')->where('actor_i18n.culture', '=', $this->culture);
            })
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->pluck('actor_i18n.authorized_form_of_name', 'repository.id')
            ->all();
    }

    /**
     * Validation rules for the `caais` part of the accession form. A row whose
     * fields are all blank is the form's empty template row and is skipped on
     * save, so the type column is only required once something else is filled.
     */
    public function rules(): array
    {
        $in = fn (string $key) => Rule::in(array_keys($this->choices()[$key]));
        $max = 'max:'.self::MAX_ROWS;

        return [
            'caais' => ['nullable', 'array'],
            'caais._profile' => ['nullable', 'boolean'],
            'caais.repository_id' => ['nullable', 'integer', 'exists:repository,id'],
            'caais.rules_or_conventions' => ['nullable', 'string', 'max:1024'],

            'caais.sources' => ['nullable', 'array', $max],
            'caais.sources.*' => ['nullable', 'string', $in('confidentiality')],

            'caais.extents' => ['nullable', 'array', $max],
            'caais.extents.*.extent_type' => ['nullable', 'required_with:caais.extents.*.quantity,caais.extents.*.unit,caais.extents.*.content_type,caais.extents.*.carrier_type,caais.extents.*.digital_file_formats,caais.extents.*.note', $in('extent_type')],
            'caais.extents.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'caais.extents.*.is_estimate' => ['nullable', 'boolean'],
            'caais.extents.*.unit' => ['nullable', 'required_with:caais.extents.*.quantity', $in('unit')],
            'caais.extents.*.content_type' => ['nullable', $in('content_type')],
            'caais.extents.*.carrier_type' => ['nullable', $in('carrier_type')],
            'caais.extents.*.digital_file_formats' => ['nullable', 'string', 'max:1024'],
            'caais.extents.*.note' => ['nullable', 'string', 'max:65535'],

            'caais.languages' => ['nullable', 'array', $max],
            'caais.languages.*.language' => ['nullable', $in('language')],
            'caais.languages.*.note' => ['nullable', 'string', 'max:1024'],

            'caais.preservation' => ['nullable', 'array', $max],
            'caais.preservation.*.requirement_type' => ['nullable', 'required_with:caais.preservation.*.requirement_value,caais.preservation.*.note', $in('requirement_type')],
            'caais.preservation.*.requirement_value' => ['nullable', 'required_with:caais.preservation.*.requirement_type', 'string', 'max:65535'],
            'caais.preservation.*.note' => ['nullable', 'string', 'max:65535'],

            'caais.events' => ['nullable', 'array', $max],
            'caais.events.*.event_type' => ['nullable', 'required_with:caais.events.*.event_date,caais.events.*.agent,caais.events.*.note', $in('event_type')],
            'caais.events.*.event_date' => ['nullable', 'date'],
            'caais.events.*.agent' => ['nullable', 'string', 'max:255'],
            'caais.events.*.note' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * Everything the profile holds for one accession.
     */
    public function get(int $accessionId): array
    {
        $empty = [
            'repository_id' => null, 'repository_name' => null, 'rules_or_conventions' => null,
            'sources' => [], 'extents' => [], 'languages' => [], 'preservation' => [], 'events' => [], 'revisions' => [],
        ];
        if (! $this->installed()) {
            return $empty;
        }

        $main = DB::table('accession_caais')->where('accession_id', $accessionId)->first();
        $rows = fn (string $table) => DB::table($table)->where('accession_id', $accessionId)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return [
            'repository_id' => $main->repository_id ?? null,
            'repository_name' => ($main->repository_id ?? null) ? $this->repositoryName((int) $main->repository_id) : null,
            'rules_or_conventions' => $main->rules_or_conventions ?? null,
            'sources' => DB::table('accession_caais_source')->where('accession_id', $accessionId)
                ->pluck('confidentiality', 'actor_id')->all(),
            'extents' => $rows('accession_caais_extent'),
            'languages' => $rows('accession_caais_language'),
            'preservation' => $rows('accession_caais_preservation'),
            'events' => $rows('accession_caais_event'),
            'revisions' => DB::table('accession_caais_revision')->where('accession_id', $accessionId)
                ->orderBy('revision_date')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /**
     * Save the validated `caais` input. The repository link is always saved.
     * The repeatable elements are replaced only when the form carried the
     * profile section (caais[_profile]=1), so saving with the profile switched
     * off never wipes what was recorded while it was on.
     */
    public function save(int $accessionId, array $input): void
    {
        if (! $this->installed()) {
            return;
        }

        DB::transaction(function () use ($accessionId, $input) {
            $main = ['repository_id' => ($input['repository_id'] ?? null) ?: null];
            $withProfile = ! empty($input['_profile']);
            if ($withProfile) {
                $main['rules_or_conventions'] = $this->clean($input['rules_or_conventions'] ?? null);
            }
            DB::table('accession_caais')->updateOrInsert(['accession_id' => $accessionId], $main + ['updated_at' => now()]);

            if (! $withProfile) {
                return;
            }

            DB::table('accession_caais_source')->where('accession_id', $accessionId)->delete();
            foreach ((array) ($input['sources'] ?? []) as $actorId => $code) {
                if ($code !== null && $code !== '' && ctype_digit((string) $actorId)) {
                    DB::table('accession_caais_source')->insert([
                        'accession_id' => $accessionId, 'actor_id' => (int) $actorId, 'confidentiality' => $code,
                    ]);
                }
            }

            $this->replaceRows($accessionId, 'accession_caais_extent', $input['extents'] ?? [], 'extent_type', fn ($r) => [
                'extent_type' => $r['extent_type'],
                'quantity' => ($r['quantity'] ?? '') === '' ? null : $r['quantity'],
                'is_estimate' => empty($r['is_estimate']) ? 0 : 1,
                'unit' => $this->clean($r['unit'] ?? null),
                'content_type' => $this->clean($r['content_type'] ?? null),
                'carrier_type' => $this->clean($r['carrier_type'] ?? null),
                'digital_file_formats' => $this->clean($r['digital_file_formats'] ?? null),
                'note' => $this->clean($r['note'] ?? null),
            ]);
            $this->replaceRows($accessionId, 'accession_caais_language', $input['languages'] ?? [], null, fn ($r) => [
                'language' => $this->clean($r['language'] ?? null),
                'note' => $this->clean($r['note'] ?? null),
            ]);
            $this->replaceRows($accessionId, 'accession_caais_preservation', $input['preservation'] ?? [], 'requirement_type', fn ($r) => [
                'requirement_type' => $r['requirement_type'],
                'requirement_value' => (string) $r['requirement_value'],
                'note' => $this->clean($r['note'] ?? null),
            ]);
            $this->replaceRows($accessionId, 'accession_caais_event', $input['events'] ?? [], 'event_type', fn ($r) => [
                'event_type' => $r['event_type'],
                'event_date' => $this->clean($r['event_date'] ?? null),
                'agent' => $this->clean($r['agent'] ?? null),
                'note' => $this->clean($r['note'] ?? null),
            ]);
        });
    }

    /**
     * CAAIS 7.2: append one creation or revision entry. 7.2.3 agent is the
     * signed-in user's name, snapshotted so it survives a deleted account.
     */
    public function recordRevision(int $accessionId, string $type, ?string $note = null): void
    {
        if (! $this->installed()) {
            return;
        }
        $user = auth()->user();
        DB::table('accession_caais_revision')->insert([
            'accession_id' => $accessionId,
            'revision_type' => $type,
            'revision_date' => now(),
            'agent' => $user->username ?? null,
            'user_id' => $user->id ?? null,
            'note' => $note,
        ]);
    }

    /**
     * CAAIS mandatory elements this accession does not yet satisfy, as
     * "element - name" strings. Empty = conformant at the mandatory level.
     */
    public function missingMandatory(object $accession, array $profile, int $sourceCount, int $dateCount): array
    {
        $missing = [];
        if (trim((string) ($accession->identifier ?? '')) === '') {
            $missing[] = '1.2 - '.__('Identifiers');
        }
        if ($sourceCount === 0) {
            $missing[] = '2.1 - '.__('Source of material');
        }
        if ($dateCount === 0) {
            $missing[] = '3.1 - '.__('Date of material');
        }
        if ($profile['extents'] === [] && trim((string) ($accession->received_extent_units ?? '')) === '') {
            $missing[] = '3.2 - '.__('Extent statement');
        }
        $types = array_column($profile['events'], 'event_type');
        if (! in_array(self::EVENT_PHYSICAL, $types, true)) {
            $missing[] = '5.1 - '.__('Event: physical transfer');
        }
        if (! in_array('created', array_column($profile['revisions'], 'revision_type'), true)) {
            $missing[] = '7.2 - '.__('Date of creation (record created)');
        }

        return $missing;
    }

    /**
     * One accession as a CAAIS 1.0 record, keyed by the crosswalk above. The
     * CCA publishes CAAIS as an element set with no XML schema or JSON binding,
     * so this is a JSON serialisation whose keys follow the element names and
     * whose `crosswalk` block names each element number.
     *
     * With $external, sources that carry a 2.1.6 confidentiality instruction
     * are withheld, which is the behaviour CAAIS asks of shared outputs.
     */
    public function exportRecord(int $accessionId, AccessionService $core, bool $external = false): ?array
    {
        $a = $core->getById($accessionId);
        if (! $a) {
            return null;
        }
        $p = $this->get($accessionId);
        $terms = $core->getTermNames(array_filter([$a->acquisition_type_id, $a->processing_status_id]));

        $identifiers = [['type' => 'Accession number', 'value' => $a->identifier, 'note' => null]];
        foreach ($core->getAlternativeIdentifiers($accessionId) as $alt) {
            $identifiers[] = ['type' => $alt->label, 'value' => $alt->identifier, 'note' => null];
        }

        $sources = [];
        $donors = $core->getDonors($accessionId);
        // DonorService decrypts the encrypted contact columns (email, city).
        $contacts = new \AhgDonorManage\Services\DonorService($this->culture);
        foreach ($donors as $d) {
            $conf = $p['sources'][$d->id] ?? null;
            if ($external && $conf) {
                continue;
            }
            $c = $contacts->getContacts((int) $d->id)->first();
            $sources[] = [
                'source_type' => null,
                'source_name' => $d->name,
                'source_contact_information' => $c ? $this->joinNonEmpty([
                    $c->contact_person ?? null, $c->street_address ?? null, $c->city ?? null,
                    $c->region ?? null, $c->postal_code ?? null, $c->country_code ?? null,
                    $c->telephone ?? null, $c->email ?? null,
                ]) : null,
                'source_role' => 'Donor',
                'source_note' => null,
                'source_confidentiality' => $this->label('confidentiality', $conf),
            ];
        }
        if ($donors->isEmpty() && trim((string) $a->source_of_acquisition) !== '') {
            $sources[] = [
                'source_type' => null, 'source_name' => $a->source_of_acquisition,
                'source_contact_information' => null, 'source_role' => 'Immediate source of acquisition',
                'source_note' => null, 'source_confidentiality' => null,
            ];
        }

        // Conformance counts every source, including ones withheld above.
        $sourceTotal = $donors->count() ?: (trim((string) $a->source_of_acquisition) === '' ? 0 : 1);

        $dates = $core->getDates($accessionId)->map(fn ($d) => $d->date_display
            ?: $this->joinNonEmpty([$d->start_date, $d->end_date], ' - '))->filter()->values()->all();

        $extents = array_map(fn ($e) => [
            'extent_type' => $this->label('extent_type', $e['extent_type']),
            'quantity_and_unit_of_measure' => $this->joinNonEmpty([
                $e['is_estimate'] ? 'ca.' : null,
                $e['quantity'] === null ? null : self::formatQuantity($e['quantity']),
                $this->label('unit', $e['unit']),
            ], ' '),
            'content_type' => $this->label('content_type', $e['content_type']),
            'carrier_type' => $this->label('carrier_type', $e['carrier_type']),
            'digital_file_formats' => $e['digital_file_formats'],
            'extent_note' => $e['note'],
        ], $p['extents']);
        if ($extents === [] && trim((string) $a->received_extent_units) !== '') {
            $extents[] = ['extent_type' => $this->label('extent_type', 'extent_received'), 'quantity_and_unit_of_measure' => null,
                'content_type' => null, 'carrier_type' => null, 'digital_file_formats' => null, 'extent_note' => $a->received_extent_units];
        }

        $preservation = array_map(fn ($r) => [
            'preservation_requirement_type' => $this->label('requirement_type', $r['requirement_type']),
            'preservation_requirement_value' => $r['requirement_value'],
            'preservation_requirement_note' => $r['note'],
        ], $p['preservation']);
        if ($preservation === [] && trim((string) $a->physical_characteristics) !== '') {
            $preservation[] = ['preservation_requirement_type' => $this->label('requirement_type', 'physical_condition'),
                'preservation_requirement_value' => $a->physical_characteristics, 'preservation_requirement_note' => null];
        }

        $events = array_map(fn ($e) => [
            'event_type' => $this->label('event_type', $e['event_type']),
            'event_date' => $e['event_date'], 'event_agent' => $e['agent'], 'event_note' => $e['note'],
        ], $p['events']);
        if ($a->date) {
            // Core accession.date is the acquisition date, not the date of the
            // material (3.1), so it travels as an event.
            $events[] = ['event_type' => 'Accession date', 'event_date' => (string) $a->date, 'event_agent' => null, 'event_note' => null];
        }
        foreach ($core->getAccessionEvents($accessionId) as $e) {
            $events[] = ['event_type' => $e->type_name, 'event_date' => $e->date, 'event_agent' => $e->agent, 'event_note' => $e->note];
        }

        $rights = $core->getRights($accessionId)->map(fn ($r) => [
            'rights_type' => $r->basis_name,
            'rights_value' => $this->joinNonEmpty([$r->start_date, $r->end_date], ' - '),
            'rights_note' => $r->rights_note,
        ])->all();

        $text = fn ($v) => trim((string) $v) === '' ? [] : [(string) $v];

        return [
            'identity' => [
                'repository' => $p['repository_name'],
                'identifiers' => $identifiers,
                'accession_title' => $a->title,
                'archival_unit' => [],
                'acquisition_method' => $terms[$a->acquisition_type_id] ?? null,
                'disposition_authority' => [],
                'status' => $terms[$a->processing_status_id] ?? null,
            ],
            'source' => [
                'source_of_material' => array_values($sources),
                'preliminary_custodial_history' => $text($a->archival_history),
            ],
            'materials' => [
                'date_of_material' => $dates === [] ? null : implode('; ', $dates),
                'extent_statement' => $extents,
                'preliminary_scope_and_content' => $text($a->scope_and_content),
                'language_of_material' => array_values(array_filter(array_map(
                    fn ($l) => $this->joinNonEmpty([$this->label('language', $l['language']), $l['note']], ' - '),
                    $p['languages']
                ))),
            ],
            'management' => [
                'storage_location' => $text($a->location_information),
                'rights' => $rights,
                'preservation_requirements' => $preservation,
                'appraisal' => $text($a->appraisal) === [] ? [] : [['appraisal_type' => null, 'appraisal_value' => $a->appraisal, 'appraisal_note' => null]],
                'associated_documentation' => [],
            ],
            'events' => $events,
            'general' => [
                'general_note' => $text($a->processing_notes),
            ],
            'control' => [
                'rules_or_conventions' => $p['rules_or_conventions'],
                'date_of_creation_or_revision' => array_map(fn ($r) => [
                    'creation_or_revision_type' => $this->label('revision_type', $r['revision_type']),
                    'creation_or_revision_date' => (string) $r['revision_date'],
                    'creation_or_revision_agent' => $r['agent'],
                    'creation_or_revision_note' => $r['note'],
                ], $p['revisions']),
                'language_of_accession_record' => $a->source_culture,
            ],
            'conformance' => [
                'missing_mandatory' => $this->missingMandatory($a, $p, $sourceTotal, count($dates)),
            ],
        ];
    }

    /** Wrap records in the export envelope, crosswalk included. */
    public function envelope(array $records): array
    {
        return [
            'standard' => 'Canadian Archival Accession Information Standard (CAAIS)',
            'standard_version' => '1.0',
            'standard_publisher' => 'Canadian Council of Archives',
            'serialisation' => 'heratio-caais-json/1',
            'generated_at' => now()->toIso8601String(),
            'crosswalk' => array_map(fn ($c) => ['element' => $c[0], 'source' => $c[1]], self::CROSSWALK),
            'records' => $records,
        ];
    }

    /** 12.500 -> 12.5, 10.000 -> 10, 10 -> 10; blank -> ''. */
    public static function formatQuantity($q): string
    {
        $q = trim((string) $q);

        return str_contains($q, '.') ? rtrim(rtrim($q, '0'), '.') : $q;
    }

    private function repositoryName(int $id): ?string
    {
        return DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->value('authorized_form_of_name')
            ?? DB::table('actor_i18n')->where('id', $id)->value('authorized_form_of_name');
    }

    /**
     * Replace one repeatable element's rows. Rows with every field blank are
     * the form's template row and are dropped; $typeKey rows without a type
     * cannot pass validation, so they never reach here.
     */
    private function replaceRows(int $accessionId, string $table, array $rows, ?string $typeKey, callable $map): void
    {
        DB::table($table)->where('accession_id', $accessionId)->delete();
        $sort = 0;
        foreach (array_values($rows) as $row) {
            if (! is_array($row) || $this->blank($row)) {
                continue;
            }
            if ($typeKey !== null && ($row[$typeKey] ?? '') === '') {
                continue;
            }
            DB::table($table)->insert(['accession_id' => $accessionId, 'sort_order' => $sort++] + $map($row));
        }
    }

    private function blank(array $row): bool
    {
        foreach ($row as $k => $v) {
            if ($k !== 'is_estimate' && $v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function clean($v): ?string
    {
        $v = $v === null ? '' : trim((string) $v);

        return $v === '' ? null : $v;
    }

    private function joinNonEmpty(array $parts, string $sep = ', '): ?string
    {
        $parts = array_filter(array_map(fn ($p) => trim((string) $p), $parts), fn ($p) => $p !== '');

        return $parts === [] ? null : implode($sep, $parts);
    }
}
