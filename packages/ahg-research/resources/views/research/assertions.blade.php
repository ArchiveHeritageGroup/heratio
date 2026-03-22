{{-- Assertions - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Assertions')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Assertions</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-gavel text-primary me-2"></i>Assertions</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if(!empty($assertions))
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Claim</th><th>Source</th><th>Status</th><th>Confidence</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($assertions as $a)
                <tr>
                    <td><strong>{{ e(Str::limit($a->claim ?? '', 60)) }}</strong></td>
                    <td>{{ e($a->source_title ?? '-') }}</td>
                    <td><span class="badge bg-{{ ($a->status ?? '') === 'approved' ? 'success' : (($a->status ?? '') === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($a->status ?? 'pending') }}</span></td>
                    <td>{{ number_format(($a->confidence ?? 0) * 100) }}%</td>
                    <td class="small">{{ $a->created_at ?? '' }}</td>
                    <td><a href="{{ route('research.dashboard', ['view_assertion' => $a->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center py-4 text-muted"><i class="fas fa-gavel fa-2x mb-2 opacity-50"></i><p>No assertions found.</p></div>
        @endif
    </div>
</div>
@endsection