@extends('theme::layouts.1col')

@section('title', 'ILL Request: ' . ($request->ill_number ?? $id))

@section('content')
<div class="container py-4">

  {{-- Back link + ILL number ─────────────────────────────────────────── --}}
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <a href="{{ route('library.ill') }}" class="text-muted me-2">
        <i class="fas fa-arrow-left"></i>
      </a>
      <h1 class="d-inline-block mb-0">
        ILL Request:
        <span class="font-monospace">{{ e($request->ill_number ?? $id) }}</span>
      </h1>
      @if($request->opac_suppress)
        <span class="badge bg-dark ms-2" title="{{ __('Suppressed from OPAC') }}">Suppressed</span>
      @endif
    </div>
    <div>
      @if(auth()->check())
        <form method="post" action="{{ route('library.ill-opac-suppress', $request->id) }}"
              class="d-inline">
          @csrf
          @method('patch')
          <input type="hidden" name="suppress" value="{{ $request->opac_suppress ? '0' : '1' }}">
          <button type="submit" class="btn btn-sm btn-outline-dark me-2"
                  title="{{ $request->opac_suppress ? 'Show in OPAC' : 'Hide from OPAC' }}">
            <i class="fas fa-eye{{ $request->opac_suppress ? '-slash' : '' }} me-1"></i>
            {{ $request->opac_suppress ? 'Unsuppress' : 'Suppress' }}
          </button>
        </form>
        <form method="post" action="{{ route('library.ill-delete', $request->id) }}"
              class="d-inline"
              onsubmit="return confirm('Delete this ILL request? This cannot be undone.');">
          @csrf
          @method('delete')
          <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-trash me-1"></i>{{ __('Delete') }}
          </button>
        </form>
      @endif
    </div>
  </div>

  {{-- Error / success flash messages ─────────────────────────────────── --}}
  @if(session('ill_error'))
    <div class="alert alert-danger">{{ session('ill_error') }}</div>
  @endif
  @if(session('ill_success'))
    <div class="alert alert-success">{{ session('ill_success') }}</div>
  @endif

  <div class="row">

    {{-- Left: metadata + transition panel ───────────────────────────── --}}
    <div class="col-md-8">

      {{-- Metadata card ─────────────────────────────────────────────── --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="fas fa-info-circle me-1"></i>{{ __('Request Details') }}
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Type') }}</dt>
            <dd class="col-sm-9">
              <span class="badge {{ ($request->type ?? '') === 'borrow' ? 'bg-primary' : 'bg-success' }}">
                {{ ucfirst($request->type ?? '—') }}
              </span>
            </dd>

            <dt class="col-sm-3">{{ __('Title') }}</dt>
            <dd class="col-sm-9">{{ e($request->title ?? '—') }}</dd>

            <dt class="col-sm-3">{{ __('Author') }}</dt>
            <dd class="col-sm-9">{{ e($request->author ?? '—') }}</dd>

            <dt class="col-sm-3">{{ __('ISBN') }}</dt>
            <dd class="col-sm-9">{{ e($request->isbn ?? '—') }}</dd>

            @if(!empty($request->issn))
            <dt class="col-sm-3">{{ __('ISSN') }}</dt>
            <dd class="col-sm-9">{{ e($request->issn) }}</dd>
            @endif

            @if(!empty($request->volume) || !empty($request->issue))
            <dt class="col-sm-3">{{ __('Volume / Issue') }}</dt>
            <dd class="col-sm-9">
              {{ e(trim(($request->volume ?? '').' '.($request->issue ?? ''))) }}
            </dd>
            @endif

            @if(!empty($request->pages))
            <dt class="col-sm-3">{{ __('Pages') }}</dt>
            <dd class="col-sm-9">{{ e($request->pages) }}</dd>
            @endif

            @if(!empty($request->edition))
            <dt class="col-sm-3">{{ __('Edition') }}</dt>
            <dd class="col-sm-9">{{ e($request->edition) }}</dd>
            @endif

            @if(!empty($request->publication_year))
            <dt class="col-sm-3">{{ __('Year') }}</dt>
            <dd class="col-sm-9">{{ e($request->publication_year) }}</dd>
            @endif

            <hr class="mt-2 mb-2">

            <dt class="col-sm-3">{{ __('Partner Library') }}</dt>
            <dd class="col-sm-9">{{ e($request->library_name ?? '—') }}</dd>

            @if(!empty($request->library_symbol))
            <dt class="col-sm-3">{{ __('Library Symbol') }}</dt>
            <dd class="col-sm-9 font-monospace">{{ e($request->library_symbol) }}</dd>
            @endif

            @if(!empty($request->patron_id))
            <dt class="col-sm-3">{{ __('Patron') }}</dt>
            <dd class="col-sm-9">
              <a href="{{ route('library.patron-view', $request->patron_id) }}">
                {{ e($request->patron_id) }}
              </a>
            </dd>
            @endif

            <hr class="mt-2 mb-2">

            <dt class="col-sm-3">{{ __('Requested') }}</dt>
            <dd class="col-sm-9">{{ $request->request_date ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('Due Date') }}</dt>
            <dd class="col-sm-9 {{ ($request->status ?? '') === 'overdue' ? 'text-danger fw-bold' : '' }}">
              {{ $request->due_date ?? '—' }}
              @if(($request->status ?? '') === 'overdue')
                <i class="fas fa-exclamation-triangle text-warning ms-1"></i>
              @endif
            </dd>

            <dt class="col-sm-3">{{ __('Current Status') }}</dt>
            <dd class="col-sm-9">
              <span class="badge fs-6
                @if(($request->status ?? '') === 'overdue') bg-warning text-dark
                @elseif(($request->status ?? '') === 'lost') bg-danger
                @elseif(($request->status ?? '') === 'received') bg-success
                @elseif(($request->status ?? '') === 'returned') bg-success
                @else bg-secondary @endif">
                {{ ucfirst(str_replace('_', ' ', $request->status ?? '—')) }}
              </span>
            </dd>
          </dl>
        </div>
      </div>

      {{-- ISO 10160 transition panel ─────────────────────────────────── --}}
      @if(auth()->check() && !empty($available_transitions))
      <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
          <i class="fas fa-exchange-alt me-1"></i>
          {{ __('Update Status (ISO 10160)') }}
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            Available transitions from <strong>{{ ucfirst($request->status ?? '') }}</strong>:
          </p>
          <div class="d-flex flex-wrap gap-2">
            @foreach($available_transitions as $t)
              <form method="post" action="{{ route('library.ill-transition', $request->id) }}">
                @csrf
                <input type="hidden" name="status" value="{{ $t }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                  <i class="fas fa-arrow-right me-1"></i>
                  Move to {{ ucfirst($t) }}
                </button>
              </form>
            @endforeach
          </div>

          {{-- Optional staff note ─────────────────────────────────────── --}}
          <hr class="mt-3 mb-3">
          <div class="row">
            <div class="col-md-8">
              <label for="transition_note" class="form-label small text-muted">
                {{ __('Add a note (optional)') }}
              </label>
              <textarea id="transition_note" name="note" rows="2"
                        class="form-control form-control-sm"
                        placeholder="{{ __('e.g. Courier tracking number, reason for cancellation…') }}"
              ></textarea>
            </div>
          </div>
        </div>
      </div>
      @endif

      {{-- Staff notes ─────────────────────────────────────────────────── --}}
      @if(!empty($request->staff_note))
      <div class="card mb-4">
        <div class="card-header">
          <i class="fas fa-sticky-note me-1"></i>{{ __('Staff Notes') }}
        </div>
        <div class="card-body">
          <pre class="mb-0" style="white-space: pre-wrap;">{{ e($request->staff_note) }}</pre>
        </div>
      </div>
      @endif

      {{-- Patron note ─────────────────────────────────────────────────── --}}
      @if(!empty($request->requester_note))
      <div class="card mb-4">
        <div class="card-header">
          <i class="fas fa-user me-1"></i>{{ __('Requester Note') }}
        </div>
        <div class="card-body">
          <p class="mb-0">{{ e($request->requester_note) }}</p>
        </div>
      </div>
      @endif

      {{-- Audit history ─────────────────────────────────────────────── --}}
      @if(!empty($audit_log) && count($audit_log) > 0)
      <div class="card">
        <div class="card-header">
          <i class="fas fa-history me-1"></i>{{ __('Audit Trail') }}
        </div>
        <div class="card-body p-0">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('When') }}</th>
                <th>{{ __('From') }}</th>
                <th>{{ __('To') }}</th>
                <th>{{ __('Description') }}</th>
                <th>{{ __('By') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($audit_log as $entry)
              <tr>
                <td class="text-muted small">{{ $entry->created_at ?? '—' }}</td>
                <td>
                  @if($entry->from_status)
                    <span class="badge bg-secondary">{{ ucfirst($entry->from_status) }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <span class="badge bg-info">{{ ucfirst($entry->to_status) }}</span>
                </td>
                <td class="small">{{ e($entry->description ?? '') }}</td>
                <td class="small text-muted">{{ e($entry->changed_by ?? '') }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

    </div>{{-- /col-md-8 --}}

    {{-- Right: sidebar ─────────────────────────────────────────────────── --}}
    <div class="col-md-4">

      {{-- Requester form (staff) ─────────────────────────────────────── --}}
      @if(auth()->check())
      <div class="card mb-3">
        <div class="card-header">
          <i class="fas fa-edit me-1"></i>{{ __('Edit Request') }}
        </div>
        <div class="card-body">
          <form method="post" action="{{ route('library.ill-update', $request->id) }}">
            @csrf
            @method('patch')

            <div class="mb-2">
              <label class="form-label small">{{ __('Partner Library') }}</label>
              <input type="text" name="library_name" value="{{ e($request->library_name ?? '') }}"
                     class="form-control form-control-sm">
            </div>
            <div class="mb-2">
              <label class="form-label small">{{ __('Library Symbol') }}</label>
              <input type="text" name="library_symbol" value="{{ e($request->library_symbol ?? '') }}"
                     class="form-control form-control-sm" placeholder="{{ __('e.g. SABINET, NAZ') }}">
            </div>
            <div class="mb-2">
              <label class="form-label small">{{ __('Due Date') }}</label>
              <input type="date" name="due_date" value="{{ $request->due_date ?? '' }}"
                     class="form-control form-control-sm">
            </div>
            <div class="mb-2">
              <label class="form-label small">{{ __('Patron ID') }}</label>
              <input type="number" name="patron_id" value="{{ $request->patron_id ?? '' }}"
                     class="form-control form-control-sm">
            </div>

            <button type="submit" class="btn btn-sm btn-primary w-100">
              <i class="fas fa-save me-1"></i>{{ __('Save Changes') }}
            </button>
          </form>
        </div>
      </div>
      @endif

      {{-- ISO 10160 state machine info ───────────────────────────────── --}}
      <div class="card mb-3">
        <div class="card-header">
          <i class="fas fa-project-diagram me-1"></i>{{ __('ISO 10160 State Guide') }}
        </div>
        <div class="card-body small">
          <p class="text-muted mb-2">Borrow (we request):</p>
          <ol class="mb-2 ps-3 small">
            <li>Pending</li>
            <li>Requested</li>
            <li>Shipped <span class="text-muted">→</span> Received <span class="text-muted">→</span> Returned</li>
            <li class="text-muted">Cancelled / Lost / Unfulfilled (terminal)</li>
          </ol>
          <p class="text-muted mb-2">Lend (they request from us):</p>
          <ol class="mb-0 ps-3 small">
            <li>Pending</li>
            <li>Shipped <span class="text-muted">→</span> Received (terminal)</li>
            <li class="text-muted">Cancelled / Unfulfilled (terminal)</li>
          </ol>
        </div>
      </div>

      {{-- Quick actions ─────────────────────────────────────────────── --}}
      @if(auth()->check())
      <div class="card">
        <div class="card-body">
          <a href="{{ route('library.ill') }}" class="btn btn-outline-secondary btn-sm w-100 mb-2">
            <i class="fas fa-list me-1"></i>{{ __('Back to ILL List') }}
          </a>
          <a href="{{ route('library.ill-create') }}" class="btn btn-outline-primary btn-sm w-100">
            <i class="fas fa-plus me-1"></i>{{ __('New Request') }}
          </a>
        </div>
      </div>
      @endif

    </div>{{-- /col-md-4 --}}

  </div>{{-- /row --}}

</div>
@endsection