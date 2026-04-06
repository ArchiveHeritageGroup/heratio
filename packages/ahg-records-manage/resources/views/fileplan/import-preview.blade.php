@extends('theme::layouts.1col')
@section('title', 'Import File Plan - Preview')
@section('body-class', 'admin records')

@section('title-block')
<h1>Import File Plan - Step 3: Preview</h1>
@endsection

@section('content')

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ $totalRows }}</div>
                <small class="text-muted">Total Rows</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ $maxDepth + 1 }}</div>
                <small class="text-muted">Levels Deep</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ count($validationErrors) }}</div>
                <small class="text-muted">Warnings</small>
            </div>
        </div>
    </div>
</div>

@if(!empty($validationErrors))
<div class="card mb-3">
    <div class="card-header bg-warning text-dark">Validation Warnings</div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($validationErrors as $err)
                <li class="{{ str_contains($err, 'Missing') ? 'text-danger' : 'text-warning' }}">{{ $err }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-header">Preview Tree (first 50 nodes)</div>
    <div class="card-body">
        @if(empty($previewNodes))
            <p class="text-muted">No nodes to preview.</p>
        @else
            <ul class="list-unstyled mb-0">
                @foreach($previewNodes as $pn)
                <li style="padding-left: {{ ($pn['depth'] ?? 0) * 20 }}px;" class="py-1">
                    <span class="badge bg-secondary">{{ $pn['code'] }}</span>
                    {{ $pn['title'] }}
                </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<form method="post" action="{{ route('records.fileplan.import.commit') }}">
    @csrf
    <input type="hidden" name="file_path" value="{{ $filePath }}">
    <input type="hidden" name="department" value="{{ $department }}">
    <input type="hidden" name="agency_code" value="{{ $agencyCode }}">
    @foreach($mapping as $key => $val)
        <input type="hidden" name="mapping[{{ $key }}]" value="{{ $val }}">
    @endforeach

    <div class="d-flex justify-content-between">
        <a href="{{ route('records.fileplan.import') }}" class="btn btn-secondary">Back</a>
        <button type="submit" class="btn btn-success" onclick="return confirm('Proceed with import? This will create {{ $totalRows }} nodes.');">
            Import {{ $totalRows }} Node(s)
        </button>
    </div>
</form>
@endsection
