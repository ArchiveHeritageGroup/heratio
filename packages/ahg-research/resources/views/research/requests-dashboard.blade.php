{{-- Requests Dashboard - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'My Requests')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">My Requests</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-inbox text-primary me-2"></i>{{ __('My Requests') }}</h1>
<div class="row mb-4">
    <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['pending'] ?? 0 }}</h3><small>{{ __('Pending') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['in_progress'] ?? 0 }}</h3><small>{{ __('In Progress') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['completed'] ?? 0 }}</h3><small>{{ __('Completed') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['denied'] ?? 0 }}</h3><small>{{ __('Denied') }}</small></div></div></div>
</div>
<div class="card"><div class="card-body p-0">
    @if(!empty($requests))
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>{{ __('Request') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
        <tbody>
            @foreach($requests as $r)
            <tr>
                <td><strong>{{ e($r->title ?? 'Request #' . $r->id) }}</strong></td>
                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $r->request_type ?? '')) }}</span></td>
                <td><span class="badge bg-{{ match($r->status ?? '') { 'approved' => 'success', 'denied' => 'danger', 'in_progress' => 'primary', 'completed' => 'info', default => 'warning' } }}">{{ ucfirst(str_replace('_', ' ', $r->status ?? 'pending')) }}</span></td>
                <td class="small">{{ $r->created_at ?? '' }}</td>
                <td class="small">{{ $r->updated_at ?? '' }}</td>
                <td><a href="{{ route('research.dashboard', ['request_correspond' => $r->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="text-center py-4 text-muted">No requests found.</div>
    @endif
</div></div>
@endsection