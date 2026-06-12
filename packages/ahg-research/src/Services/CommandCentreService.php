<?php

/**
 * CommandCentreService - the research journey for a project (Research OS #1225).
 *
 * Turns the flat pile of research features into an ordered journey:
 * Intent -> Question -> Capture -> Evidence & Triage -> Reading -> Claims ->
 * Decision Log -> Writing -> Review -> Publish. For a given project it reports,
 * per phase, a status + a count + the link to that phase, so the per-project
 * Command Centre can show the researcher where they are and what is next.
 *
 * Read-only. Every probe is Schema::hasTable-guarded and try/catch-wrapped, so a
 * partial install or a missing column degrades a phase to a zero count rather
 * than 500-ing the project page. Phase links are gated through Route::has so a
 * not-yet-registered slice simply renders without a link.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
 * Part of Heratio. Licensed under the GNU AGPL v3 or later.
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CommandCentreService
{
    /**
     * Build the ordered journey for a project.
     *
     * @return array<int,array<string,mixed>> each: key,label,icon,status,count,hint,url
     */
    public function journey(int $projectId, ?int $researcherId = null): array
    {
        $phases = [];

        // 1. Intent - the project exists and has a description.
        $hasDesc = false;
        try {
            $p = DB::table('research_project')->where('id', $projectId)->first();
            $hasDesc = $p && trim((string) ($p->description ?? $p->title ?? '')) !== '';
        } catch (\Throwable $e) {
        }
        $phases[] = $this->phase('intent', 'Intent', 'fa-compass',
            $hasDesc ? 'done' : 'todo', $hasDesc ? 1 : 0,
            'Define the mission - field, problem, hypothesis, outputs.',
            $this->url('research.viewProject', ['id' => $projectId]));

        // 2. Question - a versioned Research Design Brief.
        $qCount = $this->count('research_question_brief_version', function ($q) use ($projectId) {
            return $q->join('research_question_brief as b', 'research_question_brief_version.brief_id', '=', 'b.id')
                ->where('b.project_id', $projectId);
        });
        $phases[] = $this->phase('question', 'Question', 'fa-question-circle',
            $qCount > 0 ? 'done' : 'todo', $qCount,
            'Sharpen the research question into a versioned design brief.',
            $this->url('research.question.builder', ['projectId' => $projectId]));

        // 3. Capture - inbox items (researcher-scoped, optionally this project).
        $cCount = 0;
        if ($researcherId) {
            $cCount = $this->count('research_inbox_item', function ($q) use ($researcherId, $projectId) {
                return $q->where('researcher_id', $researcherId)
                    ->where(function ($w) use ($projectId) {
                        $w->where('project_id', $projectId)->orWhereNull('project_id');
                    });
            });
        }
        $phases[] = $this->phase('capture', 'Capture', 'fa-inbox',
            $cCount > 0 ? 'started' : 'todo', $cCount,
            'Frictionless capture - nothing is lost; triage later.',
            $this->url('research.inbox.index'));

        // 4. Evidence & Triage - triaged sources for this project.
        $tCount = $this->count('research_source_triage', fn ($q) => $q->where('project_id', $projectId));
        $phases[] = $this->phase('evidence', 'Evidence & Triage', 'fa-folder-tree',
            $tCount > 0 ? 'started' : 'todo', $tCount,
            'Intake sources, triage them, track honest read-status.',
            $this->url('research.triage.index', ['projectId' => $projectId]));

        // 5. Reading - annotations by this researcher.
        $rCount = 0;
        if ($researcherId) {
            $rCount = $this->count('research_annotation', fn ($q) => $q->where('researcher_id', $researcherId));
        }
        $phases[] = $this->phase('reading', 'Reading', 'fa-highlighter',
            $rCount > 0 ? 'started' : 'todo', $rCount,
            'Deep reading with role-tagged highlights.',
            $this->url('research.annotations'));

        // 6. Claims - the spine. Plus the count with no citation.
        $clCount = $this->count('research_assertion', fn ($q) => $q->where('project_id', $projectId));
        $noCite = 0;
        try {
            if (Schema::hasTable('research_assertion') && Schema::hasTable('research_assertion_evidence')) {
                $noCite = (int) DB::table('research_assertion as a')
                    ->leftJoin('research_assertion_evidence as e', 'e.assertion_id', '=', 'a.id')
                    ->where('a.project_id', $projectId)
                    ->whereNull('e.id')
                    ->distinct()->count('a.id');
            }
        } catch (\Throwable $e) {
        }
        $phases[] = $this->phase('claims', 'Claims', 'fa-scale-balanced',
            $clCount > 0 ? 'done' : 'todo', $clCount,
            $noCite > 0 ? $noCite.' claim(s) still have no evidence.' : 'The spine - every claim knows its evidence.',
            $this->url('research.claims.index', ['projectId' => $projectId]), $noCite > 0 ? 'warn' : null);

        // 7. Decision Log - the memory of every loop.
        $dCount = $this->count('research_decision_log', fn ($q) => $q->where('project_id', $projectId));
        $phases[] = $this->phase('decisions', 'Decision Log', 'fa-clock-rotate-left',
            $dCount > 0 ? 'started' : 'todo', $dCount,
            'Record every scope change, exclusion and pivot - with receipts.',
            $this->url('research.decisions.index', ['projectId' => $projectId]));

        // 8. Method - the discipline-template Method Protocol.
        $mCount = $this->count('research_method_protocol', fn ($q) => $q->where('project_id', $projectId));
        $phases[] = $this->phase('method', 'Method', 'fa-flask',
            $mCount > 0 ? 'started' : 'todo', $mCount,
            'Pick a discipline template, write the Method Protocol once, reuse it.',
            $this->url('research.method.index', ['projectId' => $projectId]));

        // 9. Argument - drag claims into the argument chain.
        $aCount = $this->count('research_argument_step', function ($q) use ($projectId) {
            return $q->join('research_argument as ra', 'research_argument_step.argument_id', '=', 'ra.id')
                ->where('ra.project_id', $projectId);
        });
        $phases[] = $this->phase('argument', 'Argument', 'fa-diagram-project',
            $aCount > 0 ? 'started' : 'todo', $aCount,
            'Build the argument chain from your claims; the system flags weak spots.',
            $this->url('research.argument.show', ['projectId' => $projectId]));

        // 10. Writing.
        $phases[] = $this->phase('writing', 'Writing', 'fa-pen-nib', 'info', null,
            'Write as you go - journals, reports, lectures.',
            $this->url('research.reports'));

        // 11. Review - supervisor comments + adversarial reviewer twin.
        $revCount = $this->count('research_review_comment', fn ($q) => $q->where('project_id', $projectId));
        $phases[] = $this->phase('review', 'Review', 'fa-user-check',
            $revCount > 0 ? 'started' : 'todo', $revCount,
            'Claim-anchored supervisor comments and an adversarial reviewer twin.',
            $this->url('research.review.index', ['projectId' => $projectId]));

        // 12. Publish - journal match + submission workflow.
        $subCount = $this->count('research_submission', fn ($q) => $q->where('project_id', $projectId));
        $phases[] = $this->phase('publish', 'Publish', 'fa-paper-plane',
            $subCount > 0 ? 'started' : 'todo', $subCount,
            'Match a target journal, check requirements, track the submission.',
            $this->url('research.publication.index', ['projectId' => $projectId]));

        return $phases;
    }

    /**
     * Build the ordered list of secondary Research OS tools for a project.
     *
     * These sit alongside the core journey - the Contradiction Engine, Analysis
     * Bridge, Field Alerts, Research Memory, Argument warnings, Writing/Export,
     * Replication Pack, Grant Engine, Time Machine, Impact Tracking and AI
     * Disclosure. Each entry is the same shape as a journey phase (key, label,
     * icon, count, hint, url) so the Command Centre partial can render them with
     * the same styling.
     *
     * Read-only and defensive: every count is Schema::hasTable-guarded and
     * try/catch-wrapped, and every link is Route::has-gated. A tool whose route
     * is not registered is omitted entirely, so a partial install simply renders
     * fewer cards rather than 500-ing the project page. A meaningful, cheap count
     * is surfaced where one exists (open contradictions, analysis results, unread
     * alerts, live memory items, open AI flags); otherwise the count is null.
     *
     * @return array<int,array<string,mixed>> each: key,label,icon,count,hint,url
     */
    public function tools(int $projectId, ?int $researcherId = null): array
    {
        $defs = [];

        // Contradiction Engine - open contradictions across the Claim Ledger.
        $contraOpen = $this->count('research_contradiction', function ($q) use ($projectId) {
            return $q->where('project_id', $projectId)->where('status', 'open');
        });
        $defs[] = $this->tool('contradictions', 'Contradiction Engine', 'fa-bolt',
            'research.contradictions.index', ['projectId' => $projectId],
            $contraOpen,
            'Surface claims that quietly disagree with each other.');

        // Analysis Bridge - recorded analysis results for this project.
        $analysisCount = $this->count('research_analysis_result', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('analysis', 'Analysis Bridge', 'fa-chart-column',
            'research.analysis.index', ['projectId' => $projectId],
            $analysisCount,
            'Register analysis outputs and link them back to your claims.');

        // Field Alerts - unread alerts (retractions, updates, new related work).
        $alertsUnread = $this->count('research_field_alert', function ($q) use ($projectId) {
            return $q->where('project_id', $projectId)->where('is_read', 0);
        });
        $defs[] = $this->tool('alerts', 'Field Alerts', 'fa-bell',
            'research.alerts.index', ['projectId' => $projectId],
            $alertsUnread,
            'Watch cited work for retractions, updates and new related research.');

        // Research Memory - live (open / carry-forward) memory items for this
        // project, plus the researcher's detached carry-forward pool if known.
        $memoryCount = $this->count('research_memory_item', function ($q) use ($projectId, $researcherId) {
            return $q->whereIn('status', ['open', 'carry_forward'])
                ->where(function ($w) use ($projectId, $researcherId) {
                    $w->where('project_id', $projectId);
                    if ($researcherId) {
                        $w->orWhere(function ($p) use ($researcherId) {
                            $p->whereNull('project_id')->where('researcher_id', $researcherId);
                        });
                    }
                });
        });
        $defs[] = $this->tool('memory', 'Research Memory', 'fa-brain',
            'research.memory.index', ['projectId' => $projectId],
            $memoryCount,
            'Carry intellectual memory forward so the next project starts smarter.');

        // Argument warnings - the argument canvas flags weak spots.
        $defs[] = $this->tool('argument', 'Argument warnings', 'fa-triangle-exclamation',
            'research.argument.show', ['projectId' => $projectId],
            null,
            'Review the argument chain; the system flags unsupported steps.');

        // Writing / Export - one-click, full-fidelity, open-format export.
        $defs[] = $this->tool('export', 'Writing / Export', 'fa-file-export',
            'research.export.index', ['projectId' => $projectId],
            null,
            'Export the whole project in open formats - the exit door is always open.');

        // Replication Pack - build a reproducible bundle.
        $defs[] = $this->tool('replication', 'Replication Pack', 'fa-box-archive',
            'research.replication.index', ['projectId' => $projectId],
            null,
            'Assemble a reproducible pack of the evidence behind your findings.');

        // Grant Engine - drafted grant applications for this project.
        $grantCount = $this->count('research_grant_draft', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('grant', 'Grant Engine', 'fa-hand-holding-dollar',
            'research.grant.index', ['projectId' => $projectId],
            $grantCount,
            'Draft funder applications from your project, section by section.');

        // Data Management Plan (DMP) Builder - FAIR / maDMP plans for this project.
        $dmpCount = $this->count('research_dmp', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('dmp', 'Data Management Plan', 'fa-database',
            'research.dmp.index', ['projectId' => $projectId],
            $dmpCount,
            'Build the FAIR data management plan funders require, on the maDMP standard.');

        // Research Outputs register (CRIS / RIM) - the scholarly outputs this
        // project has produced, each with a resolvable persistent identifier.
        $outputCount = $this->count('research_output', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('outputs', 'Research Outputs', 'fa-book',
            'research.outputs.index', ['projectId' => $projectId],
            $outputCount,
            'Register the articles, datasets, software and reports this project produced, each with a DOI or persistent identifier.');

        // Research Ethics & Consent register - the project's ethics approvals and
        // the consent basis for its human-subject / sensitive data.
        $ethicsCount = $this->count('research_ethics', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('ethics', 'Research Ethics & Consent', 'fa-shield-alt',
            'research.ethics.index', ['projectId' => $projectId],
            $ethicsCount,
            'Record this project\'s ethics approvals and the consent basis for its human-subject and sensitive data.');

        // Research Funding tracker - the awarded-funding ledger for this project
        // (sources, amounts, currencies, award periods). Distinct from drafting a
        // grant proposal (Grant Engine).
        $fundingCount = $this->count('research_funding', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('funding', 'Research Funding', 'fa-hand-holding-dollar',
            'research.funding.index', ['projectId' => $projectId],
            $fundingCount,
            'Track this project\'s funding sources, amounts, currencies and award periods - the awarded-funding record.');

        // Research Team & Collaborators register - who is on the project and in
        // what capacity (name, role, affiliation, ORCID). The broader contributor
        // list, distinct from the project's single owner.
        $teamCount = $this->count('research_team_member', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('team', 'Research Team', 'fa-users',
            'research.team.index', ['projectId' => $projectId],
            $teamCount,
            'Document who is on this project - co-investigators, students, partners and collaborators - with their role, affiliation and ORCID.');

        // Time Machine - read-only reconstruction of how the work developed.
        $defs[] = $this->tool('timemachine', 'Time Machine', 'fa-clock-rotate-left',
            'research.timemachine.index', ['projectId' => $projectId],
            null,
            'Replay how the research developed - the honesty engine.');

        // Impact Tracking - downstream signals on published outputs.
        $impactCount = $this->count('research_impact_signal', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('impact', 'Impact Tracking', 'fa-arrow-trend-up',
            'research.impact.index', ['projectId' => $projectId],
            $impactCount,
            'Track citations, mentions and dataset reuse of your published work.');

        // AI Disclosure - logged AI usage for the disclosure statement.
        $aiCount = $this->count('research_ai_disclosure_log', fn ($q) => $q->where('project_id', $projectId));
        $defs[] = $this->tool('aidisclosure', 'AI Disclosure', 'fa-robot',
            'research.aidisclosure.index', ['projectId' => $projectId],
            $aiCount > 0 ? $aiCount : null,
            'Track and disclose any AI assistance, transparently and on the record.');

        // Drop any tool whose route is not registered (url() returned null).
        return array_values(array_filter($defs, fn ($t) => ! empty($t['url'])));
    }

    /**
     * Progress summary across the journey (done vs total scorable phases).
     *
     * @param  array<int,array<string,mixed>>  $journey
     * @return array{done:int,total:int,pct:int,next:?array<string,mixed>}
     */
    public function progress(array $journey): array
    {
        $scorable = array_values(array_filter($journey, fn ($p) => $p['status'] !== 'info'));
        $total = count($scorable);
        $done = count(array_filter($scorable, fn ($p) => $p['status'] === 'done'));
        $next = null;
        foreach ($scorable as $p) {
            if ($p['status'] !== 'done') {
                $next = $p;
                break;
            }
        }

        return [
            'done' => $done,
            'total' => $total,
            'pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'next' => $next,
        ];
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder):\Illuminate\Database\Query\Builder  $scope
     */
    protected function count(string $table, callable $scope): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            return (int) $scope(DB::table($table))->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function url(string $name, array $params = []): ?string
    {
        try {
            return Route::has($name) ? route($name, $params) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function phase(string $key, string $label, string $icon, string $status, ?int $count, string $hint, ?string $url, ?string $flag = null): array
    {
        return compact('key', 'label', 'icon', 'status', 'count', 'hint', 'url', 'flag');
    }

    /**
     * Build one secondary-tool entry. The link is Route::has-gated via url(), so
     * an unregistered slice yields a null url and is dropped by the caller.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    protected function tool(string $key, string $label, string $icon, string $routeName, array $params, ?int $count, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'count' => $count,
            'hint' => $hint,
            'url' => $this->url($routeName, $params),
        ];
    }
}
