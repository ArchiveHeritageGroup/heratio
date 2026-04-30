@extends('theme::layouts.2col')
@section('title', 'Static pages')
@section('body-class', 'admin staticpage')

@section('sidebar')
  <div class="sidebar-widget mb-3">
    <h4>{{ __('Static pages') }}</h4>
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
  <h1>{{ __('List pages') }}</h1>

  @php
    $protectedSlugs = ['home', 'about', 'contact'];
  @endphp

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
              <a href="{{ url('/pages/' . $page->slug . '/edit') }}">{{ $page->title }}</a>
              @if(in_array($page->slug, $protectedSlugs))
                <i class="fas fa-lock ms-1 text-muted" title="{{ __('Protected page') }}"></i>
              @endif
            </td>
            <td>{{ $page->slug }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @auth
    <section class="actions mb-3">
      <a class="btn atom-btn-outline-light" href="{{ url('/staticpage/add') }}" title="{{ __('Add new') }}">Add new</a>
    </section>
  @endauth
@endsection
