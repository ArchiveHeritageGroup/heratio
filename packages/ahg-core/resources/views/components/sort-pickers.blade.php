<div class="dropdown">
  <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-sort" aria-hidden="true"></i>
    {{ $options[request('sort', $default ?? 'lastUpdated')] ?? 'Sort' }}
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    @foreach($options as $key => $label)
      @php
        $params = array_merge(request()->except('page'), ['sort' => $key]);
        $active = request('sort', $default ?? 'lastUpdated') === $key;
      @endphp
      <li>
        <a class="dropdown-item {{ $active ? 'active' : '' }}"
           href="{{ request()->url() }}?{{ http_build_query($params) }}">
          {{ $label }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
