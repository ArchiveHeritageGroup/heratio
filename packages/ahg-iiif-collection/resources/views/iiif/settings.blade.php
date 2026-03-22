@extends('theme::layouts.1col')
@section('title', 'IIIF Viewer Settings')
@section('body-class', 'admin iiif settings')
@section('title-block')<h1 class="mb-0"><i class="fas fa-images me-2"></i>IIIF Viewer Settings</h1>@endsection
@section('content')
<form method="post" action="{{ route('iiif.settings.update') }}">@csrf
<div class="card mb-4"><div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-home me-2"></i>Homepage Featured Collection</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-6 mb-3"><label class="form-label">Featured Collection <span class="badge bg-secondary ms-1">Optional</span></label><select name="homepage_collection_id" class="form-select"><option value="">-- None --</option>@foreach($collections ?? [] as $c)<option value="{{ $c->id }}" {{ ($settings['homepage_collection_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
  <div class="col-md-6 mb-3"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="homepage_collection_enabled" value="1" id="enabled" {{ ($settings['homepage_collection_enabled'] ?? '1') === '1' ? 'checked' : '' }}><label class="form-check-label" for="enabled">Enable Homepage Carousel <span class="badge bg-secondary ms-1">Optional</span></label></div></div>
  <div class="col-md-4 mb-3"><label class="form-label">Carousel Height <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="homepage_carousel_height" class="form-control" value="{{ $settings['homepage_carousel_height'] ?? '450px' }}"></div>
  <div class="col-md-4 mb-3"><label class="form-label">Auto-play Interval (ms) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" name="homepage_carousel_interval" class="form-control" value="{{ $settings['homepage_carousel_interval'] ?? '5000' }}"></div>
  <div class="col-md-4 mb-3"><label class="form-label">Max Items <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" name="homepage_max_items" class="form-control" value="{{ $settings['homepage_max_items'] ?? '12' }}"></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><button type="submit" class="btn atom-btn-outline-light">Save Settings</button></section>
</form>
@endsection
