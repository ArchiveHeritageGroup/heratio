{{-- Compliance Dashboard - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'adminStatistics'])
@endsection
@section('title', 'Compliance Dashboard')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Compliance Dashboard</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-shield-alt text-primary me-2"></i>Compliance Dashboard</h1>
<div class="row mb-4">
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['compliant'] ?? 0 }}</h3><small>Compliant</small></div></div></div>
    <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['warnings'] ?? 0 }}</h3><small>Warnings</small></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['violations'] ?? 0 }}</h3><small>Violations</small></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center"><h3 class="mb-0">{{ $stats['pending_review'] ?? 0 }}</h3><small>Pending Review</small></div></div></div>
</div>
@if(!empty($checks))
<div class="card">
    <div class="card-header"><h5 class="mb-0">Compliance Checks</h5></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Check</th><th>Category</th><th>Status</th><th>Last Run</th><th>Details</th></tr></thead>
            <tbody>
                @foreach($checks as $check)
                <tr>
                    <td><strong>{{ e($check->name ?? '') }}</strong></td>
                    <td>{{ e($check->category ?? '-') }}</td>
                    <td><span class="badge bg-{{ ($check->status ?? '') === 'pass' ? 'success' : (($check->status ?? '') === 'warning' ? 'warning' : 'danger') }}">{{ ucfirst($check->status ?? 'unknown') }}</span></td>
                    <td class="small">{{ $check->last_run_at ?? '' }}</td>
                    <td class="small">{{ e(Str::limit($check->details ?? '', 60)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-info">No compliance checks configured.</div>
@endif
@endsection