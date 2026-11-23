<x-library-layout>
  @section('title', __('ILL supplier rota'))

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">{{ __('ILL supplier rota') }}</h2>
    <a href="{{ route('library.ill-requests.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('ILL requests') }}</a>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if(session('ill_inbound_token'))
    <div class="alert alert-warning">
      <strong>{{ __('Copy this inbound token now. It will not be shown again.') }}</strong>
      <div><code class="user-select-all">{{ session('ill_inbound_token') }}</code></div>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <strong>{{ __('Automatic routing') }}:</strong>
        <span class="badge {{ $autoEnabled ? 'bg-success' : 'bg-secondary' }}">{{ $autoEnabled ? __('On') : __('Off') }}</span>
        <div class="small text-muted">{{ __('When on, a decline or timeout sends the request to the next supplier without staff action.') }}</div>
      </div>
      <form method="POST" action="{{ route('library.ill-routing.auto') }}">
        @csrf
        <input type="hidden" name="enabled" value="{{ $autoEnabled ? 0 : 1 }}">
        <button class="btn btn-sm {{ $autoEnabled ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $autoEnabled ? __('Turn off') : __('Turn on') }}</button>
      </form>
    </div>
  </div>

  @if($attention->isNotEmpty())
    <div class="card mb-3 border-warning">
      <div class="card-header">{{ __('Requests needing attention') }}</div>
      <ul class="list-group list-group-flush">
        @foreach($attention as $item)
          <li class="list-group-item"><a href="{{ route('library.ill-routing.request', $item->id) }}">{{ $item->ill_number }}</a> <span class="small text-muted">{{ $item->reason }}</span></li>
        @endforeach
      </ul>
    </div>
  @endif

  @if($skipped->isNotEmpty())
    <div class="alert alert-info small">
      {{ __('Skipped by routing:') }}
      @foreach($skipped as $s)<code>{{ $s->edi_partner_code }}</code> ({{ $s->reason }})@if(!$loop->last), @endif @endforeach
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header">{{ __('Suppliers in rank order') }}</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr>
          <th>{{ __('Partner') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Timeout (hours)') }}</th>
          <th>{{ __('Material types') }}</th><th>{{ __('Active') }}</th><th>{{ __('Inbound token') }}</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($entries as $entry)
            <tr>
              <td>
                <code>{{ $entry->edi_partner_code }}</code>
                @if($entry->test_mode)<span class="badge bg-warning text-dark">{{ __('TEST') }}</span>@endif
                @unless($entry->partner_active)<span class="badge bg-secondary">{{ __('Partner inactive') }}</span>@endunless
                <form id="rota-{{ $entry->id }}" method="POST" action="{{ route('library.ill-routing.update', $entry->id) }}">@csrf @method('PATCH')</form>
              </td>
              <td><input form="rota-{{ $entry->id }}" type="number" name="priority" min="1" max="999" required value="{{ $entry->priority }}" class="form-control form-control-sm" aria-label="{{ __('Priority') }}"></td>
              <td><input form="rota-{{ $entry->id }}" type="number" name="timeout_hours" min="1" max="720" required value="{{ $entry->timeout_hours }}" class="form-control form-control-sm" aria-label="{{ __('Timeout (hours)') }}"></td>
              <td><input form="rota-{{ $entry->id }}" name="material_types" maxlength="255" value="{{ $entry->material_types }}" placeholder="{{ __('All') }}" class="form-control form-control-sm" aria-label="{{ __('Material types') }}"></td>
              <td><input form="rota-{{ $entry->id }}" type="checkbox" name="is_active" value="1" @checked($entry->is_active) class="form-check-input" aria-label="{{ __('Active') }}"></td>
              <td>
                <form method="POST" action="{{ route('library.ill-routing.token', $entry->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Issue a new token? The old one stops working.')) }});">
                  @csrf
                  <button class="btn btn-outline-secondary btn-sm">{{ $entry->has_token ? __('Replace') : __('Issue') }}</button>
                </form>
              </td>
              <td class="text-nowrap">
                <button form="rota-{{ $entry->id }}" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                <form method="POST" action="{{ route('library.ill-routing.destroy', $entry->id) }}" class="d-inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Remove this supplier from the rota?')) }});">
                  @csrf @method('DELETE')
                  <button class="btn btn-outline-danger btn-sm">{{ __('Remove') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted">{{ __('No suppliers on the rota yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">{{ __('Add a supplier') }}</div>
    <div class="card-body">
      @if($available->isEmpty())
        <p class="text-muted mb-0">{{ __('Every active trading partner is already on the rota.') }}</p>
      @else
        <form method="POST" action="{{ route('library.ill-routing.store') }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-md-4">
            <label class="form-label small" for="rota-partner">{{ __('Trading partner') }}</label>
            <select id="rota-partner" name="trading_partner_id" class="form-select form-select-sm" required>
              @foreach($available as $p)<option value="{{ $p->id }}">{{ $p->edi_partner_code }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small" for="rota-priority">{{ __('Priority') }}</label>
            <input id="rota-priority" type="number" name="priority" min="1" max="999" required value="{{ old('priority', 100) }}" class="form-control form-control-sm">
          </div>
          <div class="col-md-2">
            <label class="form-label small" for="rota-timeout">{{ __('Timeout (hours)') }}</label>
            <input id="rota-timeout" type="number" name="timeout_hours" min="1" max="720" required value="{{ old('timeout_hours', 72) }}" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small" for="rota-material">{{ __('Material types (comma separated, blank for all)') }}</label>
            <input id="rota-material" name="material_types" maxlength="255" value="{{ old('material_types') }}" class="form-control form-control-sm">
          </div>
          <input type="hidden" name="is_active" value="1">
          <div class="col-md-1"><button class="btn btn-primary btn-sm w-100">{{ __('Add') }}</button></div>
        </form>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="card-header">{{ __('Recent inbound messages') }}</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>{{ __('Received') }}</th><th>{{ __('Partner') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Detail') }}</th></tr></thead>
        <tbody>
          @forelse($recent as $m)
            <tr>
              <td class="text-nowrap">{{ $m->created_at }}</td>
              <td><code>{{ $m->edi_partner_code }}</code></td>
              <td>
                @if($m->ill_request_id)
                  <a href="{{ route('library.ill-routing.request', $m->ill_request_id) }}">{{ $m->ill_number ?: $m->ill_request_id }}</a>
                @else
                  {{ $m->ill_number ?: '-' }}
                @endif
              </td>
              <td>{{ $m->raw_status ?: '-' }}</td>
              <td>{{ $outcomes[$m->outcome] ?? $m->outcome }}</td>
              <td class="small">{{ $m->detail }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted">{{ __('No inbound messages yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-library-layout>
