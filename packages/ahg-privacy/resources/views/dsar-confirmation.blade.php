@extends('theme::layouts.1col')

@section('title', 'Request Submitted Successfully')

@section('content')
@php
  // The reference number is flashed from PrivacyController::dsarRequestStore.
  // Falling back to ?ref= query param so this page is also reachable via a
  // direct link emailed to the requestor.
  $reference = session('reference_number') ?? request()->query('ref');
@endphp

<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <a href="{{ url()->previous() && url()->previous() !== url()->current() ? url()->previous() : route('ahgprivacy.dsar-request') }}"
       class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back') }}">
      <i class="fas fa-arrow-left"></i>
    </a>
    <i class="fas fa-check-circle text-success me-2 fa-2x"></i>
    <h1 class="h3 mb-0">{{ __('Request Submitted Successfully') }}</h1>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>{{ __('Your reference number') }}</h5>
    </div>
    <div class="card-body">
      @if($reference)
        <div class="d-flex align-items-center mb-3">
          <code class="fs-3 fw-bold text-primary user-select-all me-3" id="dsar-ref">{{ $reference }}</code>
          <button type="button" class="btn btn-sm atom-btn-white" id="copy-ref-btn" title="{{ __('Copy to clipboard') }}">
            <i class="fas fa-copy me-1"></i>{{ __('Copy') }}
          </button>
        </div>
        <p class="mb-2">{{ __('Please save this reference number — you will need it to track the status of your request.') }}</p>
      @else
        <div class="alert alert-warning mb-0">
          <i class="fas fa-exclamation-triangle me-2"></i>{{ __('No reference number was provided. If you have just submitted a request and reached this page directly, please refer to the confirmation email or contact the data protection officer.') }}
        </div>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('What happens next') }}</h6></div>
    <div class="card-body">
      <ol class="mb-0">
        <li>{{ __('Your request has been logged and assigned to the data protection officer.') }}</li>
        <li>{{ __('You may be contacted to verify your identity before the request is processed.') }}</li>
        <li>{{ __('You will receive a response by email within the statutory window for the applicable jurisdiction (typically 30 days).') }}</li>
        <li>{{ __('You can check the status of your request at any time using the reference number above.') }}</li>
      </ol>
    </div>
  </div>

  <div class="d-flex gap-2 flex-wrap">
    @if($reference)
      <a href="{{ route('ahgprivacy.dsar-status', ['ref' => $reference]) }}" class="btn btn-primary">
        <i class="fas fa-search me-1"></i>{{ __('Check request status') }}
      </a>
    @endif
    <a href="{{ url('/') }}" class="btn atom-btn-white"><i class="fas fa-home me-1"></i>{{ __('Back to home') }}</a>
    <a href="{{ route('ahgprivacy.dsar-request') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Submit another request') }}</a>
  </div>
</div>

@if($reference)
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('copy-ref-btn');
  var ref = document.getElementById('dsar-ref');
  if (btn && ref && navigator.clipboard) {
    btn.addEventListener('click', function() {
      navigator.clipboard.writeText(ref.textContent.trim()).then(function() {
        var prev = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __('Copied') }}';
        setTimeout(function() { btn.innerHTML = prev; }, 2000);
      });
    });
  }
});
</script>
@endif
@endsection
