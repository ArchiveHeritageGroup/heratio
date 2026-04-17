{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plansailingisystems
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Batch Mint DOIs')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-plus me-2"></i>Batch Mint DOIs</h1>
            <p class="text-muted">Queue multiple records for DOI minting</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('doi.index') }}" class="btn atom-btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="post" action="{{ $formAction ?? route('doi.batch-mint') }}">
        @csrf
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Records Without DOIs</h5>
                <div>
                    <button type="button" id="doi-select-all" class="btn btn-sm atom-btn-outline-secondary">
                        <i class="fas fa-check-square me-1"></i> Select All
                    </button>
                    <button type="button" id="doi-deselect-all" class="btn btn-sm atom-btn-outline-secondary">
                        <i class="fas fa-square me-1"></i> Deselect All
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                @if ($records->isEmpty())
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                        <p>All records have DOIs!</p>
                        <a href="{{ route('doi.browse') }}" class="btn atom-btn-outline-primary">View All DOIs</a>
                    </div>
                @else
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="doi-check-all">
                                </th>
                                <th>Title</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="object_ids[]" value="{{ $record->id }}"
                                               class="form-check-input doi-record-checkbox">
                                    </td>
                                    <td>{{ $record->title ?? 'Untitled' }}</td>
                                    <td class="text-muted">{{ $record->id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="text-muted p-3 small">
                        Showing up to 100 records. Use CLI for larger batches: <code>php artisan doi:mint --all --limit=500</code>
                    </div>
                @endif
            </div>
        </div>

        @if (!$records->isEmpty())
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <label class="form-label">Initial DOI State</label>
                            <select name="state" class="form-select" style="max-width: 200px;">
                                <option value="findable">Findable (Recommended)</option>
                                <option value="registered">Registered</option>
                                <option value="draft">Draft</option>
                            </select>
                            <div class="form-text">
                                Findable = publicly discoverable, Registered = resolvable but not indexed, Draft = not yet active
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-1"></i> Queue Selected for Minting
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll    = document.getElementById('doi-check-all');
    var selectAll   = document.getElementById('doi-select-all');
    var deselectAll = document.getElementById('doi-deselect-all');
    var boxes       = document.querySelectorAll('.doi-record-checkbox');
    if (checkAll) {
        checkAll.addEventListener('change', function () { boxes.forEach(function (cb) { cb.checked = checkAll.checked; }); });
    }
    if (selectAll) {
        selectAll.addEventListener('click', function () { boxes.forEach(function (cb) { cb.checked = true; }); if (checkAll) { checkAll.checked = true; } });
    }
    if (deselectAll) {
        deselectAll.addEventListener('click', function () { boxes.forEach(function (cb) { cb.checked = false; }); if (checkAll) { checkAll.checked = false; } });
    }
});
</script>
@endsection
