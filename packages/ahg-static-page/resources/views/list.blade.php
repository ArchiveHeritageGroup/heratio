@extends('theme::layouts.1col')

@section('title', __('List pages'))
@section('body-class', 'admin staticpage list')

@section('content')

  <h1>{{ __('List pages') }}</h1>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>{{ __('Title') }}</th>
          <th>{{ __('Slug') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pages as $page)
          <tr>
            <td>
              <a href="{{ route('staticpage.show', $page->slug) }}">{{ $page->title }}</a>
            </td>
            <td>
              {{ $page->slug }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if(isset($pager))
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif

  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('staticpage.create') }}">{{ __('Add new') }}</a>
  </section>

@endsection
