@extends('theme::layouts.1col')
@section('title', 'Copy Cataloguing')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left me-1"></i>MARC Editor
            </a>
            <div>
                <h2 class="mb-0">Copy Cataloguing</h2>
                <span class="badge bg-info text-dark mt-1">Z39.50</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error') || isset($searchError))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') ?? $searchError }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            {{-- Target selector + search form --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Z39.50 Search</h5>
                        <a href="{{ route('library.copy-cataloguing-targets') }}"
                           class="btn btn-sm btn-light">
                            <i class="fas fa-server me-1"></i>Targets
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('library.copy-cataloguing-search') }}">
                        <div class="mb-3">
                            <label for="target_id" class="form-label">Target <span class="text-danger">*</span></label>
                            <select name="target_id" id="target_id" class="form-select" required>
                                <option value="">— select target —</option>
                                @foreach($targets as $t)
                                    <option value="{{ $t->id }}"
                                            {{ (request('target_id') == $t->id || (isset($targetId) && $targetId == $t->id)) ? 'selected' : '' }}>
                                        {{ $t->name }} ({{ $t->host }}:{{ $t->port }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="query" class="form-label">Query <span class="text-danger">*</span></label>
                            <input type="text" name="query" id="query" class="form-control"
                                   value="{{ $query ?? '' }}"
                                   placeholder="isbn=9780123456 or title=python programming">
                            <div class="form-text small text-muted">
                                Use field prefixes: <code>title=</code>, <code>author=</code>,
                                <code>isbn=</code>, <code>issn=</code>, <code>subject=</code>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </form>
                </div>
            </div>

            {{-- Quick targets info --}}
            <div class="card shadow-sm">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h5 class="mb-0">Available Targets</h5>
                </div>
                <div class="card-body p-0">
                    @if($targets->isEmpty())
                        <p class="text-muted small p-3 mb-0">
                            No active targets. <a href="{{ route('library.copy-cataloguing-targets') }}">Add one.</a>
                        </p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Target</th><th>Host</th></tr>
                            </thead>
                            <tbody>
                                @foreach($targets as $t)
                                    <tr>
                                        <td>{{ $t->name }}</td>
                                        <td><small class="text-muted">{{ $t->host }}:{{ $t->port }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            {{-- Search results --}}
            @if(isset($records) && !empty($records))
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-list me-2"></i>Search Results
                        </span>
                        <span class="badge bg-secondary">{{ $recordCount ?? count($records) }} record(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>ISBN</th>
                                    <th>ISSN</th>
                                    <th>Publisher</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($records as $rec)
                                    <tr>
                                        <td>{{ $rec['title'] ?? '' }}</td>
                                        <td>{{ $rec['author'] ?? '' }}</td>
                                        <td>{{ $rec['isbn'] ?? '' }}</td>
                                        <td>{{ $rec['issn'] ?? '' }}</td>
                                        <td>{{ $rec['publisher'] ?? '' }}</td>
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('library.copy-cataloguing-import') }}"
                                                  class="d-inline">
                                                @csrf
                                                <input type="hidden" name="marc_content"
                                                       value="{{ $rec['marc_content'] }}">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-file-import me-1"></i>Import
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif(isset($searchError))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Search failed: {{ $searchError }}
                </div>
            @else
                <div class="alert alert-light">
                    <i class="fas fa-info-circle me-2"></i>
                    Select a Z39.50 target and enter a query to search remote library catalogues.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
