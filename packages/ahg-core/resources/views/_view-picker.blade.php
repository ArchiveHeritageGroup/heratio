<div class="btn-group btn-group-sm" role="group" aria-label="{{ __('View options') }}">
  <a
    class="btn atom-btn-white text-wrap{{ ($view ?? '') === ($cardView ?? 'card') ? ' active' : '' }}"
    {!! ($view ?? '') === ($cardView ?? 'card') ? 'aria-current="page"' : '' !!}
    href="{{ request()->fullUrlWithQuery(['view' => $cardView ?? 'card']) }}">
    <i class="fas fa-th-large me-1" aria-hidden="true"></i>
    {{ __('Card view') }}
  </a>
  <a
    class="btn atom-btn-white text-wrap{{ ($view ?? '') === ($tableView ?? 'table') ? ' active' : '' }}"
    {!! ($view ?? '') === ($tableView ?? 'table') ? 'aria-current="page"' : '' !!}
    href="{{ request()->fullUrlWithQuery(['view' => $tableView ?? 'table']) }}">
    <i class="fas fa-list me-1" aria-hidden="true"></i>
    {{ __('Table view') }}
  </a>
</div>
