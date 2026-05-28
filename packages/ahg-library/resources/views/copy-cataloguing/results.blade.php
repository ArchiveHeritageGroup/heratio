@extends('theme::layouts.1col')
@section('title', 'Copy Cataloguing Results')
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.marc-copy-cataloguing') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">Z39.50 Search Results</h2>
            <p class="text-muted small mb-0">
                Target: <strong>{{ $targetName }}</strong> —
                Query: <em>"{{ $query }}"</em> —
                {{ $count }} record(s) found
            </p>
        </div>
    </div>

    @if($count === 0)
        <div class="alert alert-warning">
            <i class="fas fa-search me-2"></i>No records returned from
            <strong>{{ $targetName }}</strong> for query <em>"{{ $query }}"</em>.
            Verify the query format and target connectivity.
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Records</span>
                <span class="badge bg-secondary">{{ count($records) }} shown</span>
            </div>
            <div class="card-body p-0">
                @foreach($records as $idx => $rec)
                    @php $recId = 'rec_' . $idx; @endphp
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading_{{ $recId }}">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#{{ $recId }}"
                                    aria-expanded="false" aria-controls="{{ $recId }}">
                                <div class="me-auto">
                                    <span class="badge bg-secondary me-2">#{{ $idx + 1 }}</span>
                                    <span class="fw-semibold">{{ $rec['title'] ?: '(no title)' }}</span>
                                    @if($rec['author'])
                                        <span class="text-muted ms-3">/ {{ $rec['author'] }}</span>
                                    @endif
                                    @if($rec['isbn'])
                                        <span class="small text-muted ms-3">ISBN {{ $rec['isbn'] }}</span>
                                    @endif
                                </div>
                            </button>
                        </h2>
                        <div id="{{ $recId }}" class="accordion-collapse collapse"
                             aria-labelledby="heading_{{ $recId }}">
                            <div class="accordion-body">
                                <dl class="row small mb-3">
                                    <dt class="col-md-3 text-muted">Control (001)</dt>
                                    <dd class="col-md-9 font-monospace"><code>{{ $rec['control_001'] ?: '—' }}</code></dd>
                                    <dt class="col-md-3 text-muted">ISBN</dt>
                                    <dd class="col-md-9">{{ $rec['isbn'] ?: '—' }}</dd>
                                    <dt class="col-md-3 text-muted">ISSN</dt>
                                    <dd class="col-md-9">{{ $rec['issn'] ?: '—' }}</dd>
                                    <dt class="col-md-3 text-muted">Publisher</dt>
                                    <dd class="col-md-9">{{ $rec['publisher'] ?: '—' }}</dd>
                                    <dt class="col-md-3 text-muted">Date</dt>
                                    <dd class="col-md-9">{{ $rec['pub_date'] ?: '—' }}</dd>
                                </dl>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('library.marc-copy-cataloguing.preview') }}">
                                        @csrf
                                        <input type="hidden" name="marc_content" value="{{ base64_encode($rec['marc_content']) }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm me-2">
                                            <i class="fas fa-eye me-1"></i>Preview & Import
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
