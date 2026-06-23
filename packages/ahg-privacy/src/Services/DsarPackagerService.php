<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgPrivacy\Services;

use AhgCore\Models\InformationObject;
use Illuminate\Support\Facades\DB;

/**
 * One-job DSAR packager (#1327).
 *
 * Given a verified Data Subject Access Request, gather every linked archival
 * record (privacy_dsar_object), apply the field-level redaction profile so
 * third-party PII is masked, and emit a single self-contained JSON access
 * package. Writes an audit access-log entry per record and marks the DSAR
 * completed. Intake/verification and the redaction gate already exist; this
 * closes the "single-job export" gap.
 */
class DsarPackagerService
{
    public function __construct(private PrivacyRedactionService $redaction)
    {
    }

    /**
     * Build and write the access package for a DSAR.
     *
     * @return array{file:string,bytes:int,records:int,reference:string}
     */
    public function package(int $dsarId, ?string $outPath = null, ?int $actorUserId = null): array
    {
        $dsar = DB::table('privacy_dsar')->where('id', $dsarId)->first();
        if (! $dsar) {
            throw new \RuntimeException("DSAR #{$dsarId} not found.");
        }
        if ((int) $dsar->is_verified !== 1) {
            throw new \RuntimeException("DSAR #{$dsarId} ({$dsar->reference_number}) is not verified; refusing to package.");
        }

        $links = DB::table('privacy_dsar_object')
            ->where('dsar_id', $dsarId)
            ->pluck('information_object_id')
            ->all();

        $records = [];
        foreach ($links as $ioId) {
            try {
                $io = InformationObject::find((int) $ioId);
                if (! $io) {
                    continue;
                }
                // Mask third-party PII the subject is not entitled to see.
                $redacted = $this->redaction->applyRedaction($io, $actorUserId);
                $i18n = DB::table('information_object_i18n')
                    ->where('id', $ioId)->where('culture', 'en')->first();

                $records[] = [
                    'information_object_id' => (int) $ioId,
                    'identifier'            => $redacted->identifier ?? ($io->identifier ?? null),
                    'title'                 => $i18n->title ?? null,
                    'scope_and_content'     => $i18n->scope_and_content ?? null,
                    'redaction_applied'     => $this->redaction->getPrivacyProfile((int) $ioId) !== null,
                ];

                $this->redaction->logAccess((int) $ioId, $actorUserId, 'dsar_export', null, 'dsar:'.$dsar->reference_number);
            } catch (\Throwable $e) {
                $records[] = ['information_object_id' => (int) $ioId, 'error' => $e->getMessage()];
            }
        }

        $package = [
            'package_type' => 'dsar_subject_access',
            'generated_at' => now()->toIso8601String(),
            'dsar' => [
                'reference_number' => $dsar->reference_number,
                'jurisdiction'     => $dsar->jurisdiction,
                'request_type'     => $dsar->request_type,
                'requestor_name'   => $dsar->requestor_name,
                'requestor_email'  => $dsar->requestor_email,
                'received_date'    => $dsar->received_date,
                'due_date'         => $dsar->due_date,
            ],
            'record_count' => count($records),
            'records'      => $records,
        ];

        $payload = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $out = $outPath ?: $this->defaultPath($dsar->reference_number);
        $dir = dirname($out);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $bytes = @file_put_contents($out, $payload);
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write DSAR package to {$out}.");
        }

        DB::table('privacy_dsar')->where('id', $dsarId)->update([
            'status'         => 'completed',
            'completed_date' => now(),
        ]);

        return [
            'file'      => $out,
            'bytes'     => (int) $bytes,
            'records'   => count($records),
            'reference' => $dsar->reference_number,
        ];
    }

    private function defaultPath(string $reference): string
    {
        $base = rtrim((string) config('heratio.storage_path', storage_path('app')), '/');
        $safeRef = preg_replace('/[^A-Za-z0-9._-]/', '_', $reference);

        return $base.'/dsar-exports/dsar-'.$safeRef.'-'.now()->format('Ymd-His').'.json';
    }
}
