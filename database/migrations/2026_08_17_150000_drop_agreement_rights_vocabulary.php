<?php

/**
 * Drop `agreement_rights_vocabulary` - reversibly (#1468).
 *
 * 25 curated donor-agreement terms in four categories (usage, restriction,
 * condition, license) that no code has ever read. Authored reference data for a
 * feature that was never wired up.
 *
 * The #1464 cleanup deliberately left it alone, on the grounds that "whether to
 * wire it or abandon it is a question for a human; a cleanup migration should not
 * answer it by deleting". That question has now been answered - abandon it - for
 * two reasons found while looking for somewhere to attach it:
 *
 *  1. Its seven `license` rows (CC BY, CC BY-SA, CC0, ...) duplicate
 *     `rights_cc_license`, which #1464 made the authoritative licence list.
 *     Keeping a rival list is precisely the problem that consolidation solved.
 *  2. Its other 18 rows have nowhere to go. There is no column, no link table
 *     and no UI - and #1464 chose `rights_record` as where rights live, keyed by
 *     object_id with a `donor` basis. Attaching this vocabulary would mean
 *     designing a second donor-agreement rights model: a feature, not a cleanup.
 *
 * It was also never consistently deployed - 25 rows on dev and heratio.org, none
 * on sasa.
 *
 * NOTHING IS LOST. down() recreates the table and re-seeds all 25 rows verbatim,
 * so the curated content now lives permanently in version control instead of in
 * two of three databases. If the donor-agreement rights picker is ever wanted,
 * the vocabulary is one rollback (or one copy-paste) away.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The 25 terms, verbatim: [id, code, name, description, category, is_active, sort_order]. */
    private const TERMS = [
        [1, 'USE_RESEARCH', 'Research Use', 'May be used for research purposes', 'usage', 1, 10],
        [2, 'USE_EDUCATION', 'Educational Use', 'May be used for educational purposes', 'usage', 1, 20],
        [3, 'USE_PUBLICATION', 'Publication Rights', 'May be published in institutional publications', 'usage', 1, 30],
        [4, 'USE_EXHIBITION', 'Exhibition Rights', 'May be exhibited publicly', 'usage', 1, 40],
        [5, 'USE_DIGITIZATION', 'Digitization Rights', 'May be digitized for preservation/access', 'usage', 1, 50],
        [6, 'USE_WEB', 'Web Display', 'May be displayed on institutional website', 'usage', 1, 60],
        [7, 'USE_COMMERCIAL', 'Commercial Use', 'May be used for commercial purposes', 'usage', 1, 70],
        [8, 'USE_DERIVATIVE', 'Derivative Works', 'Derivative works may be created', 'usage', 1, 80],
        [9, 'USE_LOAN', 'Loan Rights', 'May be loaned to other institutions', 'usage', 1, 90],
        [10, 'REST_NO_REPRO', 'No Reproduction', 'Reproduction not permitted', 'restriction', 1, 100],
        [11, 'REST_NO_COMMERCIAL', 'Non-Commercial Only', 'Commercial use prohibited', 'restriction', 1, 110],
        [12, 'REST_APPROVAL', 'Requires Approval', 'Usage requires donor approval', 'restriction', 1, 120],
        [13, 'REST_EMBARGO', 'Embargo Period', 'Access restricted until specified date', 'restriction', 1, 130],
        [14, 'REST_REDACTION', 'Redaction Required', 'Certain content must be redacted', 'restriction', 1, 140],
        [15, 'COND_ATTRIBUTION', 'Attribution Required', 'Must credit donor/source', 'condition', 1, 200],
        [16, 'COND_NOTIFY', 'Notification Required', 'Donor must be notified of use', 'condition', 1, 210],
        [17, 'COND_COPY', 'Copy to Donor', 'Donor receives copy of publications', 'condition', 1, 220],
        [18, 'COND_REVIEW', 'Review Required', 'Donor may review before publication', 'condition', 1, 230],
        [19, 'LIC_CC_BY', 'CC BY', 'Creative Commons Attribution', 'license', 1, 300],
        [20, 'LIC_CC_BY_SA', 'CC BY-SA', 'Creative Commons Attribution-ShareAlike', 'license', 1, 310],
        [21, 'LIC_CC_BY_NC', 'CC BY-NC', 'Creative Commons Attribution-NonCommercial', 'license', 1, 320],
        [22, 'LIC_CC_BY_NC_SA', 'CC BY-NC-SA', 'Creative Commons Attribution-NonCommercial-ShareAlike', 'license', 1, 330],
        [23, 'LIC_CC_BY_ND', 'CC BY-ND', 'Creative Commons Attribution-NoDerivatives', 'license', 1, 340],
        [24, 'LIC_CC0', 'CC0', 'Public Domain Dedication', 'license', 1, 350],
        [25, 'LIC_CUSTOM', 'Custom License', 'Custom licensing terms apply', 'license', 1, 360],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('agreement_rights_vocabulary')) {
            return;
        }

        // Refuse if something started reading it since this was written - a row
        // count above the seeded 25 means someone has been curating it.
        $count = DB::table('agreement_rights_vocabulary')->count();
        if ($count > count(self::TERMS)) {
            Log::warning("Not dropping agreement_rights_vocabulary: it holds {$count} rows, more than the ".count(self::TERMS).' seeded terms - it is being used. See #1468.');

            return;
        }

        Schema::drop('agreement_rights_vocabulary');
        Log::info("#1468 dropped agreement_rights_vocabulary ({$count} rows); the 25 terms are preserved in this migration and restored by its down().");
    }

    public function down(): void
    {
        if (Schema::hasTable('agreement_rights_vocabulary')) {
            return;
        }

        DB::statement(
            'CREATE TABLE `agreement_rights_vocabulary` ('.
            '`id` int NOT NULL AUTO_INCREMENT,'.
            '`code` varchar(50) NOT NULL,'.
            '`name` varchar(255) NOT NULL,'.
            '`description` text,'.
            "`category` varchar(45) NOT NULL COMMENT 'usage, restriction, condition, license',".
            '`is_active` tinyint(1) NOT NULL DEFAULT 1,'.
            '`sort_order` int NOT NULL DEFAULT 0,'.
            'PRIMARY KEY (`id`),'.
            'UNIQUE KEY `code` (`code`),'.
            'KEY `idx_rights_category` (`category`),'.
            'KEY `idx_rights_active` (`is_active`)'.
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        DB::table('agreement_rights_vocabulary')->insert(array_map(fn ($t) => [
            'id' => $t[0], 'code' => $t[1], 'name' => $t[2], 'description' => $t[3],
            'category' => $t[4], 'is_active' => $t[5], 'sort_order' => $t[6],
        ], self::TERMS));
    }
};
