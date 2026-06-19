{{-- heratio#1192 - Live virtual openings: admin schedule + manage. --}}
@extends('theme::layouts.1col')

@section('title', __('Live openings') . ' — ' . $space->name)
@section('body-class', 'exhibition-space openings')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-calendar-day me-2"></i>{{ __('Live openings') }}
      <small class="text-muted">{{ $space->name }}</small>
    </h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'openings'])
  </div>

  <p class="text-muted small mb-3">
    {{ __('Schedule a ticketed live opening that visitors attend together in the 3D walkthrough. RSVPs are capacity-checked, and each event gets a public page with a join link that goes live shortly before the start time.') }}
    <br>
    <em>{{ __('First slice: scheduling, ticketing and a timed join link. Live multi-user spatial audio and a real-time docent are the next slice (heratio#1150).') }}</em>
  </p>

  @if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header py-2"><strong><i class="fas fa-plus me-1"></i>{{ __('Schedule an opening') }}</strong></div>
        <div class="card-body">
          <form method="post" action="{{ route('exhibition-space.openings.store', ['slug' => $space->slug]) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="ev_title">{{ __('Title') }}</label>
              <input type="text" id="ev_title" name="title" class="form-control" maxlength="200" required
                     value="{{ old('title') }}" placeholder="{{ __('Opening night: New acquisitions') }}">
            </div>
            <div class="mb-3">
              <label class="form-label" for="ev_host">{{ __('Host / docent name') }}</label>
              <input type="text" id="ev_host" name="host_name" class="form-control" maxlength="160"
                     value="{{ old('host_name') }}" placeholder="{{ __('Dr A. Curator') }}">
            </div>
            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label" for="ev_starts">{{ __('Starts at') }}</label>
                <input type="datetime-local" id="ev_starts" name="starts_at" class="form-control" required
                       value="{{ old('starts_at') }}">
              </div>
              <div class="col-5">
                <label class="form-label" for="ev_duration">{{ __('Duration (min)') }}</label>
                <input type="number" id="ev_duration" name="duration_minutes" class="form-control" min="5" max="1440" step="5"
                       value="{{ old('duration_minutes', 60) }}" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="ev_capacity">{{ __('Capacity (seats)') }}</label>
              <input type="number" id="ev_capacity" name="capacity" class="form-control" min="1" max="100000" step="1"
                     value="{{ old('capacity', 50) }}" required>
            </div>
            {{-- heratio#1192 slice 2b - paid ticketing. Leave price blank or 0 for a free opening. --}}
            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label" for="ev_price">{{ __('Ticket price') }}</label>
                <input type="number" id="ev_price" name="price" class="form-control" min="0" max="99999999.99" step="0.01"
                       value="{{ old('price') }}" placeholder="0.00">
                <div class="form-text">{{ __('Blank or 0 = free admission.') }}</div>
              </div>
              <div class="col-5">
                <label class="form-label" for="ev_currency">{{ __('Currency') }}</label>
                <input type="text" id="ev_currency" name="currency" class="form-control text-uppercase" maxlength="3"
                       value="{{ old('currency') }}" placeholder="{{ __('USD') }}">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="ev_desc">{{ __('Description') }}</label>
              <textarea id="ev_desc" name="description" class="form-control" rows="3" maxlength="5000"
                        placeholder="{{ __('What visitors will see and do.') }}">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus me-1"></i>{{ __('Schedule opening') }}</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-header py-2"><strong><i class="fas fa-list me-1"></i>{{ __('Scheduled openings') }}</strong></div>
        <div class="card-body p-0">
          @if(count($events) === 0)
            <div class="p-3 text-muted">{{ __('No openings scheduled yet.') }}</div>
          @else
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('When') }}</th>
                    <th class="text-end">{{ __('Seats') }}</th>
                    <th class="text-end">{{ __('Price') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($events as $ev)
                    <tr>
                      <td>
                        <div class="fw-semibold">{{ $ev->title }}</div>
                        @if($ev->host_name)<div class="small text-muted">{{ __('Host') }}: {{ $ev->host_name }}</div>@endif
                        <a class="small" href="{{ route('exhibition-space.opening-public', ['token' => $ev->public_token]) }}" target="_blank" rel="noopener">
                          <i class="fas fa-external-link-alt me-1"></i>{{ __('Public event page') }}
                        </a>
                      </td>
                      <td class="small">
                        {{ \Illuminate\Support\Carbon::parse($ev->starts_at)->format('D j M Y, H:i') }}<br>
                        <span class="text-muted">{{ $ev->duration_minutes }} {{ __('min') }}</span>
                      </td>
                      <td class="text-end small">
                        {{ $ev->reserved }} / {{ $ev->capacity }}
                        <div class="text-muted">{{ $ev->remaining }} {{ __('left') }}</div>
                      </td>
                      @php $isPaidEv = isset($ev->price) && (float) $ev->price > 0; @endphp
                      <td class="text-end small">
                        @if($isPaidEv)
                          <span class="fw-semibold">{{ number_format((float) $ev->price, 2) }}</span>
                          <div class="text-muted">{{ $ev->currency }}</div>
                        @else
                          <span class="badge bg-light text-dark">{{ __('Free') }}</span>
                        @endif
                      </td>
                      <td>
                        @php $sty = ['scheduled' => 'bg-info', 'live' => 'bg-success', 'ended' => 'bg-secondary', 'cancelled' => 'bg-danger'][$ev->status] ?? 'bg-secondary'; @endphp
                        <span class="badge {{ $sty }}">{{ $statuses[$ev->status] ?? $ev->status }}</span>
                      </td>
                      <td class="text-end">
                        <div class="d-inline-flex flex-column gap-1">
                          <form method="post" action="{{ route('exhibition-space.openings.status', ['slug' => $space->slug, 'eventId' => $ev->id]) }}" class="d-flex gap-1">
                            @csrf
                            <select name="status" class="form-select form-select-sm" style="width:auto">
                              @foreach($statuses as $k => $label)
                                <option value="{{ $k }}" @selected($ev->status === $k)>{{ $label }}</option>
                              @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Set') }}</button>
                          </form>
                          <form method="post" action="{{ route('exhibition-space.openings.delete', ['slug' => $space->slug, 'eventId' => $ev->id]) }}"
                                onsubmit="return confirm('{{ __('Delete this opening and all its tickets?') }}');">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    @if($isPaidEv && !empty($rsvpsByEvent[$ev->id]))
                      {{-- heratio#1192 slice 2b - paid opening: list tickets so the curator can
                           settle pending (unpaid) ones. Marking paid confirms + emails the ticket. --}}
                      <tr class="table-light">
                        <td colspan="6" class="small">
                          <div class="fw-semibold mb-1"><i class="fas fa-ticket-alt me-1"></i>{{ __('Tickets') }}</div>
                          <table class="table table-sm mb-0">
                            <tbody>
                              @foreach($rsvpsByEvent[$ev->id] as $r)
                                <tr>
                                  <td>{{ $r->name }} <span class="text-muted">&lt;{{ $r->email }}&gt;</span></td>
                                  <td class="text-muted"><code>{{ $r->ticket_code }}</code> &times;{{ $r->party_size }}</td>
                                  <td>
                                    @if($r->status === 'pending')
                                      <span class="badge bg-warning text-dark">{{ __('Payment pending') }}</span>
                                    @elseif($r->status === 'confirmed')
                                      <span class="badge bg-success">{{ __('Paid') }}</span>
                                      @if(isset($r->amount_paid) && $r->amount_paid !== null)
                                        <span class="text-muted">{{ number_format((float) $r->amount_paid, 2) }} {{ $ev->currency }}</span>
                                      @endif
                                    @else
                                      <span class="badge bg-secondary">{{ $r->status }}</span>
                                    @endif
                                  </td>
                                  <td class="text-end">
                                    @if($r->status === 'pending')
                                      <form method="post" action="{{ route('exhibition-space.openings.mark-paid', ['slug' => $space->slug, 'eventId' => $ev->id, 'rsvpId' => $r->id]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit">
                                          <i class="fas fa-check me-1"></i>{{ __('Mark as paid') }}
                                        </button>
                                      </form>
                                    @endif
                                  </td>
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </td>
                      </tr>
                    @endif
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
