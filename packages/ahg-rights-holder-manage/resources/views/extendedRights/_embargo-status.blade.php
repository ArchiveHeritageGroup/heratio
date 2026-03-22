@php
/**
 * Embargo Status Warning Banner
 */
$embargo = $embargo ?? null;
if (!$embargo) return;

$typeLabels = [
    'full' => 'This record is restricted',
    'metadata_only' => 'Digital content is restricted',
    'digital_only' => 'Digital files are restricted',
];
$message = $typeLabels[$embargo->embargo_type ?? 'full'] ?? 'Access restricted';
@endphp

<div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
  <i class="fas fa-lock fa-2x me-3"></i>
  <div>
    <strong>{{ $message }}</strong>
    @if(($embargo->end_date ?? null) && !($embargo->is_perpetual ?? false))
      <br><small>Available from: {{ $embargo->end_date }}</small>
    @endif
    @if($embargo->public_message ?? null)
      <br><small>{{ $embargo->public_message }}</small>
    @endif
  </div>
</div>
