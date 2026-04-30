@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reports'])@endsection
@section('title', 'Research Reports')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Reports</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="fas fa-file-alt text-primary me-2"></i>Research Reports</h1>
    <a href="{{ route('research.reportTemplates') }}" class="btn btn-outline-secondary btn-sm me-1"><i class="fas fa-layer-group me-1"></i>{{ __('Templates') }}</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReportModal"><i class="fas fa-plus me-1"></i>{{ __('New Report') }}</button>
</div>

{{-- Status Tabs --}}
<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link {{ empty($currentStatus) ? 'active' : '' }}" href="{{ route('research.reports') }}">All</a></li>
    @foreach(['draft' => 'Draft', 'in_progress' => 'In Progress', 'review' => 'Review', 'completed' => 'Completed'] as $sKey => $sLabel)
    <li class="nav-item"><a class="nav-link {{ ($currentStatus ?? '') === $sKey ? 'active' : '' }}" href="{{ route('research.reports', ['status' => $sKey]) }}">{{ $sLabel }}</a></li>
    @endforeach
</ul>

@if(!empty($reports))
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Template') }}</th>
                <th>{{ __('Project') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-center">{{ __('Sections') }}</th>
                <th>{{ __('Last Updated') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($reports as $report)
            <tr>
                <td>
                    <a href="{{ route('research.viewReport', $report->id) }}" class="text-decoration-none fw-semibold">{{ e($report->title) }}</a>
                    @if($report->description ?? null)
                        <br><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($report->description, 60)) }}</small>
                    @endif
                </td>
                <td>
                    @php
                        $tplColors = ['research_summary' => 'primary', 'genealogical' => 'success', 'historical' => 'info', 'source_analysis' => 'warning', 'finding_aid' => 'secondary'];
                    @endphp
                    <span class="badge bg-{{ $tplColors[$report->template_type ?? 'custom'] ?? 'dark' }}">{{ ucwords(str_replace('_', ' ', $report->template_type ?? 'custom')) }}</span>
                </td>
                <td>
                    @if($report->project_title ?? null)
                        <small>{{ e($report->project_title) }}</small>
                    @else
                        <small class="text-muted">{{ __('None') }}</small>
                    @endif
                </td>
                <td>
                    @php $sc = ['draft' => 'secondary', 'in_progress' => 'primary', 'review' => 'warning', 'completed' => 'success']; @endphp
                    <span class="badge rounded-pill bg-{{ $sc[$report->status ?? 'draft'] ?? 'dark' }}">{{ ucwords(str_replace('_', ' ', $report->status ?? 'draft')) }}</span>
                </td>
                <td class="text-center"><span class="badge bg-light text-dark">{{ (int) ($report->section_count ?? 0) }}</span></td>
                <td><small class="text-muted">{{ date('M j, Y H:i', strtotime($report->updated_at)) }}</small></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('research.viewReport', $report->id) }}" class="btn btn-outline-primary" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                        <a href="{{ route('research.viewReport', $report->id) }}?export=pdf" class="btn btn-outline-danger" title="{{ __('Export PDF') }}"><i class="fas fa-file-pdf"></i></a>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
<div class="text-center py-5">
    <i class="fas fa-file-alt fa-4x text-muted mb-3 opacity-50"></i>
    <h4 class="text-muted">{{ __('No reports yet') }}</h4>
    <p class="text-muted">Create a report to document your research findings.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReportModal"><i class="fas fa-plus me-1"></i>{{ __('Create First Report') }}</button>
</div>
@endif

{{-- New Report Modal with Template Picker --}}
<div class="modal fade" id="newReportModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
    <form method="POST" action="{{ route('research.reports') }}">
        @csrf
        <input type="hidden" name="form_action" value="create">
        <input type="hidden" name="template_type" id="selectedTemplate" value="custom">
        <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Create New Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            {{-- Step 1: Template --}}
            <h6 class="mb-3"><span class="badge bg-primary me-2">1</span>Choose a Template</h6>
            @php
                $tplIcons = [
                    'research_summary' => ['fas fa-clipboard-list', 'primary'],
                    'genealogical' => ['fas fa-sitemap', 'success'],
                    'historical' => ['fas fa-landmark', 'info'],
                    'source_analysis' => ['fas fa-search', 'warning'],
                    'finding_aid' => ['fas fa-map', 'secondary'],
                    'custom' => ['fas fa-pencil-alt', 'dark'],
                ];
            @endphp
            <div class="row mb-4">
                @foreach($templates ?? [] as $tpl)
                @php
                    $icon = $tplIcons[$tpl->code][0] ?? 'fas fa-file-alt';
                    $color = $tplIcons[$tpl->code][1] ?? 'secondary';
                @endphp
                <div class="col-md-4 mb-2">
                    <div class="card h-100 template-card {{ $tpl->code === 'custom' ? 'selected' : '' }}" data-template="{{ $tpl->code }}" role="button" style="cursor:pointer;">
                        <div class="card-body text-center py-3">
                            <i class="{{ $icon }} fa-2x text-{{ $color }} mb-2"></i>
                            <h6 class="card-title mb-1">{{ e($tpl->name) }}</h6>
                            <p class="card-text small text-muted mb-0">{{ e(\Illuminate\Support\Str::limit($tpl->description ?? '', 80)) }}</p>
                            @php $sCount = count(json_decode($tpl->sections_config ?? '[]', true) ?: []); @endphp
                            @if($sCount > 0)
                                <small class="text-muted">{{ $sCount }} sections</small>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Step 2: Details --}}
            <h6 class="mb-3"><span class="badge bg-primary me-2">2</span>Report Details</h6>
            <div class="mb-3"><label class="form-label">{{ __('Report Title *') }}</label><input type="text" name="title" class="form-control" required placeholder="{{ __('Enter report title...') }}"></div>
            <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="2" placeholder="{{ __('Brief description...') }}"></textarea></div>
            <div class="mb-3">
                <label class="form-label">{{ __('Project') }}</label>
                <select name="project_id" class="form-select">
                    <option value="">{{ __('No Project') }}</option>
                    @foreach($projects ?? [] as $p)
                        <option value="{{ $p->id }}">{{ e($p->title) }}</option>
                    @endforeach
                </select>
                <small class="form-text">{{ __('Link to a project to auto-populate data.') }}</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Create Report') }}</button>
        </div>
    </form>
</div>
</div>
</div>

<style>
.template-card { transition: all 0.2s; border: 2px solid transparent; }
.template-card:hover { border-color: #0d6efd; box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1); }
.template-card.selected { border-color: #0d6efd; background-color: #f0f7ff; }
</style>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cards = document.querySelectorAll('.template-card');
    var hidden = document.getElementById('selectedTemplate');
    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            cards.forEach(function(c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            hidden.value = card.dataset.template;
        });
    });
});
</script>
@endpush
@endsection
