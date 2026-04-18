<?php

/**
 * SpectrumController - Controller for Heratio
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



namespace AhgSpectrum\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use AhgSpectrum\Services\SpectrumNotificationService;
use AhgSpectrum\Services\SpectrumWorkflowService;

class SpectrumController extends Controller
{
    /**
     * Spectrum 5.1 procedure constants
     *
     * Primary procedures
     */
    const PROC_OBJECT_ENTRY  = 'object_entry';
    const PROC_ACQUISITION   = 'acquisition';
    const PROC_LOCATION      = 'location_movement';
    const PROC_INVENTORY     = 'inventory_control';
    const PROC_CATALOGUING   = 'cataloguing';
    const PROC_EXIT          = 'object_exit';
    const PROC_LOAN_IN       = 'loans_in';
    const PROC_LOAN_OUT      = 'loans_out';
    const PROC_DOCUMENTATION = 'documentation_planning';

    /**
     * Additional procedures
     */
    const PROC_USE_OF_COLLECTIONS = 'use_of_collections';
    const PROC_CONDITION          = 'condition_checking';
    const PROC_CONSERVATION       = 'conservation';
    const PROC_VALUATION          = 'valuation';
    const PROC_INSURANCE          = 'insurance';
    const PROC_EMERGENCY          = 'emergency_planning';
    const PROC_LOSS               = 'loss_damage';
    const PROC_DEACCESSION        = 'deaccession';
    const PROC_RIGHTS             = 'rights_management';
    const PROC_REPRODUCTION       = 'reproduction';
    const PROC_COLLECTIONS_REVIEW = 'collections_review';
    const PROC_AUDIT              = 'audit';

    /** @deprecated Use PROC_EMERGENCY — kept for backward compatibility with existing DB records */
    const PROC_RISK          = 'risk_management';
    /** @deprecated Merged into PROC_DEACCESSION in Spectrum 5.1 */
    const PROC_DISPOSAL      = 'disposal';
    /** @deprecated Not a current Spectrum 5.1 procedure name — use PROC_DOCUMENTATION / PROC_INVENTORY */
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
     *
     * Ordered: Primary procedures first, then Additional procedures,
     * following the official Spectrum 5.1 standard.
     */
    protected function getProcedures(): array
    {
        return [
            // ── Primary procedures ──────────────────────────────────
            self::PROC_OBJECT_ENTRY => [
                'label'       => 'Object entry',
                'description' => 'Recording information about objects entering the museum temporarily or for acquisition consideration.',
                'group'       => 'primary',
                'category'    => 'pre-entry',
                'icon'        => 'fa-sign-in',
            ],
            self::PROC_ACQUISITION => [
                'label'       => 'Acquisition and accessioning',
                'description' => 'Formally acquiring objects for the permanent collection and assigning accession numbers.',
                'group'       => 'primary',
                'category'    => 'acquisition',
                'icon'        => 'fa-plus-circle',
            ],
            self::PROC_LOCATION => [
                'label'       => 'Location and movement control',
                'description' => 'Tracking object locations and movements within and outside the museum.',
                'group'       => 'primary',
                'category'    => 'location',
                'icon'        => 'fa-map-marker',
            ],
            self::PROC_INVENTORY => [
                'label'       => 'Inventory',
                'description' => 'Verifying and reconciling object locations and records.',
                'group'       => 'primary',
                'category'    => 'control',
                'icon'        => 'fa-list-alt',
            ],
            self::PROC_CATALOGUING => [
                'label'       => 'Cataloguing',
                'description' => 'Creating and maintaining catalogue records.',
                'group'       => 'primary',
                'category'    => 'documentation',
                'icon'        => 'fa-book',
            ],
            self::PROC_EXIT => [
                'label'       => 'Object exit',
                'description' => 'Recording objects leaving the museum.',
                'group'       => 'primary',
                'category'    => 'exit',
                'icon'        => 'fa-sign-out',
            ],
            self::PROC_LOAN_IN => [
                'label'       => 'Loans in (borrowing objects)',
                'description' => 'Borrowing objects from other institutions or individuals.',
                'group'       => 'primary',
                'category'    => 'loans',
                'icon'        => 'fa-arrow-circle-down',
            ],
            self::PROC_LOAN_OUT => [
                'label'       => 'Loans out (lending objects)',
                'description' => 'Lending objects to other institutions.',
                'group'       => 'primary',
                'category'    => 'loans',
                'icon'        => 'fa-arrow-circle-up',
            ],
            self::PROC_DOCUMENTATION => [
                'label'       => 'Documentation planning',
                'description' => 'Planning documentation projects and priorities.',
                'group'       => 'primary',
                'category'    => 'documentation',
                'icon'        => 'fa-file-alt',
            ],

            // ── Additional procedures ───────────────────────────────
            self::PROC_USE_OF_COLLECTIONS => [
                'label'       => 'Use of collections',
                'description' => 'Managing and recording the use of collections for exhibitions, research, education and other purposes.',
                'group'       => 'additional',
                'category'    => 'access',
                'icon'        => 'fa-hands-helping',
            ],
            self::PROC_CONDITION => [
                'label'       => 'Condition checking and technical assessment',
                'description' => 'Recording and monitoring object condition and technical properties.',
                'group'       => 'additional',
                'category'    => 'care',
                'icon'        => 'fa-heartbeat',
            ],
            self::PROC_CONSERVATION => [
                'label'       => 'Collections care and conservation',
                'description' => 'Planning and documenting preventive and interventive conservation treatments.',
                'group'       => 'additional',
                'category'    => 'care',
                'icon'        => 'fa-medkit',
            ],
            self::PROC_VALUATION => [
                'label'       => 'Valuation',
                'description' => 'Recording object valuations for insurance, audit and reporting.',
                'group'       => 'additional',
                'category'    => 'financial',
                'icon'        => 'fa-dollar-sign',
            ],
            self::PROC_INSURANCE => [
                'label'       => 'Insurance and indemnity',
                'description' => 'Managing insurance and government indemnity for collections.',
                'group'       => 'additional',
                'category'    => 'financial',
                'icon'        => 'fa-shield-alt',
            ],
            self::PROC_EMERGENCY => [
                'label'       => 'Emergency planning for collections',
                'description' => 'Identifying risks to collections and planning responses to emergencies.',
                'group'       => 'additional',
                'category'    => 'risk',
                'icon'        => 'fa-fire-extinguisher',
            ],
            self::PROC_LOSS => [
                'label'       => 'Damage and loss',
                'description' => 'Recording and responding to damage or loss of objects.',
                'group'       => 'additional',
                'category'    => 'risk',
                'icon'        => 'fa-exclamation-triangle',
            ],
            self::PROC_DEACCESSION => [
                'label'       => 'Deaccessioning and disposal',
                'description' => 'Formally removing objects from the collection and arranging their disposal.',
                'group'       => 'additional',
                'category'    => 'disposal',
                'icon'        => 'fa-minus-circle',
            ],
            self::PROC_RIGHTS => [
                'label'       => 'Rights management',
                'description' => 'Managing intellectual property and other rights associated with collections.',
                'group'       => 'additional',
                'category'    => 'legal',
                'icon'        => 'fa-copyright',
            ],
            self::PROC_REPRODUCTION => [
                'label'       => 'Reproduction',
                'description' => 'Managing reproduction and image licensing requests.',
                'group'       => 'additional',
                'category'    => 'legal',
                'icon'        => 'fa-copy',
            ],
            self::PROC_COLLECTIONS_REVIEW => [
                'label'       => 'Collections review',
                'description' => 'Reviewing collections against organisational policies and priorities.',
                'group'       => 'additional',
                'category'    => 'control',
                'icon'        => 'fa-search',
            ],
            self::PROC_AUDIT => [
                'label'       => 'Audit',
                'description' => 'Auditing collections, records and procedures.',
                'group'       => 'additional',
                'category'    => 'control',
                'icon'        => 'fa-clipboard-check',
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

        // Use explicit final_states if defined
        if (!empty($configData['final_states'])) {
            return $configData['final_states'];
        }

        // Derive: a final state has no outgoing transitions except 'restart'
        $states      = $configData['states'] ?? [];
        $transitions = $configData['transitions'] ?? [];
        $finalStates = [];

        foreach ($states as $state) {
            $hasOutgoing = false;
            foreach ($transitions as $tKey => $tDef) {
                if ($tKey === 'restart') {
                    continue;
                }
                if (isset($tDef['from']) && in_array($state, $tDef['from'])) {
                    $hasOutgoing = true;
                    break;
                }
            }
            if (!$hasOutgoing) {
                $finalStates[] = $state;
            }
        }

        return $finalStates;
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

        // Get the originator (user who made the first transition for this procedure)
        $originatorId = null;
        if ($resource && Schema::hasTable('spectrum_workflow_history')) {
            $originatorId = DB::table('spectrum_workflow_history')
                ->where('record_id', $resource->id)
                ->where('procedure_type', $procedureType)
                ->orderBy('created_at', 'asc')
                ->value('user_id');
        }

        // Determine which transitions lead to a final state
        $finalStates = $this->getFinalStates($procedureType);
        $finalTransitionKeys = [];
        foreach ($workflowConfig['transitions'] ?? [] as $tKey => $tDef) {
            if ($tKey !== 'restart' && in_array($tDef['to'] ?? '', $finalStates)) {
                $finalTransitionKeys[] = $tKey;
            }
        }

        return view('spectrum::workflow', [
            'resource'              => $resource,
            'procedureType'         => $procedureType,
            'procedures'            => $procedures,
            'procedureStatuses'     => $procedureStatuses,
            'currentProcedure'      => $currentProcedure,
            'timeline'              => $timeline,
            'procedureTimeline'     => $procedureTimeline,
            'progress'              => $progress,
            'statusOptions'         => $statusOptions,
            'statusColors'          => self::$statusColors,
            'canEdit'               => $canEdit,
            'workflowConfig'        => $workflowConfig,
            'users'                 => $users,
            'originatorId'          => $originatorId,
            'finalTransitionKeys'   => $finalTransitionKeys,
            'linkedProcedures'      => SpectrumWorkflowService::getLinkedProcedures($procedureType),
            'downstreamStatus'      => $resource ? SpectrumWorkflowService::getDownstreamStatus($resource->id, $procedureType) : [],
            'upstreamStatus'        => $resource ? SpectrumWorkflowService::getUpstreamStatus($resource->id, $procedureType) : [],
            'condensedSteps'        => $workflowConfig['condensed_steps'] ?? [],
        ]);
    }

    // ----------------------------------------------------------------
    // Workflow Transition (POST)
    // ----------------------------------------------------------------

    public function workflowTransition(Request $request)
    {
        $request->validate([
            'slug'           => 'required|string',
            'procedure_type' => 'required|string',
            'transition_key' => 'required|string',
            'from_state'     => 'required|string',
            'assigned_to'    => 'required|integer|exists:user,id',
            'note'           => 'nullable|string|max:1000',
        ]);

        $slug          = $request->input('slug');
        $procedureType = $request->input('procedure_type');
        $transitionKey = $request->input('transition_key');
        $fromState     = $request->input('from_state');
        $note          = $request->input('note');
        $assignedTo    = $request->input('assigned_to');
        $userId        = Auth::id();

        $resource = $this->getResourceBySlug($slug);
        if (!$resource) {
            abort(404);
        }

        // Get workflow config to validate transition
        $config = DB::table('spectrum_workflow_config')
            ->where('procedure_type', $procedureType)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            abort(404, 'No workflow configuration found for this procedure type.');
        }

        $configData   = json_decode($config->config_json, true);
        $transitions  = $configData['transitions'] ?? [];

        if (!isset($transitions[$transitionKey])) {
            abort(404, 'Invalid transition.');
        }

        $transition = $transitions[$transitionKey];
        $toState    = $transition['to'];

        // Validate from state
        if (!in_array($fromState, $transition['from'])) {
            abort(400, 'Invalid state transition.');
        }

        $assignedToInt = $assignedTo ? (int) $assignedTo : null;

        // Identify the originator (first user who acted on this procedure)
        $originatorId = DB::table('spectrum_workflow_history')
            ->where('procedure_type', $procedureType)
            ->where('record_id', $resource->id)
            ->orderBy('created_at', 'asc')
            ->value('user_id');

        $finalStates = $this->getFinalStates($procedureType);
        $states      = $configData['states'] ?? [];

        // ── Closure sign-off: only the originator may close ──
        if ($transitionKey === 'close' && in_array($toState, $finalStates)) {
            if ($originatorId && $userId !== (int) $originatorId) {
                return redirect()
                    ->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => $procedureType])
                    ->with('error', __('Only the originator can sign off and close this procedure.'));
            }
            $assignedToInt = $originatorId ? (int) $originatorId : $userId;
        }

        // ── Pre-close step: route to originator for sign-off ──
        // The pre-close state is the state one before "closed" in the states array.
        $closedIndex   = array_search('closed', $states);
        $preCloseState = ($closedIndex && $closedIndex > 0) ? $states[$closedIndex - 1] : null;

        if ($preCloseState && $toState === $preCloseState && $transitionKey !== 'restart' && $transitionKey !== 'reject') {
            // Auto-assign to originator so they can review and close
            if ($originatorId) {
                $assignedToInt = (int) $originatorId;
            }
        }

        // ── Final step (non-close): route to originator ──
        if ($transitionKey !== 'close' && $transitionKey !== 'restart' && in_array($toState, $finalStates)) {
            if ($originatorId) {
                $assignedToInt = (int) $originatorId;
            }
        }

        // On rejection, auto-assign back to the submitter
        if ($transitionKey === 'reject') {
            $submitter = DB::table('spectrum_workflow_history')
                ->where('procedure_type', $procedureType)
                ->where('record_id', $resource->id)
                ->whereNotIn('transition_key', ['reject', 'restart', 'auto_trigger'])
                ->orderBy('created_at', 'desc')
                ->value('user_id');

            if ($submitter) {
                $assignedToInt = (int) $submitter;
            }
        }

        $assignmentData = [];
        if ($assignedToInt) {
            $assignmentData = [
                'assigned_to' => $assignedToInt,
                'assigned_at' => now(),
                'assigned_by' => $userId,
            ];
        }

        // Update or create workflow state
        $existingState = DB::table('spectrum_workflow_state')
            ->where('record_id', $resource->id)
            ->where('procedure_type', $procedureType)
            ->first();

        if ($existingState) {
            $updateData = [
                'current_state' => $toState,
                'updated_at'    => now(),
            ];
            if ($assignedToInt) {
                $updateData = array_merge($updateData, $assignmentData);
            }
            DB::table('spectrum_workflow_state')
                ->where('id', $existingState->id)
                ->update($updateData);
        } else {
            $insertData = [
                'procedure_type' => $procedureType,
                'record_id'      => $resource->id,
                'current_state'  => $toState,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
            if ($assignedToInt) {
                $insertData = array_merge($insertData, $assignmentData);
            }
            DB::table('spectrum_workflow_state')->insert($insertData);
        }

        // Record history — include sign-off metadata on closure
        $historyMeta = $assignedToInt ? ['assigned_to' => $assignedToInt] : [];
        if ($transitionKey === 'close' && in_array($toState, $finalStates)) {
            $historyMeta['signed_off_by'] = $userId;
            $historyMeta['signed_off_at'] = now()->toIso8601String();
        }

        DB::table('spectrum_workflow_history')->insert([
            'procedure_type' => $procedureType,
            'record_id'      => $resource->id,
            'from_state'     => $fromState,
            'to_state'       => $toState,
            'transition_key' => $transitionKey,
            'user_id'        => $userId,
            'assigned_to'    => $assignedToInt,
            'note'           => $note,
            'metadata'       => !empty($historyMeta) ? json_encode($historyMeta) : null,
            'created_at'     => now(),
        ]);

        $isFinalState = $this->isFinalState($procedureType, $toState);

        // ── Notify originator when pre-close state is reached (sign-off needed) ──
        if ($preCloseState && $toState === $preCloseState && $originatorId && $originatorId !== $userId) {
            $procLabel = $this->getProcedures()[$procedureType]['label'] ?? $procedureType;
            SpectrumNotificationService::createAssignmentNotification(
                (int) $originatorId,
                $resource->id,
                $procedureType,
                $userId,
                $toState
            );
            // Also create a specific sign-off notification
            DB::table('spectrum_notification')->insert([
                'user_id'           => $originatorId,
                'notification_type' => 'sign_off_required',
                'subject'           => "Sign-off required: {$procLabel}",
                'message'           => "The procedure \"{$procLabel}\" has reached its final step and requires your sign-off to close.\n\n"
                    . "Object: " . ($resource->title ?: $resource->slug) . "\n"
                    . "View: " . url('/admin/spectrum/workflow?slug=' . $resource->slug . '&procedure_type=' . $procedureType),
                'created_at'        => now(),
            ]);
        }

        // Create in-app assignment notification (not for final states)
        if ($assignedToInt && $assignedToInt !== $userId && !$isFinalState) {
            SpectrumNotificationService::createAssignmentNotification(
                $assignedToInt,
                $resource->id,
                $procedureType,
                $userId,
                $toState
            );
        }

        // Create transition notifications for relevant users
        SpectrumNotificationService::createTransitionNotification(
            $resource,
            $procedureType,
            $fromState,
            $toState,
            $transitionKey,
            $userId,
            $assignedToInt,
            $note
        );

        // Send email notifications
        $this->sendTransitionEmails(
            $resource, $procedureType, $fromState, $toState,
            $transitionKey, $userId, $assignedToInt, $note
        );

        // Mark existing notifications as read when task reaches final state
        $triggered = [];
        if ($isFinalState) {
            SpectrumNotificationService::markTaskNotificationsAsReadByObject($resource->id, $procedureType);

            // Trigger downstream procedures
            $triggered = SpectrumWorkflowService::triggerDownstreamProcedures(
                $resource->id,
                $procedureType,
                $userId
            );

            // Create notifications for triggered procedures
            foreach ($triggered as $triggeredProc) {
                SpectrumNotificationService::createTransitionNotification(
                    $resource,
                    $triggeredProc,
                    'not_started',
                    'pending',
                    'auto_trigger',
                    $userId,
                    null,
                    'Automatically triggered by completion of ' . ($this->getProcedures()[$procedureType]['label'] ?? $procedureType)
                );
            }
        }

        $message = ucwords(str_replace('_', ' ', $transitionKey)) . ' completed.';
        if (!empty($triggered)) {
            $labels = array_map(
                fn($t) => SpectrumWorkflowService::getProcedureLabel($t),
                $triggered
            );
            $message .= ' Triggered: ' . implode(', ', $labels) . '.';
        }

        return redirect()
            ->route('ahgspectrum.workflow', ['slug' => $slug, 'procedure_type' => $procedureType])
            ->with('success', $message);
    }

    /**
     * Link or unlink a SOP document URL to a procedure's workflow config.
     */
    public function workflowSop(Request $request)
    {
        $request->validate([
            'procedure_type' => 'required|string',
            'sop_url'        => 'nullable|url|max:2000',
        ]);

        $procedureType = $request->input('procedure_type');
        $sopUrl        = $request->input('sop_url') ?: null;

        $config = DB::table('spectrum_workflow_config')
            ->where('procedure_type', $procedureType)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            abort(404);
        }

        $configData = json_decode($config->config_json, true);
        $configData['sop_url'] = $sopUrl;

        DB::table('spectrum_workflow_config')
            ->where('id', $config->id)
            ->update([
                'config_json' => json_encode($configData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);

        $message = $sopUrl ? __('SOP document linked.') : __('SOP document removed.');

        return redirect()
            ->route('ahgspectrum.workflow', ['slug' => $request->query('slug', ''), 'procedure_type' => $procedureType])
            ->with('success', $message);
    }

    /**
     * Send email notifications for a workflow state transition to relevant users.
     */
    protected function sendTransitionEmails(
        object $resource,
        string $procedureType,
        string $fromState,
        string $toState,
        string $transitionKey,
        int $actingUserId,
        ?int $assignedTo,
        ?string $note
    ): void {
        $actingUser = DB::table('user')->where('id', $actingUserId)->first();
        $actingName = $actingUser ? $actingUser->username : 'System';

        $procedures     = $this->getProcedures();
        $procedureLabel = $procedures[$procedureType]['label'] ?? ucwords(str_replace('_', ' ', $procedureType));
        $fromLabel      = ucwords(str_replace('_', ' ', $fromState));
        $toLabel        = ucwords(str_replace('_', ' ', $toState));
        $transitionLabel = ucwords(str_replace('_', ' ', $transitionKey));

        $objectTitle = $resource->title ?: ($resource->slug ?? 'Untitled');

        $subject = "Spectrum: {$transitionLabel} — {$procedureLabel}";
        $message = "{$actingName} performed '{$transitionLabel}' on a task.\n\n"
            . "Object: {$objectTitle}\n"
            . "Procedure: {$procedureLabel}\n"
            . "State: {$fromLabel} → {$toLabel}\n";
        if ($note) {
            $message .= "Note: {$note}\n";
        }

        $notifyUserIds = [];

        if ($assignedTo && $assignedTo !== $actingUserId) {
            $notifyUserIds[] = $assignedTo;
        }

        $previousState = DB::table('spectrum_workflow_state')
            ->where('record_id', $resource->id)
            ->where('procedure_type', $procedureType)
            ->first();
        if ($previousState && $previousState->assigned_to
            && $previousState->assigned_to !== $actingUserId
            && !in_array($previousState->assigned_to, $notifyUserIds)) {
            $notifyUserIds[] = $previousState->assigned_to;
        }

        if (empty($notifyUserIds) && in_array($transitionKey, ['submit_for_review', 'complete', 'report'])) {
            $admins = DB::table('acl_user_group')
                ->where('group_id', 100)
                ->where('user_id', '!=', $actingUserId)
                ->pluck('user_id')
                ->toArray();
            $notifyUserIds = array_merge($notifyUserIds, $admins);
        }

        $notifyUserIds = array_unique($notifyUserIds);

        foreach ($notifyUserIds as $uid) {
            SpectrumNotificationService::sendEmailNotification($uid, $subject, $message);
        }
    }

    // ----------------------------------------------------------------
    // Notification management
    // ----------------------------------------------------------------

    /**
     * List notifications for the current user (JSON or page).
     */
    public function notifications(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            abort(401);
        }

        $unreadOnly    = $request->boolean('unread_only', false);
        $notifications = SpectrumNotificationService::getUserNotifications($userId, 50, $unreadOnly);
        $unreadCount   = SpectrumNotificationService::getUnreadCount($userId);

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
            ]);
        }

        return view('spectrum::notifications', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }

    /**
     * Mark a notification as read (AJAX).
     */
    public function notificationMarkRead(Request $request)
    {
        $userId         = Auth::id();
        $notificationId = $request->input('id');

        if (!$userId || !$notificationId) {
            return response()->json(['success' => false], 400);
        }

        $result = SpectrumNotificationService::markAsRead((int) $notificationId, $userId);

        return response()->json([
            'success'      => $result,
            'unread_count' => SpectrumNotificationService::getUnreadCount($userId),
        ]);
    }

    /**
     * Mark all notifications as read (AJAX).
     */
    public function notificationMarkAllRead(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false], 401);
        }

        $count = SpectrumNotificationService::markAllAsRead($userId);

        return response()->json([
            'success'      => true,
            'marked_count' => $count,
            'unread_count' => 0,
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

        $activeProcedureTypes = [];
        if (Schema::hasTable('spectrum_workflow_config')) {
            $configs = DB::table('spectrum_workflow_config')->where('is_active', 1)->get();
            foreach ($configs as $config) {
                $configData = json_decode($config->config_json, true);
                $workflowConfigs[$config->procedure_type] = $configData;
                $activeProcedureTypes[] = $config->procedure_type;
                $finals = $this->getFinalStates($config->procedure_type);
                if (!empty($finals)) {
                    $finalStatesByProcedure[$config->procedure_type] = $finals;
                }
            }
        }

        // Collect all final states across all procedures
        $allFinalStates = [];
        foreach ($finalStatesByProcedure as $finals) {
            $allFinalStates = array_merge($allFinalStates, $finals);
        }
        $allFinalStates = array_unique($allFinalStates);

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

        // Only show active procedure types
        if (!empty($activeProcedureTypes)) {
            $query->whereIn('sws.procedure_type', $activeProcedureTypes);
        }

        // Exclude final/closed states
        if (!empty($allFinalStates)) {
            $query->whereNotIn('sws.current_state', $allFinalStates);
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
        $currentJurisdiction = $request->get('jurisdiction', 'all');

        // Jurisdiction definitions
        $jurisdictions = [
            'popia'     => [
                'name' => 'POPIA', 'country' => 'South Africa', 'icon' => 'za',
                'full_name' => 'Protection of Personal Information Act', 'dsar_days' => 30,
                'breach_hours' => 72, 'effective_date' => '2021-07-01',
                'regulator' => 'Information Regulator', 'regulator_url' => 'https://inforegulator.org.za',
            ],
            'ndpa'      => [
                'name' => 'NDPA', 'country' => 'Nigeria', 'icon' => 'ng',
                'full_name' => 'Nigeria Data Protection Act', 'dsar_days' => 30,
                'breach_hours' => 72, 'effective_date' => '2023-06-14',
                'regulator' => 'NDPC', 'regulator_url' => 'https://ndpc.gov.ng',
            ],
            'kenya_dpa' => [
                'name' => 'Kenya DPA', 'country' => 'Kenya', 'icon' => 'ke',
                'full_name' => 'Data Protection Act 2019', 'dsar_days' => 30,
                'breach_hours' => 72, 'effective_date' => '2019-11-25',
                'regulator' => 'ODPC', 'regulator_url' => 'https://www.odpc.go.ke',
            ],
            'gdpr'      => [
                'name' => 'GDPR', 'country' => 'EU', 'icon' => 'eu',
                'full_name' => 'General Data Protection Regulation', 'dsar_days' => 30,
                'breach_hours' => 72, 'effective_date' => '2018-05-25',
                'regulator' => 'EDPB', 'regulator_url' => 'https://edpb.europa.eu',
            ],
            'pipeda'    => [
                'name' => 'PIPEDA', 'country' => 'Canada', 'icon' => 'ca',
                'full_name' => 'Personal Information Protection and Electronic Documents Act', 'dsar_days' => 30,
                'breach_hours' => null, 'effective_date' => '2000-04-13',
                'regulator' => 'OPC', 'regulator_url' => 'https://www.priv.gc.ca',
            ],
            'ccpa'      => [
                'name' => 'CCPA', 'country' => 'California', 'icon' => 'us',
                'full_name' => 'California Consumer Privacy Act', 'dsar_days' => 45,
                'breach_hours' => null, 'effective_date' => '2020-01-01',
                'regulator' => 'CPPA', 'regulator_url' => 'https://cppa.ca.gov',
            ],
        ];

        // African jurisdictions subset for default "all" view
        $africanJurisdictions = array_intersect_key($jurisdictions, array_flip(['popia', 'ndpa', 'kenya_dpa']));

        // Active jurisdiction from DB
        $activeJurisdiction = null;
        if (Schema::hasTable('privacy_jurisdiction')) {
            $activeJurisdiction = DB::table('privacy_jurisdiction')
                ->where('is_active', 1)
                ->first();
        }

        // DSAR stats
        $dsarStats = ['pending' => 0, 'overdue' => 0];
        if (Schema::hasTable('privacy_dsar_request')) {
            $dsarStats = [
                'pending'   => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
                'overdue'   => DB::table('privacy_dsar_request')
                    ->where('status', '!=', 'completed')
                    ->where('deadline_date', '<', date('Y-m-d'))->count(),
            ];
        }

        // Breach stats
        $breachStats = ['open' => 0, 'critical' => 0];
        if (Schema::hasTable('privacy_breach_incident')) {
            $breachStats = [
                'open'     => DB::table('privacy_breach_incident')->where('status', 'open')->count(),
                'critical' => DB::table('privacy_breach_incident')->where('severity', 'critical')->count(),
            ];
        }

        // ROPA stats
        $ropaStats = ['total' => 0, 'approved' => 0, 'requiring_dpia' => 0];
        if (Schema::hasTable('privacy_processing_activity')) {
            $ropaStats = [
                'total'          => DB::table('privacy_processing_activity')->count(),
                'approved'       => DB::table('privacy_processing_activity')->where('status', 'approved')->count(),
                'requiring_dpia' => DB::table('privacy_processing_activity')->where('dpia_required', 1)->count(),
            ];
        }

        // Consent stats
        $consentStats = ['active' => 0];
        if (Schema::hasTable('privacy_consent_record')) {
            $consentStats = [
                'active' => DB::table('privacy_consent_record')->where('status', 'active')->count(),
            ];
        }

        // Notification count
        $notificationCount = 0;
        if (Schema::hasTable('privacy_notification')) {
            $notificationCount = DB::table('privacy_notification')
                ->where('is_read', 0)->count();
        }

        // Compliance score (simple calculation based on ROPA approval rate)
        $complianceScore = $ropaStats['total'] > 0
            ? round(($ropaStats['approved'] / $ropaStats['total']) * 100)
            : 0;

        $stats = [
            'compliance_score' => $complianceScore,
            'dsar'    => $dsarStats,
            'breach'  => $breachStats,
            'ropa'    => $ropaStats,
            'consent' => $consentStats,
        ];

        return view('spectrum::privacy-compliance', [
            'stats'                 => $stats,
            'currentJurisdiction'   => $currentJurisdiction,
            'jurisdictions'         => $jurisdictions,
            'africanJurisdictions'  => $africanJurisdictions,
            'activeJurisdiction'    => $activeJurisdiction,
            'notificationCount'     => $notificationCount,
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

    /**
     * Save annotations for a condition photo (AJAX).
     */
    public function saveAnnotations(Request $request)
    {
        $photoId = (int) $request->input('photo_id');
        $annotations = $request->input('annotations', []);

        $photo = DB::table('spectrum_condition_photos')->where('id', $photoId)->first();
        if (!$photo) {
            return response()->json(['success' => false, 'message' => 'Photo not found'], 404);
        }

        DB::table('spectrum_condition_photos')
            ->where('id', $photoId)
            ->update([
                'annotations' => json_encode($annotations),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get annotations for a condition photo (AJAX).
     */
    public function getAnnotations(Request $request)
    {
        $photoId = (int) $request->input('photo_id');

        $photo = DB::table('spectrum_condition_photos')->where('id', $photoId)->first();
        if (!$photo) {
            return response()->json(['success' => false, 'message' => 'Photo not found'], 404);
        }

        $annotations = $photo->annotations ? json_decode($photo->annotations, true) : [];

        return response()->json(['success' => true, 'annotations' => $annotations]);
    }

    /**
     * Export an annotated condition photo as PNG.
     */
    public function exportAnnotatedPhoto(Request $request)
    {
        $photoId = (int) $request->input('photo_id');
        $format = $request->input('format', 'png');

        $photo = DB::table('spectrum_condition_photos')->where('id', $photoId)->first();
        if (!$photo) {
            abort(404, 'Photo not found');
        }

        // Serve the original photo for client-side annotation rendering
        $basePath = config('heratio.uploads_path');
        $filePath = $basePath . '/spectrum/condition-photos/' . $photo->filename;

        if (!file_exists($filePath)) {
            abort(404, 'Photo file not found');
        }

        $mimeType = $photo->mime_type ?: 'image/png';

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="annotated-' . $photo->original_filename . '"',
        ]);
    }

    // ── Spectrum Reports (cloned from AtoM spectrumReports module) ──

    public function reportIndex()
    {
        $stats = ['conditionCheck' => 0, 'loanIn' => 0, 'loanOut' => 0, 'valuation' => 0, 'acquisition' => 0, 'objectEntry' => 0, 'objectExit' => 0, 'movement' => 0, 'conservation' => 0, 'deaccession' => 0];
        $recentActivity = collect();
        try {
            if (Schema::hasTable('spectrum_condition_check')) { $stats['conditionCheck'] = DB::table('spectrum_condition_check')->count(); }
            if (Schema::hasTable('spectrum_loan')) {
                $stats['loanIn'] = DB::table('spectrum_loan')->where('direction', 'in')->count();
                $stats['loanOut'] = DB::table('spectrum_loan')->where('direction', 'out')->count();
            }
            if (Schema::hasTable('spectrum_valuation')) { $stats['valuation'] = DB::table('spectrum_valuation')->count(); }
            if (Schema::hasTable('spectrum_acquisition')) { $stats['acquisition'] = DB::table('spectrum_acquisition')->count(); }
            if (Schema::hasTable('spectrum_object_entry')) { $stats['objectEntry'] = DB::table('spectrum_object_entry')->count(); }
            if (Schema::hasTable('spectrum_object_exit')) { $stats['objectExit'] = DB::table('spectrum_object_exit')->count(); }
            if (Schema::hasTable('spectrum_movement')) { $stats['movement'] = DB::table('spectrum_movement')->count(); }
            if (Schema::hasTable('spectrum_conservation')) { $stats['conservation'] = DB::table('spectrum_conservation')->count(); }
            if (Schema::hasTable('spectrum_deaccession')) { $stats['deaccession'] = DB::table('spectrum_deaccession')->count(); }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.index', compact('stats', 'recentActivity'));
    }

    public function reportObjectEntry()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_object_entry')) {
                $items = DB::table('spectrum_object_entry as e')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('e.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('e.*', 'io.title as object_title')
                    ->orderByDesc('e.entry_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.object-entry', compact('items'));
    }

    public function reportAcquisitions()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_acquisition')) {
                $items = DB::table('spectrum_acquisition as a')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('a.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('a.*', 'io.title as object_title')
                    ->orderByDesc('a.acquisition_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.acquisitions', compact('items'));
    }

    public function reportLoans()
    {
        $loansIn = collect(); $loansOut = collect();
        try {
            if (Schema::hasTable('spectrum_loan')) {
                $base = DB::table('spectrum_loan as l')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('l.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('l.*', 'io.title as object_title');
                $loansIn = (clone $base)->where('l.direction', 'in')->orderByDesc('l.start_date')->limit(500)->get();
                $loansOut = (clone $base)->where('l.direction', 'out')->orderByDesc('l.start_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.loans', compact('loansIn', 'loansOut'));
    }

    public function reportMovements()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_movement')) {
                $items = DB::table('spectrum_movement as m')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('m.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('m.*', 'io.title as object_title')
                    ->orderByDesc('m.movement_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.movements', compact('items'));
    }

    public function reportConditions()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_condition_check')) {
                $items = DB::table('spectrum_condition_check as c')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('c.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('c.*', 'io.title as object_title')
                    ->orderByDesc('c.check_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.conditions', compact('items'));
    }

    public function reportConservation()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_conservation')) {
                $items = DB::table('spectrum_conservation as c')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('c.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('c.*', 'io.title as object_title')
                    ->orderByDesc('c.treatment_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.conservation', compact('items'));
    }

    public function reportValuations()
    {
        $items = collect();
        try {
            if (Schema::hasTable('spectrum_valuation')) {
                $items = DB::table('spectrum_valuation as v')
                    ->leftJoin('information_object_i18n as io', function ($j) { $j->on('v.object_id', '=', 'io.id')->where('io.culture', '=', 'en'); })
                    ->select('v.*', 'io.title as object_title')
                    ->orderByDesc('v.valuation_date')->limit(500)->get();
            }
        } catch (\Throwable $e) {}
        return view('spectrum::reports.valuations', compact('items'));
    }
}
