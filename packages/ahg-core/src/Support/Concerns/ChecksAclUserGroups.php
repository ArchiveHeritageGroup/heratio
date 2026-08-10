<?php

/**
 * ChecksAclUserGroups - shared per-request cache of a user's ACL group ids.
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

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Resolve (and per-request cache) the acl_user_group group ids for a user.
 * Was byte-identical in the AclCheck services of ahg-share-link and
 * ahg-version-control (including the static cache). The cache property is
 * declared here; each using class gets its own copy (trait statics are
 * per-using-class), matching the original per-class behaviour.
 */
trait ChecksAclUserGroups
{
    /** @var array<int,array<int,int>> */
    private static array $groupCache = [];

    private function getUserGroups(int $userId): array
    {
        if (isset(self::$groupCache[$userId])) {
            return self::$groupCache[$userId];
        }
        try {
            $rows = DB::table('acl_user_group')->where('user_id', $userId)->pluck('group_id')->all();
            self::$groupCache[$userId] = array_map('intval', $rows);
        } catch (\Throwable $e) {
            self::$groupCache[$userId] = [];
        }

        return self::$groupCache[$userId];
    }
}
