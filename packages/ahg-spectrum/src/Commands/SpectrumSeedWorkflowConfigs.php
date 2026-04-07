<?php

namespace AhgSpectrum\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AhgSpectrum\Services\SpectrumWorkflowService;
use Illuminate\Support\Str;

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
                    $this->line("  Updated: {$procedureType} ({$config['name']}) — " . count($config['config']['steps']) . " steps");
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
                $this->line("  Created: {$procedureType} ({$config['name']}) — " . count($config['config']['steps']) . " steps");
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
     * Build a procedure-specific config from its condensed steps.
     *
     * Each condensed step becomes a real workflow state and transition.
     * The step text IS the action label the user clicks.
     */
    protected static function buildConfig(
        string $name,
        array $steps,
        array $triggers = []
    ): array {
        // Build states: pending + one state per step + closed
        $states = ['pending'];
        $stateLabels = ['pending' => 'Pending'];
        $stepDefs = [];
        $transitions = [];

        foreach ($steps as $i => $stepName) {
            // Convert step name to a clean snake_case state key
            $stateKey = Str::snake(Str::ascii($stepName));
            $stateKey = preg_replace('/[^a-z0-9_]/', '_', $stateKey);
            $stateKey = preg_replace('/_+/', '_', trim($stateKey, '_'));
            // Truncate to fit varchar(50)
            $stateKey = substr($stateKey, 0, 50);
            $states[] = $stateKey;
            $stateLabels[$stateKey] = $stepName;

            $stepDefs[] = [
                'order' => $i + 1,
                'name'  => $stepName,
                'state' => $stateKey,
            ];

            // Transition from previous state to this state
            $prevState = $i === 0 ? 'pending' : $states[$i]; // states[$i] is the previous step's state
            $transitionKey = $stateKey; // use same key as state

            $transitions[$transitionKey] = [
                'from'  => [$prevState],
                'to'    => $stateKey,
                'label' => $stepName,
            ];
        }

        // Add 'closed' as the final state after the last step
        $states[] = 'closed';
        $stateLabels['closed'] = 'Closed';

        $lastStepState = $states[count($states) - 2]; // second-to-last = last step state
        $transitions['close'] = [
            'from'  => [$lastStepState],
            'to'    => 'closed',
            'label' => 'Close procedure',
        ];

        // Reject: from any step state back to pending
        $stepStates = array_slice($states, 1, -1); // exclude pending and closed
        $transitions['reject'] = [
            'from'  => $stepStates,
            'to'    => 'pending',
            'label' => 'Reject / send back',
        ];

        // Restart: from closed back to pending
        $transitions['restart'] = [
            'from'  => ['closed'],
            'to'    => 'pending',
            'label' => 'Restart procedure',
        ];

        return [
            'name'   => $name,
            'config' => [
                'states'       => $states,
                'steps'        => $stepDefs,
                'transitions'  => $transitions,
                'initial_state' => 'pending',
                'final_states'  => ['closed'],
                'state_labels'  => $stateLabels,
                'triggers'      => $triggers,
            ],
        ];
    }

    /**
     * All 21 Spectrum 5.1 procedure configs with procedure-specific steps.
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
                    'Route to next action',
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
                    'Review and publish/release',
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
                    'Refer for treatment or monitoring',
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
                    'Review result and future care',
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
                    'Monitor and update cover',
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
                    'Recover, stabilise or conserve',
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
                    'Identify rights attached to object/data',
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
                    'Store/link reproduction and release',
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
                    'Feed outputs into planning or care',
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
