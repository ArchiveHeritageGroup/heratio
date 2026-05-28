<?php

/**
 * Fix ai_operator_attestation user_id FK type mismatch.
 *
 * Problem: The ai_operator_attestation.user_id column was defined as
 * BIGINT UNSIGNED, but Heratio user.id is BIGINT (signed). This causes
 * MySQL error 3780 on PSIS installs where strict FK checking is enabled.
 *
 * Additionally, this same BIGINT UNSIGNED issue exists in two other
 * oversight columns that reference the user table, so all three are fixed
 * here for consistency.
 *
 * Affected columns:
 *   - ai_operator_attestation.user_id
 *   - ai_oversight_policy.halted_by_user_id
 *   - ai_review_decision.reviewer_user_id
 *
 * All changed to BIGINT SIGNED to match Heratio user.id (bigint, no unsigned).
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html#error_er_referenced_2nd_secondary
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use a raw MODIFY COLUMN statement so we can change UNSIGNED → SIGNED
        // without Laravel's Blueprint trying to auto-detect the direction.
        // Each statement is guarded with a conditional so it is idempotent —
        // safe to re-run on any environment.

        // ── ai_operator_attestation.user_id ─────────────────────────────────
        if ($this->columnExists('ai_operator_attestation', 'user_id')) {
            $type = $this->getColumnType('ai_operator_attestation', 'user_id');

            if ($type === 'bigint') {
                // Already signed — nothing to do.
            } else {
                // UNSIGNED BIGINT → signed BIGINT (no auto_increment, not null)
                DB::statement(
                    'ALTER TABLE `ai_operator_attestation` MODIFY COLUMN `user_id` BIGINT NOT NULL'
                );
            }
        }

        // ── ai_oversight_policy.halted_by_user_id ───────────────────────────
        if ($this->columnExists('ai_oversight_policy', 'halted_by_user_id')) {
            $type = $this->getColumnType('ai_oversight_policy', 'halted_by_user_id');

            if ($type === 'bigint') {
                // Already signed — nothing to do.
            } else {
                DB::statement(
                    'ALTER TABLE `ai_oversight_policy` MODIFY COLUMN `halted_by_user_id` BIGINT NULL DEFAULT NULL'
                );
            }
        }

        // ── ai_review_decision.reviewer_user_id ──────────────────────────────
        if ($this->columnExists('ai_review_decision', 'reviewer_user_id')) {
            $type = $this->getColumnType('ai_review_decision', 'reviewer_user_id');

            if ($type === 'bigint') {
                // Already signed — nothing to do.
            } else {
                DB::statement(
                    'ALTER TABLE `ai_review_decision` MODIFY COLUMN `reviewer_user_id` BIGINT NOT NULL'
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Revert BIGINT SIGNED → BIGINT UNSIGNED for the three columns.
     * This is the original (broken) state that triggered MySQL error 3780
     * on PSIS installs — included for completeness only.
     */
    public function down(): void
    {
        if ($this->columnExists('ai_operator_attestation', 'user_id')) {
            DB::statement(
                'ALTER TABLE `ai_operator_attestation` MODIFY COLUMN `user_id` BIGINT UNSIGNED NOT NULL'
            );
        }

        if ($this->columnExists('ai_oversight_policy', 'halted_by_user_id')) {
            DB::statement(
                'ALTER TABLE `ai_oversight_policy` MODIFY COLUMN `halted_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
            );
        }

        if ($this->columnExists('ai_review_decision', 'reviewer_user_id')) {
            DB::statement(
                'ALTER TABLE `ai_review_decision` MODIFY COLUMN `reviewer_user_id` BIGINT UNSIGNED NOT NULL'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * Return the bare MySQL type name for a column (e.g. 'bigint', 'int', 'varchar').
     * Uses MySQL's information_schema so it always reflects the real DB type,
     * regardless of the DB driver abstraction layer.
     */
    private function getColumnType(string $table, string $column): string
    {
        $result = DB::selectOne(
            <<<SQL
            SELECT COLUMN_TYPE
            FROM   information_schema.COLUMNS
            WHERE  TABLE_SCHEMA = DATABASE()
              AND  TABLE_NAME   = ?
              AND  COLUMN_NAME  = ?
            SQL,
            [$table, $column]
        );

        if (! $result) {
            // Fallback — assume unsigned so we attempt the alter.
            return 'bigint unsigned';
        }

        // COLUMN_TYPE may be "bigint unsigned" or "bigint(20) unsigned" — strip any size/precision.
        $type = preg_replace('/\(.*\)/', '', $result->COLUMN_TYPE);
        $type = trim($type);

        return $type;
    }
};
