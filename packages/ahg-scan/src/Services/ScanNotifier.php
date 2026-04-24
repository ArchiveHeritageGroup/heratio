<?php

/**
 * ScanNotifier — Heratio ahg-scan (P6)
 *
 * Sends a terse failure email to the folder's configured recipients when
 * a file fails after all retries have been exhausted. Uses Laravel's
 * default mailer; no-ops silently when no recipients are configured.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScanNotifier
{
    /**
     * Notify on a final failure for an ingest_file (after max_attempts
     * retries). Silently no-ops when the folder has no recipients or
     * notify_on_failure is off.
     */
    public static function notifyFinalFailure(int $fileId): void
    {
        $row = DB::table('ingest_file as f')
            ->leftJoin('scan_folder as sf', 'sf.ingest_session_id', '=', 'f.session_id')
            ->leftJoin('ingest_session as s', 's.id', '=', 'f.session_id')
            ->where('f.id', $fileId)
            ->select(
                'f.id', 'f.original_name', 'f.stored_path', 'f.attempts', 'f.error_message',
                'f.status', 'f.resolved_io_id', 'f.created_at',
                'sf.code as folder_code', 'sf.label as folder_label',
                'sf.notify_emails', 'sf.notify_on_failure',
                's.title as session_title'
            )
            ->first();

        if (!$row) { return; }
        if (empty($row->notify_on_failure) || empty($row->notify_emails)) { return; }

        $recipients = array_filter(array_map('trim', explode(',', $row->notify_emails)));
        if (empty($recipients)) { return; }

        $subject = "[Heratio scanner] {$row->folder_code}: ingest failed — " . $row->original_name;
        $inboxUrl = rtrim(config('app.url') ?: '', '/') . '/admin/scan/inbox/' . $row->id;

        $body = <<<TXT
A scanner-ingest pipeline has failed after all retries.

  File:     {$row->original_name}
  Path:     {$row->stored_path}
  Folder:   {$row->folder_label} ({$row->folder_code})
  Session:  {$row->session_title}
  Attempts: {$row->attempts}
  Created:  {$row->created_at}
  Status:   {$row->status}

Error message:
{$row->error_message}

Inbox: {$inboxUrl}

You're receiving this because the watched folder has notify_on_failure turned on.
Toggle it off in Admin → Scan → Watched folders if you no longer want these.
TXT;

        try {
            Mail::raw($body, function ($message) use ($recipients, $subject) {
                $message->to($recipients)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] notifier failed for ingest_file ' . $fileId . ': ' . $e->getMessage());
        }
    }
}
