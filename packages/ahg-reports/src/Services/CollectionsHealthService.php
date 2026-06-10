<?php

/**
 * CollectionsHealthService - Service for Heratio
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



namespace AhgReports\Services;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates cross-collection "health" signals for the Collections Health
 * dashboard (issue #1215). Read-only: every method is a pure aggregate over
 * existing tables, creates no rows.
 *
 * Scope note: the archival-record universe is information_object rows with
 * parent_id != ROOT_ID (the synthetic root). Digital-object and
 * condition-report coverage are measured against that same denominator so the
 * percentages are directly comparable.
 */
class CollectionsHealthService
{
    /** Synthetic root information_object id; real records sit beneath it. */
    private const ROOT_ID = 1;

    /**
     * One call returns every KPI block the dashboard renders.
     *
     * @return array{
     *   domains: array<int,array{class_name:string,label:string,count:int}>,
     *   total_objects:int,
     *   io: array{total:int,published:int,draft:int,unassessed:int,
     *             published_pct:float,draft_pct:float,unassessed_pct:float},
     *   digital: array{with:int,without:int,total:int,pct:float},
     *   condition: array{with:int,without:int,total:int,pct:float}
     * }
     */
    public function getHealthStats(): array
    {
        return [
            'domains'       => $this->domainCounts(),
            'total_objects' => (int) DB::table('object')->count(),
            'io'            => $this->publicationCoverage(),
            'digital'       => $this->digitalCoverage(),
            'condition'     => $this->conditionCoverage(),
        ];
    }

    /**
     * Record counts grouped by object.class_name (GLAM domain), with a
     * friendly label for the four primary archival classes.
     *
     * @return array<int,array{class_name:string,label:string,count:int}>
     */
    public function domainCounts(): array
    {
        $labels = [
            'QubitInformationObject' => 'Archival descriptions',
            'QubitActor'             => 'Authority records',
            'QubitRepository'        => 'Repositories',
            'QubitTerm'              => 'Taxonomy terms',
        ];

        $rows = DB::table('object')
            ->select('class_name', DB::raw('COUNT(*) AS cnt'))
            ->groupBy('class_name')
            ->orderByDesc('cnt')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $name = (string) $r->class_name;
            $out[] = [
                'class_name' => $name,
                'label'      => $labels[$name] ?? $this->humanise($name),
                'count'      => (int) $r->cnt,
            ];
        }

        return $out;
    }

    /**
     * Publication-status coverage across real archival descriptions.
     *
     * published   = a status row (type=publication) set to Published
     * draft       = a status row (type=publication) set to Draft
     * unassessed  = total real IOs minus those two (no publication status row,
     *               or a status value outside published/draft)
     *
     * @return array{total:int,published:int,draft:int,unassessed:int,
     *               published_pct:float,draft_pct:float,unassessed_pct:float}
     */
    public function publicationCoverage(): array
    {
        $total = (int) DB::table('information_object')
            ->where('parent_id', '!=', self::ROOT_ID)
            ->count();

        $published = (int) DB::table('status')
            ->join('information_object', 'information_object.id', '=', 'status.object_id')
            ->where('information_object.parent_id', '!=', self::ROOT_ID)
            ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->where('status.status_id', TermId::PUBLICATION_STATUS_PUBLISHED)
            ->distinct()
            ->count('status.object_id');

        $draft = (int) DB::table('status')
            ->join('information_object', 'information_object.id', '=', 'status.object_id')
            ->where('information_object.parent_id', '!=', self::ROOT_ID)
            ->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->where('status.status_id', TermId::PUBLICATION_STATUS_DRAFT)
            ->distinct()
            ->count('status.object_id');

        $unassessed = max(0, $total - $published - $draft);

        return [
            'total'          => $total,
            'published'      => $published,
            'draft'          => $draft,
            'unassessed'     => $unassessed,
            'published_pct'  => $this->pct($published, $total),
            'draft_pct'      => $this->pct($draft, $total),
            'unassessed_pct' => $this->pct($unassessed, $total),
        ];
    }

    /**
     * Digital-object coverage: distinct real IOs carrying a digital_object
     * row directly on the record, against the total real-IO count.
     *
     * @return array{with:int,without:int,total:int,pct:float}
     */
    public function digitalCoverage(): array
    {
        $total = (int) DB::table('information_object')
            ->where('parent_id', '!=', self::ROOT_ID)
            ->count();

        $with = (int) DB::table('digital_object')
            ->join('information_object', 'information_object.id', '=', 'digital_object.object_id')
            ->where('information_object.parent_id', '!=', self::ROOT_ID)
            ->distinct()
            ->count('digital_object.object_id');

        return [
            'with'    => $with,
            'without' => max(0, $total - $with),
            'total'   => $total,
            'pct'     => $this->pct($with, $total),
        ];
    }

    /**
     * Preservation-assessment coverage: distinct real IOs with at least one
     * condition_report, against the total real-IO count.
     *
     * @return array{with:int,without:int,total:int,pct:float}
     */
    public function conditionCoverage(): array
    {
        $total = (int) DB::table('information_object')
            ->where('parent_id', '!=', self::ROOT_ID)
            ->count();

        $with = 0;
        if (Schema::hasTable('condition_report')) {
            $with = (int) DB::table('condition_report')
                ->join('information_object', 'information_object.id', '=', 'condition_report.information_object_id')
                ->where('information_object.parent_id', '!=', self::ROOT_ID)
                ->distinct()
                ->count('condition_report.information_object_id');
        }

        return [
            'with'    => $with,
            'without' => max(0, $total - $with),
            'total'   => $total,
            'pct'     => $this->pct($with, $total),
        ];
    }

    /** Percentage of $part over $whole, one decimal, guards divide-by-zero. */
    private function pct(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round($part / $whole * 100, 1);
    }

    /** Turn a "QubitFooBar" class name into "Foo bar" for display. */
    private function humanise(string $className): string
    {
        $name = preg_replace('/^(Qubit|Ric)/', '', $className) ?? $className;
        $name = preg_replace('/(?<!^)([A-Z])/', ' $1', $name) ?? $name;

        return ucfirst(strtolower(trim($name)));
    }
}
