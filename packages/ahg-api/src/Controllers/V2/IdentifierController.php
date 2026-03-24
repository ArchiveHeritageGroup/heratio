<?php

namespace AhgApi\Controllers\V2;

use AhgApi\Services\GlamIdentifierService;
use AhgApi\Services\IsbnLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Identifier API controller.
 *
 * Ported from AtoM identifierApi module — provides ISBN/ISSN lookup,
 * identifier validation, type detection, barcode generation, and
 * sector-based identifier type listing.
 */
class IdentifierController extends BaseApiController
{
    public function __construct(
        protected GlamIdentifierService $identifierService,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/v2/identifiers/lookup
     *
     * Look up metadata by ISBN or ISSN.
     */
    public function lookup(Request $request): JsonResponse
    {
        $type = $request->get('type', 'isbn');
        $value = $request->get('value');

        if (empty($value)) {
            return $this->error('Bad Request', 'Value is required.', 400);
        }

        try {
            $lookupService = new IsbnLookupService(config('services.google_books.api_key'));

            $result = match ($type) {
                'isbn' => $lookupService->lookupByIsbn($value),
                'issn' => $lookupService->lookupByIssn($value),
                default => throw new \InvalidArgumentException('Unsupported type: ' . $type),
            };

            if (!$result) {
                return $this->success([
                    'found' => false,
                    'message' => 'No results found',
                ]);
            }

            return $this->success([
                'found' => true,
                'raw' => $result,
                'mapped' => $lookupService->mapToLibraryFields($result),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error('Bad Request', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v2/identifiers/validate
     *
     * Validate an identifier value against a given type.
     */
    public function validate(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $value = $request->get('value');

        if (empty($type) || empty($value)) {
            return $this->error('Bad Request', 'Type and value are required.', 400);
        }

        try {
            $validation = $this->identifierService->validateIdentifier($value, $type);

            return $this->success([
                'type' => $type,
                'value' => $value,
                'validation' => $validation,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v2/identifiers/detect
     *
     * Auto-detect identifier type from a value string.
     */
    public function detect(Request $request): JsonResponse
    {
        $value = $request->get('value');

        if (empty($value)) {
            return $this->error('Bad Request', 'Value is required.', 400);
        }

        try {
            $type = $this->identifierService->detectIdentifierType($value);
            $validation = $type ? $this->identifierService->validateIdentifier($value, $type) : null;

            return $this->success([
                'value' => $value,
                'detected_type' => $type,
                'validation' => $validation,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v2/identifiers/barcode/{objectId}
     *
     * Generate barcode data for an information object.
     */
    public function barcode(int $objectId, Request $request): JsonResponse
    {
        if ($objectId < 1) {
            return $this->error('Bad Request', 'Invalid object ID.', 400);
        }

        // Verify object exists
        $object = DB::table('information_object')->where('id', $objectId)->first();
        if (!$object) {
            return $this->error('Not Found', 'Object not found.', 404);
        }

        try {
            $identifier = $this->identifierService->getBestBarcodeIdentifier($objectId);

            if (!$identifier) {
                return $this->error('Bad Request', 'No valid identifier found for barcode generation.', 400);
            }

            $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
            $qrUrl = url('/' . ($slug ?? ''));

            return $this->success([
                'barcode' => [
                    'object_id' => $objectId,
                    'sector' => $identifier['sector'],
                    'identifier_type' => $identifier['type'],
                    'identifier_value' => $identifier['value'],
                    'barcodes' => [
                        'linear' => [
                            'svg' => $this->generateSimpleSvg($identifier['value']),
                        ],
                        'qr' => [
                            'url' => $qrUrl,
                            'svg' => sprintf(
                                '<img src="https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=%s" alt="QR" />',
                                urlencode($qrUrl)
                            ),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v2/identifiers/types/{objectId}
     *
     * Get available identifier types for a given object based on its sector.
     */
    public function types(int $objectId): JsonResponse
    {
        if ($objectId < 1) {
            return $this->error('Bad Request', 'Invalid object ID.', 400);
        }

        $object = DB::table('information_object')->where('id', $objectId)->first();
        if (!$object) {
            return $this->error('Not Found', 'Object not found.', 404);
        }

        try {
            $sector = $this->identifierService->detectObjectSector($objectId);
            $types = $this->identifierService->getIdentifierTypesForSector($sector);

            return $this->success([
                'object_id' => $objectId,
                'sector' => $sector,
                'types' => $types,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v2/identifiers/all/{objectId}
     *
     * Get all identifiers for a given object including minted DOIs.
     */
    public function all(int $objectId): JsonResponse
    {
        if ($objectId < 1) {
            return $this->error('Bad Request', 'Invalid object ID.', 400);
        }

        $object = DB::table('information_object')->where('id', $objectId)->first();
        if (!$object) {
            return $this->error('Not Found', 'Object not found.', 404);
        }

        try {
            $identifiers = $this->identifierService->getAllIdentifiers($objectId);
            $sector = $this->identifierService->detectObjectSector($objectId);

            return $this->success([
                'object_id' => $objectId,
                'sector' => $sector,
                'identifiers' => $identifiers,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Internal Error', $e->getMessage(), 500);
        }
    }

    /**
     * Generate a simple SVG barcode representation.
     */
    private function generateSimpleSvg(string $data): string
    {
        $width = 200;
        $height = 50;
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">',
            $width,
            $height + 20
        );
        $svg .= '<rect width="100%" height="100%" fill="white"/>';

        $x = 10;
        foreach (str_split($data) as $char) {
            $barWidth = (ord($char) % 3) + 1;
            $svg .= sprintf(
                '<rect x="%d" y="0" width="%d" height="%d" fill="black"/>',
                $x,
                $barWidth,
                $height
            );
            $x += $barWidth + 2;
        }

        $svg .= sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-family="monospace" font-size="10">%s</text>',
            $width / 2,
            $height + 15,
            htmlspecialchars($data)
        );
        $svg .= '</svg>';

        return $svg;
    }
}
