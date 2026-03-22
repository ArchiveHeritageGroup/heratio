@php
/**
 * Embargo Block Partial
 * Shows embargo message when content is restricted
 */
$objectId = $objectId ?? null;
$type = $type ?? 'record';
if (!$objectId) return;

$embargoInfo = $embargoInfo ?? null;
if (!$embargoInfo) return;

$typeMessages = [
    'record' => 'This record is currently under embargo and not available for public viewing.',
    'metadata' => 'The metadata for this record is restricted.',
    'thumbnail' => 'Preview images are not available for this record.',
    'digital_object' => 'Digital content for this record is restricted.',
    'download' => 'Downloads are not available for this record.',
];
$message = $embargoInfo['public_message'] ?? $typeMessages[$type] ?? $typeMessages['record'];
@endphp
<div class="alert alert-warning border-warning mb-3 embargo-notice">
    <div class="d-flex align-items-start">
        <i class="fas fa-lock fa-2x me-3 text-warning"></i>
        <div>
            <h5 class="alert-heading mb-1">{{ $embargoInfo['type_label'] ?? 'Access Restricted' }}</h5>
            <p class="mb-1">{{ $message }}</p>
            @if(!($embargoInfo['is_perpetual'] ?? false) && ($embargoInfo['end_date'] ?? null))
                <small class="text-muted">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Available from: {{ date('j F Y', strtotime($embargoInfo['end_date'])) }}
                </small>
            @elseif($embargoInfo['is_perpetual'] ?? false)
                <small class="text-muted">
                    <i class="fas fa-ban me-1"></i> Indefinite restriction
                </small>
            @endif
        </div>
    </div>
</div>
