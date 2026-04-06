@extends('theme::layouts.1col')
@section('title', 'Import Result - Session #' . $session->id)
@section('body-class', 'admin records')

@section('title-block')
<h1>Import Result - Session #{{ $session->id }}</h1>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ $session->imported_nodes }}</div>
                <small class="text-muted">Imported</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ $session->total_nodes }}</div>
                <small class="text-muted">Total Rows</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold">{{ $session->linked_records }}</div>
                <small class="text-muted">Linked Records</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <span class="badge fs-6 {{ $session->status === 'completed' ? 'bg-success' : ($session->status === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}">
                    {{ ucfirst($session->status) }}
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Session Details</div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th style="width:180px;">Source Type</th><td>{{ ucfirst($session->source_type) }}</td></tr>
                <tr><th>Source File</th><td>{{ $session->source_filename ?: '-' }}</td></tr>
                <tr><th>Department</th><td>{{ $session->department ?: '-' }}</td></tr>
                <tr><th>Agency Code</th><td>{{ $session->agency_code ?: '-' }}</td></tr>
                <tr><th>Started</th><td>{{ $session->created_at }}</td></tr>
                <tr><th>Completed</th><td>{{ $session->completed_at ?: 'In progress' }}</td></tr>
                @if($session->column_mapping_json)
                <tr><th>Column Mapping</th><td>
                    @php $colMap = json_decode($session->column_mapping_json, true); @endphp
                    @if($colMap)
                        @foreach($colMap as $field => $col)
                            <span class="badge bg-secondary me-1">{{ $field }}: {{ $col }}</span>
                        @endforeach
                    @endif
                </td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@if(!empty($errors))
<div class="card mb-3">
    <div class="card-header bg-danger text-white">Errors ({{ count($errors) }})</div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($errors as $err)
                <li class="text-danger">{{ $err }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="d-flex justify-content-between">
    <div>
        <a href="{{ route('records.fileplan.index') }}" class="btn btn-primary me-1">View File Plan</a>
        <a href="{{ route('records.fileplan.import') }}" class="btn btn-outline-primary">New Import</a>
    </div>
    <div>
        @if($session->status === 'completed')
            <form method="post" action="{{ route('records.fileplan.import.link', $session->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success" onclick="return confirm('Link records matching file plan codes?');">
                    Link Records
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
