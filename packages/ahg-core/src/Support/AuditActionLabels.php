<?php

/**
 * Human labels for the raw action codes in `security_audit_log`.
 *
 * Two problems this solves.
 *
 * The audit-log filter used to build its Action list with
 * SELECT DISTINCT action FROM security_audit_log, so the options were whatever
 * that instance happened to have logged. dev offered thirteen actions and
 * heratio.org nine, purely because different features had been exercised on
 * each - the same screen looked like a different screen depending on where you
 * opened it, and an action you wanted to filter for simply was not there until
 * somebody performed it. The catalogue below is the canonical set, so every
 * instance offers the same choices.
 *
 * And the codes are raw: `digital_object_attach`, `publication_status_change`.
 * They are fine as stored values and poor as something to read down a column.
 *
 * Unknown codes are NOT hidden - an instance may log something this list does
 * not know yet, and dropping it would make those rows unfilterable. They are
 * humanised generically (`some_new_action` -> `Some new action`) and still
 * offered.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Support;

class AuditActionLabels
{
    /**
     * Canonical action code => human label.
     *
     * @var array<string,string>
     */
    private const LABELS = [
        // Record lifecycle
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'view' => 'Viewed',
        'download' => 'Downloaded',
        'search' => 'Searched',
        'export' => 'Exported',
        'import' => 'Imported',
        'print' => 'Printed',

        // Digital objects
        'digital_object_upload' => 'Digital object uploaded',
        'digital_object_attach' => 'Digital object attached',
        'digital_object_delete' => 'Digital object deleted',
        'digital_object_replace' => 'Digital object replaced',

        // Description / publication
        'publication_status_change' => 'Publication status changed',
        'metadata_extraction_apply' => 'Extracted metadata applied',
        'finding_aid_generate' => 'Finding aid generated',
        'finding_aid_delete' => 'Finding aid deleted',

        // Access and identity
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Sign-in failed',
        'password_change' => 'Password changed',
        'password_reset' => 'Password reset',
        'permission_change' => 'Permissions changed',
        'group_change' => 'Group membership changed',
        'clearance_change' => 'Security clearance changed',
        'access_request' => 'Access requested',
        'access_granted' => 'Access granted',
        'access_denied' => 'Access denied',
    ];

    /**
     * The canonical list, as code => label, alphabetical by label.
     *
     * @return array<string,string>
     */
    public static function catalogue(): array
    {
        $out = self::LABELS;
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** The human label for one code; humanised generically when unknown. */
    public static function label(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        return self::LABELS[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

    /**
     * The catalogue plus any codes actually present that it does not know, so
     * an instance logging something new can still filter on it.
     *
     * @param  iterable<string|null>  $present
     * @return array<string,string> code => label, alphabetical by label
     */
    public static function withPresent(iterable $present): array
    {
        $out = self::LABELS;
        foreach ($present as $code) {
            $code = trim((string) $code);
            if ($code !== '' && ! isset($out[$code])) {
                $out[$code] = self::label($code);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }
}
