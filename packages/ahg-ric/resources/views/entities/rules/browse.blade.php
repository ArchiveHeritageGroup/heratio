@extends('theme::layouts.1col')
@section('title', 'RiC Rules & Mandates')
@section('body-class', 'admin ric')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-gavel me-2"></i>RiC Rules & Mandates</h1>
    <a href="{{ route('ric.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> RiC Dashboard</a>
</div>
<form method="get" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:400px">
        <input type="text" name="search" class="form-control" placeholder="Search rules..." value="{{ $params['search'] ?? '' }}">
        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>
<table class="table table-sm table-striped table-hover">
    <thead><tr><th>Title</th><th>Type</th><th>Jurisdiction</th><th>Dates</th><th>Created</th></tr></thead>
    <tbody>
        @forelse($result->items as $item)
        <tr>
            <td><a href="{{ route('ric.entities.show', ['rules', $item->slug]) }}">{{ $item->title ?? 'Unnamed' }}</a></td>
            <td><span class="badge bg-warning text-dark">{{ $item->type_id ?? '' }}</span></td>
            <td>{{ $item->jurisdiction ?? '' }}</td>
            <td>{{ collect([$item->start_date, $item->end_date])->filter()->implode(' - ') }}</td>
            <td>{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '' }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-muted">No rules found</td></tr>
        @endforelse
    </tbody>
</table>
@if($result->last_page > 1)
<nav><ul class="pagination pagination-sm">
    @for($p = 1; $p <= $result->last_page; $p++)
    <li class="page-item {{ $p == $result->page ? 'active' : '' }}"><a class="page-link" href="?page={{ $p }}&search={{ $params['search'] ?? '' }}">{{ $p }}</a></li>
    @endfor
</ul></nav>
@endif
<p class="text-muted small">{{ $result->total }} rule{{ $result->total !== 1 ? 's' : '' }} total</p>
@endsection
