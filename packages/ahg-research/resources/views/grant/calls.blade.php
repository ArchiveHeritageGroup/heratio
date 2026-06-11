{{-- Grant Engine - tracked funder calls (heratio#1239) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Tracked Grant Calls'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.grant.index', $project->id ?? 0) }}">{{ __('Grant Engine') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Tracked calls') }}</li>
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-bullhorn text-primary me-2"></i>{{ __('Tracked Grant Calls') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#trackCallForm"><i class="fas fa-plus me-1"></i>{{ __('Track a call') }}</button>
        <a href="{{ route('research.grant.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Track funder calls and deadlines you are watching for this project. This is a tracker only - no submission happens here.') }}</p>

{{-- Track-a-call form (collapsed) --}}
<div class="collapse mb-4" id="trackCallForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Track a Grant Call') }}</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('research.grant.calls.store', $project->id ?? 0) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Funder') }} <span class="text-danger">*</span></label>
                        <input type="text" name="funder" class="form-control" maxlength="255" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Call title') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="255" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Deadline') }}</label>
                        <input type="date" name="deadline" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('URL') }}</label>
                        <input type="text" name="url" class="form-control" maxlength="500" placeholder="https://...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Status') }}</label>
                        <select name="status" class="form-select">
                            @foreach($statusOptions as $code => $label)
                                <option value="{{ e($code) }}">{{ e($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="5000"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>{{ __('Track call') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Calls list --}}
@if(empty($calls))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No grant calls tracked yet. Track one to keep an eye on funder deadlines.') }}</div>
@else
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Funder') }}</th>
                <th>{{ __('Call') }}</th>
                <th>{{ __('Deadline') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calls as $c)
            <tr>
                <td>{{ e($c['funder']) }}</td>
                <td>
                    @if(!empty($c['url']))
                        <a href="{{ e($c['url']) }}" target="_blank" rel="noopener noreferrer">{{ e($c['title']) }} <i class="fas fa-external-link-alt small"></i></a>
                    @else
                        {{ e($c['title']) }}
                    @endif
                    @if(!empty($c['notes']))<div class="text-muted small">{{ e($c['notes']) }}</div>@endif
                </td>
                <td class="text-muted small">{{ e($c['deadline'] ?: '-') }}</td>
                <td>
                    <form method="POST" action="{{ route('research.grant.calls.update', [$project->id ?? 0, $c['id']]) }}" class="d-flex gap-1">
                        @csrf
                        @method('PUT')
                        <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                            @foreach($statusOptions as $code => $label)
                                <option value="{{ e($code) }}" @selected($c['status'] === $code)>{{ e($label) }}</option>
                            @endforeach
                        </select>
                    </form>
                </td>
                <td class="text-end">
                    <form method="POST" action="{{ route('research.grant.calls.destroy', [$project->id ?? 0, $c['id']]) }}" onsubmit="return confirm('{{ __('Stop tracking this call?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Remove') }}</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
