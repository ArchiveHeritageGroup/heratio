@extends('ahg-theme-b5::layout')

@section('title', __('Recovery Codes'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fas fa-key"></i> {{ __('Recovery Codes') }}</h4>
        </div>
        <div class="card-body">

          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif

          @if(empty($codes))
            <div class="alert alert-warning text-center">
              {{ __('Recovery codes are only shown once, immediately after enrolment or regeneration. Reloading this page does not re-display them.') }}
            </div>

            <p class="text-center">
              {{ __('You currently have :n unused recovery code(s).', ['n' => $remainingCount]) }}
            </p>

            <div class="d-flex justify-content-between">
              <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
              <form method="POST" action="{{ route('security-clearance.regenerate-recovery-codes') }}" class="d-inline">
                @csrf
                <input type="hidden" name="return" value="{{ $returnUrl }}">
                <button type="submit" class="btn btn-warning"
                        onclick="return confirm('{{ __('Generate new codes? Your previous codes will be invalidated.') }}')">
                  {{ __('Regenerate codes') }}
                </button>
              </form>
            </div>
          @else
            <div class="alert alert-danger">
              <strong>{{ __('Save these codes now.') }}</strong>
              {{ __('They are shown ONCE. Each code works only once. Use them to sign in if you lose access to your authenticator app.') }}
            </div>

            <pre id="mfaCodes" class="bg-light border rounded p-3 mb-3" style="font-size: 1.15em; line-height: 1.8em; letter-spacing: 1px;">@foreach($codes as $i => $code){{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}.  {{ $code }}
@endforeach</pre>

            <div class="d-flex gap-2 mb-3">
              <button type="button" class="btn btn-outline-primary" id="copyCodesBtn">
                <i class="fas fa-copy"></i> {{ __('Copy to clipboard') }}
              </button>
              <a href="data:text/plain;charset=utf-8,{{ rawurlencode(implode(PHP_EOL, $codes)) }}"
                 download="heratio-recovery-codes.txt"
                 class="btn btn-outline-primary">
                <i class="fas fa-download"></i> {{ __('Download .txt') }}
              </a>
              <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print"></i> {{ __('Print') }}
              </button>
            </div>

            <div class="text-center mt-4">
              <a href="{{ $returnUrl }}" class="btn btn-primary">{{ __('I have saved my codes') }}</a>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

@if(!empty($codes))
@push('scripts')
<script>
document.getElementById('copyCodesBtn')?.addEventListener('click', function() {
  const pre = document.getElementById('mfaCodes');
  if (!pre) return;
  navigator.clipboard.writeText(pre.textContent.trim()).then(() => {
    this.innerHTML = '<i class="fas fa-check"></i> {{ __('Copied') }}';
    setTimeout(() => {
      this.innerHTML = '<i class="fas fa-copy"></i> {{ __('Copy to clipboard') }}';
    }, 2000);
  });
});
</script>
@endpush
@endif
@endsection
