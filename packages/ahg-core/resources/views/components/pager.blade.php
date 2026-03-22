@if(isset($pager) && $pager->haveToPaginate())
  <nav aria-label="Page navigation">

    <div class="result-count text-center mb-2">
      Results {{ ($pager->getPage() - 1) * $pager->getMaxPerPage() + 1 }}
      to {{ min($pager->getPage() * $pager->getMaxPerPage(), $pager->getNbResults()) }}
      of {{ number_format($pager->getNbResults()) }}
    </div>

    <ul class="pagination justify-content-center">
      {{-- Previous --}}
      @if($pager->getPage() <= 1)
        <li class="page-item disabled">
          <span class="page-link">Previous</span>
        </li>
      @else
        <li class="page-item">
          <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pager->getPreviousPage()]) }}">Previous</a>
        </li>
      @endif

      {{-- Page links --}}
      @php $pageLinks = $pager->getLinks(7); @endphp
      @foreach($pageLinks as $link)
        @if($link == $pager->getPage())
          <li class="page-item active d-none d-sm-block" aria-current="page">
            <span class="page-link">{{ $link }}</span>
          </li>
        @else
          <li class="page-item d-none d-sm-block">
            <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $link]) }}" title="Go to page {{ $link }}">{{ $link }}</a>
          </li>
        @endif
      @endforeach

      {{-- Dots + Last page (if not already shown) --}}
      @if($pager->getLastPage() > 1 && !in_array($pager->getLastPage(), $pageLinks))
        <li class="page-item disabled dots d-none d-sm-block">
          <span class="page-link">&hellip;</span>
        </li>
        <li class="page-item d-none d-sm-block">
          <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pager->getLastPage()]) }}" title="{{ $pager->getLastPage() }}">{{ $pager->getLastPage() }}</a>
        </li>
      @endif

      {{-- Next --}}
      @if($pager->getPage() >= $pager->getLastPage())
        <li class="page-item disabled">
          <span class="page-link">Next</span>
        </li>
      @else
        <li class="page-item">
          <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pager->getNextPage()]) }}" title="Next">Next</a>
        </li>
      @endif
    </ul>
  </nav>
@endif
