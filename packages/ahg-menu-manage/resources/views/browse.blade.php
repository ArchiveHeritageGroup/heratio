@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Site menu list') }}
    </h1>
    <span class="small" id="heading-label">
      {{ __('Hierarchical list of menus for the site, first column') }}
    </span>
  </div>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>{{ __('Name') }}</th>
          <th>{{ __('Label') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($tree as $index => $item)
          <tr>
            <td @if(($item['parent_id'] ?? null) == \AhgMenuManage\Services\MenuService::ROOT_ID) class="fw-bold" @endif>

              {!! str_repeat('&nbsp;&nbsp;', max(0, ($item['depth'] ?? 0) - 1)) !!}

              @php
                // Determine prev/next siblings
                $prevSibling = null;
                $nextSibling = null;
                $currentParent = $item['parent_id'] ?? null;
                // Look backwards for previous sibling
                for ($p = $index - 1; $p >= 0; $p--) {
                    $pParent = $tree[$p]['parent_id'] ?? null;
                    if ($pParent == $currentParent) {
                        $prevSibling = $tree[$p]['id'];
                        break;
                    }
                    // If we've gone above our depth level, stop
                    if (($tree[$p]['depth'] ?? 0) < ($item['depth'] ?? 0)) break;
                }
                // Look forwards for next sibling
                for ($n = $index + 1; $n < count($tree); $n++) {
                    $nParent = $tree[$n]['parent_id'] ?? null;
                    if ($nParent == $currentParent) {
                        $nextSibling = $tree[$n]['id'];
                        break;
                    }
                    if (($tree[$n]['depth'] ?? 0) < ($item['depth'] ?? 0)) break;
                }
              @endphp

              @if($prevSibling)
                <a href="{{ route('menu.moveUp', $item['id']) }}"
                   title="{{ __('Move item up in list') }}"
                   onclick="event.preventDefault(); document.getElementById('move-up-{{ $item['id'] }}').submit();">
                  <i class="fas fa-arrow-up fa-sm"></i>
                </a>
                <form id="move-up-{{ $item['id'] }}" action="{{ route('menu.moveUp', $item['id']) }}" method="POST" style="display:none;">@csrf</form>
              @else
                &nbsp;&nbsp;
              @endif

              @if($nextSibling)
                <a href="{{ route('menu.moveDown', $item['id']) }}"
                   title="{{ __('Move item down in list') }}"
                   onclick="event.preventDefault(); document.getElementById('move-down-{{ $item['id'] }}').submit();">
                  <i class="fas fa-arrow-down fa-sm"></i>
                </a>
                <form id="move-down-{{ $item['id'] }}" action="{{ route('menu.moveDown', $item['id']) }}" method="POST" style="display:none;">@csrf</form>
              @else
                &nbsp;&nbsp;
              @endif

              @if($item['isProtected'] ?? false)
                <a href="{{ route('menu.edit', $item['id']) }}" class="readOnly" title="{{ __('Edit menu') }}">
                  {{ $item['name'] ?: '[Unnamed]' }}
                </a>
              @else
                <a href="{{ route('menu.edit', $item['id']) }}" title="{{ __('Edit menu') }}">
                  {{ $item['name'] ?: '[Unnamed]' }}
                </a>
              @endif

            </td>
            <td>{{ $item['label'] ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('menu.create') }}">{{ __('Add new') }}</a>
  </section>
@endsection
