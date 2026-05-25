@extends('theme::layouts.2col')
@section('title', 'Human oversight (Article 14)')
@section('body-class', 'admin ai-compliance')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => []])
@endsection

@section('title-block')
  <h1>{{ __('Human oversight') }}</h1>
  <p class="text-muted small mb-0">{{ __('EU AI Act Article 14 - human-in-the-loop controls per AI service') }}</p>
@endsection

@section('content')

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Automation-bias attestation card --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h3 class="h5 mb-1">{{ __('Automation-bias attestation') }}</h3>
        <small class="text-muted">{{ __('Annual operator acknowledgement under Article 14(4)(b)') }}</small>
      </div>
      <div>
        @if ($hasAttestation && $attestation)
          <span class="badge bg-success me-2"><i class="bi bi-check2-circle"></i> {{ __('Active') }}</span>
          <small class="text-muted">{{ __('Expires') }} {{ $attestation->expires_at->format('Y-m-d') }}</small>
        @elseif ($attestation)
          <span class="badge bg-danger me-2"><i class="bi bi-x-circle"></i> {{ __('Expired') }}</span>
          <small class="text-muted">{{ __('Was active until') }} {{ $attestation->expires_at->format('Y-m-d') }}</small>
        @else
          <span class="badge bg-warning text-dark me-2"><i class="bi bi-exclamation-triangle"></i> {{ __('Not attested') }}</span>
        @endif
      </div>
    </div>

    @if (!$hasAttestation)
      <hr>
      <p class="small mb-2">{{ __('I acknowledge that AI output may contain plausible-sounding but incorrect information (automation bias). I will critically review every AI suggestion against the underlying source before approving or publishing it.') }}</p>
      <form method="post" action="{{ route('ai-compliance.oversight.attest') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-pen"></i> {{ __('Attest now') }}</button>
        <small class="text-muted ms-2">{{ __('A receipt is written to the inference chain (#693).') }}</small>
      </form>
    @endif
  </div>
</div>

{{-- Per-service policies --}}
<h2 class="h4 mb-3">{{ __('Per-service oversight policies') }}</h2>

<form method="post" action="{{ route('ai-compliance.oversight.halt-all') }}" class="mb-3" onsubmit="return confirm('{{ __('Halt EVERY AI service NOW?') }}')">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger">
    <i class="bi bi-octagon"></i> {{ __('Emergency: halt ALL AI services') }}
  </button>
</form>

<div class="table-responsive">
  <table class="table table-striped table-sm align-middle">
    <thead>
      <tr>
        <th>{{ __('Service') }}</th>
        <th>{{ __('Status') }}</th>
        <th class="text-center">{{ __('Review required') }}</th>
        <th class="text-center">{{ __('Confidence gate') }}</th>
        <th class="text-center">{{ __('Dual review') }}</th>
        <th>{{ __('Bias prompt') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($policies as $p)
        <tr>
          <td><strong>{{ strtoupper($p->service) }}</strong></td>
          <td>
            @if ($p->halted)
              <span class="badge bg-danger"><i class="bi bi-octagon-fill"></i> {{ __('HALTED') }}</span>
              @if ($p->halted_reason)
                <small class="text-muted d-block">{{ $p->halted_reason }}</small>
              @endif
            @else
              <span class="badge bg-success"><i class="bi bi-check-circle"></i> {{ __('Running') }}</span>
            @endif
          </td>
          <td class="text-center">
            @if ($p->requires_human_review)
              <i class="bi bi-check2-square text-success"></i>
            @else
              <i class="bi bi-dash-circle text-muted"></i>
            @endif
          </td>
          <td class="text-center"><small>{{ number_format($p->confidence_threshold, 2) }}</small></td>
          <td class="text-center">
            @if ($p->dual_review_required)
              <i class="bi bi-people-fill text-warning" title="{{ __('Art. 14(5) two-person verification') }}"></i>
            @else
              <i class="bi bi-dash-circle text-muted"></i>
            @endif
          </td>
          <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($p->automation_bias_prompt_text ?? '-', 80) }}</small></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#policy-{{ $p->id }}" title="{{ __('Edit policy') }}">
              <i class="bi bi-pencil"></i>
            </button>
            @if ($p->halted)
              <form method="post" action="{{ route('ai-compliance.oversight.resume', $p->service) }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-outline-success" title="{{ __('Resume') }}"><i class="bi bi-play-fill"></i></button>
              </form>
            @else
              <form method="post" action="{{ route('ai-compliance.oversight.halt', $p->service) }}" class="d-inline" onsubmit="return confirm('{{ __('Halt this service?') }}')">
                @csrf
                <button class="btn btn-sm btn-outline-danger" title="{{ __('Halt') }}"><i class="bi bi-octagon"></i></button>
              </form>
            @endif
          </td>
        </tr>
        <tr id="policy-{{ $p->id }}" class="collapse">
          <td colspan="7">
            <form method="post" action="{{ route('ai-compliance.oversight.update', $p->id) }}" class="row g-2">
              @csrf
              @method('PUT')

              <div class="col-md-3 form-check">
                <input type="hidden" name="requires_human_review" value="0">
                <input type="checkbox" name="requires_human_review" value="1" id="rhr-{{ $p->id }}" class="form-check-input" @checked($p->requires_human_review)>
                <label class="form-check-label small" for="rhr-{{ $p->id }}">{{ __('Requires human review') }}</label>
              </div>

              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Confidence threshold') }}</label>
                <input type="number" step="0.01" min="0" max="1" name="confidence_threshold" value="{{ $p->confidence_threshold }}" class="form-control form-control-sm">
              </div>

              <div class="col-md-3 form-check">
                <input type="hidden" name="dual_review_required" value="0">
                <input type="checkbox" name="dual_review_required" value="1" id="drr-{{ $p->id }}" class="form-check-input" @checked($p->dual_review_required)>
                <label class="form-check-label small" for="drr-{{ $p->id }}">{{ __('Dual review (Art. 14(5))') }}</label>
              </div>

              <div class="col-md-12">
                <label class="form-label small mb-0">{{ __('Automation-bias banner text') }}</label>
                <input type="text" name="automation_bias_prompt_text" value="{{ $p->automation_bias_prompt_text }}" class="form-control form-control-sm" maxlength="512">
              </div>

              <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2"></i> {{ __('Save policy') }}</button>
              </div>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Pending countersignatures --}}
@if (count($pendingCounter) > 0)
<h2 class="h4 mb-3 mt-4">{{ __('Pending two-person verifications') }} <span class="badge bg-warning text-dark">{{ count($pendingCounter) }}</span></h2>
<p class="small text-muted">{{ __('Article 14(5) - biometric ID actions need a second-person countersignature before a decision is taken on the basis of the AI output.') }}</p>

<div class="table-responsive">
  <table class="table table-striped table-sm">
    <thead>
      <tr>
        <th>{{ __('Service') }}</th>
        <th>{{ __('Decision') }}</th>
        <th>{{ __('First reviewer') }}</th>
        <th>{{ __('When') }}</th>
        <th>{{ __('Note') }}</th>
        <th class="text-end">{{ __('Countersign') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($pendingCounter as $d)
        <tr>
          <td><span class="badge bg-secondary">{{ strtoupper($d->service) }}</span></td>
          <td>{{ __(ucfirst($d->decision)) }}</td>
          <td>#{{ $d->reviewer_user_id }}</td>
          <td><small>{{ $d->created_at->diffForHumans() }}</small></td>
          <td><small>{{ \Illuminate\Support\Str::limit($d->note ?? '-', 80) }}</small></td>
          <td class="text-end">
            <form method="post" action="{{ route('ai-compliance.oversight.countersign', $d->id) }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-warning"><i class="bi bi-pen"></i> {{ __('Countersign') }}</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection
