{{-- Request Triage - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'researchers'])@endsection
@section('title', 'Request Triage')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Request Triage</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-sort-amount-down text-primary me-2"></i>Request Triage</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card">
    <div class="card-body p-0">
        @if(!empty($requests))
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Request</th><th>Researcher</th><th>Type</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach($requests as $r)
                <tr>
                    <td><strong>{{ e($r->title ?? 'Request #' . $r->id) }}</strong></td>
                    <td>{{ e(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $r->request_type ?? '')) }}</span></td>
                    <td><span class="badge bg-{{ match($r->priority ?? '') { 'high' => 'danger', 'medium' => 'warning', default => 'secondary' } }}">{{ ucfirst($r->priority ?? 'normal') }}</span></td>
                    <td class="small">{{ $r->created_at ?? '' }}</td>
                    <td>
                        <form method="POST" class="d-inline">@csrf <input type="hidden" name="request_id" value="{{ $r->id }}">
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success"><i class="fas fa-check"></i></button>
                            <button type="submit" name="action" value="deny" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center py-4 text-muted">No pending requests.</div>
        @endif
    </div>
</div>
@endsection