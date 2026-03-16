<?php

declare(strict_types=1);

namespace AhgInformationObjectManage\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extended Rights Service
 *
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/lib/Services/ExtendedRightsService.php
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/lib/Services/EmbargoService.php
 */
class ExtendedRightsService
{
    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? app()->getLocale();
    }

    // =========================================
    // STANDARD RIGHTS (rights + rights_i18n via relation table)
    // =========================================

    /**
     * Get standard rights for an IO via relation table (type_id=168).
     * Relation links: subject_id = information_object.id, object_id = rights.id
     */
    public function getRightsForObject(int $objectId, ?string $culture = null): Collection
    {
        $culture = $culture ?? $this->culture;

        try {
            return DB::table('relation as r')
                ->join('rights', 'rights.id', '=', 'r.object_id')
                ->leftJoin('rights_i18n as ri', function ($j) use ($culture) {
                    $j->on('ri.id', '=', 'rights.id')
                        ->where('ri.culture', $culture);
                })
                ->where('r.subject_id', $objectId)
                ->where('r.type_id', 168)
                ->select([
                    'rights.id',
                    'rights.start_date',
                    'rights.end_date',
                    'rights.basis_id',
                    'rights.rights_holder_id',
                    'rights.copyright_status_id',
                    'rights.copyright_status_date',
                    'rights.copyright_jurisdiction',
                    'rights.statute_determination_date',
                    'rights.statute_citation_id',
                    'rights.source_culture',
                    'ri.rights_note',
                    'ri.copyright_note',
                    'ri.identifier_value',
                    'ri.identifier_type',
                    'ri.identifier_role',
                    'ri.license_terms',
                    'ri.license_note',
                    'ri.statute_jurisdiction',
                    'ri.statute_note',
                ])
                ->get()
                ->map(function ($right) {
                    // Resolve term names for basis and copyright status
                    if ($right->basis_id) {
                        $right->basis_name = $this->getTermName($right->basis_id, $this->culture);
                    }
                    if ($right->copyright_status_id) {
                        $right->copyright_status_name = $this->getTermName($right->copyright_status_id, $this->culture);
                    }
                    if ($right->rights_holder_id) {
                        $right->rights_holder_name = $this->getRightsHolderName($right->rights_holder_id);
                    }
                    return $right;
                });
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    // =========================================
    // EXTENDED RIGHTS (extended_rights table)
    // =========================================

    /**
     * Get extended rights for an object.
     */
    public function getExtendedRights(int $objectId): Collection
    {
        try {
            return DB::table('extended_rights as er')
                ->leftJoin('rights_statement as rs', 'er.rights_statement_id', '=', 'rs.id')
                ->leftJoin('rights_statement_i18n as rs_i18n', function ($j) {
                    $j->on('rs.id', '=', 'rs_i18n.rights_statement_id')
                        ->where('rs_i18n.culture', '=', $this->culture);
                })
                ->leftJoin('rights_cc_license as cc', 'er.creative_commons_license_id', '=', 'cc.id')
                ->where('er.object_id', $objectId)
                ->select([
                    'er.*',
                    'rs.code as rights_statement_code',
                    'rs.uri as rights_statement_uri',
                    DB::raw("COALESCE(rs.icon_filename, '') as rights_statement_icon"),
                    'rs_i18n.name as rights_statement_name',
                    'cc.code as cc_license_code',
                    'cc.uri as cc_license_uri',
                ])
                ->orderByDesc('er.is_primary')
                ->orderByDesc('er.created_at')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get TK labels assigned to an extended rights record.
     */
    public function getTkLabelsForRights(int $extendedRightsId): Collection
    {
        try {
            return DB::table('extended_rights_tk_label as ertl')
                ->join('rights_tk_label as tkl', 'ertl.tk_label_id', '=', 'tkl.id')
                ->where('ertl.extended_rights_id', $extendedRightsId)
                ->select(['tkl.*'])
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    // =========================================
    // EMBARGO (embargo table)
    // =========================================

    /**
     * Get active embargo for an object.
     */
    public function getActiveEmbargo(int $objectId): ?object
    {
        try {
            return DB::table('embargo')
                ->where('object_id', $objectId)
                ->where('is_active', 1)
                ->where('start_date', '<=', date('Y-m-d'))
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', date('Y-m-d'));
                })
                ->orderByDesc('created_at')
                ->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    /**
     * Get all embargoes for an object (active and historical).
     */
    public function getAllEmbargoes(int $objectId): Collection
    {
        try {
            return DB::table('embargo')
                ->where('object_id', $objectId)
                ->orderByDesc('created_at')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Create a new embargo.
     */
    public function createEmbargo(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $status = strtotime($startDate) <= time() ? 'active' : 'pending';

        return DB::table('embargo')->insertGetId([
            'object_id'         => $data['object_id'],
            'embargo_type'      => $data['embargo_type'],
            'start_date'        => $startDate,
            'end_date'          => $data['end_date'] ?? null,
            'reason'            => $data['reason'] ?? null,
            'is_perpetual'      => $data['is_perpetual'] ?? 0,
            'status'            => $status,
            'created_by'        => $data['created_by'] ?? null,
            'notify_on_expiry'  => $data['notify_on_expiry'] ?? 1,
            'notify_days_before'=> $data['notify_days_before'] ?? 30,
            'is_active'         => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    /**
     * Update an existing embargo.
     */
    public function updateEmbargo(int $id, array $data): bool
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $allowed = [
            'embargo_type', 'start_date', 'end_date', 'reason',
            'is_perpetual', 'status', 'notify_on_expiry', 'notify_days_before',
            'updated_by',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        return DB::table('embargo')
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * Lift an embargo.
     */
    public function liftEmbargo(int $id, int $userId, string $reason): bool
    {
        return DB::table('embargo')
            ->where('id', $id)
            ->update([
                'is_active'   => 0,
                'status'      => 'lifted',
                'lifted_by'   => $userId,
                'lifted_at'   => date('Y-m-d H:i:s'),
                'lift_reason' => $reason,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Create embargo with optional propagation to child records.
     */
    public function createEmbargoWithPropagation(array $data, bool $applyToChildren = false): array
    {
        $results = ['created' => 0, 'failed' => 0, 'ids' => []];

        // Create embargo for the main object
        try {
            $embargoId = $this->createEmbargo($data);
            $results['created']++;
            $results['ids'][] = $embargoId;
        } catch (\Exception $e) {
            $results['failed']++;
            return $results;
        }

        // If propagation requested, apply to all descendants
        if ($applyToChildren) {
            $object = DB::table('information_object')
                ->where('id', $data['object_id'])
                ->select('lft', 'rgt')
                ->first();

            if ($object && $object->lft && $object->rgt) {
                $descendants = DB::table('information_object')
                    ->where('lft', '>', $object->lft)
                    ->where('rgt', '<', $object->rgt)
                    ->pluck('id')
                    ->toArray();

                foreach ($descendants as $childId) {
                    try {
                        $childData = $data;
                        $childData['object_id'] = $childId;
                        $childEmbargoId = $this->createEmbargo($childData);
                        $results['created']++;
                        $results['ids'][] = $childEmbargoId;
                    } catch (\Exception $e) {
                        $results['failed']++;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Count descendants for an IO (for propagation UI).
     */
    public function getDescendantCount(int $objectId): int
    {
        $object = DB::table('information_object')
            ->where('id', $objectId)
            ->select('lft', 'rgt')
            ->first();

        if (!$object || !$object->lft || !$object->rgt) {
            return 0;
        }

        return DB::table('information_object')
            ->where('lft', '>', $object->lft)
            ->where('rgt', '<', $object->rgt)
            ->count();
    }

    // =========================================
    // TERM / LOOKUP HELPERS
    // =========================================

    /**
     * Resolve a single term name by ID.
     */
    public function getTermName(int $termId, ?string $culture = null): ?string
    {
        $culture = $culture ?? $this->culture;

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $culture)
            ->value('name');
    }

    /**
     * Batch-resolve term names.
     */
    public function getTermNames(array $ids, ?string $culture = null): array
    {
        if (empty($ids)) {
            return [];
        }

        $culture = $culture ?? $this->culture;

        return DB::table('term_i18n')
            ->whereIn('id', $ids)
            ->where('culture', $culture)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Resolve rights holder name from actor_i18n.
     */
    public function getRightsHolderName(int $actorId): ?string
    {
        return DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', $this->culture)
            ->value('authorized_form_of_name');
    }

    // =========================================
    // RIGHTS STATEMENTS & CC LICENSES (for forms)
    // =========================================

    /**
     * Get all active rights statements.
     */
    public function getRightsStatements(): Collection
    {
        try {
            return DB::table('rights_statement as rs')
                ->leftJoin('rights_statement_i18n as rs_i18n', function ($j) {
                    $j->on('rs.id', '=', 'rs_i18n.rights_statement_id')
                        ->where('rs_i18n.culture', '=', $this->culture);
                })
                ->where('rs.is_active', 1)
                ->select('rs.id', 'rs.code', 'rs.uri', 'rs.category', 'rs.icon_filename', 'rs_i18n.name', 'rs_i18n.definition')
                ->orderBy('rs.sort_order')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get all active Creative Commons licenses.
     */
    public function getCreativeCommonsLicenses(): Collection
    {
        try {
            return DB::table('rights_cc_license')
                ->where('is_active', 1)
                ->select('id', 'code', 'uri')
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) {
                    $item->name = 'CC ' . strtoupper($item->code);
                    return $item;
                });
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get all active TK labels.
     */
    public function getTkLabels(): Collection
    {
        try {
            return DB::table('rights_tk_label')
                ->where('is_active', 1)
                ->select('id', 'code', 'uri', 'icon_path', 'category', 'color')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    /**
     * Get donors/actors for rights holder selection.
     */
    public function getDonors(int $limit = 200): Collection
    {
        try {
            return DB::table('actor as a')
                ->leftJoin('actor_i18n as ai', function ($j) {
                    $j->on('ai.id', '=', 'a.id')
                        ->where('ai.culture', '=', $this->culture);
                })
                ->select(['a.id', 'ai.authorized_form_of_name as name'])
                ->whereNotNull('ai.authorized_form_of_name')
                ->orderBy('ai.authorized_form_of_name')
                ->limit($limit)
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            return collect();
        }
    }

    // =========================================
    // JSON-LD EXPORT
    // =========================================

    /**
     * Build a JSON-LD rights object for an IO.
     */
    public function exportJsonLd(int $objectId, ?string $culture = null): array
    {
        $culture = $culture ?? $this->culture;

        // Get standard rights via relation
        $standardRights = $this->getRightsForObject($objectId, $culture);

        // Get extended rights
        $extendedRights = $this->getExtendedRights($objectId);

        // Get active embargo
        $embargo = $this->getActiveEmbargo($objectId);

        // Get IO metadata
        $io = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        $siteUrl = rtrim(config('app.url', ''), '/');

        $jsonLd = [
            '@context' => [
                '@vocab'  => 'http://schema.org/',
                'dcterms' => 'http://purl.org/dc/terms/',
                'edm'     => 'http://www.europeana.eu/schemas/edm/',
                'cc'      => 'http://creativecommons.org/ns#',
                'rs'      => 'http://rightsstatements.org/vocab/',
            ],
            '@type'      => 'ArchiveComponent',
            '@id'        => $siteUrl . '/' . ($io->slug ?? ''),
            'identifier' => $io->identifier ?? null,
            'name'       => $io->title ?? null,
        ];

        // Standard rights
        if ($standardRights->isNotEmpty()) {
            $jsonLd['rights'] = $standardRights->map(function ($r) {
                return [
                    '@type'       => 'PropertyValue',
                    'propertyID'  => 'rights',
                    'value'       => $r->rights_note ?? '',
                    'description' => $r->copyright_note ?? '',
                    'startDate'   => $r->start_date,
                    'endDate'     => $r->end_date,
                ];
            })->values()->toArray();
        }

        // Extended rights - rights statement
        $primary = $extendedRights->firstWhere('is_primary', 1);
        if ($primary && !empty($primary->rights_statement_uri)) {
            $jsonLd['dcterms:rights'] = [
                '@id'   => $primary->rights_statement_uri,
                '@type' => 'dcterms:RightsStatement',
                'name'  => $primary->rights_statement_name ?? $primary->rights_statement_code ?? null,
            ];
        }

        // Extended rights - CC license
        if ($primary && !empty($primary->cc_license_uri)) {
            $jsonLd['cc:license'] = [
                '@id'   => $primary->cc_license_uri,
                '@type' => 'cc:License',
                'name'  => $primary->cc_license_code ?? null,
            ];
        }

        // Embargo info
        if ($embargo) {
            $jsonLd['accessRestriction'] = [
                '@type'     => 'PropertyValue',
                'propertyID'=> 'embargo',
                'value'     => $embargo->embargo_type,
                'startDate' => $embargo->start_date,
                'endDate'   => $embargo->end_date,
                'status'    => $embargo->status,
            ];
        }

        return $jsonLd;
    }
}
