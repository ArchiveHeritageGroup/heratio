<?php

/**
 * ResolvesReminderRecipient - shared recipient lookup for Spectrum reminder commands.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgSpectrum\Console\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Pick the user to notify for an object: the repository's primary contact
 * if any, else the first administrator with an email. Was byte-identical
 * in SpectrumConditionCheckReminderCommand and SpectrumValuationReminderCommand.
 */
trait ResolvesReminderRecipient
{
    private function getRecipientForObject(int $objectId): ?int
    {
        // Repository contact (information_object.repository_id -> ?)
        $repoId = DB::table('information_object')->where('id', $objectId)->value('repository_id');
        if ($repoId) {
            $contact = DB::table('contact_information')
                ->where('actor_id', $repoId)
                ->where('primary_contact', 1)
                ->value('actor_id');
            if ($contact) {
                $userId = DB::table('user')->whereNotNull('email')->value('id');
                if ($userId) return (int) $userId;
            }
        }
        // Fallback: first user with an admin role.
        $admin = DB::table('user as u')
            ->join('user_group as ug', 'u.id', '=', 'ug.user_id')
            ->join('aclgroup as g', 'ug.group_id', '=', 'g.id')
            ->where('g.name', 'administrator')
            ->whereNotNull('u.email')
            ->value('u.id');
        return $admin ? (int) $admin : null;
    }
}
