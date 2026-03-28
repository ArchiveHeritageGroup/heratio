@extends('theme::layouts.1col')

@section('title', 'Translation settings')

@section('content')
<h1>Translation settings</h1>

@if (session('notice'))
  <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<form method="POST" action="{{ route('ahgtranslation.settings') }}">
  @csrf

  <div class="accordion mb-3">
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#main-collapse" aria-expanded="true">Translation settings</button>
      </h2>
      <div id="main-collapse" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">MT endpoint</label>
            <input class="form-control" name="endpoint" value="{{ $endpoint }}" />
            <small class="form-text text-muted">Example: http://192.168.0.112:5004/ai/v1/translate</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Timeout (seconds)</label>
            <input class="form-control" name="timeout" value="{{ $timeout }}" />
          </div>

          <div class="mb-3">
            <label class="form-label">API Key</label>
            <input class="form-control" name="api_key" value="{{ $apiKey }}" type="password" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ url()->previous() }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
    <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
  </ul>
</form>

<hr/>
<p>
  Health check:
  <a href="{{ route('ahgtranslation.health') }}" target="_blank">{{ route('ahgtranslation.health') }}</a>
</p>
@endsection
