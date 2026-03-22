@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-book me-2"></i>Bibliographies</h1>@endsection
@section('content')
<div class="d-flex justify-content-between mb-3"><span></span><button class="btn atom-btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus me-1"></i>New Bibliography</button></div>
<div class="row">
@forelse($bibliographies as $b)
<div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body">
    <h5 class="card-title"><a href="{{ route('research.viewBibliography', $b->id) }}">{{ e($b->name) }}</a></h5>
    @if($b->description ?? null)<p class="card-text small text-muted">{{ e($b->description) }}</p>@endif
    <small class="text-muted">Style: {{ $b->citation_style ?? 'chicago' }}</small>
</div></div></div>
@empty
<div class="col-12 text-center text-muted py-4"><i class="fas fa-book fa-3x mb-3 d-block"></i>No bibliographies yet.</div>
@endforelse
</div>
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="POST">@csrf<input type="hidden" name="form_action" value="create">
<div class="modal-header"><h5 class="modal-title">New Bibliography</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" name="name" required></div>
    <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="description" rows="2"></textarea></div>
    <div class="mb-3"><label class="form-label">Citation Style <span class="badge bg-secondary ms-1">Optional</span></label><select name="citation_style" class="form-select"><option value="chicago">Chicago</option><option value="mla">MLA</option><option value="apa">APA</option><option value="harvard">Harvard</option><option value="turabian">Turabian</option></select></div>
</div>
<div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Create</button></div>
</form></div></div></div>
@endsection
