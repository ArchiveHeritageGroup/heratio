<?php

/**
 * SpectrumController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class SpectrumController extends Controller
{
    /**
     * Spectrum 5.1 procedure constants
     */
    const PROC_OBJECT_ENTRY = 'object_entry';
    const PROC_ACQUISITION = 'acquisition';
    const PROC_LOCATION = 'location_movement';
    const PROC_INVENTORY = 'inventory_control';
    const PROC_CATALOGUING = 'cataloguing';
    const PROC_CONDITION = 'condition_checking';
    const PROC_CONSERVATION = 'conservation';
    const PROC_RISK = 'risk_management';
    const PROC_INSURANCE = 'insurance';
    const PROC_VALUATION = 'valuation';
    const PROC_AUDIT = 'audit';
    const PROC_RIGHTS = 'rights_management';
    const PROC_REPRODUCTION = 'reproduction';
    const PROC_LOAN_IN = 'loans_in';
    const PROC_LOAN_OUT = 'loans_out';
    const PROC_LOSS = 'loss_damage';
    const PROC_DEACCESSION = 'deaccession';
    const PROC_DISPOSAL = 'disposal';
    const PROC_DOCUMENTATION = 'documentation_planning';
    const PROC_EXIT = 'object_exit';
    const PROC_RETROSPECTIVE = 'retrospective_documentation';

    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_OVERDUE = 'overdue';

    protected static array $statusColors = [
        self::STATUS_NOT_STARTED => '#95a5a6',
        self::STATUS_IN_PROGRESS => '#3498db',
        self::STATUS_PENDING_REVIEW => '#f39c12',
        self::STATUS_COMPLETED => '#27ae60',
        self::STATUS_ON_HOLD => '#9b59b6',
        self::STATUS_OVERDUE => '#e74c3c',
    ];

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    protected function getCulture(): string
    {
        return app()->getLocale() ?: 'en';
    }

    /**
     * Resolve an information_object by its slug, with i18n title and repository name.
     */
    protected function getResourceBySlug(string $slug): ?object
    {
        $culture = $this->getCulture();

        $slugRecord = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRecord) {
            return null;
        }

        $resource = DB::table('information_object')->where('id', $slugRecord->object_id)->first();
        if (!$resource) {
            return null;
        }

        $resource->slug = $slug;

        $i18n = DB::table('information_object_i18n')
            ->where('id', $resource->id)
            ->where('culture', $culture)
            ->first();
        $resource->title = $i18n->title ?? null;

        if ($resource->repository_id) {
            $repoI18n = DB::table('actor_i18n')
                ->where('id', $resource->repository_id)
                ->where('culture', $culture)
                ->first();
            $resource->repositoryName = $repoI18n->authorized_form_of_name ?? null;
        }

        return $resource;
    }

    /**
     * Get or create a condition check record for a given object.
     */
    protected function getOrCreateConditionCheck(int $objectId): ?object
    {
        if (!Schema::hasTable('spectrum_condition_check')) {
            return null;
        }

        $check = DB::table('spectrum_condition_check')
            ->where('object_id', $objectId)
            ->orderBy('check_date', 'desc')
            ->first();

        if (!$check) {
            $newId = DB::table('spectrum_condition_check')->insertGetId([
                'object_id'                  => $objectId,
                'condition_check_reference'  => 'CC-' . date('Ymd') . '-' . $objectId,
                'check_date'                 => now(),
                'checked_by'                 => Auth::user()->username ?? 'system',
                'created_at'                 => now(),
            ]);
            $check = DB::table('spectrum_condition_check')->where('id', $newId)->first();
        }

        return $check;
    }

    /**
     * Return all Spectrum 5.1 procedure definitions.
     */
    protected function getProcedures(): array
    {
        return [
            self::PROC_OBJECT_ENTRY => [
                'label' => 'Object Entry',
                'description' => 'Recording information about objects entering the museum temporarily or for acquisition consideration.',
                'category' => 'pre-entry',
                'icon' => 'fa-sign-in',
            ],
            self::PROC_ACQUISITION => [
                'label' => 'Acquisition',
                'description' => 'Formally acquiring objects for the permanent collection.',
                'category' => 'acquisition',
                'icon' => 'fa-plus-circle',
            ],
            self::PROC_LOCATION => [
                'label' => 'Location & Movement',
                'description' => 'Tracking object locations and movements within and outside the museum.',
                'category' => 'location',
                'icon' => 'fa-map-marker',
            ],
            self::PROC_INVENTORY => [
                'label' => 'Inventory Control',
                'description' => 'Verifying and reconciling object locations and records.',
                'category' => 'control',
                'icon' => 'fa-list-alt',
            ],
            self::PROC_CATALOGUING => [
                'label' => 'Cataloguing',
                'description' => 'Creating and maintaining catalogue records.',
                'category' => 'documentation',
                'icon' => 'fa-book',
            ],
            self::PROC_CONDITION => [
                'label' => 'Condition Checking',
                'description' => 'Recording and monitoring object condition.',
                'category' => 'care',
                'icon' => 'fa-heartbeat',
            ],
            self::PROC_CONSERVATION => [
                'label' => 'Conservation',
                'description' => 'Planning and documenting conservation treatments.',
                'category' => 'care',
                'icon' => 'fa-medkit',
            ],
            self::PROC_VALUATION => [
                'label' => 'Valuation',
                'description' => 'Recording object valuations for insurance and reporting.',
                'category' => 'financial',
                'icon' => 'fa-dollar-sign',
            ],
            self::PROC_INSURANCE => [
                'label' => 'Insurance',
                'description' => 'Managing insurance for collections.',
                'category' => 'financial',
                'icon' => 'fa-shield-alt',
            ],
            self::PROC_LOAN_IN => [
                'label' => 'Loans In',
                'description' => 'Borrowing objects from other institutions or individuals.',
                'category' => 'loans',
                'icon' => 'fa-arrow-circle-down',
            ],
            self::PROC_LOAN_OUT => [
                'label' => 'Loans Out',
                'description' => 'Lending objects to other institutions.',
                'category' => 'loans',
                'icon' => 'fa-arrow-circle-up',
            ],
            self::PROC_LOSS => [
                'label' => 'Loss & Damage',
                'description' => 'Recording and responding to loss or damage.',
                'category' => 'risk',
                'icon' => 'fa-exclamation-triangle',
            ],
            self::PROC_DEACCESSION => [
                'label' => 'Deaccession',
                'description' => 'Formally removing objects from the collection.',
                'category' => 'disposal',
                'icon' => 'fa-minus-circle',
            ],
            self::PROC_DISPOSAL => [
                'label' => 'Disposal',
                'description' => 'Physically disposing of deaccessioned objects.',
                'category' => 'disposal',
                'icon' => 'fa-trash',
            ],
            self::PROC_EXIT => [
                'label' => 'Object Exit',
                'description' => 'Recording objects leaving the museum.',
                'category' => 'exit',
                'icon' => 'fa-sign-out',
            ],
            self::PROC_RISK => [
                'label' => 'Risk Management',
                'description' => 'Identifying and mitigating collection risks.',
                'category' => 'risk',
                'icon' => 'fa-shield-alt',
            ],
            self::PROC_AUDIT => [
                'label' => 'Audit',
                'description' => 'Auditing collections and procedures.',
                'category' => 'control',
                'icon' => 'fa-clipboard-check',
            ],
            self::PROC_RIGHTS => [
                'label' => 'Rights Management',
                'description' => 'Managing rights and reproductions.',
                'category' => 'documentation',
                'icon' => 'fa-copyright',
            ],
            self::PROC_REPRODUCTION => [
                'label' => 'Reproduction',
                'description' => 'Managing reproduction requests.',
                'category' => 'documentation',
                'icon' => 'fa-copy',
            ],
            self::PROC_DOCUMENTATION => [
                'label' => 'Documentation Planning',
                'description' => 'Planning documentation projects.',
                'category' => 'documentation',
                'icon' => 'fa-file-alt',
            ],
            self::PROC_RETROSPECTIVE => [
                'label' => 'Retrospective Documentation',
                'description' => 'Documenting existing collections retrospectively.',
                'category' => 'documentation',
                'icon' => 'fa-history',
            ],
        ];
    }

    /**
     * Get workflow final states for a procedure type from its config.
     */
    protected function getFinalStates(string $procedureType): array
    {
        if (!Schema::hasTable('spectrum_workflow_config')) {
            return [];
        }

        $config = DB::table('spectrum_workflow_config')
            ->where('procedure_type', $procedureType)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            return [];
        }

        $configData = json_decode($config->config_json, true);

        return $configData['final_states'] ?? [];
    }

    /**
     * Check if a state is a final state for a given procedure.
     */
    protected function isFinalState(string $procedureType, string $state): bool
    {
        return in_array($state, $this->getFinalStates($procedureType));
    }

    /**
     * Get repositories for filter dropdown.
     */
    protected function getRepositoriesForFilter(): array
    {
        try {
            return DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->where('actor_i18n.culture', 'en')
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->select('repository.id', 'actor_i18n.authorized_form_of_name')
                ->orderBy('actor_i18n.authorized_form_of_name')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // ----------------------------------------------------------------
    // Per-object Spectrum index (object entry point)
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $slug = $request->query('slug');
        $resource = null;
        $museumData = [];
        $grapData = null;

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }

            // Museum metadata
            if (Schema::hasTable('museum_metadata')) {
                $mm = DB::table('museum_metadata')->where('object_id', $resource->id)->first();
                if ($mm) {
                    $museumData = (array) $mm;
                }
            }

            // GRAP data
            if (Schema::hasTable('grap_heritage_asset')) {
                $grapData = DB::table('grap_heritage_asset')->where('object_id', $resource->id)->first();
            }
        }

        return view('spectrum::index', [
            'resource'   => $resource,
            'museumData' => $museumData,
            'grapData'   => $grapData,
        ]);
    }

    // ----------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------

    public function dashboard(Request $request)
    {
        $repoId = $request->query('repository') ? (int) $request->query('repository') : null;
        $procedures = $this->getProcedures();

        // Workflow statistics
        $workflowStats = $this->getWorkflowStatistics($repoId);
        $recentActivity = $this->getRecentWorkflowActivity($repoId);
        $procedureStatusCounts = $this->getProcedureStatusCounts($repoId);
        $overallCompletion = $this->calculateOverallCompletion($repoId);
        $repositories = $this->getRepositoriesForFilter();

        return view('spectrum::dashboard', [
            'procedures'            => $procedures,
            'workflowStats'         => $workflowStats,
            'recentActivity'        => $recentActivity,
            'procedureStatusCounts' => $procedureStatusCounts,
            'overallCompletion'     => $overallCompletion,
            'repositories'          => $repositories,
            'selectedRepository'    => $request->query('repository', ''),
        ]);
    }

    // ----------------------------------------------------------------
    // Workflow
    // ----------------------------------------------------------------

    public function workflow(Request $request)
    {
        $slug = $request->query('slug');
        $procedureType = $request->query('procedure_type', self::PROC_ACQUISITION);

        $resource = null;
        $procedures = $this->getProcedures();
        $procedureStatuses = [];
        $currentProcedure = null;
        $timeline = [];
        $procedureTimeline = [];
        $progress = ['total' => 0, 'completed' => 0, 'inProgress' => 0, 'overdue' => 0, 'notStarted' => 0, 'percentComplete' => 0];
        $canEdit = false;

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }

            // Procedure statuses from workflow_state table
            if (Schema::hasTable('spectrum_workflow_state')) {
                $states = DB::table('spectrum_workflow_state')
                    ->where('record_id', $resource->id)
                    ->get();
                foreach ($states as $state) {
                    $procedureStatuses[$state->procedure_type] = $state;
                }
            }

            $currentProcedure = $procedureStatuses[$procedureType] ?? null;

            // Timeline from workflow_history
            if (Schema::hasTable('spectrum_workflow_history')) {
                $history = DB::table('spectrum_workflow_history as h')
                    ->leftJoin('user as u', 'h.user_id', '=', 'u.id')
                    ->where('h.record_id', $resource->id)
                    ->select('h.*', 'u.username as user_name')
                    ->orderBy('h.created_at', 'desc')
                    ->get()
                    ->toArray();

                $timeline = $history;
                $procedureTimeline = array_filter($history, fn($e) => $e->procedure_type === $procedureType);
            }

            // Progress
            $total = count($procedures);
            $completed = 0;
            $inProgress = 0;
            $overdue = 0;
            foreach ($procedures as $procId => $procDef) {
                $st = $procedureStatuses[$procId] ?? null;
                if ($st) {
                    $cs = $st->current_state;
                    if (in_array($cs, ['completed', 'verified', 'closed', 'confirmed'])) {
                        $completed++;
                    } elseif (in_array($cs, ['in_progress', 'pending_review'])) {
                        $inProgress++;
                    }
                }
            }
            $progress = [
                'total'           => $total,
                'completed'       => $completed,
                'inProgress'      => $inProgress,
                'overdue'         => $overdue,
                'notStarted'      => $total - $completed - $inProgress - $overdue,
                'percentComplete' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ];

            $canEdit = Auth::check();
        }

        // Status options
        $statusOptions = [
            self::STATUS_NOT_STARTED    => 'Not Started',
            self::STATUS_IN_PROGRESS    => 'In Progress',
            self::STATUS_PENDING_REVIEW => 'Pending Review',
            self::STATUS_COMPLETED      => 'Completed',
            self::STATUS_ON_HOLD        => 'On Hold',
        ];

        // Workflow config for available transitions
        $workflowConfig = null;
        if (Schema::hasTable('spectrum_workflow_config')) {
            $wc = DB::table('spectrum_workflow_config')
                ->where('procedure_type', $procedureType)
                ->where('is_active', 1)
                ->first();
            if ($wc) {
                $workflowConfig = json_decode($wc->config_json, true);
            }
        }

        // Users for assignment dropdown
        $users = DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get();

        return view('spectrum::workflow', [
            'resource'            => $resource,
            'procedureType'       => $procedureType,
            'procedures'          => $procedures,
            'procedureStatuses'   => $procedureStatuses,
            'currentProcedure'    => $currentProcedure,
            'timeline'            => $timeline,
            'procedureTimeline'   => $procedureTimeline,
            'progress'            => $progress,
            'statusOptions'       => $statusOptions,
            'statusColors'        => self::$statusColors,
            'canEdit'             => $canEdit,
            'workflowConfig'      => $workflowConfig,
            'users'               => $users,
        ]);
    }

    // ----------------------------------------------------------------
    // General Procedures (institution-level, record_id = 0)
    // ----------------------------------------------------------------

    public function general(Request $request)
    {
        $procedures = $this->getProcedures();
        $procedureStatuses = [];
        $recentHistory = [];

        if (Schema::hasTable('spectrum_workflow_state')) {
            $states = DB::table('spectrum_workflow_state')->where('record_id', 0)->get();
            foreach ($states as $state) {
                $procedureStatuses[$state->procedure_type] = $state->current_state;
            }
        }

        if (Schema::hasTable('spectrum_workflow_history')) {
            $recentHistory = DB::table('spectrum_workflow_history as h')
                ->leftJoin('user as u', 'h.user_id', '=', 'u.id')
                ->where('h.record_id', 0)
                ->select('h.*', 'u.username as user_name')
                ->orderBy('h.created_at', 'desc')
                ->limit(10)
                ->get()
                ->toArray();
        }

        return view('spectrum::general', [
            'procedures'        => $procedures,
            'procedureStatuses' => $procedureStatuses,
            'recentHistory'     => $recentHistory,
        ]);
    }

    public function generalWorkflow(Request $request)
    {
        $procedureType = $request->query('procedure_type', self::PROC_ACQUISITION);
        $procedures = $this->getProcedures();
        $canEdit = Auth::check();

        $workflowConfig = null;
        $currentState = null;
        $history = [];
        $users = DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get();

        if (Schema::hasTable('spectrum_workflow_config')) {
            $wc = DB::table('spectrum_workflow_config')
                ->where('procedure_type', $procedureType)
                ->where('is_active', 1)
                ->first();
            if ($wc) {
                $workflowConfig = json_decode($wc->config_json, true);
            }
        }

        if (Schema::hasTable('spectrum_workflow_state')) {
            $ws = DB::table('spectrum_workflow_state')
                ->where('record_id', 0)
                ->where('procedure_type', $procedureType)
                ->first();
            $currentState = $ws->current_state ?? null;
        }

        if (Schema::hasTable('spectrum_workflow_history')) {
            $history = DB::table('spectrum_workflow_history as h')
                ->leftJoin('user as u', 'h.user_id', '=', 'u.id')
                ->where('h.record_id', 0)
                ->where('h.procedure_type', $procedureType)
                ->select('h.*', 'u.username as user_name')
                ->orderBy('h.created_at', 'desc')
                ->get()
                ->toArray();
        }

        return view('spectrum::general-workflow', [
            'procedureType'  => $procedureType,
            'procedures'     => $procedures,
            'isGeneral'      => true,
            'recordId'       => 0,
            'canEdit'        => $canEdit,
            'workflowConfig' => $workflowConfig,
            'currentState'   => $currentState,
            'history'        => $history,
            'users'          => $users,
            'statusColors'   => self::$statusColors,
        ]);
    }

    // ----------------------------------------------------------------
    // My Tasks
    // ----------------------------------------------------------------

    public function myTasks(Request $request)
    {
        $userId = Auth::id();
        $culture = $this->getCulture();
        $procedureTypeFilter = $request->query('procedure_type');

        $workflowConfigs = [];
        $finalStatesByProcedure = [];

        if (Schema::hasTable('spectrum_workflow_config')) {
            $configs = DB::table('spectrum_workflow_config')->where('is_active', 1)->get();
            foreach ($configs as $config) {
                $configData = json_decode($config->config_json, true);
                $workflowConfigs[$config->procedure_type] = $configData;
                $finals = $configData['final_states'] ?? [];
                if (!empty($finals)) {
                    $finalStatesByProcedure[$config->procedure_type] = $finals;
                }
            }
        }

        $query = DB::table('spectrum_workflow_state as sws')
            ->select([
                'sws.*',
                'io.id as object_id',
                'io.identifier',
                'io.repository_id',
                'ioi18n.title as object_title',
                'slug.slug',
                'assigner.username as assigned_by_name',
            ])
            ->leftJoin('information_object as io', 'sws.record_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('user as assigner', 'sws.assigned_by', '=', 'assigner.id')
            ->where('sws.assigned_to', $userId);

        // Exclude final states per procedure
        if (!empty($finalStatesByProcedure)) {
            $query->where(function ($q) use ($finalStatesByProcedure) {
                foreach ($finalStatesByProcedure as $proc => $finals) {
                    $q->where(function ($inner) use ($proc, $finals) {
                        $inner->where('sws.procedure_type', '!=', $proc)
                              ->orWhereNotIn('sws.current_state', $finals);
                    });
                }
            });
        }

        if ($procedureTypeFilter) {
            $query->where('sws.procedure_type', $procedureTypeFilter);
        }

        $query->orderBy('sws.assigned_at', 'desc');
        $tasks = $query->get();

        $procedures = $this->getProcedures();

        $unreadCount = 0;
        if (Schema::hasTable('spectrum_notification')) {
            $unreadCount = DB::table('spectrum_notification')
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->count();
        }

        $procedureTypes = [];
        if (Schema::hasTable('spectrum_workflow_state')) {
            $procedureTypes = DB::table('spectrum_workflow_state')
                ->where('assigned_to', $userId)
                ->distinct()
                ->pluck('procedure_type')
                ->toArray();
        }

        return view('spectrum::my-tasks', [
            'tasks'           => $tasks,
            'procedures'      => $procedures,
            'workflowConfigs' => $workflowConfigs,
            'unreadCount'     => $unreadCount,
            'procedureTypes'  => $procedureTypes,
            'currentFilter'   => $procedureTypeFilter,
            'statusColors'    => self::$statusColors,
        ]);
    }

    // ----------------------------------------------------------------
    // Label
    // ----------------------------------------------------------------

    public function label(Request $request)
    {
        $slug = $request->query('slug');
        $resource = null;

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }
        }

        return view('spectrum::label', [
            'resource'  => $resource,
            'labelType' => $request->query('type', 'full'),
            'labelSize' => $request->query('size', 'medium'),
        ]);
    }

    // ----------------------------------------------------------------
    // Object Entry browse
    // ----------------------------------------------------------------

    public function objectEntry(Request $request)
    {
        $entries = collect();

        if (Schema::hasTable('spectrum_object_entry')) {
            $culture = $this->getCulture();

            $entries = DB::table('spectrum_object_entry as e')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('e.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'e.object_id', '=', 's.object_id')
                ->select(
                    'e.*',
                    'i18n.title as object_title',
                    's.slug'
                )
                ->orderBy('e.entry_date', 'desc')
                ->paginate(25);
        }

        return view('spectrum::object-entry', [
            'entries' => $entries,
        ]);
    }

    // ----------------------------------------------------------------
    // Acquisitions browse
    // ----------------------------------------------------------------

    public function acquisitions(Request $request)
    {
        $acquisitions = collect();

        if (Schema::hasTable('spectrum_acquisition')) {
            $culture = $this->getCulture();

            $acquisitions = DB::table('spectrum_acquisition as a')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('a.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'a.object_id', '=', 's.object_id')
                ->select(
                    'a.*',
                    'i18n.title as object_title',
                    's.slug'
                )
                ->orderBy('a.acquisition_date', 'desc')
                ->paginate(25);
        }

        return view('spectrum::acquisitions', [
            'acquisitions' => $acquisitions,
        ]);
    }

    // ----------------------------------------------------------------
    // Loans browse (in + out combined)
    // ----------------------------------------------------------------

    public function loans(Request $request)
    {
        $loansIn = collect();
        $loansOut = collect();
        $culture = $this->getCulture();

        if (Schema::hasTable('spectrum_loan_in')) {
            $loansIn = DB::table('spectrum_loan_in as l')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('l.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'l.object_id', '=', 's.object_id')
                ->select(
                    'l.*',
                    'i18n.title as object_title',
                    's.slug',
                    DB::raw("'in' as direction")
                )
                ->orderBy('l.loan_in_date', 'desc')
                ->get();
        }

        if (Schema::hasTable('spectrum_loan_out')) {
            $loansOut = DB::table('spectrum_loan_out as l')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('l.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'l.object_id', '=', 's.object_id')
                ->select(
                    'l.*',
                    'i18n.title as object_title',
                    's.slug',
                    DB::raw("'out' as direction")
                )
                ->orderBy('l.loan_out_date', 'desc')
                ->get();
        }

        return view('spectrum::loans', [
            'loansIn'  => $loansIn,
            'loansOut' => $loansOut,
        ]);
    }

    // ----------------------------------------------------------------
    // Movements browse
    // ----------------------------------------------------------------

    public function movements(Request $request)
    {
        $movements = collect();

        if (Schema::hasTable('spectrum_movement')) {
            $culture = $this->getCulture();

            $query = DB::table('spectrum_movement as m')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'm.object_id', '=', 's.object_id')
                ->leftJoin('spectrum_location as loc_from', 'm.location_from', '=', 'loc_from.id')
                ->leftJoin('spectrum_location as loc_to', 'm.location_to', '=', 'loc_to.id')
                ->select(
                    'm.*',
                    'i18n.title as object_title',
                    's.slug',
                    'loc_from.location_name as from_location_name',
                    'loc_to.location_name as to_location_name'
                )
                ->orderBy('m.movement_date', 'desc');

            $movements = $query->paginate(25);
        }

        return view('spectrum::movements', [
            'movements' => $movements,
        ]);
    }

    // ----------------------------------------------------------------
    // Conditions browse
    // ----------------------------------------------------------------

    public function conditions(Request $request)
    {
        $checks = collect();

        if (Schema::hasTable('spectrum_condition_check')) {
            $culture = $this->getCulture();

            $checks = DB::table('spectrum_condition_check as c')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('c.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'c.object_id', '=', 's.object_id')
                ->select(
                    'c.*',
                    'i18n.title as object_title',
                    's.slug'
                )
                ->orderBy('c.check_date', 'desc')
                ->paginate(25);
        }

        return view('spectrum::conditions', [
            'checks' => $checks,
        ]);
    }

    // ----------------------------------------------------------------
    // Conservation browse
    // ----------------------------------------------------------------

    public function conservation(Request $request)
    {
        $treatments = collect();

        if (Schema::hasTable('spectrum_conservation')) {
            $culture = $this->getCulture();

            $treatments = DB::table('spectrum_conservation as c')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('c.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'c.object_id', '=', 's.object_id')
                ->select(
                    'c.*',
                    'i18n.title as object_title',
                    's.slug'
                )
                ->orderBy('c.treatment_date', 'desc')
                ->paginate(25);
        }

        return view('spectrum::conservation', [
            'treatments' => $treatments,
        ]);
    }

    // ----------------------------------------------------------------
    // Valuations browse
    // ----------------------------------------------------------------

    public function valuations(Request $request)
    {
        $valuations = collect();

        if (Schema::hasTable('spectrum_valuation')) {
            $culture = $this->getCulture();

            $valuations = DB::table('spectrum_valuation as v')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('v.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'v.object_id', '=', 's.object_id')
                ->select(
                    'v.*',
                    'i18n.title as object_title',
                    's.slug'
                )
                ->orderBy('v.valuation_date', 'desc')
                ->paginate(25);
        }

        return view('spectrum::valuations', [
            'valuations' => $valuations,
        ]);
    }

    // ----------------------------------------------------------------
    // Condition Admin
    // ----------------------------------------------------------------

    public function conditionAdmin(Request $request)
    {
        $culture = $this->getCulture();
        $recentEvents = [];
        $stats = ['total_checks' => 0, 'critical' => 0, 'poor' => 0];
        $pendingScheduled = [];

        if (Schema::hasTable('spectrum_condition_check')) {
            $recentEvents = DB::table('spectrum_condition_check as c')
                ->leftJoin('information_object as io', 'c.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                ->select('c.*', 'i18n.title', 's.slug')
                ->orderBy('c.check_date', 'desc')
                ->limit(20)
                ->get()
                ->toArray();

            $stats['total_checks'] = DB::table('spectrum_condition_check')->count();
            $stats['critical'] = DB::table('spectrum_condition_check')->where('overall_condition', 'critical')->count();
            $stats['poor'] = DB::table('spectrum_condition_check')->where('overall_condition', 'poor')->count();

            $pendingScheduled = DB::table('spectrum_condition_check')
                ->where('workflow_state', 'scheduled')
                ->whereNotNull('next_check_date')
                ->where('next_check_date', '<=', now()->addDays(30))
                ->orderBy('next_check_date')
                ->limit(10)
                ->get()
                ->toArray();
        }

        return view('spectrum::condition-admin', [
            'recentEvents'     => $recentEvents,
            'stats'            => $stats,
            'pendingScheduled' => $pendingScheduled,
        ]);
    }

    // ----------------------------------------------------------------
    // Condition Photos
    // ----------------------------------------------------------------

    public function conditionPhotos(Request $request)
    {
        $slug = $request->query('slug');
        $resource = null;
        $conditionCheck = null;
        $conditionCheckId = $request->query('condition_id');
        $photos = [];
        $photosByType = [];
        $conditionChecks = [];

        $photoTypes = [
            'overall' => 'Overall View',
            'detail'  => 'Detail',
            'damage'  => 'Damage/Deterioration',
            'before'  => 'Before Treatment',
            'after'   => 'After Treatment',
            'other'   => 'Other',
        ];

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }

            if (Schema::hasTable('spectrum_condition_check')) {
                if ($conditionCheckId) {
                    $conditionCheck = DB::table('spectrum_condition_check')
                        ->where('id', $conditionCheckId)
                        ->first();
                }
                if (!$conditionCheck) {
                    $conditionCheck = $this->getOrCreateConditionCheck($resource->id);
                }
                if ($conditionCheck) {
                    $conditionCheckId = $conditionCheck->id;
                }

                // Get all condition checks for this object
                $conditionChecks = DB::table('spectrum_condition_check')
                    ->where('object_id', $resource->id)
                    ->orderBy('check_date', 'desc')
                    ->get()
                    ->toArray();
            }

            if ($conditionCheckId && Schema::hasTable('spectrum_condition_photo')) {
                $rawPhotos = DB::table('spectrum_condition_photo')
                    ->where('condition_check_id', $conditionCheckId)
                    ->orderBy('sort_order')
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($rawPhotos as $photo) {
                    $arr = (array) $photo;
                    $photos[] = $arr;
                    $type = $photo->photo_type ?? 'other';
                    $photosByType[$type][] = $arr;
                }
            }
        }

        return view('spectrum::condition-photos', [
            'resource'         => $resource,
            'conditionCheck'   => $conditionCheck ? (array) $conditionCheck : null,
            'conditionCheckId' => $conditionCheckId,
            'photos'           => $photos,
            'photosByType'     => $photosByType,
            'photoTypes'       => $photoTypes,
            'conditionChecks'  => $conditionChecks,
        ]);
    }

    // ----------------------------------------------------------------
    // Condition Risk
    // ----------------------------------------------------------------

    public function conditionRisk(Request $request)
    {
        $culture = $this->getCulture();
        $riskItems = [];

        if (Schema::hasTable('spectrum_condition_check')) {
            $riskItems = DB::table('spectrum_condition_check as c')
                ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                    $j->on('c.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'c.object_id', '=', 's.object_id')
                ->whereIn('c.overall_condition', ['critical', 'poor'])
                ->select('c.*', 'i18n.title', 's.slug')
                ->orderBy('c.check_date', 'desc')
                ->get()
                ->toArray();
        }

        return view('spectrum::condition-risk', [
            'riskItems'  => $riskItems,
            'riskMatrix' => [],
            'trends'     => [],
        ]);
    }

    // ----------------------------------------------------------------
    // Data Quality
    // ----------------------------------------------------------------

    public function dataQuality(Request $request)
    {
        $totalObjects = DB::table('information_object')->where('id', '!=', 1)->count();

        $missingTitles = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', '!=', 1)
            ->whereNull('i18n.title')
            ->count();

        $missingDates = DB::table('information_object')
            ->where('id', '!=', 1)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('event')
                    ->whereColumn('event.object_id', 'information_object.id');
            })
            ->count();

        $missingRepository = DB::table('information_object')
            ->where('id', '!=', 1)
            ->whereNull('repository_id')
            ->count();

        $missingDigitalObjects = DB::table('information_object')
            ->where('id', '!=', 1)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereColumn('digital_object.object_id', 'information_object.id');
            })
            ->count();

        $issues = $missingTitles + $missingDates + $missingRepository;
        $qualityScore = $totalObjects > 0 ? round((1 - ($issues / ($totalObjects * 3))) * 100) : 100;

        return view('spectrum::data-quality', [
            'totalObjects'         => $totalObjects,
            'missingTitles'        => $missingTitles,
            'missingDates'         => $missingDates,
            'missingRepository'    => $missingRepository,
            'missingDigitalObjects' => $missingDigitalObjects,
            'qualityScore'         => $qualityScore,
        ]);
    }

    // ----------------------------------------------------------------
    // GRAP Dashboard
    // ----------------------------------------------------------------

    public function grapDashboard(Request $request)
    {
        $slug = $request->query('slug');
        $resource = null;
        $grapData = null;
        $totalAssets = 0;
        $valuedAssets = 0;
        $pendingValuation = 0;
        $totalValue = 0;
        $assetRegisterComplete = false;
        $valuationsCurrent = false;
        $conditionComplete = false;
        $depreciationRecorded = false;
        $insuranceComplete = false;

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }

            if (Schema::hasTable('grap_heritage_asset')) {
                $grapData = DB::table('grap_heritage_asset')
                    ->where('object_id', $resource->id)
                    ->first();
            }

            if (Schema::hasTable('spectrum_grap_data')) {
                $gd = DB::table('spectrum_grap_data')
                    ->where('information_object_id', $resource->id)
                    ->first();

                if ($gd) {
                    $totalAssets = 1;
                    $valuedAssets = $gd->carrying_amount ? 1 : 0;
                    $pendingValuation = $gd->carrying_amount ? 0 : 1;
                    $totalValue = (float) ($gd->carrying_amount ?? 0);
                    $assetRegisterComplete = (bool) $gd->initial_recognition_date;
                    $valuationsCurrent = $gd->last_revaluation_date && strtotime($gd->last_revaluation_date) > strtotime('-5 years');
                    $insuranceComplete = (float) ($gd->insurance_coverage_actual ?? 0) > 0;
                    $depreciationRecorded = (float) ($gd->accumulated_depreciation ?? 0) > 0;
                }
            }
        }

        // Summary stats for institution-level GRAP dashboard (no slug)
        $grapSummary = [];
        if (!$slug && Schema::hasTable('spectrum_grap_data')) {
            $grapSummary = [
                'total_assets'       => DB::table('spectrum_grap_data')->count(),
                'total_value'        => DB::table('spectrum_grap_data')->sum('carrying_amount'),
                'pending_valuation'  => DB::table('spectrum_grap_data')->whereNull('last_revaluation_date')->count(),
                'depreciation_total' => DB::table('spectrum_grap_data')->sum('accumulated_depreciation'),
            ];
        }

        return view('spectrum::grap-dashboard', [
            'resource'              => $resource,
            'grapData'              => $grapData,
            'grapSummary'           => $grapSummary,
            'totalAssets'           => $totalAssets,
            'valuedAssets'          => $valuedAssets,
            'pendingValuation'      => $pendingValuation,
            'totalValue'            => $totalValue,
            'assetRegisterComplete' => $assetRegisterComplete,
            'valuationsCurrent'     => $valuationsCurrent,
            'conditionComplete'     => $conditionComplete,
            'depreciationRecorded'  => $depreciationRecorded,
            'insuranceComplete'     => $insuranceComplete,
        ]);
    }

    // ----------------------------------------------------------------
    // Export
    // ----------------------------------------------------------------

    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $type = $request->query('type', 'condition');
        $slug = $request->query('slug');

        // If download requested, stream the file
        if ($request->query('download')) {
            return $this->handleSpectrumDownload($format, $type, $slug);
        }

        $exportTypes = [
            'condition' => 'Condition Check History',
            'valuation' => 'Valuation History',
            'movement'  => 'Movement/Location History',
            'loan'      => 'Loan History',
            'workflow'  => 'Workflow History',
        ];

        $objectId = null;
        $identifier = null;

        if ($slug) {
            $slugRecord = DB::table('slug')->where('slug', $slug)->first();
            if ($slugRecord) {
                $objectId = $slugRecord->object_id;
                $object = DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as i18n', function ($j) {
                        $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                    })
                    ->where('io.id', $objectId)
                    ->select('io.identifier', 'i18n.title')
                    ->first();
                $identifier = $object ? ($object->title ?: $object->identifier) : $slug;
            }
        }

        // Get counts
        $counts = [
            'movements'  => Schema::hasTable('spectrum_movement') ? DB::table('spectrum_movement')->when($objectId, fn($q) => $q->where('object_id', $objectId))->count() : 0,
            'conditions' => Schema::hasTable('spectrum_condition_check') ? DB::table('spectrum_condition_check')->when($objectId, fn($q) => $q->where('object_id', $objectId))->count() : 0,
            'valuations' => Schema::hasTable('spectrum_valuation') ? DB::table('spectrum_valuation')->when($objectId, fn($q) => $q->where('object_id', $objectId))->count() : 0,
            'loansIn'    => Schema::hasTable('spectrum_loan_in') ? DB::table('spectrum_loan_in')->when($objectId, fn($q) => $q->where('object_id', $objectId))->count() : 0,
            'loansOut'   => Schema::hasTable('spectrum_loan_out') ? DB::table('spectrum_loan_out')->when($objectId, fn($q) => $q->where('object_id', $objectId))->count() : 0,
        ];

        return view('spectrum::export', [
            'exportTypes' => $exportTypes,
            'format'      => $format,
            'slug'        => $slug,
            'identifier'  => $identifier,
            'counts'      => $counts,
        ]);
    }

    public function spectrumExport(Request $request)
    {
        // Alias to export for backward compatibility
        return $this->export($request);
    }

    /**
     * Handle actual file download for spectrum data.
     */
    protected function handleSpectrumDownload(string $format, string $type, ?string $slug)
    {
        $culture = $this->getCulture();
        $data = [];
        $filename = "spectrum_{$type}_" . date('Y-m-d');

        $objectId = null;
        if ($slug) {
            $slugRec = DB::table('slug')->where('slug', $slug)->first();
            $objectId = $slugRec->object_id ?? null;
        }

        switch ($type) {
            case 'condition':
                if (Schema::hasTable('spectrum_condition_check')) {
                    $q = DB::table('spectrum_condition_check as c')
                        ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                            $j->on('c.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                        })
                        ->select('c.*', 'i18n.title as object_title')
                        ->orderBy('c.check_date', 'desc');
                    if ($objectId) { $q->where('c.object_id', $objectId); }
                    $data = $q->get()->toArray();
                }
                break;

            case 'valuation':
                if (Schema::hasTable('spectrum_valuation')) {
                    $q = DB::table('spectrum_valuation as v')
                        ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                            $j->on('v.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                        })
                        ->select('v.*', 'i18n.title as object_title')
                        ->orderBy('v.valuation_date', 'desc');
                    if ($objectId) { $q->where('v.object_id', $objectId); }
                    $data = $q->get()->toArray();
                }
                break;

            case 'movement':
                if (Schema::hasTable('spectrum_movement')) {
                    $q = DB::table('spectrum_movement as m')
                        ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                            $j->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                        })
                        ->select('m.*', 'i18n.title as object_title')
                        ->orderBy('m.movement_date', 'desc');
                    if ($objectId) { $q->where('m.object_id', $objectId); }
                    $data = $q->get()->toArray();
                }
                break;

            case 'loan':
                if (Schema::hasTable('spectrum_loan_in')) {
                    $qIn = DB::table('spectrum_loan_in as l')
                        ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                            $j->on('l.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                        })
                        ->select('l.*', 'i18n.title as object_title', DB::raw("'IN' as direction"));
                    if ($objectId) { $qIn->where('l.object_id', $objectId); }
                    $loansIn = $qIn->get()->toArray();
                }
                if (Schema::hasTable('spectrum_loan_out')) {
                    $qOut = DB::table('spectrum_loan_out as l')
                        ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                            $j->on('l.object_id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
                        })
                        ->select('l.*', 'i18n.title as object_title', DB::raw("'OUT' as direction"));
                    if ($objectId) { $qOut->where('l.object_id', $objectId); }
                    $loansOut = $qOut->get()->toArray();
                }
                $data = array_merge($loansIn ?? [], $loansOut ?? []);
                break;

            case 'workflow':
                if (Schema::hasTable('spectrum_workflow_history')) {
                    $q = DB::table('spectrum_workflow_history as w')
                        ->leftJoin('user as u', 'w.user_id', '=', 'u.id')
                        ->select('w.*', 'u.username as user_name')
                        ->orderBy('w.created_at', 'desc');
                    if ($objectId) { $q->where('w.record_id', $objectId); }
                    $data = $q->get()->toArray();
                }
                break;
        }

        if ($format === 'csv') {
            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            ];

            $callback = function () use ($data) {
                $output = fopen('php://output', 'w');
                if (!empty($data)) {
                    fputcsv($output, array_keys((array) $data[0]));
                    foreach ($data as $row) {
                        fputcsv($output, (array) $row);
                    }
                }
                fclose($output);
            };

            return response()->stream($callback, 200, $headers);
        }

        // JSON
        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}.json\"");
    }

    // ----------------------------------------------------------------
    // Security Compliance
    // ----------------------------------------------------------------

    public function securityCompliance(Request $request)
    {
        $stats = [
            'classified_objects' => 0,
            'pending_reviews'    => 0,
            'cleared_users'      => 0,
            'access_logs_today'  => 0,
        ];
        $retentionSchedules = [];
        $recentLogs = [];

        if (Schema::hasTable('security_classification')) {
            $stats['classified_objects'] = DB::table('security_classification')->count();
        }
        if (Schema::hasTable('security_clearance_history')) {
            $stats['cleared_users'] = DB::table('security_clearance_history')->where('action', 'granted')->count();
        }
        if (Schema::hasTable('security_access_log')) {
            $stats['access_logs_today'] = DB::table('security_access_log')
                ->whereDate('created_at', date('Y-m-d'))
                ->count();
        }
        if (Schema::hasTable('security_retention_schedule')) {
            $retentionSchedules = DB::table('security_retention_schedule')->get()->toArray();
        }
        if (Schema::hasTable('security_compliance_log')) {
            $recentLogs = DB::table('security_compliance_log')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->toArray();
        }

        return view('spectrum::security-compliance', [
            'stats'              => $stats,
            'pendingReviews'     => [],
            'retentionSchedules' => $retentionSchedules,
            'recentLogs'         => $recentLogs,
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy Compliance
    // ----------------------------------------------------------------

    public function privacyCompliance(Request $request)
    {
        $complianceScore = 75;
        $ropaCount = 0;
        $dsarStats = ['total' => 0, 'pending' => 0, 'overdue' => 0, 'completed' => 0];
        $breachStats = ['total' => 0, 'open' => 0, 'notified' => 0, 'closed' => 0];

        if (Schema::hasTable('privacy_processing_activity')) {
            $ropaCount = DB::table('privacy_processing_activity')->count();
        }
        if (Schema::hasTable('privacy_dsar_request')) {
            $dsarStats = [
                'total'     => DB::table('privacy_dsar_request')->count(),
                'pending'   => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
                'overdue'   => DB::table('privacy_dsar_request')
                    ->where('status', '!=', 'completed')
                    ->where('deadline_date', '<', date('Y-m-d'))->count(),
                'completed' => DB::table('privacy_dsar_request')->where('status', 'completed')->count(),
            ];
        }
        if (Schema::hasTable('privacy_breach_incident')) {
            $breachStats = [
                'total'    => DB::table('privacy_breach_incident')->count(),
                'open'     => DB::table('privacy_breach_incident')->where('status', 'open')->count(),
                'notified' => DB::table('privacy_breach_incident')->where('regulator_notified', 1)->count(),
                'closed'   => DB::table('privacy_breach_incident')->where('status', 'closed')->count(),
            ];
        }

        return view('spectrum::privacy-compliance', [
            'complianceScore' => $complianceScore,
            'ropaCount'       => $ropaCount,
            'dsarStats'       => $dsarStats,
            'breachStats'     => $breachStats,
            'recentActivity'  => [],
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy Admin
    // ----------------------------------------------------------------

    public function privacyAdmin(Request $request)
    {
        $complianceScore = 75;
        $ropaCount = 0;
        $dsarStats = ['pending' => 0, 'overdue' => 0, 'completed' => 0];
        $breachStats = ['open' => 0, 'closed' => 0];

        if (Schema::hasTable('privacy_processing_activity')) {
            $ropaCount = DB::table('privacy_processing_activity')->count();
        }
        if (Schema::hasTable('privacy_dsar_request')) {
            $dsarStats = [
                'pending'   => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
                'overdue'   => DB::table('privacy_dsar_request')
                    ->where('status', '!=', 'completed')
                    ->where('deadline_date', '<', date('Y-m-d'))->count(),
                'completed' => DB::table('privacy_dsar_request')->where('status', 'completed')->count(),
            ];
        }
        if (Schema::hasTable('privacy_breach_incident')) {
            $breachStats = [
                'open'   => DB::table('privacy_breach_incident')->where('status', 'open')->count(),
                'closed' => DB::table('privacy_breach_incident')->where('status', 'closed')->count(),
            ];
        }

        return view('spectrum::privacy-admin', [
            'complianceScore' => $complianceScore,
            'ropaCount'       => $ropaCount,
            'dsarStats'       => $dsarStats,
            'breachStats'     => $breachStats,
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy ROPA
    // ----------------------------------------------------------------

    public function privacyRopa(Request $request)
    {
        $activities = [];

        if (Schema::hasTable('privacy_processing_activity')) {
            $activities = DB::table('privacy_processing_activity')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        }

        return view('spectrum::privacy-ropa', [
            'activities' => $activities,
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy DSAR
    // ----------------------------------------------------------------

    public function privacyDsar(Request $request)
    {
        $requests = [];
        $stats = ['total' => 0, 'pending' => 0, 'overdue' => 0, 'completed' => 0];

        if (Schema::hasTable('privacy_dsar_request')) {
            $requests = DB::table('privacy_dsar_request')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            $stats = [
                'total'     => count($requests),
                'pending'   => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
                'overdue'   => DB::table('privacy_dsar_request')
                    ->where('status', '!=', 'completed')
                    ->where('deadline_date', '<', date('Y-m-d'))->count(),
                'completed' => DB::table('privacy_dsar_request')->where('status', 'completed')->count(),
            ];
        }

        return view('spectrum::privacy-dsar', [
            'requests' => $requests,
            'stats'    => $stats,
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy Breaches
    // ----------------------------------------------------------------

    public function privacyBreaches(Request $request)
    {
        $breaches = [];
        $stats = [];

        if (Schema::hasTable('privacy_breach_incident')) {
            $breaches = DB::table('privacy_breach_incident')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            $stats = [
                'total'    => count($breaches),
                'open'     => DB::table('privacy_breach_incident')->where('status', 'open')->count(),
                'notified' => DB::table('privacy_breach_incident')->where('regulator_notified', 1)->count(),
                'closed'   => DB::table('privacy_breach_incident')->where('status', 'closed')->count(),
            ];
        }

        return view('spectrum::privacy-breaches', [
            'breaches' => $breaches,
            'stats'    => $stats,
        ]);
    }

    // ----------------------------------------------------------------
    // Privacy Templates
    // ----------------------------------------------------------------

    public function privacyTemplates(Request $request)
    {
        $templates = [];

        if (Schema::hasTable('privacy_template')) {
            $templates = DB::table('privacy_template')
                ->where('is_active', 1)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->toArray();
        }

        return view('spectrum::privacy-templates', [
            'templates' => $templates,
        ]);
    }

    // ----------------------------------------------------------------
    // Dashboard helper methods
    // ----------------------------------------------------------------

    protected function getWorkflowStatistics(?int $repoId = null): array
    {
        $stats = [
            'total_objects'          => 0,
            'objects_with_workflows' => 0,
            'completed_procedures'   => 0,
            'in_progress_procedures' => 0,
            'pending_procedures'     => 0,
        ];

        try {
            $stats['total_objects'] = DB::table('information_object')->count();

            if (Schema::hasTable('spectrum_workflow_state')) {
                $stats['objects_with_workflows'] = DB::table('spectrum_workflow_state')
                    ->distinct('record_id')
                    ->count('record_id');

                $statusCounts = DB::table('spectrum_workflow_state')
                    ->select('current_state', DB::raw('COUNT(*) as count'))
                    ->groupBy('current_state')
                    ->get();

                foreach ($statusCounts as $row) {
                    if (in_array($row->current_state, ['completed', 'verified', 'closed', 'confirmed'])) {
                        $stats['completed_procedures'] += $row->count;
                    } elseif ($row->current_state === 'pending') {
                        $stats['pending_procedures'] += $row->count;
                    } else {
                        $stats['in_progress_procedures'] += $row->count;
                    }
                }
            }
        } catch (\Exception $e) {
            // Tables may not exist
        }

        return $stats;
    }

    protected function getRecentWorkflowActivity(?int $repoId = null): array
    {
        if (!Schema::hasTable('spectrum_workflow_history')) {
            return [];
        }

        try {
            return DB::table('spectrum_workflow_history as h')
                ->join('slug as s', 'h.record_id', '=', 's.object_id')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('h.record_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('user as u', 'h.user_id', '=', 'u.id')
                ->select('h.*', 's.slug', 'ioi.title as object_title', 'u.username as user_name')
                ->orderBy('h.created_at', 'desc')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getProcedureStatusCounts(?int $repoId = null): array
    {
        if (!Schema::hasTable('spectrum_workflow_state')) {
            return [];
        }

        $counts = [];

        try {
            $results = DB::table('spectrum_workflow_state')
                ->select('procedure_type', 'current_state', DB::raw('COUNT(*) as count'))
                ->groupBy('procedure_type', 'current_state')
                ->get();

            foreach ($results as $row) {
                if (!isset($counts[$row->procedure_type])) {
                    $counts[$row->procedure_type] = [];
                }
                $counts[$row->procedure_type][$row->current_state] = $row->count;
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        return $counts;
    }

    protected function calculateOverallCompletion(?int $repoId = null): array
    {
        if (!Schema::hasTable('spectrum_workflow_state')) {
            return ['percentage' => 0, 'completed' => 0, 'total' => 0];
        }

        try {
            $total = DB::table('spectrum_workflow_state')->count();
            if ($total === 0) {
                return ['percentage' => 0, 'completed' => 0, 'total' => 0];
            }

            $completed = DB::table('spectrum_workflow_state')
                ->whereIn('current_state', ['completed', 'verified', 'closed', 'confirmed', 'documented'])
                ->count();

            return [
                'percentage' => round(($completed / $total) * 100),
                'completed'  => $completed,
                'total'      => $total,
            ];
        } catch (\Exception $e) {
            return ['percentage' => 0, 'completed' => 0, 'total' => 0];
        }
    }
}
