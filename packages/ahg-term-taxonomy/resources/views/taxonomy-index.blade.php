@extends('theme::layouts.1col')

@section('title', 'List taxonomies')
@section('body-class', 'browse taxonomy')

@section('content')
  <h1>List taxonomies</h1>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        @foreach($taxonomies as $taxonomy)
          <tr>
            <td>
              <a href="{{ route('term.browse', ['taxonomy' => $taxonomy->id]) }}">
                {{ $taxonomy->name }}
              </a>
            </td>
            <td>{{ $taxonomy->note ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if(isset($pager))
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif
@endsection
