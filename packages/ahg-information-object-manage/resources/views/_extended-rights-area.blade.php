@php /**
 * Extended Rights Area for Information Object View
 *
 * Displays:
 * - RightsStatements.org badge
 * - Creative Commons license
 * - TK Labels
 * - Embargo status
 * - Provenance/donor info (if user has permission)
 */

// Only show if resource exists and is valid
if (!isset($resource)) {
    return;
}

// Get resource ID safely
$resourceId = null;
if ($resource instanceof QubitInformationObject) {
    $resourceId = $resource->id;
} elseif (is_object($resource) && isset($resource->id)) {
    $resourceId = $resource->id;
}

if (!$resourceId) {
    return;
}

// Check if user can see detailed rights
$canSeeDetails = auth()->check();

// Safe ACL check - only if resource is a proper Qubit object
$canEdit = false;
if ($resource instanceof QubitInformationObject) {
    try {
        $canEdit = \AtomExtensions\Services\AclService::check($resource, 'update');
    } catch (Exception $e) {
        $canEdit = false;
    }
} @endphp

@if(function_exists('checkPluginEnabled') && checkPluginEnabled('ahgExtendedRightsPlugin'))
<!-- Extended Rights Display -->
@include('ahg-extended-rights::_rights-display', ['objectId' => $resourceId])
<!-- Provenance Display (authenticated users only) -->
@if($canSeeDetails)
  @include('ahg-extended-rights::_provenance-display', ['objectId' => $resourceId])
@endif
<!-- Embargo Warning (public) -->
@include('ahg-extended-rights::_embargo-status', ['objectId' => $resourceId])
@endif
