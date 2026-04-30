@extends('theme::layouts.1col')
@section('title', 'File Plan')
@section('body-class', 'admin records')

@section('title-block')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0">{{ __('File Plan') }}</h1>
    <div>
        <a href="{{ route('records.fileplan.import') }}" class="btn btn-outline-primary btn-sm me-1">
            <i class="fas fa-file-import"></i> Import File Plan
        </a>
        <a href="{{ route('records.fileplan.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Create Node
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ number_format($stats['total_nodes']) }}</div>
                <small class="text-muted">Total Nodes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ count($stats['by_department']) }}</div>
                <small class="text-muted">Departments</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ number_format($stats['linked_records']) }}</div>
                <small class="text-muted">Linked Records</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ count($stats['by_type']) }}</div>
                <small class="text-muted">Node Types</small>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(empty($tree))
    <div class="alert alert-info">
        No file plan nodes found. <a href="{{ route('records.fileplan.create') }}">Create one</a> or
        <a href="{{ route('records.fileplan.import') }}">import a file plan</a>.
    </div>
@else
    <div class="card">
        <div class="card-body">
            <ul class="list-unstyled mb-0" id="fileplan-tree">
                @foreach($tree as $node)
                    @include('ahg-records::fileplan._tree-node', ['node' => $node, 'level' => 0])
                @endforeach
            </ul>
        </div>
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.fp-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var target = document.getElementById(this.getAttribute('data-target'));
            if (target) {
                target.classList.toggle('d-none');
                this.querySelector('i').classList.toggle('fa-caret-right');
                this.querySelector('i').classList.toggle('fa-caret-down');
            }
        });
    });
});
</script>
@endpush
@endsection
