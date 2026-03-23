@php /**
 * Rights Indicator for Browse/Search Results
 * Shows small icons next to titles
 */

if (!isset($doc) || !isset($doc['id'])) {
    return;
}

$objectId = $doc['id'];

// Quick check for rights/embargo
$rights = \Illuminate\Database\Capsule\Manager::table('extended_rights')
    ->where('object_id', $objectId)
    ->select('rights_statement_id', 'creative_commons_id')
    ->first();

$embargo = \Illuminate\Database\Capsule\Manager::table('embargo')
    ->where('object_id', $objectId)
    ->where('is_active', true)
    ->exists(); @endphp

@if($rights || $embargo)
<span class="rights-indicators ms-2">
  @if($embargo)
    <i class="fas fa-lock text-warning" title="{{ __('Under embargo') }}"></i>
  @endif
  @if($rights && $rights->rights_statement_id)
    <i class="fas fa-copyright text-info" title="{{ __('Has rights statement') }}"></i>
  @endif
  @if($rights && $rights->creative_commons_id)
    <i class="fab fa-creative-commons text-success" title="{{ __('CC licensed') }}"></i>
  @endif
</span>
@endif
