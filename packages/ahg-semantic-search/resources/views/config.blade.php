{{--
  Semantic Search Settings
  Cloned from AtoM ahgSemanticSearchPlugin/modules/semanticSearchAdmin/templates/configSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', 'Semantic Search — Settings')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <a href="{{ route('semantic-search.index') }}" class="text-decoration-none text-muted">
                <i class="fas fa-brain me-2"></i>Semantic Search
            </a>
            <i class="fas fa-chevron-right mx-2 small text-muted"></i>
            Settings
        </h1>
    </div>

    @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('notice') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form method="POST" action="{{ route('semantic-search.config') }}">
        @csrf

        <div class="row">
            {{-- General Settings --}}
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>General Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_search_enabled"
                                   name="semantic_search_enabled" value="1"
                                   {{ ($config['semantic_search_enabled'] ?? '0') == '1' || ($config['semantic_search_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_search_enabled">
                                <strong>Enable Semantic Search</strong>
                            </label>
                            <div class="form-text">When enabled, search queries will be expanded with synonyms.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="semantic_expansion_limit">{{ __('Expansion Limit') }}</label>
                            <input type="number" class="form-control" id="semantic_expansion_limit"
                                   name="semantic_expansion_limit"
                                   value="{{ $config['semantic_expansion_limit'] ?? 5 }}" min="1" max="20">
                            <div class="form-text">Maximum number of synonyms per term.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="semantic_min_weight">{{ __('Minimum Weight') }}</label>
                            <input type="number" class="form-control" id="semantic_min_weight"
                                   name="semantic_min_weight"
                                   value="{{ $config['semantic_min_weight'] ?? 0.6 }}"
                                   min="0" max="1" step="0.1">
                            <div class="form-text">Minimum relevance weight for synonyms (0.0 - 1.0).</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_show_expansion"
                                   name="semantic_show_expansion" value="1"
                                   {{ ($config['semantic_show_expansion'] ?? '1') == '1' || ($config['semantic_show_expansion'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_show_expansion">
                                Show Expansion Info
                            </label>
                            <div class="form-text">Display which synonyms were used on search results page.</div>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="semantic_log_searches"
                                   name="semantic_log_searches" value="1"
                                   {{ ($config['semantic_log_searches'] ?? '1') == '1' || ($config['semantic_log_searches'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_log_searches">
                                Log Searches
                            </label>
                            <div class="form-text">Keep a log of expanded searches for analysis.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Data Sources --}}
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Data Sources</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_local_synonyms"
                                   name="semantic_local_synonyms" value="1"
                                   {{ ($config['semantic_local_synonyms'] ?? '1') == '1' || ($config['semantic_local_synonyms'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_local_synonyms">
                                <i class="fas fa-file-alt me-1 text-secondary"></i>Local Synonyms
                            </label>
                            <div class="form-text">Use locally defined archival, museum, and library terms.</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_wordnet_enabled"
                                   name="semantic_wordnet_enabled" value="1"
                                   {{ ($config['semantic_wordnet_enabled'] ?? '0') == '1' || ($config['semantic_wordnet_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_wordnet_enabled">
                                <i class="fas fa-cloud me-1 text-info"></i>WordNet (Datamuse API)
                            </label>
                            <div class="form-text">Fetch synonyms from WordNet via Datamuse API.</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_wikidata_enabled"
                                   name="semantic_wikidata_enabled" value="1"
                                   {{ ($config['semantic_wikidata_enabled'] ?? '0') == '1' || ($config['semantic_wikidata_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_wikidata_enabled">
                                <i class="fas fa-globe me-1 text-dark"></i>Wikidata
                            </label>
                            <div class="form-text">Fetch heritage and archival terms from Wikidata SPARQL.</div>
                        </div>

                        <hr>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="semantic_ollama_enabled"
                                   name="semantic_ollama_enabled" value="1"
                                   {{ ($config['semantic_ollama_enabled'] ?? '0') == '1' || ($config['semantic_ollama_enabled'] ?? '') === 'true' ? 'checked' : '' }}>
                            <label class="form-check-label" for="semantic_ollama_enabled">
                                <i class="fas fa-robot me-1 text-purple"></i>Ollama Embeddings
                            </label>
                            <div class="form-text">Use Ollama for vector embeddings and semantic similarity.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="semantic_ollama_endpoint">{{ __('Ollama Endpoint') }}</label>
                            <input type="url" class="form-control" id="semantic_ollama_endpoint"
                                   name="semantic_ollama_endpoint"
                                   value="{{ $config['semantic_ollama_endpoint'] ?? 'http://localhost:11434' }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="semantic_ollama_model">{{ __('Ollama Model') }}</label>
                            <select class="form-select" id="semantic_ollama_model" name="semantic_ollama_model">
                                @php
                                    $models = ['nomic-embed-text', 'mxbai-embed-large', 'all-minilm', 'snowflake-arctic-embed'];
                                    $current = $config['semantic_ollama_model'] ?? 'nomic-embed-text';
                                @endphp
                                @foreach($models as $model)
                                <option value="{{ $model }}" {{ $model === $current ? 'selected' : '' }}>
                                    {{ $model }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Elasticsearch Integration --}}
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fab fa-searchengin me-2"></i>Elasticsearch Integration</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="semantic_es_synonyms_path">{{ __('Synonyms File Path') }}</label>
                                    <input type="text" class="form-control" id="semantic_es_synonyms_path"
                                           name="semantic_es_synonyms_path"
                                           value="{{ $config['semantic_es_synonyms_path'] ?? '/etc/elasticsearch/synonyms/ahg_synonyms.txt' }}">
                                    <div class="form-text">Path where the Elasticsearch synonyms file will be exported.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Export Synonyms') }}</label>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary sync-btn" data-type="elasticsearch">
                                            <i class="fas fa-file-export me-1"></i>Export to Elasticsearch
                                        </button>
                                    </div>
                                    <div class="form-text">Generate synonyms file for Elasticsearch. Requires ES restart to apply.</div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong>
                            After exporting synonyms, you need to restart Elasticsearch for changes to take effect. Add the synonyms filter to your index settings.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('semantic-search.index') }}" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Save Settings
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sync-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = this.dataset.type;
            btn.disabled = true;
            var originalHtml = btn.innerHTML;
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
                alert(data.message || 'Export complete');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            })
            .catch(error => {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    });
});
</script>
@endsection
