@php
/**
 * Item Physical Location View partial
 * Editor-level access required
 */

// Check editor access
if (!auth()->check()) return;

$user = auth()->user();
$isEditor = in_array($user->role ?? '', ['editor', 'administrator']);

if (!$isEditor) return;
if (empty($itemLocation)) return;

$locationParts = array_filter([
    !empty($itemLocation['box_number']) ? __('Box') . ' ' . $itemLocation['box_number'] : null,
    !empty($itemLocation['folder_number']) ? __('Folder') . ' ' . $itemLocation['folder_number'] : null,
    !empty($itemLocation['shelf']) ? __('Shelf') . ' ' . $itemLocation['shelf'] : null,
    !empty($itemLocation['row']) ? __('Row') . ' ' . $itemLocation['row'] : null,
    !empty($itemLocation['position']) ? __('Pos') . ' ' . $itemLocation['position'] : null,
    !empty($itemLocation['item_number']) ? __('Item') . ' ' . $itemLocation['item_number'] : null,
]);

$containerLocationParts = [];
if (!empty($itemLocation['container'])) {
    $c = $itemLocation['container'];
    $containerLocationParts = array_filter([
        $c['building'] ?? null,
        !empty($c['floor']) ? __('Floor') . ' ' . $c['floor'] : null,
        !empty($c['room']) ? __('Room') . ' ' . $c['room'] : null,
        !empty($c['aisle']) ? __('Aisle') . ' ' . $c['aisle'] : null,
        !empty($c['bay']) ? __('Bay') . ' ' . $c['bay'] : null,
        !empty($c['rack']) ? __('Rack') . ' ' . $c['rack'] : null,
        !empty($c['shelf']) ? __('Shelf') . ' ' . $c['shelf'] : null,
    ]);
}

$accessLabels = [
    'available' => __('Available'),
    'in_use' => __('In Use'),
    'restricted' => __('Restricted'),
    'offsite' => __('Offsite'),
    'missing' => __('Missing'),
];

$conditionLabels = [
    'excellent' => __('Excellent'),
    'good' => __('Good'),
    'fair' => __('Fair'),
    'poor' => __('Poor'),
    'critical' => __('Critical'),
]; @endphp

@if(!empty($locationParts))
<div class="field">
  <h3>{{ __('Item location') }}</h3>
  <div><i class="fas fa-folder-open me-1 text-warning"></i>{!! implode(' &gt; ', $locationParts) !!}</div>
</div>
@endif

@if(!empty($itemLocation['container']))
<!-- Storage Container -->
<section class="card mb-3">
  <div class="card-header bg-secondary text-white">
    <h4 class="mb-0"><i class="fas fa-box me-2"></i>{{ __('Storage container') }}</h4>
  </div>
  <div class="card-body">
    @php
    $containerLink = '<a href="' . route('physicalobject.show', $itemLocation['container']['slug'] ?? '') . '">' . e($itemLocation['container']['name'] ?? '') . '</a>';
    if (!empty($itemLocation['container']['location'])) {
        $containerLink .= ' <span class="text-muted">(' . e($itemLocation['container']['location']) . ')</span>';
    }
    @endphp
    <div class="field">
      <h3>{{ __('Container') }}</h3>
      <div>{!! $containerLink !!}</div>
    </div>

    @if(!empty($containerLocationParts))
    <div class="field">
      <h3>{{ __('Container location') }}</h3>
      <div><i class="fas fa-building me-1 text-primary"></i>{!! implode(' &gt; ', $containerLocationParts) !!}</div>
    </div>
    @endif

    @if(!empty($itemLocation['container']['barcode']))
    <div class="field">
      <h3>{{ __('Container barcode') }}</h3>
      <div><code><i class="fas fa-barcode me-1"></i>{{ $itemLocation['container']['barcode'] }}</code></div>
    </div>
    @endif

    @if(!empty($itemLocation['container']['security_level']))
    <div class="field">
      <h3>{{ __('Security level') }}</h3>
      <div><span class="badge bg-danger"><i class="fas fa-lock me-1"></i>{{ ucfirst($itemLocation['container']['security_level']) }}</span></div>
    </div>
    @endif
  </div>
</section>
@endif

<!-- Item Details -->
@php $hasItemDetails = !empty($itemLocation['barcode']) || !empty($itemLocation['box_number']) ||
    !empty($itemLocation['folder_number']) || !empty($itemLocation['shelf']) ||
    !empty($itemLocation['row']) || !empty($itemLocation['position']) ||
    !empty($itemLocation['item_number']) || !empty($itemLocation['extent_value']); @endphp
@if($hasItemDetails)
<section class="card mb-3">
  <div class="card-header bg-info text-white">
    <h4 class="mb-0"><i class="fas fa-archive me-2"></i>{{ __('Item details') }}</h4>
  </div>
  <div class="card-body">
    @if(!empty($itemLocation['barcode']))
    <div class="field">
      <h3>{{ __('Item barcode') }}</h3>
      <div><code><i class="fas fa-barcode me-1"></i>{{ $itemLocation['barcode'] }}</code></div>
    </div>
    @endif

    @if(!empty($itemLocation['box_number']))
    <div class="field">
      <h3>{{ __('Box') }}</h3>
      <div>{{ $itemLocation['box_number'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['folder_number']))
    <div class="field">
      <h3>{{ __('Folder') }}</h3>
      <div>{{ $itemLocation['folder_number'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['shelf']))
    <div class="field">
      <h3>{{ __('Shelf') }}</h3>
      <div>{{ $itemLocation['shelf'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['row']))
    <div class="field">
      <h3>{{ __('Row') }}</h3>
      <div>{{ $itemLocation['row'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['position']))
    <div class="field">
      <h3>{{ __('Position') }}</h3>
      <div>{{ $itemLocation['position'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['item_number']))
    <div class="field">
      <h3>{{ __('Item number') }}</h3>
      <div>{{ $itemLocation['item_number'] }}</div>
    </div>
    @endif

    @if(!empty($itemLocation['extent_value']))
    <div class="field">
      <h3>{{ __('Extent') }}</h3>
      <div>{{ $itemLocation['extent_value'] }} {{ $itemLocation['extent_unit'] ?? '' }}</div>
    </div>
    @endif
  </div>
</section>
@endif

<!-- Status & Condition -->
<section class="card mb-3">
  <div class="card-header bg-warning text-dark">
    <h4 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Status & condition') }}</h4>
  </div>
  <div class="card-body">
    @php $status = $itemLocation['access_status'] ?? 'available';
    $badgeClass = match($status) {
        'available' => 'bg-success',
        'in_use' => 'bg-warning text-dark',
        'restricted' => 'bg-danger',
        'offsite' => 'bg-info',
        'missing' => 'bg-dark',
        default => 'bg-secondary'
    }; @endphp
    <div class="field">
      <h3>{{ __('Access status') }}</h3>
      <div><span class="badge {{ $badgeClass }}">{{ $accessLabels[$status] ?? ucfirst($status) }}</span></div>
    </div>

    @if(!empty($itemLocation['condition_status']))
    @php $condition = $itemLocation['condition_status'];
    $condBadgeClass = match($condition) {
        'excellent' => 'bg-success',
        'good' => 'bg-primary',
        'fair' => 'bg-warning text-dark',
        'poor' => 'bg-orange',
        'critical' => 'bg-danger',
        default => 'bg-secondary'
    }; @endphp
    <div class="field">
      <h3>{{ __('Condition') }}</h3>
      <div><span class="badge {{ $condBadgeClass }}">{{ $conditionLabels[$condition] ?? ucfirst($condition) }}</span></div>
    </div>
    @endif

    @if(!empty($itemLocation['condition_notes']))
    <div class="field">
      <h3>{{ __('Condition notes') }}</h3>
      <div>{!! nl2br(e($itemLocation['condition_notes'])) !!}</div>
    </div>
    @endif

    @if(!empty($itemLocation['last_accessed_at']))
    @php $accessedText = date('Y-m-d H:i', strtotime($itemLocation['last_accessed_at']));
    if (!empty($itemLocation['accessed_by'])) {
        $accessedText .= ' <span class="text-muted">(' . e($itemLocation['accessed_by']) . ')</span>';
    } @endphp
    <div class="field">
      <h3>{{ __('Last accessed') }}</h3>
      <div>{!! $accessedText !!}</div>
    </div>
    @endif

    @if(!empty($itemLocation['notes']))
    <div class="field">
      <h3>{{ __('Notes') }}</h3>
      <div>{!! nl2br(e($itemLocation['notes'])) !!}</div>
    </div>
    @endif
  </div>
</section>
