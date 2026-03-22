{{-- Custody Chain - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])
@endsection
@section('title', 'Custody Chain')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.retrievalQueue') }}">Retrieval Queue</a></li><li class="breadcrumb-item active">Custody Chain</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-link text-primary me-2"></i>Chain of Custody</h1>
<div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-box me-2"></i>Item Details</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ e($item->title ?? 'Item #' . ($item->id ?? '')) }}</dd>
            <dt class="col-sm-3">Identifier</dt><dd class="col-sm-9">{{ e($item->identifier ?? '-') }}</dd>
            <dt class="col-sm-3">Current Location</dt><dd class="col-sm-9">{{ e($item->current_location ?? '-') }}</dd>
            <dt class="col-sm-3">Current Holder</dt><dd class="col-sm-9">{{ e($item->current_holder ?? '-') }}</dd>
        </dl>
    </div>
</div>
<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Custody History</h5></div>
    <div class="card-body p-0">
        @if(!empty($chain))
        <table class="table table-striped mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Action</th><th>From</th><th>To</th><th>Staff</th><th>Notes</th></tr></thead>
            <tbody>
                @foreach($chain as $entry)
                <tr>
                    <td class="small">{{ $entry->created_at ?? '' }}</td>
                    <td><span class="badge bg-{{ match($entry->action ?? '') { 'checkout' => 'primary', 'return' => 'success', 'transfer' => 'info', default => 'secondary' } }}">{{ ucfirst($entry->action ?? '') }}</span></td>
                    <td>{{ e($entry->from_location ?? '-') }}</td>
                    <td>{{ e($entry->to_location ?? '-') }}</td>
                    <td>{{ e($entry->staff_name ?? '-') }}</td>
                    <td class="small">{{ e(Str::limit($entry->notes ?? '', 40)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center py-4 text-muted">No custody history.</div>
        @endif
    </div>
</div>
@endsection