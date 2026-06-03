@extends('ahg-theme-b5::layouts.admin')

@section('content')
<div class="container-fluid py-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/admin/privacy/index">{{ __('Privacy') }}</a></li>
      <li class="breadcrumb-item active">{{ __('Description privacy') }}</li>
    </ol>
  </nav>

  <h1 class="h4 mb-1">{{ __('Field-level redaction') }}</h1>
  <p class="text-muted">{{ __('Description') }} #{{ $io->id }} — {{ $io->title ?: __('(untitled)') }}</p>

  @if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
  @endif
  @if($activeDsar)
    <div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i>{{ __('A data subject access request is currently processing.') }}
      <a href="/admin/privacy/dsar" class="alert-link">{{ __('View DSAR log') }}</a></div>
  @endif

  {{-- Profile --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">{{ __('Privacy profile') }}</div>
    <div class="card-body">
      <form method="post" action="/admin/privacy/description/{{ $io->id }}/redaction" class="row g-2">
        @csrf
        <div class="col-md-4">
          <label class="form-label small fw-bold">{{ __('Reason') }}</label>
          <select name="privacy_reason_id" class="form-select form-select-sm">
            @foreach($reasons as $r)
              <option value="{{ $r->id }}" @selected(optional($profile)->privacy_reason_id == $r->id)>{{ $r->label_en }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Status') }}</label>
          <select name="redaction_status" class="form-select form-select-sm">
            @foreach(['none','partial','full','pending'] as $s)
              <option value="{{ $s }}" @selected(optional($profile)->redaction_status === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label small fw-bold">{{ __('Legal basis reference') }}</label>
          <input type="text" name="legal_basis_reference" class="form-control form-control-sm"
                 value="{{ optional($profile)->legal_basis_reference }}" placeholder="e.g. POPIA s.37, GDPR Art.17(3)(e)">
        </div>
        <div class="col-12">
          <label class="form-label small fw-bold">{{ __('Notes') }}</label>
          <textarea name="notes" class="form-control form-control-sm" rows="2">{{ optional($profile)->notes }}</textarea>
        </div>
        <div class="col-12"><button class="btn btn-sm atom-btn-outline-success">{{ __('Save profile') }}</button></div>
      </form>
    </div>
  </div>

  {{-- Redacted fields --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">{{ __('Redacted fields') }}</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead><tr>
          <th>{{ __('Field') }}</th><th>{{ __('Type') }}</th><th>{{ __('Pattern') }}</th>
          <th>{{ __('Reason') }}</th><th>{{ __('Sensitive') }}</th><th></th>
        </tr></thead>
        <tbody>
          @forelse(optional($profile)->fields ?? [] as $f)
            <tr>
              <td><code>{{ $f->field_name }}</code></td>
              <td>{{ $f->redaction_type }}</td>
              <td>{{ $f->redaction_pattern ?: '—' }}</td>
              <td>{{ $f->reason }}</td>
              <td>{!! $f->is_sensitive ? '<span class="badge bg-danger">'.__('Yes').'</span>' : '—' !!}</td>
              <td class="text-end">
                <form method="post" action="/admin/privacy/description/{{ $io->id }}/redaction/field/{{ $f->id }}/remove" onsubmit="return confirm('{{ __('Remove this field redaction?') }}')">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No fields redacted on this description.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Add field --}}
  <div class="card">
    <div class="card-header fw-bold">{{ __('Add field redaction') }}</div>
    <div class="card-body">
      <form method="post" action="/admin/privacy/description/{{ $io->id }}/redaction/field" class="row g-2">
        @csrf
        <div class="col-md-4">
          <label class="form-label small fw-bold">{{ __('Field') }}</label>
          <select name="field_name" class="form-select form-select-sm">
            @foreach($redactableFields as $rf)<option value="{{ $rf }}">{{ $rf }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Type') }}</label>
          <select name="redaction_type" class="form-select form-select-sm">
            <option value="full">full</option><option value="partial">partial</option><option value="pseudonymised">pseudonymised</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Pattern (partial)') }}</label>
          <select name="redaction_pattern" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach($patterns as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3 form-check mt-4 ms-2">
          <input type="checkbox" name="is_sensitive" value="1" class="form-check-input" id="is_sensitive">
          <label class="form-check-label small" for="is_sensitive">{{ __('Special category / sensitive') }}</label>
        </div>
        <div class="col-12">
          <label class="form-label small fw-bold">{{ __('Reason') }}</label>
          <input type="text" name="reason" class="form-control form-control-sm" required placeholder="{{ __('Why this field is redacted') }}">
        </div>
        <div class="col-12"><button class="btn btn-sm atom-btn-outline-success">{{ __('Add field redaction') }}</button></div>
      </form>
    </div>
  </div>
</div>
@endsection
