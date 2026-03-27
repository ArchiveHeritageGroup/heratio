@php /**
 * Access Header - Shows classification and restriction badges
 * Include at top of ISAD detail view: @include('ahg-information-object-manage::_access-header', ['resource' => $resource])
 */

if (!isset($resource) || !$resource->id) return;

$userId = auth()->check() ? auth()->id() : null;
$service = \AtomExtensions\Services\Access\AccessFilterService::getInstance();
$access = $service->checkAccess($resource->id, $userId);

// Only show if there are restrictions
if (empty($access['classification']) && empty($access['restrictions']) && empty($access['embargo'])) {
    return;
} @endphp

<div class="access-header mb-3 border-bottom pb-2">
    <div class="d-flex flex-wrap align-items-center gap-2">

        @if(!empty($access['classification']))
            @php $classCode = $access['classification']['code'];
            $classColors = [
                'PUBLIC' => 'success',
                'INTERNAL' => 'info',
                'CONFIDENTIAL' => 'primary',
                'SECRET' => 'warning',
                'TOP_SECRET' => 'danger',
            ];
            $color = $classColors[$classCode] ?? 'secondary'; @endphp
            <span class="badge bg-{{ $color }} fs-6">
                <i class="fas fa-shield-alt me-1"></i>
                {{ esc_entities($access['classification']['name']) }}
            </span>
        @endif

        @if(!empty($access['donor_restrictions']))
            @foreach($access['donor_restrictions'] as $r)
                <span class="badge bg-warning text-dark"
                      title="{{ esc_entities($r['message'] ?? '') }}">
                    <i class="fas fa-user-shield me-1"></i>
                    {{ esc_entities($r['donor'] ?? 'Donor Restriction') }}
                </span>
            @endforeach
        @endif

        @if(!empty($access['embargo']))
            <span class="badge bg-secondary">
                <i class="fas fa-clock me-1"></i>
                Embargoed until {{ date('M j, Y', strtotime($access['embargo']['end_date'])) }}
            </span>
        @endif

        @if($access['level'] === 'metadata_only')
            <span class="badge bg-info text-dark">
                <i class="fas fa-file-alt me-1"></i>
                Metadata Only
            </span>
        @endif

    </div>
</div>

@if(!$access['granted'])
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Access Restricted:</strong>
    @php $messages = [];
    foreach ($access['reasons'] as $reason) {
        switch ($reason) {
            case 'classification':
                $messages[] = 'Security clearance required';
                break;
            case 'donor_restriction':
                $messages[] = 'Donor restrictions apply';
                break;
            case 'embargo':
                $messages[] = 'Material is embargoed';
                break;
        }
    }
    echo implode('. ', $messages); @endphp
</div>
@endif
