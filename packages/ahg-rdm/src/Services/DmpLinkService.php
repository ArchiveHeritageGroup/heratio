<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use AhgResearch\Services\DmpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Link an RDM dataset to a Data Management Plan (#1337 Feature 1).
 *
 * This is pure orchestration over the EXISTING ahg-research DMP builder
 * (DmpService + research_dmp/research_dmp_section): a DMP is authored once, in
 * the research portal, and a dataset just carries a reference to it
 * (rdm_dataset.dmp_id). This package never owns DMP data and never duplicates
 * the maDMP authoring tool - it reads the plan for display and writes only the
 * single foreign-key-by-convention column.
 *
 * A DMP is project-scoped, so linkage requires the dataset to have a project.
 */
class DmpLinkService
{
    /**
     * Everything the dataset views need to render the DMP panel. Degrades to
     * 'unavailable' when the ahg-research DMP slice is not installed.
     *
     * @return array{available:bool, project_id:?int, plans:array, linked:?array, completeness:?array, index_url:?string, show_url:?string}
     */
    public function context(object $dataset): array
    {
        $available = class_exists(DmpService::class)
            && Schema::hasTable('research_dmp')
            && Route::has('research.dmp.index');

        $projectId = isset($dataset->project_id) ? (int) $dataset->project_id : 0;
        $dmpId     = isset($dataset->dmp_id) ? (int) $dataset->dmp_id : 0;

        $ctx = [
            'available'    => $available,
            'project_id'   => $projectId ?: null,
            'plans'        => [],
            'linked'       => null,
            'completeness' => null,
            'index_url'    => null,
            'show_url'     => null,
        ];

        if (! $available) {
            return $ctx;
        }

        $dmp = app(DmpService::class);

        if ($projectId) {
            $ctx['plans']     = $dmp->listPlans($projectId);
            $ctx['index_url'] = route('research.dmp.index', $projectId);
        }

        if ($dmpId) {
            $plan = $dmp->getPlan($dmpId);
            if ($plan) {
                $ctx['linked']       = $plan;
                $ctx['completeness'] = $dmp->completeness($dmp->getSections($dmpId));
                if ($projectId && Route::has('research.dmp.show')) {
                    $ctx['show_url'] = route('research.dmp.show', [$projectId, $dmpId]);
                }
            } else {
                // The plan was deleted out from under us; clear the dangling link.
                DB::table('rdm_dataset')->where('id', $dataset->id)->update(['dmp_id' => null]);
            }
        }

        return $ctx;
    }

    /**
     * Link an existing plan to the dataset. The plan MUST belong to the
     * dataset's project (a DMP is project-scoped), else the link is refused.
     */
    public function link(int $datasetId, int $dmpId, ?int $userId = null): bool
    {
        $ds = DB::table('rdm_dataset')->where('id', $datasetId)->first();
        if (! $ds || empty($ds->project_id) || ! Schema::hasTable('research_dmp')) {
            return false;
        }

        $owns = DB::table('research_dmp')
            ->where('id', $dmpId)
            ->where('project_id', $ds->project_id)
            ->exists();
        if (! $owns) {
            return false;
        }

        DB::table('rdm_dataset')->where('id', $datasetId)
            ->update(['dmp_id' => $dmpId, 'updated_at' => now()]);

        return true;
    }

    /**
     * Create a fresh maDMP for the dataset's project (via the research
     * DmpService, which seeds the full section set) and link it. Returns the new
     * plan id, or null when there is no project / the DMP slice is absent.
     *
     * @param  array<string,mixed>  $meta  title, funder, contact_name, contact_email, funder_template, language
     */
    public function createAndLink(int $datasetId, array $meta, ?int $userId = null): ?int
    {
        $ds = DB::table('rdm_dataset')->where('id', $datasetId)->first();
        if (! $ds || empty($ds->project_id) || ! class_exists(DmpService::class)) {
            return null;
        }

        // research_project.owner_id is a research_researcher id - reuse it as the
        // plan owner so the DMP shows up correctly in the researcher's portal.
        $ownerId = DB::table('research_project')->where('id', $ds->project_id)->value('owner_id');

        $dmpId = app(DmpService::class)->createPlan(
            (int) $ds->project_id,
            $ownerId ? (int) $ownerId : null,
            $meta
        );
        if (! $dmpId) {
            return null;
        }

        DB::table('rdm_dataset')->where('id', $datasetId)
            ->update(['dmp_id' => $dmpId, 'updated_at' => now()]);

        return $dmpId;
    }

    /** Detach the DMP from the dataset (the plan itself is left untouched). */
    public function unlink(int $datasetId): bool
    {
        DB::table('rdm_dataset')->where('id', $datasetId)
            ->update(['dmp_id' => null, 'updated_at' => now()]);

        return true;
    }
}
