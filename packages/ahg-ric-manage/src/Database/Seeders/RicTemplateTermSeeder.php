<?php

/**
 * RicTemplateTermSeeder - idempotently seed the taxonomy-70 'ric' term.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
 *
 * Taxonomy 70 is AtoM's INFORMATION_OBJECT_TEMPLATE_ID - the description-standard
 * dropdown (isad/dc/mods/rad/dacs/museum/dam/gallery/library). Adding a `ric`
 * term makes "Records in Contexts" appear in that dropdown automatically, because
 * create.blade / InformationObjectController build the list with WHERE taxonomy_id=70.
 *
 * The term is addressed by its natural key `code='ric'` everywhere - the numeric
 * id differs per host DB and must never be hard-coded.
 */

namespace AhgRicManage\Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RicTemplateTermSeeder
{
    public const TAXONOMY_ID = 70;
    public const CODE = 'ric';
    public const NAME = 'Records in Contexts (RiC-O 1.0), International Council on Archives';

    /**
     * Insert the term if it is absent. Safe to call on every boot: it does one
     * cheap existence check and returns immediately when the term already
     * exists. Never throws - a seed failure must not block application boot.
     *
     * @return bool true if the term now exists (seeded or already present)
     */
    public static function ensure(): bool
    {
        try {
            if (! Schema::hasTable('term') || ! Schema::hasTable('term_i18n') || ! Schema::hasTable('object')) {
                return false;
            }

            $existing = DB::table('term')
                ->where('taxonomy_id', self::TAXONOMY_ID)
                ->where('code', self::CODE)
                ->value('id');
            if ($existing) {
                return true;
            }

            // Mirror the sector packages' minimal taxonomy-70 seed
            // (packages/ahg-dam/database/install.sql): object + term + term_i18n.
            // parent_id 110 matches the other standard terms (the taxonomy-70
            // root); the dropdown query is flat (WHERE taxonomy_id=70) so no
            // nested-set lft/rgt is required for it to appear.
            $parentId = DB::table('term')
                ->where('taxonomy_id', self::TAXONOMY_ID)
                ->whereNotNull('parent_id')
                ->value('parent_id') ?: null;

            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitTerm',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('term')->insert([
                'id' => $objectId,
                'taxonomy_id' => self::TAXONOMY_ID,
                'code' => self::CODE,
                'parent_id' => $parentId,
                'source_culture' => 'en',
            ]);

            DB::table('term_i18n')->insert([
                'id' => $objectId,
                'culture' => 'en',
                'name' => self::NAME,
            ]);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Resolve the term id by its natural key, or null if not seeded. */
    public static function termId(): ?int
    {
        try {
            $id = DB::table('term')
                ->where('taxonomy_id', self::TAXONOMY_ID)
                ->where('code', self::CODE)
                ->value('id');

            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
