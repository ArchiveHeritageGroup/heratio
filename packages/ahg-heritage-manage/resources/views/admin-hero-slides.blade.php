@extends('theme::layouts.1col')
@section('title', 'Hero Slides Management')
@section('body-class', 'admin heritage')

@php
$slidesArray = (array)($slides ?? []);
$editSlideData = isset($editSlide) && $editSlide ? (array)$editSlide : null;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-images me-2"></i>Hero Slides Management</h1>

<div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h2 class="h5 mb-0">{{ $editSlideData ? 'Edit Hero Slide' : 'Add New Hero Slide' }}</h2></div>
      <div class="card-body">
        <form action="{{ route('heritage.admin-hero-slides') }}" method="post" enctype="multipart/form-data">@csrf
          <input type="hidden" name="slide_action" value="{{ $editSlideData ? 'update' : 'create' }}">
          @if($editSlideData)<input type="hidden" name="slide_id" value="{{ $editSlideData['id'] }}">@endif
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Hero Image {{ $editSlideData ? '' : '' }} <span class="badge bg-{{ $editSlideData ? 'secondary' : 'danger' }} ms-1">{{ $editSlideData ? 'Optional' : 'Required' }}</span></label>
              @if($editSlideData && !empty($editSlideData['image_path']))<div class="mb-2"><img src="{{ $editSlideData['image_path'] }}" class="img-thumbnail" style="max-height:100px"><br><small class="text-muted">Current image</small></div>@endif
              <input type="file" class="form-control mb-2" name="hero_image" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text">Upload JPG, PNG, WebP, or GIF. Max 10MB. Recommended: 1920x1080px.</div>
              <div class="mt-2"><label class="form-label small">Or enter image URL: <span class="badge bg-secondary ms-1">Optional</span></label><input type="url" class="form-control form-control-sm" name="image_url" placeholder="https://example.com/image.jpg" value="{{ ($editSlideData && str_starts_with($editSlideData['image_path'] ?? '', 'http')) ? $editSlideData['image_path'] : '' }}"></div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="mb-3"><label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="title" value="{{ $editSlideData['title'] ?? '' }}" placeholder="Slide title (optional)"></div>
              <div class="mb-3"><label for="subtitle" class="form-label">Subtitle <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="subtitle" value="{{ $editSlideData['subtitle'] ?? '' }}"></div>
              <div class="mb-3"><label for="image_alt" class="form-label">Image Alt Text <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="image_alt" value="{{ $editSlideData['image_alt'] ?? '' }}" placeholder="Describe the image for accessibility"></div>
            </div>
          </div>
          <div class="row"><div class="col-12 mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="description" rows="2">{{ $editSlideData['description'] ?? '' }}</textarea></div></div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Overlay Type <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select" name="overlay_type"><option value="gradient" {{ ($editSlideData['overlay_type'] ?? 'gradient')==='gradient'?'selected':'' }}>Gradient</option><option value="solid" {{ ($editSlideData['overlay_type'] ?? '')==='solid'?'selected':'' }}>Solid</option><option value="none" {{ ($editSlideData['overlay_type'] ?? '')==='none'?'selected':'' }}>None</option></select></div>
            <div class="col-md-4 mb-3"><label class="form-label">Overlay Color <span class="badge bg-secondary ms-1">Optional</span></label><input type="color" class="form-control form-control-color w-100" name="overlay_color" value="{{ $editSlideData['overlay_color'] ?? '#000000' }}"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Overlay Opacity <span class="badge bg-secondary ms-1">Optional</span></label><input type="range" class="form-range" name="overlay_opacity" min="0" max="1" step="0.1" value="{{ $editSlideData['overlay_opacity'] ?? 0.5 }}" id="overlay_opacity"><small class="text-muted">Current: <span id="opacity_value">{{ ($editSlideData['overlay_opacity'] ?? 0.5) * 100 }}%</span></small></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Text Position <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select" name="text_position">@foreach(['left','center','right','bottom-left','bottom-right'] as $pos)<option value="{{ $pos }}" {{ ($editSlideData['text_position'] ?? 'left')===$pos?'selected':'' }}>{{ ucfirst($pos) }}</option>@endforeach</select></div>
            <div class="col-md-4 mb-3"><label class="form-label">Display Duration (seconds) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" name="display_duration" value="{{ $editSlideData['display_duration'] ?? 8 }}" min="3" max="30"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Display Order <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" name="display_order" value="{{ $editSlideData['display_order'] ?? 100 }}"></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Button Text <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="cta_text" value="{{ $editSlideData['cta_text'] ?? '' }}" placeholder="e.g., Explore Collection"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Button URL <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="cta_url" value="{{ $editSlideData['cta_url'] ?? '' }}"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Button Style <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select" name="cta_style"><option value="primary" {{ ($editSlideData['cta_style'] ?? 'primary')==='primary'?'selected':'' }}>Primary</option><option value="secondary" {{ ($editSlideData['cta_style'] ?? '')==='secondary'?'selected':'' }}>Secondary</option><option value="light" {{ ($editSlideData['cta_style'] ?? '')==='light'?'selected':'' }}>Light</option><option value="outline" {{ ($editSlideData['cta_style'] ?? '')==='outline'?'selected':'' }}>Outline</option></select></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Source Collection <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="source_collection" value="{{ $editSlideData['source_collection'] ?? '' }}"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Photographer/Credit <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="photographer_credit" value="{{ $editSlideData['photographer_credit'] ?? '' }}"></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ken_burns" value="1" {{ ($editSlideData['ken_burns'] ?? 1)?'checked':'' }}><label class="form-check-label">Ken Burns Effect <span class="badge bg-secondary ms-1">Optional</span></label></div></div>
            <div class="col-md-4 mb-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" {{ ($editSlideData['is_enabled'] ?? 1)?'checked':'' }}><label class="form-check-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label></div></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Start Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="start_date" value="{{ $editSlideData['start_date'] ?? '' }}"></div>
            <div class="col-md-6 mb-3"><label class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="end_date" value="{{ $editSlideData['end_date'] ?? '' }}"></div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-check me-1"></i>{{ $editSlideData ? 'Update Slide' : 'Add Slide' }}</button>
            @if($editSlideData)<a href="{{ route('heritage.admin-hero-slides') }}" class="btn atom-btn-white">Cancel</a>@endif
          </div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h2 class="h5 mb-0">Current Hero Slides</h2>
        <span class="badge bg-secondary">{{ count($slidesArray) }} slides</span>
      </div>
      <div class="card-body">
        @if(empty($slidesArray))
        <p class="text-muted text-center py-4"><i class="fas fa-images fs-1 d-block mb-2"></i>No hero slides configured yet.</p>
        @else
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th style="width:100px">Image</th><th>Title</th><th>Position</th><th>Order</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              @foreach($slidesArray as $slide)
              @php $slide = (array)$slide; @endphp
              <tr>
                <td>@if(!empty($slide['image_path']))<img src="{{ $slide['image_path'] }}" class="img-thumbnail" style="max-width:80px;max-height:50px;object-fit:cover">@else<span class="text-muted"><i class="fas fa-image"></i></span>@endif</td>
                <td><strong>{{ $slide['title'] ?? '(No title)' }}</strong>@if(!empty($slide['subtitle']))<br><small class="text-muted">{{ $slide['subtitle'] }}</small>@endif</td>
                <td><small>{{ $slide['text_position'] ?? 'left' }}</small></td>
                <td>{{ $slide['display_order'] }}</td>
                <td>{{ $slide['display_duration'] }}s</td>
                <td>@if($slide['is_enabled'])<span class="badge bg-success">Enabled</span>@else<span class="badge bg-secondary">Disabled</span>@endif</td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="{{ route('heritage.admin-hero-slides', ['edit' => $slide['id']]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-pencil-alt me-1"></i>Edit</a>
                    <form action="{{ route('heritage.admin-hero-slides') }}" method="post" class="d-inline">@csrf<input type="hidden" name="slide_action" value="toggle"><input type="hidden" name="slide_id" value="{{ $slide['id'] }}"><button type="submit" class="btn btn-sm btn-outline-{{ $slide['is_enabled'] ? 'warning' : 'success' }}"><i class="fas fa-{{ $slide['is_enabled'] ? 'eye-slash' : 'eye' }}"></i></button></form>
                    <form action="{{ route('heritage.admin-hero-slides') }}" method="post" class="d-inline" onsubmit="return confirm('Delete this slide?');">@csrf<input type="hidden" name="slide_action" value="delete"><input type="hidden" name="slide_id" value="{{ $slide['id'] }}"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    <script>document.getElementById('overlay_opacity')?.addEventListener('input',function(){document.getElementById('opacity_value').textContent=Math.round(this.value*100)+'%';});</script>
  </div>
</div>
@endsection
