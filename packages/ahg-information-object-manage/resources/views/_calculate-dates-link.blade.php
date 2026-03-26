@if(QubitAcl::check($resource, 'update'))
  <h4 class="h5 mb-2">{{ __('Tasks') }}</h4>
  <ul class="list-unstyled">

    <li>
      <a class="atom-icon-link" href="@php echo url_for([$resource, 'module' => 'informationobject', 'action' => 'calculateDates']); @endphp" title="{{ __("Click 'Calculate dates' to recalculate the start and end dates of a parent-level description. A job runs in the background, accounting for the earliest and most recent dates across all the child descriptions. The results display in the Start and End fields of the edit page.") }}">
        <i class="fas fa-fw fa-calendar me-1" aria-hidden="true">
        </i>{{ __('Calculate dates') }}
      </a>
    </li>

    <li>
      <i class="fas fa-fw fa-clock me-1 text-muted" aria-hidden="true">
      </i>{{ __('Last run:') }} @php echo $lastRun; @endphp
    </li>

  </ul>
@endif
