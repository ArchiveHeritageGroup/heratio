@php /**
 * Item Physical Location View partial
 * Editor-level access required
 * Include with: include_partial('informationobject/itemPhysicalLocationView', ['itemLocation' => $itemLocation])
 */

// Check editor access
$user = sfContext::getInstance()->getUser();
if (!$user->isAuthenticated()) return;

// Check for editor/admin group membership
$userId = $user->getAttribute('user_id');
if (!$userId) return;

$isEditor = \Illuminate\Database\Capsule\Manager::table('acl_user_group')
    ->where('user_id', $userId)
    ->whereIn('group_id', [100, 101])
    ->exists();

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
@php echo render_show(__('Item location'), '<i class="fas fa-folder-open me-1 text-warning"></i>' . implode(' &gt; ', $locationParts)); @endphp
@endif

@if(!empty($itemLocation['container']))
<!-- Storage Container -->
<section class="card mb-3">
  <div class="card-header bg-secondary text-white">
    <h4 class="mb-0"><i class="fas fa-box me-2"></i>{{ __('Storage container') }}</h4>
  </div>
  <div class="card-body">
    @php $containerLink = '<a href="' . url_for(['module' => 'physicalobject', 'slug' => $itemLocation['container']['slug']]) . '">' . esc_entities($itemLocation['container']['name']) . '</a>';
    if (!empty($itemLocation['container']['location'])) {
        $containerLink .= ' <span class="text-muted">(' . esc_entities($itemLocation['container']['location']) . ')</span>';
    }
    echo render_show(__('Container'), $containerLink); @endphp

    @if(!empty($containerLocationParts))
    @php echo render_show(__('Container location'), '<i class="fas fa-building me-1 text-primary"></i>' . implode(' &gt; ', $containerLocationParts)); @endphp
    @endif

    @if(!empty($itemLocation['container']['barcode']))
    @php echo render_show(__('Container barcode'), '<code><i class="fas fa-barcode me-1"></i>' . esc_entities($itemLocation['container']['barcode']) . '</code>'); @endphp
    @endif

    @if(!empty($itemLocation['container']['security_level']))
    @php echo render_show(__('Security level'), '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>' . ucfirst(esc_entities($itemLocation['container']['security_level'])) . '</span>'); @endphp
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
    @php echo render_show(__('Item barcode'), '<code><i class="fas fa-barcode me-1"></i>' . esc_entities($itemLocation['barcode']) . '</code>'); @endphp
    @endif

    @if(!empty($itemLocation['box_number']))
    @php echo render_show(__('Box'), esc_entities($itemLocation['box_number'])); @endphp
    @endif

    @if(!empty($itemLocation['folder_number']))
    @php echo render_show(__('Folder'), esc_entities($itemLocation['folder_number'])); @endphp
    @endif

    @if(!empty($itemLocation['shelf']))
    @php echo render_show(__('Shelf'), esc_entities($itemLocation['shelf'])); @endphp
    @endif

    @if(!empty($itemLocation['row']))
    @php echo render_show(__('Row'), esc_entities($itemLocation['row'])); @endphp
    @endif

    @if(!empty($itemLocation['position']))
    @php echo render_show(__('Position'), esc_entities($itemLocation['position'])); @endphp
    @endif

    @if(!empty($itemLocation['item_number']))
    @php echo render_show(__('Item number'), esc_entities($itemLocation['item_number'])); @endphp
    @endif

    @if(!empty($itemLocation['extent_value']))
    @php echo render_show(__('Extent'), esc_entities($itemLocation['extent_value']) . ' ' . esc_entities($itemLocation['extent_unit'] ?? '')); @endphp
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
    };
    echo render_show(__('Access status'), '<span class="badge ' . $badgeClass . '">' . ($accessLabels[$status] ?? ucfirst($status)) . '</span>'); @endphp

    @if(!empty($itemLocation['condition_status']))
    @php $condition = $itemLocation['condition_status'];
    $condBadgeClass = match($condition) {
        'excellent' => 'bg-success',
        'good' => 'bg-primary',
        'fair' => 'bg-warning text-dark',
        'poor' => 'bg-orange',
        'critical' => 'bg-danger',
        default => 'bg-secondary'
    };
    echo render_show(__('Condition'), '<span class="badge ' . $condBadgeClass . '">' . ($conditionLabels[$condition] ?? ucfirst($condition)) . '</span>'); @endphp
    @endif

    @if(!empty($itemLocation['condition_notes']))
    @php echo render_show(__('Condition notes'), nl2br(esc_entities($itemLocation['condition_notes']))); @endphp
    @endif

    @if(!empty($itemLocation['last_accessed_at']))
    @php $accessedText = date('Y-m-d H:i', strtotime($itemLocation['last_accessed_at']));
    if (!empty($itemLocation['accessed_by'])) {
        $accessedText .= ' <span class="text-muted">(' . esc_entities($itemLocation['accessed_by']) . ')</span>';
    }
    echo render_show(__('Last accessed'), $accessedText); @endphp
    @endif

    @if(!empty($itemLocation['notes']))
    @php echo render_show(__('Notes'), nl2br(esc_entities($itemLocation['notes']))); @endphp
    @endif
  </div>
</section>
