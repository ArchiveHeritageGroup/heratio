@php decorate_with('layout_1col.php'); @endphp
@php slot('title'); @endphp

  <h1>
    {{ __('Hierarchy') }}
    <span id="fullwidth-treeview-activity-indicator">
      <i class="fas fa-spinner fa-spin ms-2" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Loading ...') }}</span>
    </span>
  </h1>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <input type="button" id="fullwidth-treeview-reset-button" class="btn atom-btn-white" value="{{ __('Reset') }}" />
    <input type="button" id="fullwidth-treeview-more-button" class="btn atom-btn-white" data-label="{{ __('%1% more') }}" value="" />
  </div>

@php end_slot(); @endphp

@php slot('content'); @endphp

<div id='main-column'>
  <span id="fullwidth-treeview-configuration" data-items-per-page="@php echo $itemsPerPage; @endphp"></span>
</div>

@php end_slot(); @endphp
