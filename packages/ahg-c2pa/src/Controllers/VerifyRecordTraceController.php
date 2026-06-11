<?php
/**
 * Heratio - record-level provenance trace surface (provenance roadmap
 * trace-endpoint slice, building on issues #1201 and #1209).
 *
 * Where VerifyObjectController answers "is this one FILE real?", this
 * controller answers "show me everything that ever happened to this RECORD":
 * it aggregates the content-credentials provenance of every digital object on
 * an archival record into one chronological trace - capture / digitisation,
 * edits, AI-inference steps and signature / verification status - in both a
 * human-readable page and a machine-readable JSON companion, plus one
 * record-level authenticity summary (verified / partially / unsigned /
 * invalid).
 *
 * It delegates entirely to ProvenanceTraceService, which in turn reuses the
 * package's existing manifest reader (ProvenanceRecordService) - nothing here
 * re-reads a manifest, shells to c2patool or reimplements verification. Every
 * path degrades gracefully: an unknown record is a 404; a record with no
 * provenance is the dignified "no provenance recorded yet" state (NOT an
 * error); any reader fault falls back to that neutral state. The JSON endpoint
 * never 500s (an unknown record is a clean 404 JSON body).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\ProvenanceTraceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class VerifyRecordTraceController extends Controller
{
    public function __construct(private ProvenanceTraceService $service)
    {
    }

    /**
     * Human-readable record-level provenance trace page.
     * Unknown record -> 404; record with no provenance -> neutral state.
     */
    public function page(int $ioId): View|Response
    {
        $trace = $this->safeTrace($ioId);

        if ($trace['object'] === null) {
            // Genuinely unknown record: a 404 is the honest answer.
            abort(404);
        }

        return view('ahg-c2pa::verify.trace', [
            'object'        => $trace['object'],
            'summary'       => $trace['summary'],
            'summaryReason' => $trace['summary_reason'],
            'counts'        => $trace['counts'],
            'events'        => $trace['events'],
            'groups'        => $trace['groups'],
            'generatedAt'   => $trace['generated_at'],
            'jsonUrl'       => $this->safeUrl('/verify/record/' . $ioId . '/trace.json'),
        ]);
    }

    /**
     * Machine-readable companion. CORS-open, read-only GET. Never 500s: an
     * unknown record is a clean 404 JSON body; any reader fault yields the
     * dignified empty trace with HTTP 200.
     */
    public function json(int $ioId): JsonResponse
    {
        try {
            $trace = $this->service->trace($ioId);
        } catch (Throwable $e) {
            Log::warning('c2pa trace: json endpoint fell back to empty trace', [
                'information_object_id' => $ioId,
                'err'                   => $e->getMessage(),
            ]);
            $trace = ['object' => null];
        }

        if (($trace['object'] ?? null) === null) {
            return response()
                ->json([
                    'information_object_id' => $ioId,
                    'found'                 => false,
                    'summary'               => ProvenanceTraceService::SUMMARY_NONE,
                    'message'               => 'No record found for this id, or no provenance recorded yet.',
                ], 404)
                ->withHeaders($this->corsHeaders());
        }

        $object = $trace['object'];

        return response()
            ->json([
                'found'                 => true,
                'information_object_id' => (int) ($object->id ?? $ioId),
                'record'                => [
                    'title'      => $object->title ?? null,
                    'identifier' => $object->identifier ?? null,
                    'slug'       => $object->slug ?? null,
                ],
                'summary'               => $trace['summary'],
                'summary_reason'        => $trace['summary_reason'],
                'counts'                => $trace['counts'],
                'events'                => $trace['events'],
                'digital_objects'       => $trace['groups'],
                'generated_at'          => $trace['generated_at'],
            ])
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Build the trace, never letting a reader fault escape: on any throw,
     * return the dignified empty trace (object preserved when we have it would
     * require a successful load, so on a hard fault we return a null object and
     * the page 404s, which is the safe answer).
     *
     * @return array<string,mixed>
     */
    private function safeTrace(int $ioId): array
    {
        try {
            return $this->service->trace($ioId);
        } catch (Throwable $e) {
            Log::warning('c2pa trace: page fell back; trace build threw', [
                'information_object_id' => $ioId,
                'err'                   => $e->getMessage(),
            ]);

            return ['object' => null];
        }
    }

    /**
     * CORS-open, read-only headers so any page can fetch the JSON trace.
     *
     * @return array<string,string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'X-Content-Type-Options'       => 'nosniff',
        ];
    }

    private function safeUrl(string $path): string
    {
        if (function_exists('url')) {
            try {
                return (string) url($path);
            } catch (Throwable) {
                // fall through to the bare path
            }
        }

        return $path;
    }
}
