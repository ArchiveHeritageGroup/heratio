@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Bibliographies</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-book text-primary me-2"></i>My Bibliographies</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBibModal">
        <i class="fas fa-plus me-1"></i> {{ __('New Bibliography') }}
    </button>
</div>

@if(!empty($bibliographies))
    <div class="row">
        @foreach($bibliographies as $bib)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="{{ route('research.viewBibliography', $bib->id) }}" class="text-decoration-none">
                                {{ $bib->name }}
                            </a>
                        </h5>
                        @if($bib->description ?? null)
                            <p class="card-text text-muted small">{{ \Illuminate\Support\Str::limit($bib->description, 100) }}</p>
                        @endif
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-secondary">{{ $bib->entry_count ?? 0 }} entries</span>
                            <small class="text-muted">{{ ucfirst($bib->citation_style ?? 'chicago') }} style</small>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('research.viewBibliography', $bib->id) }}?export=ris" class="btn btn-outline-secondary" title="{{ __('Export RIS') }}">RIS</a>
                            <a href="{{ route('research.viewBibliography', $bib->id) }}?export=bibtex" class="btn btn-outline-secondary" title="{{ __('Export BibTeX') }}">BibTeX</a>
                            <a href="{{ route('research.viewBibliography', $bib->id) }}?export=zotero" class="btn btn-outline-secondary" title="{{ __('Export Zotero') }}">Zotero</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
            <h5>{{ __('No Bibliographies Yet') }}</h5>
            <p class="text-muted">Create a bibliography to organize your research citations.</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBibModal">
                <i class="fas fa-plus me-1"></i> {{ __('Create Bibliography') }}
            </button>
        </div>
    </div>
@endif

{{-- Create Bibliography Modal --}}
<div class="modal fade" id="createBibModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Create Bibliography') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Name *') }}</label>
                        <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g., Thesis Bibliography') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Citation Style') }}</label>
                        <select name="citation_style" class="form-select">
                            <option value="chicago">{{ __('Chicago') }}</option>
                            <option value="mla">{{ __('MLA') }}</option>
                            <option value="apa">{{ __('APA') }}</option>
                            <option value="harvard">{{ __('Harvard') }}</option>
                            <option value="turabian">{{ __('Turabian') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i> {{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
