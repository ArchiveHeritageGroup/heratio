<?php

/**
 * DestructionCertificateService - Destruction certificate management for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DestructionCertificateService
{
    /**
     * Generate a destruction certificate for a disposition queue item.
     */
    public function generateCertificate(int $dispositionId, string $authorizedBy, string $method, ?string $witness = null): array
    {
        if (!Schema::hasTable('destruction_certificate')) {
            throw new \RuntimeException('Table destruction_certificate does not exist.');
        }

        // Get disposition queue item
        $disposition = DB::table('integrity_disposition_queue')
            ->where('id', $dispositionId)
            ->first();

        if (!$disposition) {
            throw new \RuntimeException('Disposition queue item #' . $dispositionId . ' not found.');
        }

        $culture = app()->getLocale();
        $ioId = $disposition->information_object_id;

        // Get IO title
        $ioTitle = DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', $culture)
            ->value('title') ?? 'Unknown';

        // Get repository name
        $repoName = DB::table('information_object')
            ->join('repository', 'information_object.repository_id', '=', 'repository.id')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->where('information_object.id', $ioId)
            ->value('actor_i18n.authorized_form_of_name') ?? 'Unknown Repository';

        $certNumber = $this->getCertificateNumber();
        $destructionDate = now();

        // Build content hash from the certificate details
        $contentSource = implode('|', [
            $certNumber,
            $ioId,
            $authorizedBy,
            $method,
            $witness ?? '',
            $destructionDate->toIso8601String(),
        ]);
        $contentHash = hash('sha256', $contentSource);

        // Build metadata
        $metadata = [
            'io_title'        => $ioTitle,
            'repository_name' => $repoName,
            'disposition_id'  => $dispositionId,
            'digital_object_id' => $disposition->digital_object_id,
        ];

        $certId = DB::table('destruction_certificate')->insertGetId([
            'disposition_queue_id'  => $dispositionId,
            'information_object_id' => $ioId,
            'certificate_number'    => $certNumber,
            'destruction_date'      => $destructionDate,
            'destruction_method'    => $method,
            'authorized_by'         => $authorizedBy,
            'witness'               => $witness,
            'content_hash'          => $contentHash,
            'metadata'              => json_encode($metadata),
            'created_at'            => now(),
        ]);

        // Update disposition queue status
        DB::table('integrity_disposition_queue')
            ->where('id', $dispositionId)
            ->update([
                'status'      => 'destroyed',
                'reviewed_by' => $authorizedBy,
                'reviewed_at' => now(),
            ]);

        // Generate printable HTML
        $html = $this->renderCertificateHtml([
            'id'                 => $certId,
            'certificate_number' => $certNumber,
            'destruction_date'   => $destructionDate->format('Y-m-d H:i:s'),
            'destruction_method' => $method,
            'authorized_by'      => $authorizedBy,
            'witness'            => $witness,
            'content_hash'       => $contentHash,
            'io_title'           => $ioTitle,
            'repository_name'    => $repoName,
            'information_object_id' => $ioId,
        ]);

        return [
            'id'                 => $certId,
            'certificate_number' => $certNumber,
            'html'               => $html,
        ];
    }

    /**
     * Generate a unique certificate number: DC-YYYY-NNNNN
     */
    public function getCertificateNumber(): string
    {
        $year = date('Y');
        $prefix = 'DC-' . $year . '-';

        if (!Schema::hasTable('destruction_certificate')) {
            return $prefix . '00001';
        }

        $lastCert = DB::table('destruction_certificate')
            ->where('certificate_number', 'LIKE', $prefix . '%')
            ->orderBy('certificate_number', 'desc')
            ->value('certificate_number');

        if ($lastCert) {
            $lastNum = (int) substr($lastCert, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get paginated list of certificates.
     */
    public function getCertificates(int $page = 1, int $perPage = 25): array
    {
        if (!Schema::hasTable('destruction_certificate')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $culture = app()->getLocale();

        $total = DB::table('destruction_certificate')->count();

        $data = DB::table('destruction_certificate')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('destruction_certificate.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->select([
                'destruction_certificate.*',
                'information_object_i18n.title as io_title',
            ])
            ->orderBy('destruction_certificate.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Get a single certificate with IO title.
     */
    public function getCertificate(int $id): ?object
    {
        if (!Schema::hasTable('destruction_certificate')) {
            return null;
        }

        $culture = app()->getLocale();

        return DB::table('destruction_certificate')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('destruction_certificate.information_object_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object', 'destruction_certificate.information_object_id', '=', 'information_object.id')
            ->leftJoin('repository', 'information_object.repository_id', '=', 'repository.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->where('destruction_certificate.id', $id)
            ->select([
                'destruction_certificate.*',
                'information_object_i18n.title as io_title',
                'actor_i18n.authorized_form_of_name as repository_name',
            ])
            ->first();
    }

    /**
     * Render printable HTML for a certificate.
     */
    private function renderCertificateHtml(array $data): string
    {
        $witness = $data['witness'] ? '<tr><td><strong>Witness</strong></td><td>' . e($data['witness']) . '</td></tr>' : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Destruction Certificate ' . e($data['certificate_number']) . '</title>
<style>
body { font-family: "Times New Roman", serif; max-width: 800px; margin: 40px auto; padding: 20px; }
h1 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
.cert-number { text-align: center; font-size: 1.2em; color: #666; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
td { padding: 8px; border: 1px solid #ccc; }
td:first-child { width: 200px; background: #f5f5f5; font-weight: bold; }
.hash { font-family: monospace; font-size: 0.85em; word-break: break-all; }
.footer { margin-top: 40px; text-align: center; font-size: 0.9em; color: #999; }
.signatures { margin-top: 60px; display: flex; justify-content: space-between; }
.sig-block { width: 45%; text-align: center; }
.sig-line { border-top: 1px solid #333; padding-top: 5px; margin-top: 60px; }
@media print { body { margin: 0; } }
</style>
</head>
<body>
<h1>Certificate of Destruction</h1>
<p class="cert-number">' . e($data['certificate_number']) . '</p>
<table>
<tr><td><strong>Information Object</strong></td><td>' . e($data['io_title']) . ' (ID: ' . e($data['information_object_id']) . ')</td></tr>
<tr><td><strong>Repository</strong></td><td>' . e($data['repository_name']) . '</td></tr>
<tr><td><strong>Destruction Date</strong></td><td>' . e($data['destruction_date']) . '</td></tr>
<tr><td><strong>Method of Destruction</strong></td><td>' . e($data['destruction_method']) . '</td></tr>
<tr><td><strong>Authorized By</strong></td><td>' . e($data['authorized_by']) . '</td></tr>
' . $witness . '
<tr><td><strong>Content Hash</strong></td><td class="hash">' . e($data['content_hash']) . '</td></tr>
</table>
<p>This certificate confirms that the above-described records have been destroyed in accordance with the applicable retention policy and authorization procedures.</p>
<div class="signatures">
<div class="sig-block"><div class="sig-line">Authorized By</div></div>
<div class="sig-block"><div class="sig-line">Witness</div></div>
</div>
<div class="footer">Generated by Heratio Records Management System</div>
</body>
</html>';
    }
}
