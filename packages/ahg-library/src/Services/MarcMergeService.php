<?php

/**
 * MarcMergeService - field-level diff / conflict detection between an incoming
 * (edited) MARCXML record and the Heratio master for the same record.
 *
 * Workflow:
 *
 *   $svc = new MarcMergeService();
 *   $report = $svc->diff($incomingMarcXml, $culture);
 *
 * The incoming record is matched to a master information_object by its 001
 * control number (string identifier first, then numeric io.id fallback,
 * mirroring MarcXmlImporter::matchExisting). The master is re-serialized to
 * MARCXML via MarcxmlSerializer and both sides are reduced to a comparable
 * field map. Each logical field is then classified:
 *
 *   - unchanged: incoming == master
 *   - changed:   both present, values differ  -> a conflict the reviewer resolves
 *   - added:     present in incoming, absent in master
 *   - removed:   present in master, absent in incoming
 *
 * The report drives the conflict-review UI: each conflict offers a "keep
 * master" / "take incoming" choice. No writes happen here - resolution +
 * commit is the caller's job (MarcXmlImporter::commit on the chosen merge).
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MarcMergeService
{
    /**
     * Human-readable labels for the round-trip-safe scalar fields the
     * importer exposes. Keys match MarcXmlImporter::describeRecord output.
     *
     * @var array<string, string>
     */
    private const SCALAR_FIELDS = [
        'control_number'              => '001 control number',
        'title'                       => '245$a title',
        'scope_and_content'           => '520$a summary / scope',
        'extent_and_medium'           => '300$a physical description',
        'archival_history'            => '561$a custodial history',
        'acquisition'                 => '541$a acquisition source',
        'reproduction_conditions'     => '540$a terms governing use',
        'access_conditions'           => '506$a restrictions on access',
        'related_units_of_description'=> '544$a related material',
    ];

    /**
     * Repeatable (list) fields. Compared as sets.
     *
     * @var array<string, string>
     */
    private const LIST_FIELDS = [
        'subjects' => '650$a topical subjects',
        'places'   => '651$a geographic names',
        'genres'   => '655$a genre / form',
        'creators' => '1XX/7XX creators',
    ];

    private MarcXmlImporter $importer;
    private MarcxmlSerializer $serializer;

    public function __construct(?MarcXmlImporter $importer = null, ?MarcxmlSerializer $serializer = null)
    {
        $this->importer = $importer ?: new MarcXmlImporter();
        $this->serializer = $serializer ?: new MarcxmlSerializer();
    }

    /**
     * Diff an incoming MARCXML record against the Heratio master.
     *
     * @return array<string, mixed> {
     *   matched: bool,
     *   matched_io_id: ?int,
     *   control_number: ?string,
     *   title: ?string,
     *   has_conflicts: bool,
     *   conflict_count: int,
     *   fields: array<int, array{field:string,label:string,status:string,master:mixed,incoming:mixed}>,
     *   warnings: string[],
     * }
     */
    public function diff(string $incomingXml, string $culture = 'en'): array
    {
        $warnings = [];

        $records = $this->importer->parseRecords($incomingXml);
        if (empty($records)) {
            return [
                'matched'        => false,
                'matched_io_id'  => null,
                'control_number' => null,
                'title'          => null,
                'has_conflicts'  => false,
                'conflict_count' => 0,
                'fields'         => [],
                'warnings'       => ['No <record> element found in the incoming MARCXML.'],
            ];
        }

        // Single-record merge: take the first record. Batch merge can call
        // diff() per record on the caller side.
        $incoming = $this->importer->describeRecord($records[0], withMatch: true);
        $matchedIoId = $incoming['matched_io_id'] ?? null;

        $master = [];
        if ($matchedIoId !== null) {
            $master = $this->masterDescriptor((int) $matchedIoId, $culture, $warnings);
        } else {
            $warnings[] = 'No master record matched the incoming 001 control number; '
                . 'every field is reported as an addition.';
        }

        $fields = [];
        $conflictCount = 0;

        foreach (self::SCALAR_FIELDS as $key => $label) {
            $masterVal = $this->normaliseScalar($master[$key] ?? null);
            $incomingVal = $this->normaliseScalar($incoming[$key] ?? null);
            $status = $this->classifyScalar($masterVal, $incomingVal);
            if ($status === 'changed') {
                $conflictCount++;
            }
            $fields[] = [
                'field'    => $key,
                'label'    => $label,
                'status'   => $status,
                'master'   => $masterVal,
                'incoming' => $incomingVal,
            ];
        }

        foreach (self::LIST_FIELDS as $key => $label) {
            $masterList = $this->normaliseList($master[$key] ?? []);
            $incomingList = $this->normaliseList($incoming[$key] ?? []);
            $status = $this->classifyList($masterList, $incomingList);
            if ($status === 'changed') {
                $conflictCount++;
            }
            $fields[] = [
                'field'    => $key,
                'label'    => $label,
                'status'   => $status,
                'master'   => $masterList,
                'incoming' => $incomingList,
            ];
        }

        return [
            'matched'        => $matchedIoId !== null,
            'matched_io_id'  => $matchedIoId !== null ? (int) $matchedIoId : null,
            'control_number' => $incoming['control_number'] ?? null,
            'title'          => $incoming['title'] ?? null,
            'has_conflicts'  => $conflictCount > 0,
            'conflict_count' => $conflictCount,
            'fields'         => $fields,
            'warnings'       => array_values(array_merge($warnings, $incoming['warnings'] ?? [])),
        ];
    }

    /**
     * Re-serialize the master IO and reduce it through the importer's
     * descriptor so both sides share an identical shape for comparison.
     *
     * @param array<int, string> $warnings
     * @return array<string, mixed>
     */
    private function masterDescriptor(int $ioId, string $culture, array &$warnings): array
    {
        try {
            if (! Schema::hasTable('information_object')) {
                $warnings[] = 'information_object table not present; cannot load master.';

                return [];
            }
            $masterXml = $this->serializer->serializeRecord($ioId, $culture);
            if ($masterXml === '') {
                $warnings[] = "Master IO #{$ioId} produced no MARCXML.";

                return [];
            }
            // Serializer emits a bare <record>; parseRecords accepts that.
            $masterRecords = $this->importer->parseRecords($masterXml);
            if (empty($masterRecords)) {
                $warnings[] = "Master IO #{$ioId} MARCXML did not parse.";

                return [];
            }

            return $this->importer->describeRecord($masterRecords[0], withMatch: false);
        } catch (Throwable $e) {
            $warnings[] = 'Failed to load master record: ' . $e->getMessage();

            return [];
        }
    }

    private function normaliseScalar(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /**
     * @param mixed $v
     * @return array<int, string>
     */
    private function normaliseList(mixed $v): array
    {
        if (! is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }

    private function classifyScalar(?string $master, ?string $incoming): string
    {
        if ($master === $incoming) {
            return 'unchanged';
        }
        if ($master === null) {
            return 'added';
        }
        if ($incoming === null) {
            return 'removed';
        }

        return 'changed';
    }

    /**
     * @param array<int, string> $master
     * @param array<int, string> $incoming
     */
    private function classifyList(array $master, array $incoming): string
    {
        if ($master === $incoming) {
            return 'unchanged';
        }
        if (empty($master)) {
            return 'added';
        }
        if (empty($incoming)) {
            return 'removed';
        }

        return 'changed';
    }
}
