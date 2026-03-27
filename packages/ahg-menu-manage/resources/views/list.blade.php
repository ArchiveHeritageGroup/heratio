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
          <th>
            {{ __('Name') }}
          </th><th>
            {{ __('Label') }}
          </th>
        </tr>
      </thead>
      <tbody>
        @foreach($menuTree as $item)
          <tr>
            <td @if($item['parent_id'] == \AhgMenuManage\Services\MenuService::ROOT_ID) class="fw-bold" @endif>

              {!! str_repeat('&nbsp;&nbsp;', max(0, $item['depth'] - 1)) !!}

              @if(isset($item['prev']))
                <a href="{{ route('menu.browse', ['move' => $item['id'], 'before' => $item['prev']]) }}" title="{{ __('Move item up in list') }}">
                  <img src="{{ asset('images/up.gif') }}" alt="{{ __('Move up') }}">
                </a>
              @else
                &nbsp;&nbsp;
              @endif

              @if(isset($item['next']))
                <a href="{{ route('menu.browse', ['move' => $item['id'], 'after' => $item['next']]) }}" title="{{ __('Move item down in list') }}">
                  <img src="{{ asset('images/down.gif') }}" alt="{{ __('Move down') }}">
                </a>
              @else
                &nbsp;&nbsp;
              @endif

              @if($item['isProtected'] ?? false)
                <a href="{{ route('menu.edit', $item['id']) }}" class="readOnly" title="{{ __('Edit menu') }}">
                  {{ $item['name'] }}
                </a>
              @else
                <a href="{{ route('menu.edit', $item['id']) }}" title="{{ __('Edit menu') }}">
                  {{ $item['name'] }}
                </a>
              @endif

            </td><td>
              {{ $item['label'] ?? '' }}
            </td>
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
