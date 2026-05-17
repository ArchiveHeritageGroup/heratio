@extends('theme::layouts.1col')
@section('title', __('Share link issued'))
@section('body-class', 'share-link issued')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
        @if($recordSlug)
            <li class="breadcrumb-item">
                <a href="{{ url('/' . $recordSlug) }}">{{ $recordTitle }}</a>
            </li>
        @endif
        <li class="breadcrumb-item active">{{ __('Share link issued') }}</li>
    </ol>
</nav>

<div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="fas fa-check-circle fa-2x me-3"></i>
    <div>
        <strong>{{ __('Share link issued.') }}</strong>
        {{ __('Send the URL below to the recipient. They will see the record without logging in.') }}
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Public URL') }}</h5>
    </div>
    <div class="card-body">
        <div class="input-group mb-3">
            <input type="text" id="share-link-url" class="form-control font-monospace"
                   value="{{ $publicUrl }}" readonly>
            <button class="btn btn-primary" type="button" id="share-link-copy">
                <i class="fas fa-copy me-1"></i> {{ __('Copy') }}
            </button>
        </div>

        <table class="table table-sm table-borderless mb-0">
            <tr>
                <th width="180">{{ __('Expires') }}</th>
                <td>{{ $expiresAt }}</td>
            </tr>
            @if(!empty($recipientEmail))
                <tr><th>{{ __('Recipient') }}</th><td>{{ $recipientEmail }}</td></tr>
            @endif
            @if(!empty($maxAccess))
                <tr><th>{{ __('Max views') }}</th><td>{{ (int) $maxAccess }}</td></tr>
            @endif
            <tr>
                <th>{{ __('Token') }}</th>
                <td><code>{{ $token }}</code></td>
            </tr>
            <tr>
                <th>{{ __('Manage') }}</th>
                <td>
                    <a href="{{ route('share-link.admin.show', ['id' => $tokenId]) }}">
                        {{ __('View details and access log') }} &raquo;
                    </a>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="{{ route('share-link.new', ['information_object_id' => (int) $informationObjectId]) }}"
       class="btn btn-outline-primary">
        <i class="fas fa-plus me-1"></i> {{ __('Issue another') }}
    </a>
    @if($recordSlug)
        <a href="{{ url('/' . $recordSlug) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> {{ __('Back to record') }}
        </a>
    @endif
    <a href="{{ route('share-link.admin.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-list me-1"></i> {{ __('All share links') }}
    </a>
</div>

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('share-link-copy');
    var input = document.getElementById('share-link-url');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value);
            } else {
                document.execCommand('copy');
            }
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> ' + @json(__('Copied!'));
            btn.classList.add('btn-success');
            btn.classList.remove('btn-primary');
            setTimeout(function () {
                btn.innerHTML = orig;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 1800);
        } catch (e) {}
    });
})();
</script>
@endpush
@endsection
