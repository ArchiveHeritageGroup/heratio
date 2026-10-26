{{--
  API key manager for one user (#1558, #1566): their keys with scopes, last
  use, expiry, daily limit and status, a revoke button each, and a form for a
  new key whose scopes are what the user's role allows. Used by /api/keys and
  the user page.

  Props: $keys, $scopes (allowed), $scopeLabels, $publicOnly, $newKey,
         $storeUrl, $revokeRoute (fn (int $id): string)
--}}
@if($newKey)
  <div class="alert alert-success">
    <p class="mb-1">{{ __('The new key. Copy it now: it is not shown again.') }}</p>
    <code class="user-select-all fs-6">{{ $newKey }}</code>
  </div>
@endif
@if($publicOnly)
  <div class="alert alert-info">{{ __('This user has no catalogue role, so their keys read the public catalogue only: what any visitor may see.') }}</div>
@endif

@if($keys->isEmpty())
  <p class="text-muted">{{ __('No keys yet.') }}</p>
@else
  <div class="table-responsive mb-3">
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
            <form method="POST" action="{{ $revokeRoute($k->id) }}" onsubmit="return confirm({{ json_encode(__('Revoke this key? Anything using it stops working.')) }})">
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

<form method="POST" action="{{ $storeUrl }}" autocomplete="off" class="border rounded p-3">
  @csrf
  <h3 class="h6">{{ __('New key') }}</h3>
  <div class="mb-3">
    <label class="form-label" for="key-name">{{ __('Name') }}</label>
    <input class="form-control" id="key-name" name="name" required maxlength="100" placeholder="{{ __('e.g. AI assistant') }}">
  </div>
  <fieldset class="mb-3">
    <legend class="form-label fs-6">{{ __('Scopes') }}</legend>
    @foreach($scopes as $s)
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $s }}" id="scope-{{ $s }}" @checked($s === 'read')>
        <label class="form-check-label" for="scope-{{ $s }}"><code>{{ $s }}</code> - {{ __($scopeLabels[$s] ?? $s) }}</label>
      </div>
    @endforeach
    <div class="form-text">{{ __('Only the scopes this user\'s role allows are offered. To propose changes through an AI assistant, give the key read and write.') }}</div>
  </fieldset>
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
