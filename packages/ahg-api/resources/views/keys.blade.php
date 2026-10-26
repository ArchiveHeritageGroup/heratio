{{-- /api/keys - a signed-in user's own API keys (#1558, #1566). --}}
@extends('theme::layouts.1col')
@section('title', __('API keys'))

@section('content')
<h1 class="h3 mb-3">{{ __('API keys') }}</h1>
<p class="text-muted">{{ __('A key lets a program or an AI assistant use the catalogue as you, with your permissions. Revoke a key and anything using it is disconnected at once.') }}</p>

@if($newKey)
  <div class="alert alert-success">
    <p class="mb-1">{{ __('Your new key. Copy it now: it is not shown again.') }}</p>
    <code class="user-select-all fs-6">{{ $newKey }}</code>
  </div>
@endif
@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
@if(! in_array('read', $scopes, true))
  <div class="alert alert-info">{{ __('Your keys read the public catalogue only: what any visitor to this site may see.') }}</div>
@endif

<div class="card mb-4">
  <div class="card-header"><h2 class="h5 mb-0">{{ __('Your keys') }}</h2></div>
  <div class="card-body p-0">
    @if($keys->isEmpty())
      <p class="text-muted p-3 mb-0">{{ __('No keys yet.') }}</p>
    @else
    <div class="table-responsive">
      <table class="table table-bordered table-striped mb-0">
        <thead><tr>
          <th>{{ __('Name') }}</th><th>{{ __('Key') }}</th><th>{{ __('Scopes') }}</th><th>{{ __('Created') }}</th>
          <th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Daily limit') }}</th><th>{{ __('Status') }}</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($keys as $k)
          @php($expired = $k->expires_at && \Carbon\Carbon::parse($k->expires_at)->isPast())
          <tr>
            <td>{{ $k->name }}</td>
            <td><code>{{ $k->api_key_prefix }}...</code></td>
            <td>{{ implode(', ', $k->scopes) }}</td>
            <td>{{ $k->created_at ? \Carbon\Carbon::parse($k->created_at)->format('j M Y') : '-' }}</td>
            <td>{{ $k->last_used_at ? \Carbon\Carbon::parse($k->last_used_at)->format('j M Y H:i') : __('Never') }}</td>
            <td>{{ $k->expires_at ? \Carbon\Carbon::parse($k->expires_at)->format('j M Y') : __('Never') }}</td>
            <td>{{ ($k->daily_limit ?? 0) > 0 ? number_format($k->daily_limit) : __('None') }}</td>
            <td>
              @if(! $k->is_active)<span class="badge bg-secondary">{{ __('Revoked') }}</span>
              @elseif($expired)<span class="badge bg-warning text-dark">{{ __('Expired') }}</span>
              @else<span class="badge bg-success">{{ __('Active') }}</span>@endif
            </td>
            <td>
              @if($k->is_active)
              <form method="POST" action="{{ route('api.keys.revoke', $k->id) }}" onsubmit="return confirm({{ json_encode(__('Revoke this key? Anything using it stops working.')) }})">
                @csrf
                <button class="btn btn-sm btn-outline-danger">{{ __('Revoke') }}</button>
              </form>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="h5 mb-0">{{ __('New key') }}</h2></div>
  <div class="card-body">
    <form method="POST" action="{{ route('api.keys.store') }}" autocomplete="off">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="key-name">{{ __('Name') }}</label>
        <input class="form-control" id="key-name" name="name" required maxlength="100" placeholder="{{ __('e.g. AI assistant') }}">
      </div>
      @if($scopes)
      <fieldset class="mb-3">
        <legend class="form-label fs-6">{{ __('Scopes') }}</legend>
        @foreach($scopes as $s)
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $s }}" id="scope-{{ $s }}" @checked($s === 'read')>
            <label class="form-check-label" for="scope-{{ $s }}">{{ $s }}</label>
          </div>
        @endforeach
        <div class="form-text">{{ __('To propose changes through an AI assistant, give the key read and write.') }}</div>
      </fieldset>
      @endif
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label" for="key-expires">{{ __('Expires') }}</label>
          <input class="form-control" type="date" id="key-expires" name="expires_at">
          <div class="form-text">{{ __('Leave empty for no expiry.') }}</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="key-daily">{{ __('Daily limit (requests)') }}</label>
          <input class="form-control" type="number" min="1" id="key-daily" name="daily_limit">
          <div class="form-text">{{ __('Leave empty for none. The hourly limit applies either way.') }}</div>
        </div>
      </div>
      <button class="btn btn-primary">{{ __('Create key') }}</button>
    </form>
  </div>
</div>
@endsection
