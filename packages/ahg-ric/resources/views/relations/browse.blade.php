@extends('theme::layouts.1col')
@section('title', 'RiC Relations')
@section('body-class', 'admin ric')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-link me-2"></i>RiC Relations</h1>
    <a href="{{ route('ric.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> {{ __('RiC Dashboard') }}</a>
</div>
<p class="text-muted small">Global view of every relation in the triple store. Relations are edited inline on individual entity show pages.</p>
@isset($sourceBanner)
    <div class="alert alert-info small py-1 px-2 mb-3"><i class="fas fa-info-circle me-1"></i>{{ $sourceBanner }}</div>
@endisset
<form method="get" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:500px">
        <input type="text" name="q" class="form-control" placeholder="{{ __('Search predicate / dropdown code / evidence…') }}" value="{{ $q ?? '' }}">
        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>
<table class="table table-sm table-striped table-hover">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Subject') }}</th>
            <th>{{ __('Predicate') }}</th>
            <th>{{ __('Object') }}</th>
            <th>{{ __('Dates') }}</th>
            <th>{{ __('Certainty') }}</th>
            <th>{{ __('Evidence') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr>
            <td><small class="text-muted">{{ $row->id }}</small></td>
            <td>
                <a href="#" class="text-decoration-none" onclick="return false;">
                    <span class="badge bg-secondary">{{ $row->subject_class ?: '?' }}</span>
                    #{{ $row->subject_id }}
                </a>
            </td>
            <td><code>{{ $row->rico_predicate }}</code><br><small class="text-muted">{{ $row->dropdown_code }}</small></td>
            <td>
                <span class="badge bg-info">{{ $row->object_class ?: '?' }}</span>
                #{{ $row->object_id }}
            </td>
            <td>{{ trim(($row->start_date ?: '') . ' – ' . ($row->end_date ?: ''), ' –') }}</td>
            <td>@if($row->certainty)<span class="badge bg-warning">{{ $row->certainty }}</span>@endif</td>
            <td><small>{{ Str::limit($row->evidence ?: '', 80) }}</small></td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-muted text-center">No relations found.</td></tr>
        @endforelse
    </tbody>
</table>
@php $lastPage = (int) ceil($total / $perPage); @endphp
@if($lastPage > 1)
<nav>
    <ul class="pagination pagination-sm">
        @for($p = 1; $p <= $lastPage; $p++)
        <li class="page-item {{ $p == $page ? 'active' : '' }}">
            <a class="page-link" href="?page={{ $p }}&q={{ urlencode($q ?? '') }}">{{ $p }}</a>
        </li>
        @endfor
    </ul>
</nav>
@endif
<p class="text-muted small">{{ $total }} relation{{ $total !== 1 ? 's' : '' }} total.</p>
@endsection
