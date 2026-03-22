@php /**
 * Item Physical Location partial (Edit Form)
 * Editor-level access required
 * Include with: include_partial('informationobject/itemPhysicalLocation', ['resource' => $resource, 'itemLocation' => $itemLocation])
 */

// Check editor access
$user = sfContext::getInstance()->getUser();
if (!$user->isAuthenticated()) return;

// Check for editor/admin group membership
$userId = $user->getAttribute('user_id');
if (!$userId) return;

$isEditor = \Illuminate\Database\Capsule\Manager::table('acl_user_group')
    ->where('user_id', $userId)
    ->whereIn('group_id', [100, 101]) // GROUP_ADMINISTRATOR=100, GROUP_EDITOR=101
    ->exists();

if (!$isEditor) return;

$itemLocation = $itemLocation ?? [];
$accordionParent = $accordionParent ?? 'editFormAccordion';
$culture = sfContext::getInstance()->getUser()->getCulture();

// Get physical object options for dropdown
$physicalObjects = [];
$poResult = \Illuminate\Database\Capsule\Manager::table('physical_object as po')
    ->leftJoin('physical_object_i18n as poi', function($join) use ($culture) {
        $join->on('poi.id', '=', 'po.id')->where('poi.culture', '=', $culture);
    })
    ->select(['po.id', 'poi.name', 'poi.location'])
    ->orderBy('poi.name')
    ->get();
foreach ($poResult as $po) {
    $physicalObjects[$po->id] = $po->name . ($po->location ? ' (' . $po->location . ')' : '');
} @endphp
<div class="accordion-item">
  <h2 class="accordion-header" id="heading-physical-location">
    <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapse-physical-location"
            aria-expanded="false" aria-controls="collapse-physical-location"
            style="background-color: var(--ahg-primary, #005837) !important; color: #fff !important;">
      {{ __('Item Physical Location') }}
      <span class="cco-chapter">{{ __('Storage & Access') }}</span>
    </button>
  </h2>
  <div id="collapse-physical-location" class="accordion-collapse collapse"
       aria-labelledby="heading-physical-location" data-bs-parent="#@php echo $accordionParent; @endphp">
    <div class="accordion-body">
    <!-- Container Link -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">{{ __('Storage container') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="item_physical_object_id" class="form-select">
          <option value="">{{ __('-- Select container --') }}</option>
          @php foreach ($physicalObjects as $id => $name): @endphp
            <option value="@php echo $id; @endphp" @php echo (($itemLocation['physical_object_id'] ?? '') == $id) ? 'selected' : ''; @endphp>
              @php echo esc_entities($name); @endphp
            </option>
          @php endforeach; @endphp
        </select>
        <small class="form-text text-muted">{{ __('Link to a physical storage container') }}</small>
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Item barcode') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_barcode" class="form-control" value="@php echo esc_entities($itemLocation['barcode'] ?? ''); @endphp">
      </div>
    </div>

    <!-- Location within container -->
    <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-box me-2"></i>{{ __('Location within container') }}</h6>
    <div class="row mb-3">
      <div class="col-md-2">
        <label class="form-label">{{ __('Box') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_box_number" class="form-control" value="@php echo esc_entities($itemLocation['box_number'] ?? ''); @endphp">
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Folder') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_folder_number" class="form-control" value="@php echo esc_entities($itemLocation['folder_number'] ?? ''); @endphp">
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Shelf') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_shelf" class="form-control" value="@php echo esc_entities($itemLocation['shelf'] ?? ''); @endphp">
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Row') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_row" class="form-control" value="@php echo esc_entities($itemLocation['row'] ?? ''); @endphp">
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Position') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_position" class="form-control" value="@php echo esc_entities($itemLocation['position'] ?? ''); @endphp">
      </div>
      <div class="col-md-2">
        <label class="form-label">{{ __('Item #') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_item_number" class="form-control" value="@php echo esc_entities($itemLocation['item_number'] ?? ''); @endphp">
      </div>
    </div>

    <!-- Extent -->
    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">{{ __('Extent value') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="number" step="0.01" name="item_extent_value" class="form-control" value="@php echo esc_entities($itemLocation['extent_value'] ?? ''); @endphp">
      </div>
      <div class="col-md-3">
        <label class="form-label">{{ __('Extent unit') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="item_extent_unit" class="form-select">
          <option value="">{{ __('-- Select --') }}</option>
          @php $units = ['items' => __('Items'), 'pages' => __('Pages'), 'folders' => __('Folders'), 'boxes' => __('Boxes'), 'cm' => __('cm'), 'm' => __('metres'), 'cubic_m' => __('cubic metres')];
          foreach ($units as $val => $label): @endphp
            <option value="@php echo $val; @endphp" @php echo (($itemLocation['extent_unit'] ?? '') == $val) ? 'selected' : ''; @endphp>@php echo $label; @endphp</option>
          @php endforeach; @endphp
        </select>
      </div>
    </div>

    <!-- Condition & Status -->
    <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-clipboard-check me-2"></i>{{ __('Condition & Status') }}</h6>
    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">{{ __('Condition') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="item_condition_status" class="form-select">
          <option value="">{{ __('-- Select --') }}</option>
          @php $conditions = ['excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor'), 'critical' => __('Critical')];
          foreach ($conditions as $val => $label): @endphp
            <option value="@php echo $val; @endphp" @php echo (($itemLocation['condition_status'] ?? '') == $val) ? 'selected' : ''; @endphp>@php echo $label; @endphp</option>
          @php endforeach; @endphp
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">{{ __('Access status') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="item_access_status" class="form-select">
          @php $statuses = ['available' => __('Available'), 'in_use' => __('In Use'), 'restricted' => __('Restricted'), 'offsite' => __('Offsite'), 'missing' => __('Missing')];
          foreach ($statuses as $val => $label): @endphp
            <option value="@php echo $val; @endphp" @php echo (($itemLocation['access_status'] ?? 'available') == $val) ? 'selected' : ''; @endphp>@php echo $label; @endphp</option>
          @php endforeach; @endphp
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">{{ __('Condition notes') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="item_condition_notes" class="form-control" value="@php echo esc_entities($itemLocation['condition_notes'] ?? ''); @endphp">
      </div>
    </div>

    <!-- Notes -->
    <div class="row mb-3">
      <div class="col-md-12">
        <label class="form-label">{{ __('Location notes') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="item_location_notes" class="form-control" rows="2">@php echo esc_entities($itemLocation['notes'] ?? ''); @endphp</textarea>
      </div>
    </div>
    </div>
  </div>
</div>
