{{-- Batch Review Assertions - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Batch Review Assertions')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Assertions</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-tasks text-primary me-2"></i>Batch Review Assertions</h1>
    <span class="badge bg-secondary">{{ count($assertions ?? []) }} pending</span>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

@if(!empty($assertions))
<form method="POST">
    @csrf
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Assertion</th>
                        <th>Source</th>
                        <th>Researcher</th>
                        <th>Confidence</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($assertions as $a)
                    <tr>
                        <td><input type="checkbox" name="assertion_ids[]" value="{{ $a->id }}"></td>
                        <td>
                            <strong>{{ e($a->claim ?? '') }}</strong>
                            @if($a->evidence ?? false)<br><small class="text-muted">{{ e(Str::limit($a->evidence, 80)) }}</small>@endif
                        </td>
                        <td>{{ e($a->source_title ?? '-') }}</td>
                        <td>{{ e(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')) }}</td>
                        <td>
                            @php $conf = $a->confidence ?? 0; $cc = $conf >= 0.8 ? 'success' : ($conf >= 0.5 ? 'warning' : 'danger'); @endphp
                            <span class="badge bg-{{ $cc }}">{{ number_format($conf * 100) }}%</span>
                        </td>
                        <td class="small">{{ $a->created_at ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" name="batch_action" value="approve" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Approve Selected</button>
            <button type="submit" name="batch_action" value="reject" class="btn btn-danger btn-sm"><i class="fas fa-times me-1"></i>Reject Selected</button>
            <button type="submit" name="batch_action" value="flag" class="btn btn-warning btn-sm"><i class="fas fa-flag me-1"></i>Flag for Review</button>
        </div>
    </div>
</form>
<script>document.getElementById('selectAll')?.addEventListener('change', function() { document.querySelectorAll('input[name="assertion_ids[]"]').forEach(cb => cb.checked = this.checked); });</script>
@else
<div class="alert alert-info">No assertions pending review.</div>
@endif
@endsection