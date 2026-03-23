@extends('theme::layouts.1col')
@section('title', 'Featured Collections')
@section('body-class', 'admin heritage')

@php
$featured = (array) ($featured ?? []);
$iiifCollections = (array) ($iiifCollections ?? []);
$archivalCollections = (array) ($archivalCollections ?? []);
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-star me-2"></i>Featured Collections</h1>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Collection to Featured</h5></div>
      <div class="card-body">
        <form action="{{ route('heritage.admin-featured-collections') }}" method="post">@csrf
          <input type="hidden" name="featured_action" value="add">
          <div class="row g-3">
            <div class="col-md-4"><label for="source_type" class="form-label">Collection Type <span class="badge bg-danger ms-1">Required</span></label><select class="form-select" id="source_type" name="source_type" required onchange="toggleSourceOptions(this.value)"><option value="">Select type...</option><option value="archival">Archival Collection (Fonds)</option><option value="iiif">IIIF Collection (Manifest)</option></select></div>
            <div class="col-md-4" id="archival_select_wrapper" style="display:none"><label for="archival_source_id" class="form-label">Select Archival Collection <span class="badge bg-danger ms-1">Required</span></label><select class="form-select" id="archival_source_id" name="source_id_archival"><option value="">Select collection...</option>@foreach($archivalCollections as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach</select></div>
            <div class="col-md-4" id="iiif_select_wrapper" style="display:none"><label for="iiif_source_id" class="form-label">Select IIIF Collection <span class="badge bg-danger ms-1">Required</span></label><select class="form-select" id="iiif_source_id" name="source_id_iiif"><option value="">Select collection...</option>@foreach($iiifCollections as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label for="display_order" class="form-label">Display Order <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" id="display_order" name="display_order" value="100" min="1"></div>
          </div>
          <div class="row g-3 mt-2">
            <div class="col-md-6"><label for="title" class="form-label">Override Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" id="title" name="title" placeholder="Leave blank to use original"></div>
            <div class="col-md-6"><label for="description" class="form-label">Override Description <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" id="description" name="description" placeholder="Leave blank to use original"></div>
          </div>
          <div class="mt-3"><button type="submit" class="btn atom-btn-secondary" id="add_btn" disabled><i class="fas fa-plus me-1"></i>Add to Featured</button></div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Current Featured Collections</h5>
        <span class="badge bg-light text-dark">{{ count($featured) }} collections</span>
      </div>
      <div class="card-body p-0">
        @if(empty($featured))
        <div class="text-center text-muted py-5"><i class="fas fa-inbox display-4 mb-3 d-block"></i><p class="mb-0">No featured collections yet.</p></div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th style="width:60px">Order</th><th>Collection</th><th>Type</th><th>Custom Title</th><th style="width:100px">Status</th><th style="width:150px">Actions</th></tr></thead>
            <tbody>
              @foreach($featured as $item)
              <tr>
                <td><span class="badge bg-secondary">{{ $item->display_order }}</span></td>
                <td><strong>{{ $item->source_name }}</strong>@if($item->source_slug)<br><small class="text-muted">{{ $item->source_slug }}</small>@endif</td>
                <td>@if($item->source_type==='iiif')<span class="badge bg-info"><i class="fas fa-layer-group me-1"></i>IIIF</span>@else<span class="badge bg-success"><i class="fas fa-archive me-1"></i>Archival</span>@endif</td>
                <td>@if($item->title)<em>{{ $item->title }}</em>@else<span class="text-muted">-</span>@endif</td>
                <td>@if($item->is_enabled)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Disabled</span>@endif</td>
                <td>
                  <form action="{{ route('heritage.admin-featured-collections') }}" method="post" class="d-inline">@csrf<input type="hidden" name="featured_action" value="toggle"><input type="hidden" name="featured_id" value="{{ $item->id }}"><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas {{ $item->is_enabled ? 'fa-pause' : 'fa-play' }}"></i></button></form>
                  <form action="{{ route('heritage.admin-featured-collections') }}" method="post" class="d-inline" onsubmit="return confirm('Remove?');">@csrf<input type="hidden" name="featured_action" value="remove"><input type="hidden" name="featured_id" value="{{ $item->id }}"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    <script>
    function toggleSourceOptions(type){document.getElementById('archival_select_wrapper').style.display=type==='archival'?'block':'none';document.getElementById('iiif_select_wrapper').style.display=type==='iiif'?'block':'none';if(type==='archival'){document.getElementById('archival_source_id').name='source_id';document.getElementById('iiif_source_id').name='source_id_iiif';}else if(type==='iiif'){document.getElementById('iiif_source_id').name='source_id';document.getElementById('archival_source_id').name='source_id_archival';}updateAddButton();}
    function updateAddButton(){var type=document.getElementById('source_type').value;var has=false;if(type==='archival')has=document.getElementById('archival_source_id').value!=='';else if(type==='iiif')has=document.getElementById('iiif_source_id').value!=='';document.getElementById('add_btn').disabled=!has;}
    document.getElementById('archival_source_id')?.addEventListener('change',updateAddButton);
    document.getElementById('iiif_source_id')?.addEventListener('change',updateAddButton);
    </script>
  </div>
</div>
@endsection
