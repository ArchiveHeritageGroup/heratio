<?php
/**
 * Heratio - public "verify authenticity" page (issue #1209, north star).
 *
 * The trust-anchor surface: a public, read-only page that, given an
 * information object (by slug or numeric id), shows its authenticity chain -
 * every digitisation-provenance record's content credentials and the LIVE
 * Ed25519 signature verification result (verified / tampered / unsigned). It
 * reuses ProvenanceRecordService (issue #1201); it never reimplements signing
 * or verification. Plain language so a non-technical reader can answer the one
 * question that matters: "is this real?".
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\ProvenanceRecordService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class VerifyController extends Controller
{
    public function __construct(private ProvenanceRecordService $service)
    {
    }

    /**
     * Public authenticity page for an information object addressed by its
     * (possibly multi-segment) slug.
     */
    public function bySlug(string $slug): View
    {
        $io = $this->resolveBySlug($slug);

        return $this->render($io, $slug);
    }

    /**
     * Public authenticity page for an information object addressed by id.
     * Numeric route is namespaced under /verify/id/ so it never collides with
     * a numeric slug.
     */
    public function byId(int $informationObjectId): View
    {
        $io = $this->loadObject($informationObjectId);

        return $this->render($io, (string) $informationObjectId);
    }

    /**
     * Build the authenticity chain for the resolved object (or a "not found"
     * page when the object / slug does not resolve).
     */
    private function render(?object $io, string $reference): View
    {
        if ($io === null) {
            return view('ahg-c2pa::verify.show', [
                'object'    => null,
                'reference' => $reference,
                'chain'     => [],
                'summary'   => null,
            ]);
        }

        $records = $this->service->listForObject((int) $io->id);

        $chain = [];
        $verifiedCount = 0;
        $signedCount = 0;
        foreach ($records as $record) {
            $verification = $this->service->verifyRecord((int) $record->id);

            if (($verification['status'] ?? null) === 'verified') {
                $verifiedCount++;
            }
            if (($record->manifest_id ?? null) !== null) {
                $signedCount++;
            }

            $chain[] = [
                'record'          => $record,
                'verification'    => $verification,
                'inference_steps' => $this->decodeSteps($record),
            ];
        }

        return view('ahg-c2pa::verify.show', [
            'object'    => $io,
            'reference' => $reference,
            'chain'     => $chain,
            'summary'   => [
                'total'    => count($records),
                'signed'   => $signedCount,
                'verified' => $verifiedCount,
                'tampered' => $signedCount - $verifiedCount,
            ],
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function decodeSteps(object $record): array
    {
        if (!isset($record->inference_steps) || !is_string($record->inference_steps) || $record->inference_steps === '') {
            return [];
        }
        $decoded = json_decode($record->inference_steps, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve an information object by its slug (supports multi-segment slugs).
     */
    private function resolveBySlug(string $slug): ?object
    {
        $slug = trim($slug, '/');
        if ($slug === '' || !Schema::hasTable('slug')) {
            return null;
        }

        $row = DB::table('slug')->where('slug', $slug)->first(['object_id']);
        if ($row === null || !isset($row->object_id)) {
            return null;
        }

        return $this->loadObject((int) $row->object_id);
    }

    /**
     * Load the public-safe identity of an information object: id, identifier,
     * title (en-preferred) and slug. Returns null when the row is absent.
     */
    private function loadObject(int $informationObjectId): ?object
    {
        if (!Schema::hasTable('information_object')) {
            return null;
        }
        $io = DB::table('information_object')->where('id', $informationObjectId)->first(['id', 'identifier']);
        if ($io === null) {
            return null;
        }
        if (Schema::hasTable('information_object_i18n')) {
            $i18n = DB::table('information_object_i18n')
                ->where('id', $informationObjectId)
                ->orderByRaw("culture = 'en' DESC")
                ->first(['title']);
            $io->title = $i18n->title ?? null;
        }
        if (Schema::hasTable('slug')) {
            $slug = DB::table('slug')->where('object_id', $informationObjectId)->first(['slug']);
            $io->slug = $slug->slug ?? null;
        }
        return $io;
    }
}
