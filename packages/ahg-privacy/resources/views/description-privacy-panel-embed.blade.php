{{--
  Self-contained field-redaction panel injected onto the IO detail page for
  admins (#1108 deliverable 4) by InjectFieldRedactionPanel. No @extends - it
  is appended before </body> on the (locked) show page, so it must carry its
  own markup and rely only on Bootstrap 5 + Font Awesome, both already loaded.
--}}
@php
  $status = $profile->redaction_status ?? 'none';
  $fields = $profile && $profile->relationLoaded('fields') ? $profile->fields : ($profile ? $profile->fields : collect());
  $fieldCount = $profile ? count($fields) : 0;
  $statusBadge = match ($status) {
    'full' => 'bg-danger',
    'partial' => 'bg-warning text-dark',
    'pending' => 'bg-info text-dark',
    default => 'bg-light text-dark',
  };
  $panelUrl = url('/admin/privacy/description/' . $ioId . '/redaction');
@endphp

<div class="ahg-privacy-embed" style="position:fixed;bottom:1rem;right:1rem;z-index:1050;max-width:380px;">
  <div class="card shadow border-primary">
    <div class="card-header d-flex justify-content-between align-items-center"
         style="background:var(--ahg-primary,#0d6efd);color:#fff;cursor:pointer;"
         data-bs-toggle="collapse" data-bs-target="#ahgPrivacyEmbedBody"
         role="button" aria-expanded="false" aria-controls="ahgPrivacyEmbedBody">
      <span class="fw-bold"><i class="fas fa-user-shield me-1"></i> {{ __('Field redaction') }}</span>
      <span class="badge {{ $statusBadge }}">{{ ucfirst($status) }}@if($fieldCount) - {{ $fieldCount }}@endif</span>
    </div>
    <div id="ahgPrivacyEmbedBody" class="collapse">
      <div class="card-body p-2 small">
        @if($activeDsar)
          <div class="alert alert-info py-1 px-2 mb-2 small">
            <i class="fas fa-info-circle me-1"></i>{{ __('A data subject access request is currently processing.') }}
          </div>
        @endif

        @if($fieldCount)
          <div class="text-muted mb-1">{{ __('Redacted fields:') }}</div>
          <ul class="list-unstyled mb-2">
            @foreach($fields as $f)
              <li class="d-flex justify-content-between border-bottom py-1">
                <code class="small">{{ $f->field_name }}</code>
                <span class="badge bg-secondary">{{ $f->redaction_type }}</span>
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted mb-2">{{ __('No fields are redacted on this description yet.') }}</p>
        @endif

        <a href="{{ $panelUrl }}" class="btn btn-sm btn-primary w-100">
          <i class="fas fa-pen me-1"></i>{{ __('Manage field redaction') }}
        </a>
      </div>
    </div>
  </div>
</div>
