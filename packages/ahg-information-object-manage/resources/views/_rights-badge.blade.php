@php /**
 * Rights Badge for Digital Objects
 * Shows small rights indicator on thumbnails/viewers
 */

if (!isset($resource) || !$resource->id) {
    return;
}

// Get parent information object
$informationObject = $resource->object ?? null;
if (!$informationObject) {
    return;
}

$culture = sfContext::getInstance()->user->getCulture();

// Get rights
$rights = \Illuminate\Database\Capsule\Manager::table('extended_rights as er')
    ->leftJoin('rights_statement as rs', 'rs.id', '=', 'er.rights_statement_id')
    ->leftJoin('rights_cc_license as cc', 'cc.id', '=', 'er.cc_license_id')
    ->where('er.object_id', $informationObject->id)
    ->select('rs.code as rs_code', 'rs.uri as rs_uri', 'cc.code as cc_code', 'cc.uri as cc_uri', 'cc.icon_url as cc_icon')
    ->first();

if (!$rights) {
    return;
} @endphp

<div class="rights-badge position-absolute bottom-0 end-0 m-2" style="z-index:10;">
  @if($rights->cc_code)
    <a href="@php echo $rights->cc_uri; @endphp" target="_blank" title="{{ __('@php echo $rights->cc_code; @endphp') }}" class="d-inline-block">
      <img src="@php echo $rights->cc_icon; @endphp" alt="@php echo $rights->cc_code; @endphp" style="height:20px;">
    </a>
  @elseif($rights->rs_code)
    <a href="@php echo $rights->rs_uri; @endphp" target="_blank" class="badge bg-dark text-decoration-none" title="{{ __('@php echo $rights->rs_code; @endphp') }}">
      <i class="fas fa-copyright"></i> @php echo $rights->rs_code; @endphp
    </a>
  @endif
</div>
