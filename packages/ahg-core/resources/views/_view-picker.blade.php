<div class="btn-group btn-group-sm" role="group" aria-label="{{ __('View options') }}">
  <a
    class="btn atom-btn-white text-wrap@php echo $view === $cardView ? ' active' : ''; @endphp"
    @php echo $view === $cardView ? 'aria-current="page"' : ''; @endphp
    href="@php echo url_for(
        ['module' => $module, 'action' => 'browse', 'view' => $cardView]
        + $sf_data->getRaw('sf_request')->getParameterHolder()->getAll()
    ); @endphp">
    <i class="fas fa-th-large me-1" aria-hidden="true"></i>
    {{ __('Card view') }}
  </a>
  <a
    class="btn atom-btn-white text-wrap@php echo $view === $tableView ? ' active' : ''; @endphp"
    @php echo $view === $tableView ? 'aria-current="page"' : ''; @endphp
    href="@php echo url_for(
        ['module' => $module, 'action' => 'browse', 'view' => $tableView]
        + $sf_data->getRaw('sf_request')->getParameterHolder()->getAll()
    ); @endphp">
    <i class="fas fa-list me-1" aria-hidden="true"></i>
    {{ __('Table view') }}
  </a>
</div>
