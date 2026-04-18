{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Semantic Search - Add Term')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <a href="{{ route('semantic-search.index') }}" class="text-decoration-none text-muted">
                <i class="fas fa-brain me-2"></i>Semantic Search
            </a>
            <i class="fas fa-chevron-right mx-2 small text-muted"></i>
            <a href="{{ route('semantic-search.terms') }}" class="text-decoration-none text-muted">
                Terms
            </a>
            <i class="fas fa-chevron-right mx-2 small text-muted"></i>
            Add Term
        </h1>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('semantic-search.term.add') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="term">Term <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="term" name="term" required
                                   placeholder="e.g., archive, manuscript, photograph">
                            <div class="form-text">The main term to add to the thesaurus.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="domain">Domain</label>
                            <select class="form-select" id="domain" name="domain">
                                <option value="general">General</option>
                                <option value="archival">Archival</option>
                                <option value="museum">Museum</option>
                                <option value="library">Library</option>
                                <option value="south_african">South African</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="relationship">Relationship Type</label>
                            <select class="form-select" id="relationship" name="relationship">
                                <option value="exact">Exact (synonym)</option>
                                <option value="related">Related</option>
                                <option value="broader">Broader</option>
                                <option value="narrower">Narrower</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="weight">Weight</label>
                            <input type="number" class="form-control" id="weight" name="weight"
                                   value="0.8" min="0" max="1" step="0.1">
                            <div class="form-text">Relevance weight (0.0 - 1.0). Higher = more relevant.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="synonyms">Synonyms</label>
                            <textarea class="form-control" id="synonyms" name="synonyms" rows="10"
                                      placeholder="Enter one synonym per line..."></textarea>
                            <div class="form-text">Enter each synonym on a new line.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('semantic-search.terms') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Term
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
