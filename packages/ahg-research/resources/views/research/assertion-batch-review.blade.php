{{-- Assertion Batch Review — cloned from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Assertion Batch Review</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Assertion Batch Review <span class="badge bg-warning">{{ count($assertions) }} proposed</span></h1>
</div>

<form method="post" action="{{ route('research.assertionBatchReview', $project->id) }}">
    @csrf
    <input type="hidden" name="form_action" value="batch_update">

    <div class="card mb-3">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <div>
                <input type="checkbox" id="selectAll" class="form-check-input me-2">
                <label for="selectAll" class="form-check-label">{{ __('Select All') }}</label>
            </div>
            <div class="d-flex gap-2">
                <select name="new_status" class="form-select form-select-sm" style="width:auto;">
                    <option value="verified">{{ __('Verify') }}</option>
                    <option value="disputed">{{ __('Dispute') }}</option>
                    <option value="retracted">{{ __('Retract') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check-double me-1"></i>{{ __('Apply to Selected') }}</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:40px;"></th>
                    <th>{{ __('Subject') }}</th>
                    <th>{{ __('Predicate') }}</th>
                    <th>{{ __('Object') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Evidence') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($assertions as $a)
                <tr>
                    <td><input type="checkbox" name="assertion_ids[]" value="{{ $a->id }}" class="form-check-input assertion-cb"></td>
                    <td>
                        <small>
                            {{ e(($a->subject_type ?? '') . ':' . ($a->subject_id ?? '')) }}
                            @if(!empty($a->subject_label))<br><span class="text-muted">{{ e($a->subject_label) }}</span>@endif
                        </small>
                    </td>
                    <td><strong>{{ e($a->predicate ?? '') }}</strong></td>
                    <td>
                        <small>
                            {{ e(($a->object_type ?? '') . ':' . ($a->object_id ?? '')) }}
                            @if(!empty($a->object_label))<br><span class="text-muted">{{ e($a->object_label) }}</span>@endif
                        </small>
                    </td>
                    <td><span class="badge bg-light text-dark">{{ e($a->assertion_type ?? '') }}</span></td>
                    <td>{{ (int)($a->evidence_count ?? 0) }}</td>
                    <td><small>{{ $a->created_at ?? '' }}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success single-verify" data-id="{{ $a->id }}" title="{{ __('Verify') }}"><i class="fas fa-check"></i></button>
                            <button type="button" class="btn btn-outline-danger single-dispute" data-id="{{ $a->id }}" title="{{ __('Dispute') }}"><i class="fas fa-times"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.assertion-cb').forEach(function(cb) { cb.checked = checked; });
        });
    }

    // Single verify via form POST
    document.querySelectorAll('.single-verify').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("research.assertionBatchReview", $project->id) }}';
            form.innerHTML = '@csrf<input name="form_action" value="batch_update"><input name="assertion_ids[]" value="'+id+'"><input name="new_status" value="verified">';
            document.body.appendChild(form);
            form.submit();
        });
    });

    // Single dispute via form POST
    document.querySelectorAll('.single-dispute').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var reason = prompt('Reason for dispute (optional):');
            if (reason === null) return;
            var id = this.dataset.id;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("research.assertionBatchReview", $project->id) }}';
            form.innerHTML = '@csrf<input name="form_action" value="batch_update"><input name="assertion_ids[]" value="'+id+'"><input name="new_status" value="disputed">';
            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>
@endsection
