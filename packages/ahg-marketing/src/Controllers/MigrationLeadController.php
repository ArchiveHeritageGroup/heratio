<?php

/**
 * MigrationLeadController - shows the free-migration-assessment form and handles
 * its submission. A valid submission drops a single JSON notification into the
 * Workbench bell inbox; bots that fill the honeypot are silently dropped.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgMarketing\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MigrationLeadController extends Controller
{
    /** Workbench notification bell inbox - the watcher sweeps this every 15s. */
    private const INBOX = '/var/spool/workbench/notifications';

    /** Success message shown to humans and to dropped bots alike. */
    private const SUCCESS = 'Thank you - we will be in touch about your migration assessment.';

    /** GET /migration/assessment - the lead-capture form. */
    public function show(): View
    {
        return view('marketing::migration-assessment');
    }

    /** POST /migration/assessment - validate, drop the bell notification, redirect. */
    public function submit(Request $request): RedirectResponse
    {
        // Honeypot: real users leave `website` blank; bots fill it. Drop silently
        // with the same success message so the bot cannot tell it failed.
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect()
                ->route('marketing.migration.assessment')
                ->with('status', self::SUCCESS);
        }

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|max:200',
            'organisation' => 'required|string|max:200',
            'current_atom_url' => 'nullable|url|max:300',
            'atom_version' => 'nullable|string|max:60',
            'message' => 'nullable|string|max:2000',
        ]);

        $this->dropNotification($data);

        return redirect()
            ->route('marketing.migration.assessment')
            ->with('status', self::SUCCESS);
    }

    /**
     * Write one atomic JSON file to the Workbench notification bell inbox.
     * Never surfaces a failure to the visitor - on error we log and move on.
     */
    private function dropNotification(array $data): void
    {
        try {
            $atomUrl = $data['current_atom_url'] ?? '';
            $atomVersion = $data['atom_version'] ?? '';
            $message = $data['message'] ?? '';

            $payload = [
                'username' => 'johan',
                'title' => 'New AtoM migration assessment request',
                'message' => sprintf(
                    '%s from %s (%s) requested a free AtoM migration assessment. AtoM: %s (%s). Message: %s',
                    $data['name'],
                    $data['organisation'],
                    $data['email'],
                    $atomUrl !== '' ? $atomUrl : 'n/a',
                    $atomVersion !== '' ? $atomVersion : 'n/a',
                    $message !== '' ? $message : 'none'
                ),
                'eventType' => 'lead',
                'webLink' => 'https://heratio.org/migration/assessment',
            ];

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $uuid = (string) Str::uuid();
            $final = self::INBOX.'/heratio-lead-'.$uuid.'.json';
            $tmp = self::INBOX.'/.heratio-lead-'.$uuid.'.json.tmp';

            // Write to a temp file then rename so the watcher never sees a partial write.
            if (@file_put_contents($tmp, $json) === false) {
                throw new \RuntimeException('failed to write temp notification file');
            }
            if (! @rename($tmp, $final)) {
                @unlink($tmp);
                throw new \RuntimeException('failed to move notification into inbox');
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-marketing: migration lead notification not written: '.$e->getMessage());
        }
    }
}
