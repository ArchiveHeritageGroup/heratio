@extends('theme::layouts.1col')
@section('title', 'Static pages')
@section('body-class', 'admin staticpage')

@section('content')
  <h1>List static pages</h1>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pages as $page)
          <tr>
            <td><a href="{{ url('/pages/' . $page->slug . '/edit') }}">{{ $page->title }}</a></td>
            <td>{{ $page->slug }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @auth
    <section class="actions mb-3">
      <a class="btn atom-btn-outline-light" href="{{ url('/staticpage/add') }}" title="Add new">Add new</a>
    </section>
  @endauth
@endsection
