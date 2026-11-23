<x-library-layout>
  @section('title', __('Supplier routing') . ': ' . $ill->ill_number)

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4">{{ __('Supplier routing') }}: {{ $ill->ill_number }}</h2>
    <div>
      <a href="{{ route('library.ill-routing.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Rota') }}</a>
      <a href="{{ route('library.ill-requests.show', $ill->id) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to request') }}</a>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <p>{{ $ill->title }} <span class="badge bg-secondary">{{ ucfirst($ill->status) }}</span></p>

  <div class="card mb-3">
    <div class="card-header">{{ __('Attempts') }}</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>{{ __('Partner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Reply due') }}</th><th>{{ __('Replied') }}</th><th>{{ __('Note') }}</th></tr></thead>
        <tbody>
          @forelse($attempts as $a)
            <tr>
              <td>{{ $a->sequence }}</td>
              <td><code>{{ $a->edi_partner_code ?? $a->trading_partner_id }}</code></td>
              <td>{{ $statuses[$a->status] ?? $a->status }}</td>
              <td>{{ $a->sent_at ?: '-' }}</td>
              <td>{{ $a->expires_at ?: '-' }}</td>
              <td>{{ $a->responded_at ?: '-' }}</td>
              <td class="small">{{ $a->note }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted">{{ __('Not routed yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($state['overdue'])
    <div class="alert alert-warning">{{ __('The supplier accepted this request but has not shipped it by the deadline.') }}</div>
  @endif
  @if($state['unreachable'])
    <div class="alert alert-warning">{{ __('No supplier could be reached and nothing was delivered. Retry the sends, or mark the request unfulfilled.') }}</div>
  @endif
  @unless($state['on_rota'])
    <div class="alert alert-info">{{ __('This request was sent by hand and is off the rota.') }}</div>
  @endunless
  @if($skipped->isNotEmpty())
    <div class="alert alert-info small">
      {{ __('Skipped by routing:') }}
      @foreach($skipped as $s)<code>{{ $s->edi_partner_code }}</code> ({{ $s->reason }})@if(!$loop->last), @endif @endforeach
    </div>
  @endif

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header">{{ __('Next suppliers') }}</div>
        <div class="card-body">
          @if($next->isEmpty())
            <p class="text-muted">{{ __('No further eligible supplier.') }}</p>
          @else
            <ol class="mb-3">
              @foreach($next as $s)
                <li><code>{{ $s->edi_partner_code }}</code> <span class="small text-muted">{{ __('priority :p, fill rate :r%', ['p' => $s->priority, 'r' => round($s->fill_rate * 100)]) }}</span></li>
              @endforeach
            </ol>
          @endif
          <div class="d-flex flex-wrap gap-2">
            @if($state['can_send'])
              <form method="POST" action="{{ route('library.ill-routing.route', $ill->id) }}">
                @csrf
                <button class="btn btn-primary btn-sm">{{ __('Send to next supplier') }}</button>
              </form>
            @endif
            @if($state['can_exhaust'])
              <form method="POST" action="{{ route('library.ill-routing.route', $ill->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('No supplier is left. Mark this request unfulfilled?')) }});">
                @csrf
                <button class="btn btn-outline-danger btn-sm">{{ __('Mark unfulfilled') }}</button>
              </form>
            @endif
            @if($state['unreachable'])
              <form method="POST" action="{{ route('library.ill-routing.retry', $ill->id) }}">
                @csrf
                <button class="btn btn-primary btn-sm">{{ __('Retry failed sends') }}</button>
              </form>
              <form method="POST" action="{{ route('library.ill-routing.close', $ill->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Mark this request unfulfilled?')) }});">
                @csrf
                <button class="btn btn-outline-danger btn-sm">{{ __('Mark unfulfilled') }}</button>
              </form>
            @endif
            @if($state['live'] && in_array($ill->status, ['pending', 'requested'], true))
              <form method="POST" action="{{ route('library.ill-routing.stop', $ill->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Stop waiting for this supplier? They are not told, so contact them if the item may still be coming.')) }});">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">{{ __('Stop waiting for this supplier') }}</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header">{{ __('Enter a partner reply') }}</div>
        <div class="card-body">
          @if($attempts->isEmpty())
            <p class="text-muted mb-0">{{ __('A reply can be entered once the request has been sent to a supplier.') }}</p>
          @else
            <form method="POST" action="{{ route('library.ill-routing.reply', $ill->id) }}">
              @csrf
              <label class="form-label small" for="reply-partner">{{ __('From') }}</label>
              <select id="reply-partner" name="trading_partner_id" class="form-select form-select-sm mb-2" required>
                @foreach($attempts as $a)<option value="{{ $a->trading_partner_id }}">{{ $a->edi_partner_code ?? $a->trading_partner_id }}</option>@endforeach
              </select>
              <label class="form-label small" for="reply-message">{{ __('EDI message') }}</label>
              <textarea id="reply-message" name="message" rows="4" required class="form-control form-control-sm font-monospace mb-2"></textarea>
              <button class="btn btn-outline-primary btn-sm">{{ __('Apply reply') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>
</x-library-layout>
