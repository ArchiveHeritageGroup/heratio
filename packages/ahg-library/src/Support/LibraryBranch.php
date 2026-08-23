<?php

/**
 * LibraryBranch - the branch axis for circulation
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

namespace AhgLibrary\Support;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A branch is a `repository` row - #1473. The SLIMS/Brocade evaluation settled
 * on modelling outlets as repositories inside one shared instance rather than
 * one instance per branch, which reuses the tenancy substrate the catalogue
 * already runs on (information_object.repository_id) instead of inventing a
 * second, parallel notion of "where".
 *
 * Everything here degrades to pre-branch behaviour when the branch columns are
 * absent. That is not defensive padding: the CI test database is built from
 * database/core/*.sql rather than by running migrations, and a deployed
 * instance serves requests from the moment new code lands, which is before its
 * migrations have run. Both states must return a working answer, not a 500.
 */
class LibraryBranch
{
    /**
     * Sentinel on library_loan_rule.branch_id meaning "applies at every
     * branch". Not NULL, because the column participates in a UNIQUE key and
     * MySQL treats NULLs as distinct - see the migration for the full note.
     */
    public const ALL = 0;

    /**
     * hasColumn is a schema round-trip, so it is resolved once - but keyed by
     * connection, not per process. One process can serve two databases: the
     * library package's feature harness swaps the default connection to an
     * in-memory SQLite build of a subset of the migrations, so a value cached
     * against MySQL would otherwise be answered for SQLite and describe a
     * schema that is not there.
     */
    private static array $available = [];

    private static array $options = [];

    /** Keyed by connection, for the same reason as $available. */
    private static array $staffTable = [];

    /**
     * Whether this installation can express a branch at all. False on an
     * instance whose migration has not yet run.
     */
    public static function available(): bool
    {
        $key = self::connectionKey();

        if (! array_key_exists($key, self::$available)) {
            self::$available[$key] = Schema::hasTable('library_loan_rule')
                && Schema::hasColumn('library_loan_rule', 'branch_id');
        }

        return self::$available[$key];
    }

    /** Which database a cached schema answer belongs to. */
    public static function connectionKey(): string
    {
        return (string) DB::getDefaultConnection();
    }

    /** Test seam - schema changes within a process are otherwise invisible. */
    public static function forgetSchemaCache(): void
    {
        self::$available = [];
        self::$options = [];
        self::$staffTable = [];
    }

    /**
     * The branch a transaction belongs to, in order of how directly the
     * evidence names it: an explicit choice at the desk, then the copy being
     * handled, then the patron's home branch, then the configured default.
     * Null when nothing answers - callers treat that as "unattributed", never
     * as an error.
     */
    public static function resolve(?int $explicit = null, ?int $copyId = null, ?int $patronId = null): ?int
    {
        if (! self::available()) {
            return null;
        }

        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }

        if ($copyId !== null) {
            $branch = self::columnValue('library_copy', $copyId);
            if ($branch !== null) {
                return $branch;
            }
        }

        if ($patronId !== null) {
            $branch = self::columnValue('library_patron', $patronId);
            if ($branch !== null) {
                return $branch;
            }
        }

        return self::defaultBranchId();
    }

    /**
     * The branch a single-outlet installation operates as. Unset on a fresh
     * install, which is correct: an operator who has not nominated a branch
     * should get the all-branches rules, not an arbitrary repository.
     */
    public static function defaultBranchId(): ?int
    {
        $id = AhgSettingsService::getInt('library_default_branch', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Repository id => authorised name, for pick-lists. Repositories with no
     * name in the active culture fall back to any culture, then to their id,
     * so a nameless repository is still selectable rather than blank.
     */
    public static function options(): array
    {
        $key = self::connectionKey();

        if (array_key_exists($key, self::$options)) {
            return self::$options[$key];
        }

        if (! Schema::hasTable('repository') || ! Schema::hasTable('actor_i18n')) {
            return self::$options[$key] = [];
        }

        $culture = app()->getLocale() ?: 'en';

        $rows = DB::table('repository as r')
            ->leftJoin('actor_i18n as pref', function ($join) use ($culture) {
                $join->on('pref.id', '=', 'r.id')->where('pref.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as any', 'any.id', '=', 'r.id')
            ->groupBy('r.id')
            ->orderByRaw('COALESCE(MAX(pref.authorized_form_of_name), MAX(any.authorized_form_of_name), r.id)')
            ->get([
                'r.id',
                DB::raw('MAX(pref.authorized_form_of_name) as preferred_name'),
                DB::raw('MAX(any.authorized_form_of_name) as any_name'),
            ]);

        $options = [];
        foreach ($rows as $row) {
            $name = $row->preferred_name ?: $row->any_name;
            $options[(int) $row->id] = $name !== null && trim((string) $name) !== ''
                ? (string) $name
                : '[' . __('Unnamed repository') . ' #' . $row->id . ']';
        }

        return self::$options[$key] = $options;
    }

    // ── The operator's branch - #1473 Phase 2 ───────────────────────────

    /** Session key holding a branch chosen for this shift. */
    public const SESSION_KEY = 'library_operator_branch';

    /** Session value meaning the operator has deliberately chosen every branch. */
    public const SESSION_ALL = 'all';

    /**
     * The branch whose work the signed-in operator should be looking at, or
     * null for "everything" - which is both the consortium view and the
     * correct answer for a single-outlet service that has never named a
     * branch. A null here must therefore never be read as "no access".
     *
     * A choice made this shift wins over the stored one, so covering another
     * counter for an afternoon does not require an administrator.
     */
    public static function operatorBranchId(): ?int
    {
        if (! self::available()) {
            return null;
        }

        $session = self::sessionChoice();
        if ($session === self::SESSION_ALL) {
            return null;
        }
        if (is_int($session)) {
            return $session;
        }

        $row = self::staffRow();
        if ($row === null || (int) ($row->all_branches ?? 0) === 1) {
            return null;
        }

        $branch = $row->branch_id ?? null;

        return $branch !== null ? (int) $branch : null;
    }

    /**
     * Whether the operator is looking across every branch. Distinct from
     * operatorBranchId() returning null, which it also does when nobody has
     * ever nominated a branch - the screens word those two states differently.
     */
    public static function operatorSeesAllBranches(): bool
    {
        if (! self::available()) {
            return true;
        }

        if (self::sessionChoice() === self::SESSION_ALL) {
            return true;
        }

        $row = self::staffRow();

        return $row !== null && (int) ($row->all_branches ?? 0) === 1;
    }

    /**
     * Remember the operator's choice for this shift and, where the table is
     * present, persist it so the next sign-in starts at the same counter.
     * Passing null selects every branch.
     */
    public static function chooseOperatorBranch(?int $branchId): void
    {
        $value = $branchId !== null && $branchId > 0 ? $branchId : self::SESSION_ALL;

        if (function_exists('session') && app()->bound('session')) {
            session()->put(self::SESSION_KEY, $value);
        }

        $userId = self::currentUserId();
        if ($userId === null || ! self::staffTableExists()) {
            return;
        }

        DB::table('library_staff_branch')->updateOrInsert(
            ['user_id' => $userId],
            [
                'branch_id' => is_int($value) ? $value : null,
                'all_branches' => $value === self::SESSION_ALL ? 1 : 0,
                'updated_at' => now(),
            ]
        );
    }

    /** The branch choice held for this shift: an int, SESSION_ALL, or null. */
    private static function sessionChoice(): int|string|null
    {
        if (! function_exists('session') || ! app()->bound('session')) {
            return null;
        }

        $value = session()->get(self::SESSION_KEY);

        if ($value === self::SESSION_ALL) {
            return self::SESSION_ALL;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function staffRow(): ?object
    {
        $userId = self::currentUserId();
        if ($userId === null || ! self::staffTableExists()) {
            return null;
        }

        return DB::table('library_staff_branch')->where('user_id', $userId)->first();
    }

    private static function currentUserId(): ?int
    {
        if (! app()->bound('auth') || ! Auth::check()) {
            return null;
        }

        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    private static function staffTableExists(): bool
    {
        $key = self::connectionKey();

        if (! array_key_exists($key, self::$staffTable)) {
            self::$staffTable[$key] = Schema::hasTable('library_staff_branch');
        }

        return self::$staffTable[$key];
    }

    /** Display name for one branch id, or null when it is not a repository. */
    public static function name(?int $id): ?string
    {
        if ($id === null || $id === self::ALL) {
            return null;
        }

        return self::options()[$id] ?? null;
    }

    /**
     * Label for a loan rule's branch column, where 0 is meaningful rather than
     * missing. A rule that applies everywhere must not read as a blank cell.
     */
    public static function ruleLabel(?int $id): string
    {
        if ($id === null || $id === self::ALL) {
            return __('All branches');
        }

        return self::name($id) ?? ('#' . $id);
    }

    /** branch_id of one row, or null when unset or unavailable. */
    private static function columnValue(string $table, int $id): ?int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            return null;
        }

        $value = DB::table($table)->where('id', $id)->value('branch_id');

        return $value !== null ? (int) $value : null;
    }
}
