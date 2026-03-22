@if(($rights ?? null) || ($embargo ?? null))

@if($embargo ?? null)
@php
$embargoTypes = [
    'full' => 'Full Access Restriction',
    'metadata_only' => 'Digital Content Restricted',
    'digital_object' => 'Download Restricted',
    'custom' => 'Access Restricted',
];
$embargoType = $embargoTypes[$embargo->embargo_type ?? 'full'] ?? 'Access Restricted';
@endphp
<div class="alert alert-warning border-warning mb-3">
  <div class="d-flex align-items-start">
    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
    <div>
      <h5 class="alert-heading mb-1">{{ $embargoType }}</h5>
      @if(!empty($embargo->lift_reason))
        <p class="mb-1">{{ $embargo->lift_reason }}</p>
      @else
        <p class="mb-1">Access to this material is currently restricted.</p>
      @endif
      @if(($embargo->auto_release ?? false) && ($embargo->end_date ?? null))
        <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>Available from: {{ date('j F Y', strtotime($embargo->end_date)) }}</small>
      @elseif(!($embargo->auto_release ?? true))
        <small class="text-muted"><i class="fas fa-lock me-1"></i>Indefinite restriction</small>
      @endif
    </div>
  </div>
</div>
@endif

@if($rights ?? null)
{{-- Rights Statement --}}
@if($rights->rs_code ?? null)
<h4 class="h5 mt-3 mb-2 text-muted">Rights Statement</h4>
<div class="field row g-0">
  <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Statement</h3>
  <div class="col-9 p-2">
    {{ $rights->rs_name ?? '' }}
    @if($rights->rs_uri ?? null)
      <a href="{{ $rights->rs_uri }}" target="_blank"><i class="fas fa-external-link-alt"></i></a>
    @endif
  </div>
</div>
@endif

{{-- Creative Commons --}}
@if($rights->cc_code ?? null)
<h4 class="h5 mt-3 mb-2 text-muted">License</h4>
<div class="field row g-0">
  <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">License</h3>
  <div class="col-9 p-2">
    {{ $rights->cc_name ?? '' }}
    @if($rights->cc_uri ?? null)
      <a href="{{ $rights->cc_uri }}" target="_blank"><i class="fas fa-external-link-alt"></i></a>
    @endif
  </div>
</div>
@endif

{{-- TK Labels --}}
@if(!empty($rights->tk_labels) && count($rights->tk_labels) > 0)
<h4 class="h5 mt-3 mb-2 text-muted">Traditional Knowledge Labels</h4>
@foreach($rights->tk_labels as $tk)
<div class="field row g-0">
  <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $tk->category_name ?? 'TK Label' }}</h3>
  <div class="col-9 p-2">
    {{ $tk->name ?? '' }}
    @if($tk->uri ?? null)
      <a href="{{ $tk->uri }}" target="_blank"><i class="fas fa-external-link-alt"></i></a>
    @endif
  </div>
</div>
@endforeach
@endif

@endif
@endif
