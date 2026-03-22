@extends('theme::layouts.1col')

@section('title', 'Site menu list')
@section('body-class', 'browse menus')

@section('title-block')
  <h1>Site menu list</h1>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr style="background:var(--ahg-primary);color:#fff">
          <th>Name</th>
          <th>Label</th>
        </tr>
      </thead>
      <tbody>
        @foreach($tree as $item)
          <tr>
            <td style="padding-left: {{ ($item['depth'] * 1.5) + 0.75 }}rem;">
              <a href="{{ route('menu.show', $item['id']) }}">
                {{ $item['name'] ?: '[Unnamed]' }}
              </a>
            </td>
            <td>{{ $item['label'] ?: '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  <section class="actions mb-3">
    <a class="btn atom-btn-outline-light" href="{{ route('menu.create') }}">Add new</a>
  </section>
@endsection
