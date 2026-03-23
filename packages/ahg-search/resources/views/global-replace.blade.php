@extends('theme::layouts.1col')

@section('title', __('Global search/replace'))

@section('content')
  <h1>{{ render_title($title ?? __('Global search/replace')) }}</h1>

  @if(isset($confirm) && $confirm)
    <h3 style="font-weight: normal;">{{ __('This will permanently modify %1% records.', ['%1%' => $count ?? 0]) }}</h3>
    <div class="error">
      <h2>{{ __('This action cannot be undone!') }}</h2>
    </div>
  @endif

  {{-- Search/Replace form --}}
  <form action="{{ route('search.globalReplace') }}" method="post" name="advanced-search-form">
    @csrf

    <div class="criteria mb-4">
      <div class="row">
        <div class="col-md-4">
          <label for="column" class="form-label">{{ __('Column') }} <span class="badge bg-danger ms-1">Required</span></label>
          <select name="column" id="column" class="form-select" required>
            <option value="">{{ __('-- Select a field --') }}</option>
            @foreach($columns as $value => $label)
              <option value="{{ $value }}" {{ (old('column', $column ?? '') === $value) ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-4">
          <label for="pattern" class="form-label">{{ __('Search') }} <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" name="pattern" id="pattern" class="form-control" value="{{ old('pattern', $pattern ?? '') }}" required>
        </div>

        <div class="col-md-4">
          <label for="replacement" class="form-label">{{ __('Replace') }} <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="replacement" id="replacement" class="form-control" value="{{ old('replacement', $replacement ?? '') }}">
        </div>
      </div>

      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="caseSensitive" id="caseSensitive" value="1" {{ old('caseSensitive', $caseSensitive ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="caseSensitive">{{ __('Case sensitive') }} <span class="badge bg-secondary ms-1">Optional</span></label>
      </div>
    </div>

    <ul class="actions mb-1 nav gap-2 justify-content-center">
      <li><input type="submit" class="btn atom-btn-outline-light" value="{{ __('Search') }}"></li>
      <li><input type="button" class="btn atom-btn-outline-light reset" value="{{ __('Reset') }}" onclick="window.location='{{ route('search.globalReplace') }}'"></li>
    </ul>
  </form>

  @if(isset($error))
    <div class="error">
      <ul>
        <li>{{ $error }}</li>
      </ul>
    </div>
  @endif

  @if(isset($results) && $results->count() > 0)
    <div class="table-responsive mb-3 mt-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Current value') }}</th>
            <th>{{ __('New value') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($results as $row)
            <tr>
              <td>
                @if($row->slug)
                  <a href="/{{ $row->slug }}">{{ $row->title }}</a>
                @else
                  {{ $row->title }}
                @endif
              </td>
              <td>{{ $row->current_value }}</td>
              <td>{{ $row->new_value }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Confirm form --}}
    <form action="{{ route('search.globalReplace') }}" method="post">
      @csrf
      <input type="hidden" name="column" value="{{ $column }}">
      <input type="hidden" name="pattern" value="{{ $pattern }}">
      <input type="hidden" name="replacement" value="{{ $replacement }}">
      <input type="hidden" name="caseSensitive" value="{{ $caseSensitive ? '1' : '0' }}">
      <input type="hidden" name="confirm" value="1">
      <ul class="actions mb-1 nav gap-2 justify-content-center">
        <li><input type="submit" class="btn atom-btn-outline-light" value="{{ __('Confirm') }}" onclick="return confirm('{{ __('This action cannot be undone!') }}')"></li>
        <li><a href="{{ route('search.globalReplace') }}" class="btn atom-btn-outline-light">{{ __('Cancel') }}</a></li>
      </ul>
    </form>
  @endif
@endsection
