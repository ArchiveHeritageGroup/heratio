{{-- View Activity - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'activities'])@endsection
@section('title', 'Activity Details')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li><li class="breadcrumb-item"><a href="{{ route('research.activities') }}">{{ __('Activities') }}</a></li><li class="breadcrumb-item active">{{ __('Details') }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-stream text-primary me-2"></i>{{ __('Activity Details') }}</h1>
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Activity Information') }}</div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Type') }}</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $activity->activity_type ?? '')) }}</span></dd>
        <dt class="col-sm-3">{{ __('User') }}</dt><dd class="col-sm-9">{{ e(($activity->first_name ?? '') . ' ' . ($activity->last_name ?? '')) }}</dd>
        <dt class="col-sm-3">{{ __('Description') }}</dt><dd class="col-sm-9">{{ e($activity->description ?? '') }}</dd>
        <dt class="col-sm-3">{{ __('Object') }}</dt><dd class="col-sm-9">{{ e($activity->object_type ?? '') }} #{{ $activity->object_id ?? '' }}</dd>
        <dt class="col-sm-3">{{ __('IP Address') }}</dt><dd class="col-sm-9">{{ e($activity->ip_address ?? 'N/A') }}</dd>
        <dt class="col-sm-3">{{ __('Date') }}</dt><dd class="col-sm-9">{{ $activity->created_at ?? '' }}</dd>
    </dl>
</div></div>
@if(!empty($activity->metadata))
<div class="card mb-4"><div class="card-header">{{ __('Metadata') }}</div><div class="card-body"><pre class="mb-0"><code>{{ json_encode(json_decode($activity->metadata ?? '{}'), JSON_PRETTY_PRINT) }}</code></pre></div></div>
@endif
<a href="{{ route('research.activities') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Activities') }}</a>
@endsection