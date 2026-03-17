@extends('theme::layouts.2col')
@section('title', 'Static pages')
@section('body-class', 'admin staticpage')

@section('sidebar')
  <div class="sidebar-widget mb-3">
    <h4>Static pages</h4>
    <p class="small text-muted">
      Static pages are custom content pages that appear on your site.
      You can create pages such as About, Contact, Privacy, or any other informational page.
    </p>
    <p class="small text-muted">
      Pages with the slugs <strong>home</strong>, <strong>about</strong>, and <strong>contact</strong>
      are protected and cannot be deleted as they are core to the site.
    </p>
  </div>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <h1>List static pages</h1>

  @php
    $protectedSlugs = ['home', 'about', 'contact'];
  @endphp

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
          @auth
            <th>Actions</th>
          @endauth
        </tr>
      </thead>
      <tbody>
        @foreach($pages as $page)
          <tr>
            <td>
              <a href="{{ url('/pages/' . $page->slug . '/edit') }}">{{ $page->title }}</a>
              @if(in_array($page->slug, $protectedSlugs))
                <i class="fas fa-lock ms-1 text-muted" title="Protected page"></i>
              @endif
            </td>
            <td>{{ $page->slug }}</td>
            @auth
              <td>
                <a href="{{ url('/pages/' . $page->slug . '/edit') }}" class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="fas fa-pencil-alt"></i> Edit
                </a>
                @if(!in_array($page->slug, $protectedSlugs))
                  <form action="{{ route('staticpage.destroy', $page->id) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Are you sure you want to delete this page?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
                @endif
              </td>
            @endauth
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
