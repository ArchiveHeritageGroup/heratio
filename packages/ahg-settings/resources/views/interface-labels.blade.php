@extends('theme::layouts.1col')
@section('title', 'Interface Labels')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-tags me-2"></i>Interface Labels</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <p class="text-muted">Customize the labels used throughout the user interface.</p>

    <form method="post" action="{{ route('settings.interface-labels') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header">Labels</div>
        <div class="card-body">
          @foreach($settings as $setting)
            <div class="mb-3">
              <label class="form-label"><code>{{ $setting->name }}</code></label>
              <input type="text" name="settings[{{ $setting->id }}]" class="form-control" value="{{ e($setting->value ?? '') }}">
            </div>
          @endforeach
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
