@extends('theme::layouts.1col')
@section('title', 'New IIIF Collection')
@section('body-class', 'iiif-collection new')
@section('title-block')<h1 class="mb-0">New Collection</h1>@endsection
@section('content')
<form method="post" action="{{ route('iiif-collection.store') }}">@csrf
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Collection Details</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-8 mb-3"><label for="name" class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="name" id="name" class="form-control" required></div>
  <div class="col-md-4 mb-3"><label for="viewing_hint" class="form-label">Viewing Hint <span class="badge bg-secondary ms-1">Optional</span></label><select name="viewing_hint" id="viewing_hint" class="form-select"><option value="individuals">Individuals</option><option value="paged">Paged</option><option value="continuous">Continuous</option></select></div>
  <div class="col-12 mb-3"><label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="description" id="description" class="form-control" rows="3"></textarea></div>
  <div class="col-md-6 mb-3"><label for="attribution" class="form-label">Attribution <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="attribution" id="attribution" class="form-control"></div>
  <div class="col-md-6 mb-3"><label for="parent_id" class="form-label">Parent Collection <span class="badge bg-secondary ms-1">Optional</span></label><select name="parent_id" id="parent_id" class="form-select"><option value="">-- None (top level) --</option>@foreach($allCollections ?? [] as $c)<option value="{{ $c->id }}" {{ ($parentId ?? '') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
  <div class="col-md-6 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="is_public" checked><label class="form-check-label" for="is_public">Public <span class="badge bg-secondary ms-1">Optional</span></label></div></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><a href="{{ route('iiif-collection.index') }}" class="btn atom-btn-outline-light">Cancel</a><button type="submit" class="btn atom-btn-outline-light">Create Collection</button></section>
</form>
@endsection
