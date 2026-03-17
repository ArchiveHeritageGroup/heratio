@if($lastPage > 1)
<nav>
  <ul class="pagination pagination-sm justify-content-center">
    <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
      <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
    </li>
    @for($i = max(1, $page - 2); $i <= min($lastPage, $page + 2); $i++)
      <li class="page-item {{ $i == $page ? 'active' : '' }}">
        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
      </li>
    @endfor
    <li class="page-item {{ $page >= $lastPage ? 'disabled' : '' }}">
      <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
    </li>
  </ul>
</nav>
@endif
