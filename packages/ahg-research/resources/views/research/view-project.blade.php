@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection

@section('content')
@php
    $isOwner = ($project->owner_id ?? 0) == ($researcher->id ?? 0);
@endphp

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">Projects</a></li>
        <li class="breadcrumb-item active">{{ e($project->title) }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2">{{ e($project->title) }}</h1>
        <span class="badge bg-{{ match($project->status ?? '') { 'active' => 'success', 'planning' => 'info', 'on_hold' => 'warning', 'completed' => 'secondary', default => 'dark' } }} me-2">{{ ucfirst($project->status ?? 'active') }}</span>
        <span class="badge bg-light text-dark">{{ ucfirst($project->project_type ?? 'personal') }}</span>
    </div>
    @if($isOwner)
    <div class="d-flex gap-2">
        <a href="{{ route('research.viewProject', $project->id) }}?action=edit" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
        </a>
    </div>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row">
    <div class="col-md-8">
        {{-- Description --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('Description') }}</h5></div>
            <div class="card-body">
                @if($project->description)
                    <p>{{ nl2br(e($project->description)) }}</p>
                @else
                    <p class="text-muted">No description provided.</p>
                @endif

                <div class="row mt-4">
                    @if($project->institution)
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-university me-1"></i> Institution:</strong><br>
                        {{ e($project->institution) }}
                    </div>
                    @endif
                    @if($project->supervisor ?? null)
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-user-tie me-1"></i> Supervisor:</strong><br>
                        {{ e($project->supervisor) }}
                    </div>
                    @endif
                    @if($project->funding_source ?? null)
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-money-bill me-1"></i> Funding:</strong><br>
                        {{ e($project->funding_source) }}
                    </div>
                    @endif
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-calendar me-1"></i> Timeline:</strong><br>
                        @if($project->start_date)
                            {{ date('M j, Y', strtotime($project->start_date)) }}
                            @if($project->expected_end_date) - {{ date('M j, Y', strtotime($project->expected_end_date)) }}@endif
                        @else
                            Not specified
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Milestones --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-flag me-2"></i>Milestones</h5>
                @if($isOwner)
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#milestone-add-form">
                    <i class="fas fa-plus me-1"></i> {{ __('Add') }}
                </button>
                @endif
            </div>
            <div class="card-body">
                @if($isOwner)
                <div class="collapse mb-3" id="milestone-add-form">
                    <div class="card card-body bg-light">
                        <form method="post" action="{{ route('research.viewProject', $project->id) }}">
                            @csrf
                            <input type="hidden" name="form_action" value="add_milestone">
                            <div class="mb-2">
                                <input type="text" name="milestone_title" class="form-control form-control-sm" placeholder="{{ __('Milestone title *') }}" required>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <input type="date" name="milestone_due_date" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-6">
                                    <select name="milestone_status" class="form-select form-select-sm">
                                        <option value="pending">{{ __('Pending') }}</option>
                                        <option value="in_progress">{{ __('In Progress') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <textarea name="milestone_description" class="form-control form-control-sm" rows="2" placeholder="{{ __('Description (optional)') }}"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i> {{ __('Save Milestone') }}</button>
                        </form>
                    </div>
                </div>
                @endif

                @if(!empty($milestones))
                <div class="list-group list-group-flush">
                    @foreach($milestones as $m)
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <span class="{{ ($m->status ?? '') === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">
                                {{ e($m->title ?? 'Milestone') }}
                            </span>
                            @if($m->due_date ?? null)
                                <small class="text-muted ms-2"><i class="fas fa-calendar-alt fa-xs"></i> {{ date('M j, Y', strtotime($m->due_date)) }}</small>
                            @endif
                            @if($m->description ?? null)
                                <small class="text-muted d-block mt-1">{{ e($m->description) }}</small>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-{{ ($m->status ?? '') === 'completed' ? 'success' : (($m->status ?? '') === 'in_progress' ? 'primary' : 'secondary') }}">
                                {{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}
                            </span>
                            @if($isOwner && ($m->status ?? '') !== 'completed')
                            <form method="post" action="{{ route('research.viewProject', $project->id) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="form_action" value="complete_milestone">
                                <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                <button type="submit" class="btn btn-outline-success btn-sm" title="{{ __('Mark Complete') }}"><i class="fas fa-check fa-xs"></i></button>
                            </form>
                            @endif
                            @if($isOwner)
                            <form method="post" action="{{ route('research.viewProject', $project->id) }}" class="d-inline" onsubmit="return confirm('Delete this milestone?')">
                                @csrf
                                <input type="hidden" name="form_action" value="delete_milestone">
                                <input type="hidden" name="milestone_id" value="{{ $m->id }}">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}"><i class="fas fa-trash fa-xs"></i></button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">No milestones defined.</p>
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-stream me-2"></i>Project Timeline</h5></div>
            <div class="card-body">
                @php
                    $timelineItems = [];
                    foreach ($milestones ?? [] as $ms) {
                        $timelineItems[] = (object)[
                            'date' => $ms->due_date ?: ($ms->created_at ?? date('Y-m-d')),
                            'type' => 'milestone', 'title' => $ms->title, 'status' => $ms->status,
                            'icon' => 'flag',
                            'color' => ($ms->status === 'completed') ? 'success' : (($ms->status === 'in_progress') ? 'primary' : 'secondary'),
                        ];
                    }
                    foreach ($activities ?? [] as $act) {
                        $timelineItems[] = (object)[
                            'date' => $act->created_at,
                            'type' => 'activity',
                            'title' => $act->entity_title ?? ucfirst($act->activity_type ?? ''),
                            'status' => $act->activity_type ?? '',
                            'icon' => match($act->activity_type ?? '') { 'create' => 'plus-circle', 'update','edit' => 'edit', 'view','access' => 'eye', 'delete','remove' => 'trash', 'invite' => 'user-plus', default => 'circle' },
                            'color' => match($act->activity_type ?? '') { 'create' => 'success', 'update','edit' => 'info', 'delete','remove' => 'danger', 'invite' => 'warning', default => 'secondary' },
                        ];
                    }
                    usort($timelineItems, fn($a, $b) => strcmp($b->date, $a->date));
                @endphp
                @if(!empty($timelineItems))
                <div class="timeline-vertical">
                    @php $lastDate = ''; @endphp
                    @foreach($timelineItems as $item)
                        @php $itemDate = date('M j, Y', strtotime($item->date)); @endphp
                        @if($itemDate !== $lastDate)
                            @php $lastDate = $itemDate; @endphp
                            <div class="timeline-date text-muted fw-bold small mb-2 mt-3">{{ $itemDate }}</div>
                        @endif
                        <div class="d-flex align-items-start mb-2 ms-3">
                            <span class="badge bg-{{ $item->color }} rounded-circle p-1 me-2 mt-1" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-{{ $item->icon }} fa-xs"></i>
                            </span>
                            <div>
                                <span class="{{ $item->type === 'milestone' ? 'fw-bold' : '' }}">{{ e($item->title) }}</span>
                                @if($item->type === 'milestone')
                                    <span class="badge bg-{{ $item->color }} ms-1">{{ ucfirst($item->status) }}</span>
                                @else
                                    <small class="text-muted ms-1">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</small>
                                @endif
                                <br><small class="text-muted">{{ date('H:i', strtotime($item->date)) }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">No timeline events yet.</p>
                @endif
            </div>
        </div>

        {{-- Linked Resources --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Linked Resources') }}</h5>
                @if($isOwner)
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addResourceForm">
                    <i class="fas fa-plus me-1"></i>{{ __('Link Resource') }}
                </button>
                @endif
            </div>
            @if($isOwner)
            <div class="collapse" id="addResourceForm">
                <div class="card-body border-bottom bg-light">
                    <form method="post" action="{{ route('research.viewProject', $project->id) }}">
                        @csrf
                        <input type="hidden" name="form_action" value="add_resource">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Type') }}</label>
                                <select name="resource_type" class="form-select form-select-sm">
                                    <option value="external_link">{{ __('External Link') }}</option>
                                    <option value="archive_record">{{ __('Archive Record') }}</option>
                                    <option value="document">{{ __('Document') }}</option>
                                    <option value="reference">{{ __('Reference') }}</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small">{{ __('Title') }}</label>
                                <input type="text" name="resource_title" class="form-control form-control-sm" required placeholder="{{ __('Resource title...') }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small">URL</label>
                                <input type="url" name="external_url" class="form-control form-control-sm" placeholder="{{ __('https://...') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Notes') }}</label>
                                <input type="text" name="resource_notes" class="form-control form-control-sm" placeholder="{{ __('Optional notes...') }}">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-link me-1"></i>{{ __('Link') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            <div class="card-body">
                @if(!empty($resources))
                <div class="list-group list-group-flush">
                    @foreach($resources as $resource)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <span class="badge bg-secondary me-2">{{ ucfirst(str_replace('_', ' ', $resource->resource_type ?? 'link')) }}</span>
                                @if(!empty($resource->external_url))
                                    <a href="{{ e($resource->external_url) }}" target="_blank" rel="noopener">
                                        {{ e($resource->title ?: $resource->external_url) }}
                                        <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                    </a>
                                @elseif(!empty($resource->object_id))
                                    @php $resSlug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $resource->object_id)->value('slug'); @endphp
                                    @if($resSlug)
                                        <a href="{{ url('/' . $resSlug) }}">{{ e($resource->title ?: 'View Item') }}</a>
                                    @else
                                        {{ e($resource->title ?? '') }}
                                    @endif
                                @else
                                    {{ e($resource->title ?? '') }}
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">{{ date('M j, Y', strtotime($resource->added_at ?? $resource->created_at ?? 'now')) }}</small>
                                @if($isOwner)
                                <form method="post" action="{{ route('research.viewProject', $project->id) }}" class="d-inline" onsubmit="return confirm('Remove this resource?')">
                                    @csrf
                                    <input type="hidden" name="form_action" value="remove_resource">
                                    <input type="hidden" name="resource_id" value="{{ $resource->id }}">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="{{ __('Remove') }}"><i class="fas fa-times fa-xs"></i></button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @if(!empty($resource->description))
                            <small class="text-muted d-block mt-1">{{ e($resource->description) }}</small>
                        @endif
                        @if(!empty($resource->notes))
                            <small class="text-muted d-block">{{ e($resource->notes) }}</small>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">No resources linked yet. Click "Link Resource" to add.</p>
                @endif
            </div>
        </div>
        {{-- Reports --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Reports</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newProjectReportModal"><i class="fas fa-plus me-1"></i>{{ __('New Report') }}</button>
            </div>
            <div class="card-body p-0">
                @if(!empty($reports))
                <ul class="list-group list-group-flush">
                    @foreach($reports as $rpt)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('research.viewReport', $rpt->id) }}" class="text-decoration-none fw-semibold">{{ e($rpt->title) }}</a>
                            @if($rpt->template_type ?? null)
                                <br><small class="text-muted">{{ ucwords(str_replace('_', ' ', $rpt->template_type)) }}</small>
                            @endif
                        </div>
                        <div>
                            @php $sc = ['draft' => 'secondary', 'in_progress' => 'primary', 'review' => 'warning', 'completed' => 'success']; @endphp
                            <span class="badge bg-{{ $sc[$rpt->status ?? 'draft'] ?? 'dark' }}">{{ ucwords(str_replace('_', ' ', $rpt->status ?? 'draft')) }}</span>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="card-body text-center text-muted py-4">
                    <i class="fas fa-file-alt fa-2x mb-2 opacity-50"></i>
                    <p class="mb-2">No reports yet</p>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newProjectReportModal">{{ __('Create first report') }}</button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Analysis Tools --}}
        <div class="card mb-4">
            <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-brain me-2"></i>Analysis Tools</h6></div>
            <div class="list-group list-group-flush">
                <a href="{{ url('/research/knowledge-graph/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-project-diagram me-2 text-primary"></i>{{ __('Knowledge Graph') }}</a>
                <a href="{{ url('/research/assertions/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-quote-right me-2 text-success"></i>{{ __('Assertions') }}</a>
                <a href="{{ url('/research/hypotheses/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-lightbulb me-2 text-warning"></i>{{ __('Hypotheses') }}</a>
                <a href="{{ url('/research/extraction-jobs/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-robot me-2 text-info"></i>{{ __('AI Extraction') }}</a>
                <a href="{{ url('/research/snapshots/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-camera me-2 text-secondary"></i>{{ __('Snapshots') }}</a>
                <a href="{{ url('/research/assertion-batch-review/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-tasks me-2 text-danger"></i>{{ __('Batch Review') }}</a>
            </div>
        </div>

        {{-- Visualization --}}
        <div class="card mb-4">
            <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Visualization</h6></div>
            <div class="list-group list-group-flush">
                <a href="{{ url('/research/timeline/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-stream me-2 text-primary"></i>{{ __('Timeline Builder') }}</a>
                <a href="{{ url('/research/map/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-map-marked-alt me-2 text-success"></i>{{ __('Map Builder') }}</a>
                <a href="{{ url('/research/network-graph/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-share-alt me-2 text-warning"></i>{{ __('Network Graph') }}</a>
            </div>
        </div>

        {{-- Research Output --}}
        <div class="card mb-4">
            <div class="card-header bg-success text-white"><h6 class="mb-0"><i class="fas fa-file-export me-2"></i>Research Output</h6></div>
            <div class="list-group list-group-flush">
                <a href="{{ route('research.reports', ['project_id' => $project->id]) }}" class="list-group-item list-group-item-action"><i class="fas fa-file-alt me-2 text-dark"></i>Reports ({{ count($reports ?? []) }})</a>
                <a href="{{ url('/research/ro-crate/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-box me-2 text-primary"></i>{{ __('RO-Crate Package') }}</a>
                <a href="{{ url('/research/reproducibility/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-redo me-2 text-info"></i>{{ __('Reproducibility Pack') }}</a>
                <a href="{{ url('/research/doi/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-link me-2 text-success"></i>{{ __('DOI Minting') }}</a>
                <a href="{{ url('/research/ethics-milestones/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-balance-scale me-2 text-warning"></i>{{ __('Ethics Milestones') }}</a>
                <a href="{{ url('/research/compliance/' . $project->id) }}" class="list-group-item list-group-item-action"><i class="fas fa-shield-alt me-2 text-danger"></i>{{ __('Compliance Dashboard') }}</a>
            </div>
        </div>

        {{-- Collaborators --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Collaborators</h6>
                @if($isOwner)
                <a href="{{ url('/research/invite-collaborator/' . $project->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-user-plus"></i>
                </a>
                @endif
            </div>
            @if(!empty($collaborators))
            <ul class="list-group list-group-flush">
                @foreach($collaborators as $collab)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        {{ e($collab->first_name . ' ' . $collab->last_name) }}
                        @if(($collab->role ?? '') === 'owner')
                            <i class="fas fa-crown text-warning ms-1" title="{{ __('Owner') }}"></i>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-{{ ($collab->status ?? '') === 'accepted' ? 'success' : 'warning' }}">
                            {{ ucfirst($collab->role ?? 'contributor') }}
                        </span>
                        @if($isOwner && ($collab->role ?? '') !== 'owner')
                        <form method="post" action="{{ route('research.viewProject', $project->id) }}" class="d-inline" onsubmit="return confirm('Remove this collaborator?')">
                            @csrf
                            <input type="hidden" name="form_action" value="remove_collaborator">
                            <input type="hidden" name="collaborator_researcher_id" value="{{ $collab->researcher_id }}">
                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="{{ __('Remove') }}"><i class="fas fa-times fa-xs"></i></button>
                        </form>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
            @else
            <div class="card-body text-muted">No collaborators</div>
            @endif
        </div>

        {{-- Recent Activity --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h6></div>
            @if(!empty($activities))
            <ul class="list-group list-group-flush">
                @foreach(array_slice($activities, 0, 10) as $activity)
                <li class="list-group-item">
                    <small>
                        <span class="badge bg-light text-dark">{{ ucfirst($activity->activity_type ?? '') }}</span>
                        @if($activity->entity_title ?? null)
                            {{ e(Str::limit($activity->entity_title, 30)) }}
                        @endif
                        <br><span class="text-muted">{{ date('M j, H:i', strtotime($activity->created_at)) }}</span>
                    </small>
                </li>
                @endforeach
            </ul>
            @else
            <div class="card-body text-muted">No activity recorded yet.</div>
            @endif
        </div>
    </div>
</div>

{{-- New Report Modal (project-linked) --}}
<div class="modal fade" id="newProjectReportModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
    <form method="POST" action="{{ route('research.reports') }}">
        @csrf
        <input type="hidden" name="form_action" value="create">
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        <input type="hidden" name="template_type" id="projectReportTemplate" value="custom">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>New Report for {{ e($project->title) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">{{ __('Title *') }}</label><input type="text" name="title" class="form-control" required placeholder="{{ __('Report title...') }}"></div>
            <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="mb-3">
                <label class="form-label">{{ __('Template') }}</label>
                <select name="template_type" class="form-select">
                    <option value="custom">{{ __('Custom (blank)') }}</option>
                    <option value="research_summary">{{ __('Research Summary') }}</option>
                    <option value="genealogical">{{ __('Genealogical Report') }}</option>
                    <option value="historical">{{ __('Historical Analysis') }}</option>
                    <option value="source_analysis">{{ __('Source Analysis') }}</option>
                    <option value="finding_aid">{{ __('Finding Aid') }}</option>
                </select>
                <small class="text-muted">{{ __('Template sections will be auto-created.') }}</small>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Create Report') }}</button></div>
    </form>
</div>
</div>
</div>
@endsection
