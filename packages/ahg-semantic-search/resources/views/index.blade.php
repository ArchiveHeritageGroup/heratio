{{--
  Semantic Search Dashboard
  Cloned from AtoM ahgSemanticSearchPlugin/modules/semanticSearchAdmin/templates/indexSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', 'Semantic Search')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2"><i class="fas fa-brain me-2"></i>Semantic Search</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('semantic-search.config') }}" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i>Settings
            </a>
        </div>
    </div>

    @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('notice') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Status Banner --}}
    @php $enabled = ($config['semantic_search_enabled'] ?? '0') == '1' || ($config['semantic_search_enabled'] ?? '') === 'true'; @endphp
    <div class="alert {{ $enabled ? 'alert-success' : 'alert-warning' }} mb-4">
        <div class="d-flex align-items-center">
            <i class="fas {{ $enabled ? 'fa-check-circle' : 'fa-exclamation-triangle' }} fa-2x me-3"></i>
            <div>
                <strong>{{ $enabled ? 'Semantic Search is Active' : 'Semantic Search is Disabled' }}</strong>
                <p class="mb-0 small">
                    {{ $enabled
                        ? 'Search queries are being expanded with synonyms and related terms.'
                        : 'Enable semantic search in settings to expand search queries.' }}
                </p>
            </div>
            @unless($enabled)
            <a href="{{ route('semantic-search.config') }}" class="btn btn-warning ms-auto">
                Enable Now
            </a>
            @endunless
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Total Terms</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_terms'] ?? 0) }}</h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <span class="badge bg-info">{{ $stats['active_terms'] ?? 0 }} active</span>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('semantic-search.terms') }}" class="text-primary">
                        Browse terms <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Search Logs</h6>
                            <h2 class="mb-0">{{ number_format($stats['search_logs'] ?? 0) }}</h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('semantic-search.searchLogs') }}" class="text-success">
                        View logs <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Sync Logs</h6>
                            <h2 class="mb-0">{{ number_format($stats['sync_logs'] ?? 0) }}</h2>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-sync fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('semantic-search.syncLogs') }}" class="text-info">
                        View logs <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Data Sources</h6>
                            <h2 class="mb-0">
                                @php
                                    $sources = 0;
                                    if (($config['semantic_local_synonyms'] ?? '1') == '1' || ($config['semantic_local_synonyms'] ?? '') === 'true') $sources++;
                                    if (($config['semantic_wordnet_enabled'] ?? '0') == '1' || ($config['semantic_wordnet_enabled'] ?? '') === 'true') $sources++;
                                    if (($config['semantic_wikidata_enabled'] ?? '0') == '1' || ($config['semantic_wikidata_enabled'] ?? '') === 'true') $sources++;
                                    if (($config['semantic_ollama_enabled'] ?? '0') == '1' || ($config['semantic_ollama_enabled'] ?? '') === 'true') $sources++;
                                @endphp
                                {{ $sources }} / 4
                            </h2>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-database fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        active sources
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions & Test --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100 sync-btn" data-type="local">
                                <i class="fas fa-file-import me-1"></i>Import Local
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-info w-100 sync-btn" data-type="wordnet">
                                <i class="fas fa-cloud-download-alt me-1"></i>Sync WordNet
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-success w-100 sync-btn" data-type="elasticsearch">
                                <i class="fas fa-file-export me-1"></i>Export to ES
                            </button>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('semantic-search.term.add') }}" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-plus me-1"></i>Add Term
                            </a>
                        </div>
                    </div>
                    <div id="sync-result" class="mt-3" style="display: none;">
                        <div class="alert mb-0" role="alert"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Test Query Expansion</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" id="test-query" class="form-control" placeholder="Enter a search term...">
                        <button class="btn btn-primary" type="button" id="test-expand-btn">
                            <i class="fas fa-search me-1"></i>Expand
                        </button>
                    </div>
                    <div id="expansion-result" style="display: none;">
                        <h6>Expansions:</h6>
                        <div id="expansion-terms"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync buttons
    document.querySelectorAll('.sync-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = this.dataset.type;
            var resultDiv = document.getElementById('sync-result');
            var alertDiv = resultDiv.querySelector('.alert');
            var originalHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            fetch('{{ route("semantic-search.runSync") }}?type=' + type, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.style.display = 'block';
                alertDiv.className = 'alert mb-0 alert-' + (data.success ? 'success' : 'danger');
                alertDiv.innerHTML = data.message || 'Sync completed';
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            })
            .catch(error => {
                resultDiv.style.display = 'block';
                alertDiv.className = 'alert mb-0 alert-danger';
                alertDiv.innerHTML = 'Error: ' + error.message;
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    });

    // Test expansion
    document.getElementById('test-expand-btn').addEventListener('click', function() {
        var query = document.getElementById('test-query').value;
        if (!query) return;

        var resultDiv = document.getElementById('expansion-result');
        var termsDiv = document.getElementById('expansion-terms');

        fetch('{{ route("semantic-search.testExpand") }}?query=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            resultDiv.style.display = 'block';
            if (data.success && data.expansions && Object.keys(data.expansions).length > 0) {
                var html = '';
                for (var term in data.expansions) {
                    html += '<div class="mb-2"><strong>' + term + '</strong> &rarr; ';
                    html += data.expansions[term].map(function(s) {
                        return '<span class="badge bg-secondary">' + s + '</span>';
                    }).join(' ');
                    html += '</div>';
                }
                termsDiv.innerHTML = html;
            } else {
                termsDiv.innerHTML = '<div class="text-muted">No expansions found for this query.</div>';
            }
        });
    });

    // Allow Enter key for test
    document.getElementById('test-query').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('test-expand-btn').click();
        }
    });
});
</script>
@endsection
