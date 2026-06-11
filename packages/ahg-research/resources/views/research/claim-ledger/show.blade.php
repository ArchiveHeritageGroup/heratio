{{-- Claim Ledger - claim detail - Research OS Stage 8 (heratio#1223) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Claim')

@php
    $badges = $statusBadges ?? [];
    $meta = $claim->meta ?? null;
    $st = $claim->status ?? 'idea';
    $evCount = is_array($evidence) ? count($evidence) : 0;
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.claims.index', $project->id) }}">{{ __('Claim Ledger') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Claim') }} #{{ $claim->id }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-balance-scale text-primary me-2"></i>{{ __('Claim') }} #{{ $claim->id }}</h1>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-{{ $badges[$st] ?? 'secondary' }} fs-6">{{ __($statuses[$st] ?? ucfirst($st)) }}</span>
        <a href="{{ route('research.claims.index', $project->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

@if($evCount === 0)
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('This claim has no citation. Attach at least one piece of evidence below.') }}</div>
@endif

<div class="row g-3">
    {{-- Edit claim --}}
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-bold">{{ __('Edit claim') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.claims.update', [$project->id, $claim->id]) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Claim text') }} <span class="text-danger">*</span></label>
                        <textarea name="text" class="form-control" rows="3" required>{{ $claim->object_value ?? $claim->subject_label ?? '' }}</textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ $st === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Confidence') }}</label>
                            <select name="confidence_level" class="form-select form-select-sm">
                                <option value="">{{ __('Not set') }}</option>
                                @foreach($confidenceLevels as $cl)
                                    <option value="{{ $cl }}" {{ ($meta->confidence_level ?? '') === $cl ? 'selected' : '' }}>{{ ucfirst($cl) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Originality') }}</label>
                            <select name="provenance_kind" class="form-select form-select-sm">
                                @foreach($provenanceKinds as $pk)
                                    <option value="{{ $pk }}" {{ ($meta->provenance_kind ?? 'original') === $pk ? 'selected' : '' }}>{{ ucfirst($pk) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Evidence type') }}</label>
                            <select name="evidence_type" class="form-select form-select-sm">
                                <option value="">{{ __('Not set') }}</option>
                                @foreach($evidenceTypes as $et)
                                    <option value="{{ $et }}" {{ ($meta->evidence_type ?? '') === $et ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $et)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Supporting sources') }}</label>
                            <textarea name="supporting_sources" class="form-control form-control-sm" rows="2">{{ $meta->supporting_sources ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Opposing sources') }}</label>
                            <textarea name="opposing_sources" class="form-control form-control-sm" rows="2">{{ $meta->opposing_sources ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Quotations (with page references)') }}</label>
                        <textarea name="quotations" class="form-control form-control-sm" rows="3" placeholder="{{ __('e.g. &quot;...&quot; (Author 2019, p. 42)') }}">{{ $meta->quotations ?? '' }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Method / theory link') }}</label>
                        <textarea name="method_theory_link" class="form-control form-control-sm" rows="2">{{ $meta->method_theory_link ?? '' }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('Researcher notes') }}</label>
                        <textarea name="researcher_notes" class="form-control form-control-sm" rows="2">{{ $meta->researcher_notes ?? '' }}</textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Unresolved weaknesses') }}</label>
                            <textarea name="unresolved_weaknesses" class="form-control form-control-sm" rows="2">{{ $meta->unresolved_weaknesses ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Ethical concerns') }}</label>
                            <textarea name="ethical_concerns" class="form-control form-control-sm" rows="2">{{ $meta->ethical_concerns ?? '' }}</textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save claim') }}</button>
                </form>
            </div>
        </div>

        {{-- Danger zone --}}
        <div class="card border-danger">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted small">{{ __('Delete this claim and all its evidence links. This cannot be undone.') }}</span>
                <form method="POST" action="{{ route('research.claims.destroy', [$project->id, $claim->id]) }}" onsubmit="return confirm('{{ __('Delete this claim?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete claim') }}</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Evidence --}}
    <div class="col-lg-5">
        {{-- Quick status transition --}}
        <div class="card mb-3">
            <div class="card-header fw-bold">{{ __('Lifecycle') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.claims.status', [$project->id, $claim->id]) }}" class="d-flex gap-2">
                    @csrf
                    <select name="status" class="form-select form-select-sm">
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ $st === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-flag me-1"></i>{{ __('Set') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">{{ __('Evidence') }}</span>
                <span class="badge bg-{{ $evCount > 0 ? 'success' : 'warning text-dark' }}">{{ $evCount }}</span>
            </div>
            <div class="card-body p-0">
                @if($evCount > 0)
                <ul class="list-group list-group-flush">
                    @foreach($evidence as $ev)
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-light text-dark border me-1">{{ ucfirst(str_replace('_', ' ', $ev->source_type)) }}</span>
                            <span class="badge bg-{{ $ev->relationship === 'opposes' ? 'danger' : ($ev->relationship === 'contextualizes' ? 'info' : 'success') }}">{{ ucfirst($ev->relationship) }}</span>
                            <div class="small mt-1">{{ e($ev->source_label ?? '') }}</div>
                            @if($ev->note)<div class="text-muted small fst-italic">{{ e($ev->note) }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('research.claims.evidence.detach', [$project->id, $claim->id, $ev->id]) }}" onsubmit="return confirm('{{ __('Detach this evidence?') }}');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Detach') }}"><i class="fas fa-times"></i></button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="text-center py-3 text-muted small">{{ __('No evidence attached yet.') }}</div>
                @endif
            </div>
        </div>

        {{-- Attach evidence --}}
        <div class="card">
            <div class="card-header fw-bold">{{ __('Attach evidence') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.claims.evidence.attach', [$project->id, $claim->id]) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">{{ __('Source type') }}</label>
                        <select name="source_type" id="evSourceType" class="form-select form-select-sm" required onchange="evSwitchSource()">
                            <option value="bibliography">{{ __('Bibliography') }}</option>
                            <option value="annotation">{{ __('Annotation') }}</option>
                            <option value="collection_item">{{ __('Collection item') }}</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Source') }}</label>
                        @foreach(['bibliography','annotation','collection_item'] as $stype)
                            <select name="source_id" data-srctype="{{ $stype }}" class="form-select form-select-sm ev-src-select" {{ $stype === 'bibliography' ? '' : 'style=display:none disabled' }}>
                                @php $list = $available[$stype] ?? []; @endphp
                                @if(count($list) > 0)
                                    @foreach($list as $opt)
                                        <option value="{{ $opt->id }}">{{ e(\Illuminate\Support\Str::limit($opt->label ?? ('#'.$opt->id), 80)) }}</option>
                                    @endforeach
                                @else
                                    <option value="" disabled>{{ __('No items in this project') }}</option>
                                @endif
                            </select>
                        @endforeach
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Relationship') }}</label>
                        <select name="relationship" class="form-select form-select-sm">
                            <option value="supports">{{ __('Supports') }}</option>
                            <option value="opposes">{{ __('Opposes') }}</option>
                            <option value="contextualizes">{{ __('Contextualizes') }}</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Note (optional)') }}</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="2000">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paperclip me-1"></i>{{ __('Attach') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function evSwitchSource() {
    var t = document.getElementById('evSourceType').value;
    document.querySelectorAll('.ev-src-select').forEach(function (s) {
        var match = s.getAttribute('data-srctype') === t;
        s.style.display = match ? '' : 'none';
        s.disabled = !match;
    });
}
document.addEventListener('DOMContentLoaded', evSwitchSource);
</script>
@endsection
