<nav>
  <ul class="nav nav-pills mb-3 d-flex gap-2">
  @foreach($groupsMenu as $child)
      @php
        $options = ['class' => 'btn atom-btn-white active-primary text-wrap'];
        $childUrl = $child['url'] ?? '#';
        $childLabel = $child['label'] ?? '';
        $isActive = (request()->url() == $childUrl);
        if ($isActive) {
            $options['class'] .= ' active';
            $options['aria-current'] = 'page';
        }
      @endphp
      <li class="nav-item">
        <a href="{{ $childUrl }}" class="{{ $options['class'] }}" @if($isActive) aria-current="page" @endif>
          {{ $childLabel }}
        </a>
      </li>
    @endforeach
  </ul>
</nav>
