<?php
/**
 * Heratio - public "check content credentials" tool (deepens #1209 / #1201).
 *
 * The institution-level /verify dashboard, the per-record /verify/{slug} pages
 * and the per-object /verify/{digitalObjectId} page all answer "is THIS record
 * in our custody authentic?" - they read provenance that Heratio itself wrote.
 *
 * This controller answers the broader, society-facing question: a visitor drops
 * in ANY image - one that did NOT come from this repository - and gets its C2PA
 * content-credentials verdict in plain language. No login, no DB writes, the
 * upload is never persisted.
 *
 * It reuses the package's own verifier - C2paService::verify() - and never
 * shells out to c2patool or reimplements signing/verification. The challenge a
 * file-drop adds over the per-object page is getting a manifest OUT of an
 * arbitrary uploaded file in pure PHP. We recover a manifest two honest ways,
 * both without the native binary:
 *
 *   1. the upload IS a C2PA manifest / .c2pa.json sidecar (JSON with a claim +
 *      assertions) - parse and verify it directly; or
 *   2. the upload carries a recoverable sidecar-style JSON manifest in its bytes
 *      (the canonical JSON form ManifestBuilder::toCanonicalJson() writes) - we
 *      scan the raw bytes for a balanced JSON object that has both a "claim" and
 *      an "assertions" key and verify that.
 *
 * A manifest embedded ONLY as native JUMBF/CBOR (which needs c2patool to read
 * back) is, correctly, reported as the neutral "no content credentials we can
 * read" state rather than a false negative dressed up as an error. Every odd /
 * truncated / non-image file lands in that same neutral state - this surface
 * never 500s on a bad upload.
 *
 * Verdict collapses to the same three states the per-object page uses:
 *   verified -> green   : a manifest was found AND its hashes + Ed25519 claim
 *                         signature check out;
 *   invalid  -> red     : a manifest was found but a hash/signature failed
 *                         (tampered / unknown signer);
 *   absent   -> neutral : no content credentials could be read from the file.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\C2paService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

final class PublicCheckController extends Controller
{
    /** The three human-facing states the whole surface collapses to. */
    private const STATE_VERIFIED = 'verified';   // green
    private const STATE_INVALID  = 'invalid';    // red
    private const STATE_ABSENT   = 'absent';     // neutral

    /** Hard ceiling on the upload (also enforced by the validator). */
    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB

    /** How far into the file we scan for a recoverable JSON manifest. */
    private const SCAN_LIMIT_BYTES = 8 * 1024 * 1024; // 8 MB

    /**
     * GET /verify/check - the drop-zone page. No result yet.
     */
    public function form(): View
    {
        return view('ahg-c2pa::verify.check', [
            'state'    => null,
            'result'   => null,
            'fileName' => null,
            'errorMsg' => null,
        ]);
    }

    /**
     * POST /verify/check - verify an uploaded image's content credentials.
     *
     * Validates (image mime, <= 25 MB), writes the bytes to a temp path, reads
     * any recoverable C2PA manifest, verifies it through C2paService::verify(),
     * and renders the verdict. The temp file is always deleted in a finally.
     * Never persists the upload; no DB writes. An odd/empty/non-image file
     * yields the neutral "no content credentials" state, not an error page.
     */
    public function check(Request $request): View|RedirectResponse
    {
        // Validate the upload. A genuine validation failure (missing file, wrong
        // type, too big) bounces back to the form with a message - that is a
        // user error, not a content-credentials verdict.
        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/tiff,image/webp,image/gif,image/avif,image/jp2',
                'max:' . (int) (self::MAX_BYTES / 1024), // Laravel max() is in KB
            ],
        ], [
            'file.required'  => __('Please choose an image to check.'),
            'file.image'     => __('That file is not an image. Upload a JPEG, PNG, TIFF or WebP image.'),
            'file.mimetypes' => __('That image format is not supported. Upload a JPEG, PNG, TIFF or WebP image.'),
            'file.max'       => __('That file is too large. The limit is 25 MB.'),
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('c2pa_check_error', $validator->errors()->first('file'));
        }

        $upload   = $request->file('file');
        $fileName = (string) $upload->getClientOriginalName();
        $tmpPath  = null;

        try {
            // Persist to a temp path under the system temp dir ONLY. Never under
            // storage/app or any served path - this upload is throwaway.
            $tmpPath = tempnam(sys_get_temp_dir(), 'c2pa-check-');
            if ($tmpPath === false) {
                $tmpPath = sys_get_temp_dir() . '/c2pa-check-' . bin2hex(random_bytes(8));
            }
            // Laravel's move() wraps move_uploaded_file(): drop into the temp dir.
            $upload->move(dirname($tmpPath), basename($tmpPath));

            $result = $this->verifyFile($tmpPath, $fileName);

            return view('ahg-c2pa::verify.check', [
                'state'    => $result['state'],
                'result'   => $result,
                'fileName' => $fileName,
                'errorMsg' => null,
            ]);
        } catch (Throwable $e) {
            // Defensive: a fault reading the file must never 500. Show the
            // neutral state and log for the operator.
            Log::warning('c2pa: public check fell back to neutral after a fault', [
                'file' => $fileName,
                'err'  => $e->getMessage(),
            ]);

            return view('ahg-c2pa::verify.check', [
                'state'    => self::STATE_ABSENT,
                'result'   => $this->neutralResult(),
                'fileName' => $fileName,
                'errorMsg' => null,
            ]);
        } finally {
            // ALWAYS remove the throwaway upload, on every path out.
            if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /* ----------------------------------------------------------------- *
     * Verification - recover a manifest from the file, then C2paService.
     * ----------------------------------------------------------------- */

    /**
     * Read any recoverable manifest from $path and collapse it to a verdict
     * plus a plain-language step list. Never throws - any read fault returns
     * the neutral shape so the caller stays 200/neutral.
     *
     * @return array{
     *     state: string,
     *     signer: ?string,
     *     errors: list<string>,
     *     assertions: list<array<string,mixed>>,
     *     found_manifest: bool
     * }
     */
    private function verifyFile(string $path, string $fileName): array
    {
        $manifest = $this->extractManifest($path);
        if ($manifest === null) {
            // No readable content credentials. This is NOT an error - the
            // honest, common answer for an ordinary photo.
            return $this->neutralResult();
        }

        // Reuse the package verifier verbatim: re-hash assertions + verify the
        // Ed25519 claim signature under the same kid -> public-key resolver the
        // per-record verifier (ProvenanceRecordService::verifyRecord) uses.
        $verification = C2paService::verify(
            $manifest,
            fn (string $kid): ?string => $this->resolvePublicKey($kid),
        );

        $state = $verification['ok'] ? self::STATE_VERIFIED : self::STATE_INVALID;

        return [
            'state'          => $state,
            'signer'         => $this->cleanStr($manifest['claim_signature']['kid'] ?? null),
            'errors'         => $verification['errors'],
            'assertions'     => $this->readAssertions($manifest),
            'found_manifest' => true,
        ];
    }

    /**
     * @return array{state:string, signer:null, errors:array{}, assertions:array{}, found_manifest:false}
     */
    private function neutralResult(): array
    {
        return [
            'state'          => self::STATE_ABSENT,
            'signer'         => null,
            'errors'         => [],
            'assertions'     => [],
            'found_manifest' => false,
        ];
    }

    /**
     * Recover a C2PA manifest from an uploaded file in pure PHP - WITHOUT the
     * native c2patool binary. Two paths, both reading the canonical JSON form
     * the package writes (ManifestBuilder::toCanonicalJson):
     *
     *   1. the whole file parses as a JSON object that looks like a manifest
     *      (a .c2pa.json sidecar or an exported signed-manifest JSON); or
     *   2. the file's bytes embed such a JSON object somewhere (e.g. inside a
     *      container) - we scan for a balanced {...} run that decodes to an
     *      object carrying both "claim" and "assertions".
     *
     * Returns the decoded manifest array, or null when none is recoverable
     * (the neutral state). Never throws.
     *
     * @return array<string,mixed>|null
     */
    private function extractManifest(string $path): ?array
    {
        if (!is_readable($path)) {
            return null;
        }
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            return null;
        }

        // Path 1: the upload IS a JSON manifest. Cheap on a small file; for a
        // large binary the json_decode just fails fast and we fall through.
        $head = @file_get_contents($path, false, null, 0, min((int) $size, 64));
        if (is_string($head) && preg_match('/^\s*\{/', $head) === 1) {
            $raw = @file_get_contents($path, false, null, 0, self::SCAN_LIMIT_BYTES);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if ($this->looksLikeManifest($decoded)) {
                    return $decoded;
                }
            }
        }

        // Path 2: scan the raw bytes for an embedded sidecar-style JSON
        // manifest. We read up to a bounded window so a huge file can't pin
        // memory, then find the first balanced JSON object that decodes to a
        // manifest. The canonical manifest always contains the literal token
        // "claim_signature", so we anchor the scan on it to stay cheap.
        $blob = @file_get_contents($path, false, null, 0, self::SCAN_LIMIT_BYTES);
        if (!is_string($blob) || $blob === '') {
            return null;
        }

        $anchor = strpos($blob, '"claim_signature"');
        if ($anchor === false) {
            $anchor = strpos($blob, '"manifest_label"');
        }
        if ($anchor === false) {
            return null;
        }

        // Walk backwards to the opening brace of the object that contains the
        // anchor, then forward, brace-matching, to its close.
        $start = strrpos(substr($blob, 0, $anchor), '{');
        if ($start === false) {
            return null;
        }

        $candidate = $this->balancedObjectFrom($blob, $start);
        if ($candidate === null) {
            return null;
        }

        $decoded = json_decode($candidate, true);
        return $this->looksLikeManifest($decoded) ? $decoded : null;
    }

    /**
     * Extract the balanced {...} object starting at $start in $blob, honouring
     * JSON string quoting / escapes so braces inside strings don't miscount.
     * Returns the substring or null if it never balances within the window.
     */
    private function balancedObjectFrom(string $blob, int $start): ?string
    {
        $depth    = 0;
        $inString = false;
        $escaped  = false;
        $len      = strlen($blob);

        for ($i = $start; $i < $len; $i++) {
            $ch = $blob[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($blob, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * A decoded value looks like a C2PA manifest when it is an object that
     * carries both an assertions array and a claim object. Tolerant of the
     * unsigned shape (no claim_signature) - verify() reports that as invalid
     * rather than us hiding it.
     */
    private function looksLikeManifest(mixed $decoded): bool
    {
        return is_array($decoded)
            && isset($decoded['assertions']) && is_array($decoded['assertions'])
            && isset($decoded['claim']) && is_array($decoded['claim']);
    }

    /* ----------------------------------------------------------------- *
     * Plain-language assertions - mirrors VerifyObjectController so the
     * file-drop verdict reads identically to the per-object page.
     * ----------------------------------------------------------------- */

    /**
     * Flatten a manifest into an ordered, plain-language list of what happened:
     * c2pa.actions (who / what tool / when), the training-mining stance, and any
     * standard-metadata / unknown assertions. Each entry is
     * {kind, label, summary, when, software, params}. Tolerant of partial /
     * unknown manifests (forward-compatible with future C2PA labels).
     *
     * @param array<string,mixed> $manifest
     * @return list<array<string,mixed>>
     */
    private function readAssertions(array $manifest): array
    {
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
                    if (is_array($action)) {
                        $out[] = $this->describeAction($label, $action);
                    }
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
                'summary'  => $label !== '' ? $label : __('Declaration'),
                'when'     => null,
                'software' => null,
                'params'   => $this->scalarParams($data),
            ];
        }

        return $out;
    }

    /**
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
            'c2pa.created'   => __('Digitised / created'),
            'c2pa.edited'    => __('Edited'),
            'c2pa.placed'    => __('Placed into this asset'),
            'placed'         => __('Placed into this asset'),
            'ai-generated'   => __('Generated by AI'),
            'ai-assisted'    => __('AI-assisted edit'),
            'c2pa.opened'    => __('Opened'),
            'c2pa.converted' => __('Converted'),
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
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function scalarParams(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $out[(string) $k] = is_bool($v) ? ($v ? __('yes') : __('no')) : (string) $v;
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
            ? __('AI training / data mining is permitted on this asset.')
            : __('AI training and data mining are not permitted without a licence.');
    }

    private function metadataSummary(string $label): string
    {
        return match ($label) {
            'stds.exif' => __('Embedded EXIF camera/capture metadata.'),
            'stds.iptc' => __('Embedded IPTC descriptive / rights metadata.'),
            'stds.xmp'  => __('Embedded XMP (Dublin Core / rights) metadata.'),
            default     => __('Embedded standard metadata.'),
        };
    }

    /* ----------------------------------------------------------------- *
     * Key resolution - identical policy to ProvenanceRecordService.
     * ----------------------------------------------------------------- */

    /**
     * Resolve a kid to its raw Ed25519 public key. Prefers the ai_inference_key
     * registry (shared with the EU AI Act chain), falls back to the on-disk
     * signing pubkey. A kid we cannot resolve simply fails the signature check
     * (-> invalid / red), which is the honest verdict for an unknown signer.
     */
    private function resolvePublicKey(string $kid): ?string
    {
        try {
            if (Schema::hasTable('ai_inference_key')) {
                $row = DB::table('ai_inference_key')->where('kid', $kid)->first(['public_key']);
                if ($row !== null && is_string($row->public_key) && $row->public_key !== '') {
                    return $row->public_key;
                }
            }
        } catch (Throwable) {
            // fall through to filesystem
        }

        if (!function_exists('storage_path')) {
            return null;
        }
        $pkPath = storage_path('keys/inference-signing.pk');
        if (!is_readable($pkPath)) {
            return null;
        }
        $raw = @file_get_contents($pkPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $candidateKid = substr(hash('sha256', $raw), 0, 16);
        return $candidateKid === $kid ? $raw : null;
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
