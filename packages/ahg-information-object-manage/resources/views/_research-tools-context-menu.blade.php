@php /**
 * Research Tools Context Menu - shown on information object view for approved researchers.
 * Provides links to Source Assessment, Annotation Studio, and Add to Collection.
 *
 * @param QubitInformationObject $resource  The information object
 */
$showResearch = false;
$researcherId = null;
if (auth()->check()) {
    // Admins always see research tools
    if (auth()->user()?->is_admin) {
        $showResearch = true;
    }
    try {
        $userId = auth()->id();
        $researcher = \Illuminate\Support\Facades\DB::table('research_researcher')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->first();
        if ($researcher) {
            $showResearch = true;
            $researcherId = $researcher->id;
        }
    } catch (Exception $e) {
        // research_researcher table may not exist
    }
} @endphp
@if($showResearch)
<section class="sidebar-section">
  <h4>{{ __('Research Tools') }}</h4>
  <ul class="list-unstyled">
    <li>
      <a href="/research/source-assessment/{{ $resource->id }}">
        <i class="fas fa-clipboard-check me-1"></i>{{ __('Source Assessment') }}
      </a>
    </li>
    <li>
      <a href="/research/annotation-studio/{{ $resource->id }}">
        <i class="fas fa-highlighter me-1"></i>{{ __('Annotation Studio') }}
      </a>
    </li>
    <li>
      <a href="/research/trust-score/{{ $resource->id }}">
        <i class="fas fa-star-half-alt me-1"></i>{{ __('Trust Score') }}
      </a>
    </li>
    <li>
      <a href="{{ route('research.dashboard') }}">
        <i class="fas fa-graduation-cap me-1"></i>{{ __('Research Dashboard') }}
      </a>
    </li>
  </ul>
</section>
@endif
