{{--
  Reusable "Take offline" button. Drop into any group view with:
    @include('research::research._take-offline-button', ['source' => 'project', 'id' => $project->id])
  Sources: project | collection | workspace | favorites
--}}
{{-- Single entry point (matching PSIS): every "Take offline" leads to the
     /research/mobile "Work Offline" page, where the researcher picks what to
     take offline and downloads the package. --}}
<a href="{{ route('research.mobileHome') }}" class="btn btn-sm atom-btn-outline-primary"
   title="{{ __('Take your collected records offline to browse and annotate without internet') }}">
  <i class="fas fa-laptop me-1"></i>{{ __('Take offline') }}
</a>
