@if(isset($pager) && $pager->haveToPaginate())
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
      {{-- Previous --}}
      <li class="page-item {{ $pager->getPage() <= 1 ? 'disabled' : '' }}">
        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pager->getPreviousPage()]) }}"
           aria-label="Previous">
          <span aria-hidden="true">&laquo;</span>
        </a>
      </li>

      {{-- Page links --}}
      @foreach($pager->getLinks(7) as $link)
        <li class="page-item {{ $link == $pager->getPage() ? 'active' : '' }}">
          <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $link]) }}">
            {{ $link }}
          </a>
        </li>
      @endforeach

      {{-- Next --}}
      <li class="page-item {{ $pager->getPage() >= $pager->getLastPage() ? 'disabled' : '' }}">
        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pager->getNextPage()]) }}"
           aria-label="Next">
          <span aria-hidden="true">&raquo;</span>
        </a>
      </li>
    </ul>
  </nav>

  <p class="text-center text-muted small">
    Showing {{ ($pager->getPage() - 1) * $pager->getMaxPerPage() + 1 }}
    to {{ min($pager->getPage() * $pager->getMaxPerPage(), $pager->getNbResults()) }}
    of {{ number_format($pager->getNbResults()) }} results
  </p>
@endif
