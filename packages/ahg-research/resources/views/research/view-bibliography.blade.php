@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'bibliographies'])@endsection
@section('title', $bibliography->name ?? 'Bibliography')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.bibliographies') }}">Bibliographies</a></li>
        <li class="breadcrumb-item active">{{ e($bibliography->name) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2">{{ e($bibliography->name) }}</h1>
        @if($bibliography->description ?? null)
            <p class="text-muted">{{ e($bibliography->description) }}</p>
        @endif
        <span class="badge bg-secondary">{{ count($entries) }} entries</span>
        <span class="badge bg-light text-dark">{{ ucfirst($bibliography->citation_style ?? 'chicago') }} style</span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importBibliographyModal">
            <i class="fas fa-upload me-1"></i>Import
        </button>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('research.viewBibliography', $bibliography->id) }}?export=ris">RIS (EndNote, Zotero)</a></li>
                <li><a class="dropdown-item" href="{{ route('research.viewBibliography', $bibliography->id) }}?export=bibtex">BibTeX (LaTeX)</a></li>
                <li><a class="dropdown-item" href="{{ route('research.viewBibliography', $bibliography->id) }}?export=csv">CSV</a></li>
            </ul>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editBibModal"><i class="fas fa-edit"></i></button>
    </div>
</div>

<div class="row">
    {{-- Entries (left) --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Entries</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                    <i class="fas fa-plus me-1"></i>Add Entry
                </button>
            </div>
            <div class="card-body p-0">
                @if(count($entries) > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($entries as $entry)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>{{ e($entry->title ?: 'Untitled') }}</strong>
                                        @if($entry->authors)
                                            <br><small class="text-muted">{{ e($entry->authors) }}</small>
                                        @endif
                                        @if($entry->date)
                                            <span class="ms-2 badge bg-light text-dark">{{ $entry->date }}</span>
                                        @endif
                                        @if($entry->entry_type ?? null)
                                            <span class="badge bg-secondary ms-1">{{ ucfirst($entry->entry_type) }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-secondary edit-entry-btn"
                                            data-id="{{ $entry->id }}"
                                            data-title="{{ e($entry->title ?? '') }}"
                                            data-authors="{{ e($entry->authors ?? '') }}"
                                            data-date="{{ e($entry->date ?? '') }}"
                                            data-container_title="{{ e($entry->container_title ?? '') }}"
                                            data-volume="{{ e($entry->volume ?? '') }}"
                                            data-pages="{{ e($entry->pages ?? '') }}"
                                            data-doi="{{ e($entry->doi ?? '') }}"
                                            data-url="{{ e($entry->url ?? '') }}"
                                            data-entry_type="{{ e($entry->entry_type ?? 'book') }}"
                                            data-notes="{{ e($entry->notes ?? '') }}"
                                            title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove?')">
                                            @csrf
                                            <input type="hidden" name="form_action" value="remove_entry">
                                            <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                </div>
                                @php
                                    $parts = [];
                                    if ($entry->container_title ?? null) $parts[] = '<em>' . e($entry->container_title) . '</em>';
                                    if ($entry->volume ?? null) $parts[] = 'vol. ' . e($entry->volume);
                                    if ($entry->pages ?? null) $parts[] = 'pp. ' . e($entry->pages);
                                    if ($entry->doi ?? null) $parts[] = 'DOI: ' . e($entry->doi);
                                @endphp
                                @if(!empty($parts))
                                    <div class="mt-1 small text-muted fst-italic">{!! implode(', ', $parts) !!}</div>
                                @endif
                                @if($entry->notes ?? null)
                                    <div class="mt-1 small"><i class="fas fa-sticky-note me-1 text-warning"></i>{{ e(\Illuminate\Support\Str::limit($entry->notes, 100)) }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-list fa-2x mb-2"></i>
                        <p>No entries yet</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal">Add your first entry</button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sidebar (right) --}}
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About</h6></div>
            <div class="card-body">
                <p class="mb-2"><strong>Created:</strong> {{ date('M j, Y', strtotime($bibliography->created_at)) }}</p>
                <p class="mb-0"><strong>Updated:</strong> {{ date('M j, Y', strtotime($bibliography->updated_at ?? $bibliography->created_at)) }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6></div>
            <div class="card-body">
                <a href="{{ route('research.bibliographies') }}" class="btn btn-outline-secondary w-100 mb-2"><i class="fas fa-arrow-left me-1"></i>Back to Bibliographies</a>
                <form method="POST" onsubmit="return confirm('Delete this bibliography and all entries?')">
                    @csrf
                    <input type="hidden" name="form_action" value="delete">
                    <button type="submit" class="btn btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Delete Bibliography</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Add Entry Modal --}}
<div class="modal fade" id="addEntryModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="add_entry">
    <div class="modal-header"><h5 class="modal-title">Add Bibliography Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title *</label><input type="text" class="form-control" name="title" required></div>
        <div class="row">
            <div class="col-md-8"><div class="mb-3"><label class="form-label">Authors</label><input type="text" class="form-control" name="authors" placeholder="Last, First; Last, First"></div></div>
            <div class="col-md-4"><div class="mb-3"><label class="form-label">Year</label><input type="text" class="form-control" name="year" placeholder="e.g. 2024"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">Publication</label><input type="text" class="form-control" name="publication"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Volume</label><input type="text" class="form-control" name="volume"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Pages</label><input type="text" class="form-control" name="pages" placeholder="e.g. 1-25"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">DOI</label><input type="text" class="form-control" name="doi" placeholder="10.xxxx/xxxxx"></div></div>
            <div class="col-md-6"><div class="mb-3"><label class="form-label">URL</label><input type="url" class="form-control" name="url" placeholder="https://..."></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">Entry Type</label>
                <select name="entry_type" class="form-select">
                    <option value="book">Book</option><option value="article">Article</option><option value="chapter">Chapter</option>
                    <option value="website">Website</option><option value="thesis">Thesis</option><option value="archival">Archival</option><option value="other">Other</option>
                </select></div>
            </div>
            <div class="col-md-6"><div class="mb-3"><label class="form-label">Archive Item ID</label><input type="number" class="form-control" name="object_id" placeholder="Optional — link to archive item"></div></div>
        </div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Entry</button></div>
    </form>
</div></div></div>

{{-- Edit Entry Modal --}}
<div class="modal fade" id="editEntryModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="edit_entry"><input type="hidden" name="entry_id" id="editEntryId">
    <div class="modal-header"><h5 class="modal-title">Edit Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title *</label><input type="text" class="form-control" name="title" id="editTitle" required></div>
        <div class="row">
            <div class="col-md-8"><div class="mb-3"><label class="form-label">Authors</label><input type="text" class="form-control" name="authors" id="editAuthors"></div></div>
            <div class="col-md-4"><div class="mb-3"><label class="form-label">Year</label><input type="text" class="form-control" name="year" id="editDate"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">Publication</label><input type="text" class="form-control" name="publication" id="editContainerTitle"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Volume</label><input type="text" class="form-control" name="volume" id="editVolume"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Pages</label><input type="text" class="form-control" name="pages" id="editPages"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">DOI</label><input type="text" class="form-control" name="doi" id="editDoi"></div></div>
            <div class="col-md-6"><div class="mb-3"><label class="form-label">URL</label><input type="url" class="form-control" name="url" id="editUrl"></div></div>
        </div>
        <div class="mb-3"><label class="form-label">Entry Type</label>
            <select name="entry_type" id="editEntryType" class="form-select">
                <option value="book">Book</option><option value="article">Article</option><option value="chapter">Chapter</option>
                <option value="website">Website</option><option value="thesis">Thesis</option><option value="archival">Archival</option><option value="other">Other</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button></div>
    </form>
</div></div></div>

{{-- Edit Bibliography Modal --}}
<div class="modal fade" id="editBibModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="update">
    <div class="modal-header"><h5 class="modal-title">Edit Bibliography</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name *</label><input type="text" class="form-control" name="name" value="{{ e($bibliography->name) }}" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2">{{ e($bibliography->description ?? '') }}</textarea></div>
        <div class="mb-3"><label class="form-label">Citation Style</label>
            <select name="citation_style" class="form-select">
                @foreach(['chicago', 'apa', 'mla', 'harvard', 'ieee', 'vancouver'] as $s)
                <option value="{{ $s }}" {{ ($bibliography->citation_style ?? 'chicago') === $s ? 'selected' : '' }}>{{ strtoupper($s) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button></div>
    </form>
</div></div></div>

{{-- Import Modal --}}
<div class="modal fade" id="importBibliographyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="form_action" value="import">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-upload me-2"></i>Import Citations</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Format *</label>
            <select name="format" class="form-select" required>
                <option value="bibtex">BibTeX (.bib)</option>
                <option value="ris">RIS (.ris)</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Upload File</label>
            <input type="file" name="import_file" class="form-control" accept=".bib,.ris,.txt">
        </div>
        <div class="text-center text-muted my-2">&mdash; or &mdash;</div>
        <div class="mb-3">
            <label class="form-label">Paste Content</label>
            <textarea name="import_content" class="form-control" rows="6" placeholder="Paste BibTeX or RIS content here..."></textarea>
        </div>
        <div class="alert alert-info small mb-0"><i class="fas fa-info-circle me-1"></i>Entries will be added. Duplicate titles will be skipped.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i>Import</button></div>
    </form>
</div></div></div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-entry-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = this.dataset;
            document.getElementById('editEntryId').value = d.id;
            document.getElementById('editTitle').value = d.title;
            document.getElementById('editAuthors').value = d.authors;
            document.getElementById('editDate').value = d.date;
            document.getElementById('editContainerTitle').value = d.container_title;
            document.getElementById('editVolume').value = d.volume;
            document.getElementById('editPages').value = d.pages;
            document.getElementById('editDoi').value = d.doi;
            document.getElementById('editUrl').value = d.url;
            document.getElementById('editEntryType').value = d.entry_type || 'book';
            document.getElementById('editNotes').value = d.notes;
            new bootstrap.Modal(document.getElementById('editEntryModal')).show();
        });
    });
});
</script>
@endpush
@endsection
