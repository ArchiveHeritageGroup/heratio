@extends('theme::layouts.1col')

@section('title', 'Clipboard')
@section('body-class', 'clipboard user')

@section('content')
  <h1>{{ __('Clipboard') }}</h1>

  @if(count($items))
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Item') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Saved') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td>
                @if(!empty($item->slug))
                  <a href="{{ url($item->slug) }}">{{ $item->slug }}</a>
                @else
                  {{ __('[Untitled]') }}
                @endif
              </td>
              <td>{{ $item->item_class_name ?? '' }}</td>
              <td>{{ $item->created_at ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p>{{ __('Your clipboard is empty.') }}</p>
  @endif
@endsection
