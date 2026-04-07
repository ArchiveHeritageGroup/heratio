{{-- Assertion Batch Review --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Batch Review Assertions')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Batch Review</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-tasks text-primary me-2"></i>Batch Review Assertions</h1>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-secondary">{{ count($assertions ?? []) }} proposed</span>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

@if(!empty($assertions) && count($assertions) > 0)
<form method="POST">
    @csrf
    <input type="hidden" name="form_action" value="batch_update">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Subject</th>
                            <th>Predicate</th>
                            <th>Object</th>
                            <th>Type</th>
                            <th>Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assertions as $a)
                        <tr>
                            <td><input type="checkbox" name="assertion_ids[]" value="{{ $a->id }}"></td>
                            <td>{{ e($a->subject_label ?? '') }}</td>
                            <td>{{ e($a->predicate ?? '') }}</td>
                            <td>{{ e($a->object_label ?? $a->object_value ?? '') }}</td>
                            <td><span class="badge bg-info">{{ ucfirst($a->assertion_type ?? '') }}</span></td>
                            <td>
                                @php $conf = $a->confidence ?? 0; $cc = $conf >= 80 ? 'success' : ($conf >= 50 ? 'warning' : 'danger'); @endphp
                                <span class="badge bg-{{ $cc }}">{{ $conf }}%</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 align-items-center">
            <label class="form-label mb-0 me-2">Set Status:</label>
            <select name="new_status" class="form-select form-select-sm" style="width:auto;" required>
                <option value="">-- Select --</option>
                <option value="verified">Verified</option>
                <option value="disputed">Disputed</option>
                <option value="rejected">Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Submit</button>
        </div>
    </div>
</form>
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="assertion_ids[]"]').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
</script>
@else
<div class="alert alert-info">No proposed assertions to review.</div>
@endif
@endsection
