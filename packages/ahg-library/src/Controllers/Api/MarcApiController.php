<?php

/**
 * MarcApiController - REST MARC record export / import.
 *
 * GET  /api/cataloguing/marc/export?record_ids[]=N&format=iso2709|marcxml
 *   Exports one or more information_object records as MARC. record_ids are
 *   information_object ids (the serializer's native key). format defaults to
 *   marcxml. iso2709 returns concatenated binary MARC21 (application/marc).
 *
 * POST /api/cataloguing/marc/import
 *   Body: { "marcxml": "<collection>...</collection>", "culture": "en" }
 *   Commits every <record> via MarcXmlImporter::commit (create/update +
 *   chained audit) and returns the per-record result. Reuses the existing
 *   exporters/importers from ahg-metadata-export - no MARC logic is
 *   reimplemented here.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers\Api;

use AhgMetadataExport\Services\Exporters\Marc21BinaryEncoder;
use AhgMetadataExport\Services\Exporters\MarcxmlSerializer;
use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class MarcApiController extends Controller
{
    use AuthorizesLibraryApi;

    private MarcxmlSerializer $serializer;
    private Marc21BinaryEncoder $encoder;
    private MarcXmlImporter $importer;

    public function __construct(
        ?MarcxmlSerializer $serializer = null,
        ?Marc21BinaryEncoder $encoder = null,
        ?MarcXmlImporter $importer = null
    ) {
        $this->serializer = $serializer ?: new MarcxmlSerializer();
        $this->encoder = $encoder ?: new Marc21BinaryEncoder();
        $this->importer = $importer ?: new MarcXmlImporter();
    }

    /**
     * Export records as MARCXML (default) or ISO 2709 binary.
     */
    public function export(Request $request): Response|JsonResponse
    {
        $this->authorizeLibrary($request, 'read');

        $data = validator($request->all(), [
            'record_ids'   => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer', 'min:1'],
            'format'       => ['nullable', 'string', 'in:iso2709,marcxml'],
            'culture'      => ['nullable', 'string', 'max:12'],
        ])->validate();

        $format = $data['format'] ?? 'marcxml';
        $culture = $data['culture'] ?? 'en';
        $ids = array_values(array_unique(array_map('intval', $data['record_ids'])));

        $records = [];
        $missing = [];
        foreach ($ids as $id) {
            $xml = $this->serializer->serializeRecord($id, $culture);
            if ($xml === '') {
                $missing[] = $id;
                continue;
            }
            $records[$id] = $xml;
        }

        if (empty($records)) {
            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title'  => 'Not Found',
                    'detail' => 'None of the requested record_ids produced a MARC record.',
                    'meta'   => ['missing' => $missing],
                ]],
            ], 404);
        }

        if ($format === 'iso2709') {
            $binary = '';
            foreach ($records as $xml) {
                try {
                    $binary .= $this->encoder->encodeFromMarcxml($xml);
                } catch (Throwable $e) {
                    return response()->json([
                        'errors' => [[
                            'status' => '500',
                            'title'  => 'Encoding error',
                            'detail' => $e->getMessage(),
                        ]],
                    ], 500);
                }
            }

            return response($binary, 200, [
                'Content-Type'        => 'application/marc',
                'Content-Disposition' => 'attachment; filename="marc-export.mrc"',
            ]);
        }

        // MARCXML: wrap the bare <record> bodies in a <collection>.
        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<collection xmlns="http://www.loc.gov/MARC21/slim">' . "\n"
            . implode("\n", array_values($records)) . "\n"
            . '</collection>';

        return response($body, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="marc-export.xml"',
        ]);
    }

    /**
     * Import a MARCXML payload (create/update information_object rows).
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorizeLibrary($request, 'create');

        $data = validator($this->jsonApiAttributes($request), [
            'marcxml' => ['required', 'string'],
            'culture' => ['nullable', 'string', 'max:12'],
        ])->validate();

        $culture = $data['culture'] ?? 'en';

        try {
            $results = $this->importer->commit($data['marcxml'], $culture);
        } catch (Throwable $e) {
            return response()->json([
                'errors' => [[
                    'status' => '422',
                    'title'  => 'Import failed',
                    'detail' => $e->getMessage(),
                ]],
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($results as $r) {
            if (($r['io_id'] ?? null) === null) {
                $skipped++;
            } elseif (($r['action'] ?? null) === 'update') {
                $updated++;
            } else {
                $created++;
            }
        }

        return response()->json([
            'data' => [
                'type'       => 'marc-import-result',
                'attributes' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'records' => $results,
                ],
            ],
        ], 201);
    }
}
