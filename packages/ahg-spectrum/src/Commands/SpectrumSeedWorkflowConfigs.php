<?php

namespace AhgSpectrum\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AhgSpectrum\Services\SpectrumWorkflowService;

class SpectrumSeedWorkflowConfigs extends Command
{
    protected $signature = 'spectrum:seed-configs {--force : Overwrite existing configs}';
    protected $description = 'Seed or update all 21 Spectrum 5.1 workflow procedure configs';

    public function handle(): int
    {
        $force = $this->option('force');

        $configs = self::getAllConfigs();

        $updated = 0;
        $created = 0;

        foreach ($configs as $procedureType => $config) {
            $existing = DB::table('spectrum_workflow_config')
                ->where('procedure_type', $procedureType)
                ->first();

            $json = json_encode($config['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($existing) {
                if ($force || !$existing->is_active) {
                    DB::table('spectrum_workflow_config')
                        ->where('id', $existing->id)
                        ->update([
                            'name'        => $config['name'],
                            'config_json' => $json,
                            'is_active'   => 1,
                            'updated_at'  => now(),
                        ]);
                    $updated++;
                    $this->line("  Updated: {$procedureType}");
                } else {
                    $this->line("  Skipped (exists): {$procedureType} — use --force to overwrite");
                }
            } else {
                DB::table('spectrum_workflow_config')->insert([
                    'procedure_type' => $procedureType,
                    'name'           => $config['name'],
                    'config_json'    => $json,
                    'is_active'      => 1,
                    'version'        => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $created++;
                $this->line("  Created: {$procedureType}");
            }
        }

        // Deactivate deprecated procedures
        $deprecated = ['risk_management', 'disposal', 'retrospective_documentation'];
        $deactivated = DB::table('spectrum_workflow_config')
            ->whereIn('procedure_type', $deprecated)
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $this->info("Done: {$created} created, {$updated} updated, {$deactivated} deactivated.");

        return Command::SUCCESS;
    }

    /**
     * Build the standard 6-phase config for a procedure.
     */
    protected static function buildConfig(
        string $name,
        array $condensedSteps,
        array $triggers = []
    ): array {
        return [
            'name'   => $name,
            'config' => [
                'states' => ['pending', 'approved', 'in_progress', 'documentation', 'completed', 'closed'],
                'steps' => [
                    ['order' => 1, 'name' => 'Request / trigger',       'state' => 'pending'],
                    ['order' => 2, 'name' => 'Approval',                'state' => 'approved'],
                    ['order' => 3, 'name' => 'Action steps',            'state' => 'in_progress'],
                    ['order' => 4, 'name' => 'Documentation updates',   'state' => 'documentation'],
                    ['order' => 5, 'name' => 'Outcome / closure',       'state' => 'completed'],
                    ['order' => 6, 'name' => 'Linked procedures',       'state' => 'closed'],
                ],
                'transitions' => [
                    'approve'            => ['from' => ['pending'],        'to' => 'approved',      'label' => 'Approve'],
                    'begin_action'       => ['from' => ['approved'],       'to' => 'in_progress',   'label' => 'Begin Action'],
                    'document'           => ['from' => ['in_progress'],    'to' => 'documentation', 'label' => 'Update Documentation'],
                    'submit_for_review'  => ['from' => ['documentation'],  'to' => 'completed',     'label' => 'Submit for Review'],
                    'close'              => ['from' => ['completed'],      'to' => 'closed',        'label' => 'Close & Trigger Next'],
                    'reject'             => ['from' => ['approved', 'documentation', 'completed'], 'to' => 'pending', 'label' => 'Reject'],
                    'restart'            => ['from' => ['closed'],         'to' => 'pending',        'label' => 'Restart'],
                ],
                'initial_state' => 'pending',
                'final_states'  => ['closed'],
                'state_labels' => [
                    'pending'       => 'Pending',
                    'approved'      => 'Approved',
                    'in_progress'   => 'In Progress',
                    'documentation' => 'Documentation',
                    'completed'     => 'Completed',
                    'closed'        => 'Closed',
                ],
                'triggers'        => $triggers,
                'condensed_steps' => $condensedSteps,
            ],
        ];
    }

    /**
     * All 21 Spectrum 5.1 procedure configs.
     */
    public static function getAllConfigs(): array
    {
        return [
            // ── Primary procedures ──────────────────────────────────

            'object_entry' => self::buildConfig(
                'Object entry',
                [
                    'Receive object',
                    'Capture depositor/source details',
                    'Record reason for entry',
                    'Assign temporary/entry number',
                    'Issue receipt and terms',
                    'Route to next action (loan, enquiry, acquisition)',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['object_entry'] ?? []
            ),

            'acquisition' => self::buildConfig(
                'Acquisition and accessioning',
                [
                    'Assess proposed acquisition',
                    'Confirm legal title/transfer',
                    'Approve acquisition',
                    'Decide whether to accession',
                    'Assign accession number',
                    'Mark/label object',
                    'Create and link core records',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['acquisition'] ?? []
            ),

            'location_movement' => self::buildConfig(
                'Location and movement control',
                [
                    'Authorise movement',
                    'Record current location',
                    'Record move details and responsible person',
                    'Update destination location',
                    'Keep movement history',
                    'Confirm object found where expected',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['location_movement'] ?? []
            ),

            'inventory_control' => self::buildConfig(
                'Inventory',
                [
                    'Identify location or collection to inventory',
                    'Check objects physically present',
                    'Verify minimum information exists',
                    'Assign temporary identifiers where needed',
                    'Record discrepancies',
                    'Create follow-up actions to resolve gaps',
                ]
            ),

            'cataloguing' => self::buildConfig(
                'Cataloguing',
                [
                    'Identify cataloguing need',
                    'Gather object/context information',
                    'Create or enrich catalogue record',
                    'Apply controlled terms/classifications',
                    'Link related records and authority data',
                    'Review and publish/internal release as appropriate',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['cataloguing'] ?? []
            ),

            'object_exit' => self::buildConfig(
                'Object exit',
                [
                    'Request/approve exit',
                    'Record reason and destination',
                    'Check condition and packing requirements',
                    'Document dispatch/transfer',
                    'Update location/status',
                    'Confirm exit completed and file evidence',
                ]
            ),

            'loans_in' => self::buildConfig(
                'Loans in (borrowing objects)',
                [
                    'Identify borrowing need',
                    'Request loan and lender terms',
                    'Assess suitability, risks and costs',
                    'Agree contract and dates',
                    'Receive and record object',
                    'Monitor during loan period',
                    'Renew or return at end date',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['loans_in'] ?? []
            ),

            'loans_out' => self::buildConfig(
                'Loans out (lending objects)',
                [
                    'Receive loan request',
                    'Open loan file',
                    'Assess borrower, object suitability and risks',
                    'Request missing information',
                    'Approve and agree terms',
                    'Prepare, dispatch and track loan',
                    'Receive return and close loan',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['loans_out'] ?? []
            ),

            'documentation_planning' => self::buildConfig(
                'Documentation planning',
                [
                    'Assess documentation strengths and gaps',
                    'Set priorities',
                    'Define projects/actions',
                    'Allocate resources and timescales',
                    'Implement improvements',
                    'Review progress and update plan',
                ]
            ),

            // ── Additional procedures ───────────────────────────────

            'use_of_collections' => self::buildConfig(
                'Use of collections',
                [
                    'Receive or define use request/activity',
                    'Check purpose, permissions and restrictions',
                    'Assess object/data suitability',
                    'Approve use',
                    'Record how objects/data/reproductions are used',
                    'Review outcomes and retain evidence',
                ]
            ),

            'condition_checking' => self::buildConfig(
                'Condition checking and technical assessment',
                [
                    'Trigger assessment',
                    'Examine object',
                    'Record materials, structure and condition',
                    'Note risks/issues',
                    'Make recommendations',
                    'Refer for treatment, movement limits or monitoring if needed',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['condition_checking'] ?? []
            ),

            'conservation' => self::buildConfig(
                'Collections care and conservation',
                [
                    'Identify conservation need',
                    'Assess treatment options',
                    'Approve treatment',
                    'Carry out care/conservation action',
                    'Record methods and materials used',
                    'Review result and future care requirements',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['conservation'] ?? []
            ),

            'valuation' => self::buildConfig(
                'Valuation',
                [
                    'Identify need for valuation',
                    'Define valuation basis/purpose',
                    'Gather object and provenance details',
                    'Obtain valuation',
                    'Record amount, date and valuer',
                    'Review/update when required',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['valuation'] ?? []
            ),

            'insurance' => self::buildConfig(
                'Insurance and indemnity',
                [
                    'Identify insurance/indemnity need',
                    'Check policy requirements',
                    'Gather valuation and object details',
                    'Arrange cover',
                    'Record cover terms and evidence',
                    'Monitor and update cover as needed',
                ]
            ),

            'emergency_planning' => self::buildConfig(
                'Emergency planning for collections',
                [
                    'Identify risks',
                    'Assess impact and priorities',
                    'Create emergency plan and salvage priorities',
                    'Assign roles and contacts',
                    'Train/test plan',
                    'Review and update regularly',
                ]
            ),

            'loss_damage' => self::buildConfig(
                'Damage and loss',
                [
                    'Detect incident',
                    'Secure area/object',
                    'Record damage/loss details',
                    'Investigate and notify relevant parties',
                    'Recover, stabilise or conserve if possible',
                    'Update records, claims and follow-up actions',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['loss_damage'] ?? []
            ),

            'deaccession' => self::buildConfig(
                'Deaccessioning and disposal',
                [
                    'Identify object for review',
                    'Assess against policy and ethics',
                    'Get governing body decision to deaccession',
                    'Select disposal method',
                    'Carry out disposal',
                    'Record outcome and retain audit trail',
                ],
                SpectrumWorkflowService::TRIGGER_MAP['deaccession'] ?? []
            ),

            'rights_management' => self::buildConfig(
                'Rights management',
                [
                    'Identify rights attached to object/data/reproduction',
                    'Determine owner/rightsholder',
                    'Record rights type, term and restrictions',
                    'Manage permissions/licences',
                    'Apply access/use controls',
                    'Review rights status over time',
                ]
            ),

            'reproduction' => self::buildConfig(
                'Reproduction',
                [
                    'Receive or initiate reproduction request',
                    'Check rights and permissions',
                    'Assess object handling requirements',
                    'Create reproduction',
                    'Record technical and administrative metadata',
                    'Store/link reproduction and release if approved',
                ]
            ),

            'collections_review' => self::buildConfig(
                'Collections review',
                [
                    'Define review scope and methodology',
                    'Assess collection/group',
                    'Record findings',
                    'Identify actions or recommendations',
                    'Approve next steps',
                    'Feed outputs into planning, cataloguing, care or disposal',
                ]
            ),

            'audit' => self::buildConfig(
                'Audit',
                [
                    'Define audit scope',
                    'Compare records to physical reality',
                    'Check numbering, location and completeness',
                    'Record discrepancies',
                    'Correct records or escalate issues',
                    'Report results and schedule follow-up',
                ]
            ),
        ];
    }
}
