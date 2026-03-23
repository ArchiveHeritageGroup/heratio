{{-- ACL navigation menu for user permissions pages --}}
@php
  $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName();
  $aclPages = [
      'user.indexActorAcl' => __('Actor permissions'),
      'user.indexInformationObjectAcl' => __('Information object permissions'),
      'user.indexRepositoryAcl' => __('Repository permissions'),
      'user.indexTermAcl' => __('Taxonomy permissions'),
  ];
@endphp
<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
    @foreach($aclPages as $routeName => $label)
      @php
        $isActive = ($currentRoute === $routeName);
        $classes = 'btn atom-btn-white active-primary text-wrap';
        if ($isActive) {
            $classes .= ' active';
        }
      @endphp
      <li class="nav-item">
        <a href="{{ route($routeName, ['slug' => $user->slug]) }}"
           class="{{ $classes }}"
           @if($isActive) aria-current="page" @endif>
          {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</nav>
