<?php
/**
 * Heratio - per-digital-object content-credentials detail + embeddable badge
 * (issue #1209 truth anchor / #1201 provenance-authenticity layer).
 *
 * Where AuthenticityController answers the institution-level question and
 * VerifyController answers it per information object, this controller answers
 * it for ONE digital object (a single master image / scan / file): it renders
 * the object's content-credentials chain in plain language and exposes a
 * compact, CORS-open verify badge (JSON + SVG) so the authenticity verdict can
 * travel with the object on any third-party page via a plain <img> embed.
 *
 * It reuses the package's existing manifest reader - ProvenanceRecordService
 * (verifyRecord / listForDigitalObject) - and never shells out to c2patool or
 * reimplements signing/verification. Every path degrades gracefully: an
 * unknown object is a 404; an object with no manifest is the neutral "no
 * content credentials" state (NOT an error); a reader fault falls back to the
 * neutral state and is logged. The badge endpoints never 500 - worst case they
 * return the neutral badge.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\ProvenanceRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

final class VerifyObjectController extends Controller
{
    /** The three human-facing states the whole surface collapses to. */
    private const STATE_VERIFIED = 'verified';   // green: signed AND signature checks out
    private const STATE_INVALID  = 'invalid';    // red:   signed but a signature/hash failed (tampered)
    private const STATE_ABSENT   = 'absent';     // neutral: no content credentials recorded/signed

    public function __construct(private ProvenanceRecordService $service)
    {
    }

    /**
     * Public provenance-chain detail page for a single digital object.
     * Unknown object -> 404; object with no manifest -> neutral state.
     */
    public function detail(int $digitalObjectId): View|Response
    {
        $object = $this->loadDigitalObject($digitalObjectId);
        if ($object === null) {
            // Genuinely unknown object: a 404 is the honest answer.
            abort(404);
        }

        $analysis = $this->analyse($digitalObjectId);

        return view('ahg-c2pa::verify.object', [
            'object'    => $object,
            'state'     => $analysis['state'],
            'chain'     => $analysis['chain'],
            'signer'    => $analysis['signer'],
            'signedAt'  => $analysis['signed_at'],
            'counts'    => $analysis['counts'],
            'verifyUrl' => $this->verifyUrl($digitalObjectId),
            'badgeJson' => $this->badgeUrl($digitalObjectId, 'json'),
            'badgeSvg'  => $this->badgeUrl($digitalObjectId, 'svg'),
        ]);
    }

    /**
     * Compact, CORS-open verify badge as JSON for programmatic / embed use.
     * Read-only GET. Never 500s - any fault yields the neutral badge.
     */
    public function badgeJson(int $digitalObjectId): JsonResponse
    {
        $payload = $this->neutralBadgePayload($digitalObjectId);
        try {
            if ($this->loadDigitalObject($digitalObjectId) !== null) {
                $analysis = $this->analyse($digitalObjectId);
                $payload = [
                    'status'           => $analysis['state'],
                    'signer'           => $analysis['signer'],
                    'signed_at'        => $analysis['signed_at'],
                    'assertions_count' => $analysis['counts']['assertions'],
                    'verify_url'       => $this->verifyUrl($digitalObjectId),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('c2pa: verify badge.json fell back to neutral', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
        }

        return response()
            ->json($payload)
            ->withHeaders($this->corsHeaders());
    }

    /**
     * Compact, self-contained SVG verify badge generated server-side from the
     * status. Any page can <img>-embed it. Never 500s - any fault yields the
     * neutral SVG.
     */
    public function badgeSvg(int $digitalObjectId): Response
    {
        $state = self::STATE_ABSENT;
        try {
            if ($this->loadDigitalObject($digitalObjectId) !== null) {
                $state = $this->analyse($digitalObjectId)['state'];
            }
        } catch (Throwable $e) {
            Log::warning('c2pa: verify badge.svg fell back to neutral', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
        }

        $svg = $this->renderSvg($state);

        return response($svg, 200, array_merge($this->corsHeaders(), [
            'Content-Type'  => 'image/svg+xml; charset=utf-8',
            // Embedders cache lightly; the live truth is the detail page.
            'Cache-Control' => 'public, max-age=300',
        ]));
    }

    /* ----------------------------------------------------------------- *
     * Analysis - turns the stored records into one verdict + a chain.
     * ----------------------------------------------------------------- */

    /**
     * Read this digital object's provenance records and collapse them to a
     * single verdict plus a plain-language chain. Never throws; on a reader
     * fault it returns the neutral (absent) shape so callers stay 200/neutral.
     *
     * @return array{
     *     state: string,
     *     signer: ?string,
     *     signed_at: ?string,
     *     counts: array{records:int, signed:int, verified:int, invalid:int, assertions:int},
     *     chain: list<array<string,mixed>>
     * }
     */
    private function analyse(int $digitalObjectId): array
    {
        $empty = [
            'state'     => self::STATE_ABSENT,
            'signer'    => null,
            'signed_at' => null,
            'counts'    => ['records' => 0, 'signed' => 0, 'verified' => 0, 'invalid' => 0, 'assertions' => 0],
            'chain'     => [],
        ];

        try {
            $records = $this->service->listForDigitalObject($digitalObjectId);
        } catch (Throwable $e) {
            Log::warning('c2pa: listForDigitalObject failed; neutral state', [
                'digital_object_id' => $digitalObjectId,
                'err'               => $e->getMessage(),
            ]);
            return $empty;
        }

        if ($records === []) {
            return $empty;
        }

        $chain          = [];
        $signedCount    = 0;
        $verifiedCount  = 0;
        $invalidCount   = 0;
        $assertionCount = 0;
        $signer         = null;
        $signedAt       = null;

        foreach ($records as $record) {
            $isSigned = ($record->manifest_id ?? null) !== null;

            $verification = ['status' => 'unsigned', 'ok' => false, 'errors' => [], 'manifest' => null, 'kid' => null];
            try {
                $verification = $this->service->verifyRecord((int) $record->id);
            } catch (Throwable $e) {
                // A single bad record must not blow up the chain; treat it as
                // a failed (invalid) signature on that one entry.
                Log::info('c2pa: verifyRecord threw; entry marked invalid', [
                    'provenance_id' => $record->id ?? null,
                    'err'           => $e->getMessage(),
                ]);
                $verification = ['status' => 'failed', 'ok' => false, 'errors' => [$e->getMessage()], 'manifest' => null, 'kid' => null];
            }

            $status = (string) ($verification['status'] ?? 'unsigned');
            if ($isSigned) {
                $signedCount++;
                if ($status === 'verified') {
                    $verifiedCount++;
                    $signer  ??= $this->cleanStr($verification['kid'] ?? null);
                    $signedAt ??= $this->recordSignedAt($record);
                } else {
                    $invalidCount++;
                }
            }

            $assertions = $this->readAssertions($verification['manifest'] ?? null);
            $assertionCount += count($assertions);

            $chain[] = [
                'record'          => $record,
                'verification'    => $verification,
                'entry_state'     => $this->entryState($isSigned, $status),
                'assertions'      => $assertions,
                'inference_steps' => $this->decodeSteps($record),
            ];
        }

        // Whole-object verdict: any signed-but-failing entry makes the object
        // "invalid" (red); otherwise any verified entry makes it "verified"
        // (green); otherwise it is "absent" (neutral - documented but unsigned,
        // which reads as "no content credentials" to a verifier).
        if ($invalidCount > 0) {
            $state = self::STATE_INVALID;
        } elseif ($verifiedCount > 0) {
            $state = self::STATE_VERIFIED;
        } else {
            $state = self::STATE_ABSENT;
        }

        return [
            'state'     => $state,
            'signer'    => $signer,
            'signed_at' => $signedAt,
            'counts'    => [
                'records'    => count($records),
                'signed'     => $signedCount,
                'verified'   => $verifiedCount,
                'invalid'    => $invalidCount,
                'assertions' => $assertionCount,
            ],
            'chain'     => $chain,
        ];
    }

    /**
     * Per-entry visual state for the chain rows.
     */
    private function entryState(bool $isSigned, string $verifyStatus): string
    {
        if (!$isSigned) {
            return self::STATE_ABSENT;
        }
        return $verifyStatus === 'verified' ? self::STATE_VERIFIED : self::STATE_INVALID;
    }

    /**
     * Flatten a verified/read manifest into a plain-language, ordered list of
     * what happened: the c2pa.actions.v2 actions (who/what tool/when), the
     * training-mining stance, and any standard-metadata or other assertions -
     * each as {kind, label, summary, when, software, params}. Tolerant of
     * partial/unknown manifests (forward-compatible with future C2PA labels).
     *
     * @param mixed $manifest the decoded manifest from verifyRecord(), or null
     * @return list<array<string,mixed>>
     */
    private function readAssertions(mixed $manifest): array
    {
        if (!is_array($manifest)) {
            return [];
        }
        $assertions = $manifest['assertions'] ?? null;
        if (!is_array($assertions)) {
            return [];
        }

        $out = [];
        foreach ($assertions as $a) {
            if (!is_array($a)) {
                continue;
            }
            $label = (string) ($a['label'] ?? '');
            $data  = is_array($a['data'] ?? null) ? $a['data'] : [];

            if (str_starts_with($label, 'c2pa.actions')) {
                $actions = is_array($data['actions'] ?? null) ? $data['actions'] : [];
                foreach ($actions as $action) {
                    if (!is_array($action)) {
                        continue;
                    }
                    $out[] = $this->describeAction($label, $action);
                }
                continue;
            }

            if ($label === 'c2pa.training-mining') {
                $out[] = [
                    'kind'     => 'training-mining',
                    'label'    => $label,
                    'summary'  => $this->trainingMiningSummary($data),
                    'when'     => null,
                    'software' => null,
                    'params'   => [],
                ];
                continue;
            }

            if (str_starts_with($label, 'stds.')) {
                $out[] = [
                    'kind'     => 'metadata',
                    'label'    => $label,
                    'summary'  => $this->metadataSummary($label),
                    'when'     => null,
                    'software' => null,
                    'params'   => $this->scalarParams($data),
                ];
                continue;
            }

            // Unknown / future label: surface it honestly rather than hide it.
            $out[] = [
                'kind'     => 'other',
                'label'    => $label,
                'summary'  => $label,
                'when'     => null,
                'software' => null,
                'params'   => $this->scalarParams($data),
            ];
        }

        return $out;
    }

    /**
     * Describe one c2pa action in plain language.
     *
     * @param array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function describeAction(string $label, array $action): array
    {
        $name = (string) ($action['action'] ?? 'action');
        $sw   = $action['softwareAgent'] ?? null;
        $software = null;
        if (is_array($sw)) {
            $swName = $this->cleanStr($sw['name'] ?? null);
            $swVer  = $this->cleanStr($sw['version'] ?? null);
            $software = trim((string) $swName . ($swVer !== null ? ' ' . $swVer : '')) ?: null;
        }

        $map = [
            'c2pa.created'       => 'Digitised / created',
            'c2pa.edited'        => 'Edited',
            'c2pa.placed'        => 'Placed into this asset',
            'placed'             => 'Placed into this asset',
            'ai-generated'       => 'Generated by AI',
            'ai-assisted'        => 'AI-assisted edit',
            'c2pa.opened'        => 'Opened',
            'c2pa.converted'     => 'Converted',
        ];
        $summary = $map[$name] ?? ucfirst(str_replace(['c2pa.', '-', '_'], ['', ' ', ' '], $name));

        $params = is_array($action['parameters'] ?? null) ? $this->scalarParams($action['parameters']) : [];
        $isAi = str_contains($name, 'ai-') || isset($params['model_id']) || isset($params['inferenceStep']);

        return [
            'kind'     => $isAi ? 'ai' : 'action',
            'label'    => $label,
            'action'   => $name,
            'summary'  => $summary,
            'when'     => $this->cleanStr($action['when'] ?? null),
            'software' => $software,
            'params'   => $params,
        ];
    }

    /**
     * Reduce an assertion-data array to its scalar (string/number/bool) leaf
     * values keyed by name, for compact display. Nested arrays are skipped.
     *
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function scalarParams(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $out[(string) $k] = is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function trainingMiningSummary(array $data): string
    {
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        $anyAllowed = false;
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['use'] ?? null) === 'allowed') {
                $anyAllowed = true;
                break;
            }
        }
        return $anyAllowed
            ? 'AI training / data mining is permitted on this asset.'
            : 'AI training and data mining are not permitted without a licence.';
    }

    private function metadataSummary(string $label): string
    {
        return match ($label) {
            'stds.exif' => 'Embedded EXIF camera/capture metadata.',
            'stds.iptc' => 'Embedded IPTC descriptive / rights metadata.',
            'stds.xmp'  => 'Embedded XMP (Dublin Core / rights) metadata.',
            default     => 'Embedded standard metadata.',
        };
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

    /* ----------------------------------------------------------------- *
     * Object identity + URL helpers.
     * ----------------------------------------------------------------- */

    /**
     * Load the public-safe identity of a digital object and its owning record.
     * Returns null when the digital_object row is absent (-> 404). Best-effort
     * for the joined IO fields - a missing IO leaves them null, not an error.
     */
    private function loadDigitalObject(int $digitalObjectId): ?object
    {
        if ($digitalObjectId <= 0 || !Schema::hasTable('digital_object')) {
            return null;
        }

        $do = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first(['id', 'object_id', 'name', 'mime_type']);
        if ($do === null) {
            return null;
        }

        $do->io_id       = isset($do->object_id) ? (int) $do->object_id : null;
        $do->io_title    = null;
        $do->io_ref      = null;
        $do->io_slug     = null;

        if ($do->io_id !== null && $do->io_id > 0 && Schema::hasTable('information_object')) {
            $io = DB::table('information_object')->where('id', $do->io_id)->first(['identifier']);
            if ($io !== null) {
                $do->io_ref = $io->identifier ?? null;
            }
            if (Schema::hasTable('information_object_i18n')) {
                $i18n = DB::table('information_object_i18n')
                    ->where('id', $do->io_id)
                    ->orderByRaw("culture = 'en' DESC")
                    ->first(['title']);
                $do->io_title = $i18n->title ?? null;
            }
            if (Schema::hasTable('slug')) {
                $slug = DB::table('slug')->where('object_id', $do->io_id)->first(['slug']);
                $do->io_slug = $slug->slug ?? null;
            }
        }

        return $do;
    }

    /**
     * The signed-at timestamp for a record (updated_at preferred, created_at
     * fallback), as a plain string or null.
     */
    private function recordSignedAt(object $record): ?string
    {
        $ts = $record->updated_at ?? $record->created_at ?? null;
        return is_string($ts) && $ts !== '' ? $ts : null;
    }

    private function verifyUrl(int $digitalObjectId): string
    {
        return $this->safeUrl('/verify/' . $digitalObjectId);
    }

    private function badgeUrl(int $digitalObjectId, string $ext): string
    {
        // SVG is served extensionless ('badge'): a *.svg path is grabbed by nginx
        // as a static file and 404s before Laravel. JSON keeps its .json suffix
        // (nginx passes it through). Both still render image/svg+xml / JSON.
        $suffix = $ext === 'svg' ? 'badge' : ('badge.' . $ext);

        return $this->safeUrl('/verify/' . $digitalObjectId . '/' . $suffix);
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

    /**
     * CORS-open, read-only headers for the badge endpoints so any page can
     * fetch / <img>-embed them.
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

    /**
     * @return array{status:string, signer:null, signed_at:null, assertions_count:int, verify_url:string}
     */
    private function neutralBadgePayload(int $digitalObjectId): array
    {
        return [
            'status'           => self::STATE_ABSENT,
            'signer'           => null,
            'signed_at'        => null,
            'assertions_count' => 0,
            'verify_url'       => $this->verifyUrl($digitalObjectId),
        ];
    }

    /* ----------------------------------------------------------------- *
     * SVG badge - self-contained, status-coloured, well-formed.
     * ----------------------------------------------------------------- */

    /**
     * Render a small self-contained SVG badge for the given state. No external
     * fonts/refs; sized by a fixed monospace-ish metric so it renders the same
     * everywhere it is <img>-embedded.
     */
    private function renderSvg(string $state): string
    {
        [$colour, $label] = match ($state) {
            self::STATE_VERIFIED => ['#1a7f37', "Content Credentials \u{2713}"],
            self::STATE_INVALID  => ['#cf222e', "Content Credentials \u{2717}"],
            default              => ['#6c757d', 'No Content Credentials'],
        };

        $leftText  = 'Verify';
        $rightText = (string) $label;

        // Crude but stable width metric (~6.2px per char at 11px) so the SVG is
        // self-sizing without a font server.
        $leftW  = (int) max(46, 14 + strlen($leftText) * 7);
        $rightW = (int) max(120, 14 + mb_strlen($rightText) * 7);
        $total  = $leftW + $rightW;
        $height = 20;

        $leftMid  = (int) ($leftW / 2);
        $rightMid = $leftW + (int) ($rightW / 2);

        $eLabel = $this->xml($rightText);
        $eLeft  = $this->xml($leftText);
        $eState = $this->xml($state);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$total}" height="{$height}" role="img" aria-label="Content Credentials: {$eState}">
  <title>Content Credentials: {$eState}</title>
  <linearGradient id="s" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="r"><rect width="{$total}" height="{$height}" rx="3"/></clipPath>
  <g clip-path="url(#r)">
    <rect width="{$leftW}" height="{$height}" fill="#444"/>
    <rect x="{$leftW}" width="{$rightW}" height="{$height}" fill="{$colour}"/>
    <rect width="{$total}" height="{$height}" fill="url(#s)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,DejaVu Sans,Geneva,sans-serif" font-size="11">
    <text x="{$leftMid}" y="14" fill="#010101" fill-opacity=".3">{$eLeft}</text>
    <text x="{$leftMid}" y="13">{$eLeft}</text>
    <text x="{$rightMid}" y="14" fill="#010101" fill-opacity=".3">{$eLabel}</text>
    <text x="{$rightMid}" y="13">{$eLabel}</text>
  </g>
</svg>
SVG;
    }

    private function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function cleanStr(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
