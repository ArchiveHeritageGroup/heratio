@extends('theme::layouts.1col')
@section('title', 'Plugins')
@section('body-class', 'admin plugins')

@section('content')
  <h1>Plugins</h1>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <form method="POST" action="{{ url('/sfPluginAdminPlugin/plugins') }}">
    @csrf
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr><th>Name</th><th>Version</th><th>Enabled</th></tr>
        </thead>
        <tbody>
          @foreach($plugins as $plugin)
            <tr>
              <td>{{ $plugin->name }}</td>
              <td>{{ $plugin->version ?? '—' }}</td>
              <td>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="enabled[]" value="{{ $plugin->name }}" {{ $plugin->is_enabled ? 'checked' : '' }}>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <section class="actions mb-3">
      <button type="submit" class="btn atom-btn-outline-light">Save</button>
    </section>
  </form>
@endsection
