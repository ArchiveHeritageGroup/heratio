@extends('theme::layouts.1col')

@section('title', 'Translation Languages')

@section('content')
<h1>Translation Languages</h1>

@if (session('notice'))
  <div class="alert alert-success">{{ session('notice') }}</div>
@endif

<div class="accordion mb-3">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#languages-collapse" aria-expanded="true">Enabled Languages</button>
    </h2>
    <div id="languages-collapse" class="accordion-collapse collapse show">
      <div class="accordion-body">
        <table class="table table-striped table-sm">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($languages as $lang)
              <tr>
                <td><code>{{ $lang['code'] }}</code></td>
                <td>{{ $lang['name'] }}</td>
                <td>
                  @if ($lang['default'])
                    <span class="badge bg-primary">Default</span>
                  @elseif ($lang['enabled'])
                    <span class="badge bg-success">Enabled</span>
                  @else
                    <span class="badge bg-secondary">Available</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#add-language-collapse" aria-expanded="false">Add Language</button>
    </h2>
    <div id="add-language-collapse" class="accordion-collapse collapse">
      <div class="accordion-body">
        <form method="POST" action="{{ route('ahgtranslation.addLanguage') }}">
          @csrf
          <div class="row align-items-end">
            <div class="col-md-4">
              <label class="form-label">Language code</label>
              <select class="form-select" name="code">
                @foreach ($languages as $lang)
                  @if (!$lang['enabled'])
                    <option value="{{ $lang['code'] }}">{{ $lang['name'] }} ({{ $lang['code'] }})</option>
                  @endif
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn atom-btn-outline-success">Add Language</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<ul class="actions mb-3 nav gap-2">
  <li><a href="{{ route('ahgtranslation.settings') }}" class="btn atom-btn-outline-light" role="button">Back to Settings</a></li>
</ul>
@endsection
