@extends('theme::layouts.1col')
@section('title', 'User interface labels')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-tags me-2"></i>User interface labels</h1>

    <form method="post" action="{{ route('settings.interface-labels') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#labels-collapse">User interface labels</button></h2>
          <div id="labels-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              @foreach($settings as $setting)
                <div class="mb-3">
                  <label class="form-label"><code>{{ $setting->name }}</code> <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="settings[{{ $setting->id }}]" class="form-control" value="{{ $setting->value ?? '' }}">
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
