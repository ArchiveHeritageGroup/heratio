@extends('theme::layouts.2col')
@section('title', 'User interface label')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>User interface label</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.interface-labels') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="interface-label-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#interface-label-collapse" aria-expanded="true" aria-controls="interface-label-collapse">
              User interface labels
            </button>
          </h2>
          <div id="interface-label-collapse" class="accordion-collapse collapse show" aria-labelledby="interface-label-heading">
            <div class="accordion-body">
              @foreach($settings as $setting)
                <div class="mb-3">
                  <label class="form-label"><code>{{ $setting->name }}</code></label>
                  <input type="text" name="settings[{{ $setting->id }}]" class="form-control" value="{{ e($setting->value ?? '') }}">
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
@endsection
