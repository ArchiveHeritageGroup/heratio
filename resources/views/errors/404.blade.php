@extends('theme::layouts.1col')
@section('title', '404 - Page not found')
@section('content')
<div class="text-center">
  <div id="content" class="d-inline-block mt-5 text-start" role="alert">
    <h1 class="h2 mb-0 p-3 border-bottom d-flex align-items-center">
      <i class="fas fa-fw fa-lg fa-times me-3" aria-hidden="true"></i>
      {{ __('Sorry, page not found') }}
    </h1>

    <div class="p-3">
      <p>
        {{ __('Did you type the URL correctly?') }}<br>
        {{ __('Did you follow a broken link?') }}
      </p>

      @if(($themeData['isAdmin'] ?? false))
        <div class="alert alert-warning mt-3 text-start">
          <h6 class="fw-bold mb-2"><i class="fas fa-shield-alt me-1"></i> Admin debug info</h6>
          <table class="table table-sm table-borderless mb-0 small">
            <tr><td class="fw-bold text-nowrap pe-3">URL</td><td><code>{{ request()->fullUrl() }}</code></td></tr>
            <tr><td class="fw-bold text-nowrap pe-3">Method</td><td><code>{{ request()->method() }}</code></td></tr>
            <tr><td class="fw-bold text-nowrap pe-3">IP</td><td>{{ request()->ip() }}</td></tr>
            <tr><td class="fw-bold text-nowrap pe-3">Time</td><td>{{ now()->format('Y-m-d H:i:s') }}</td></tr>
            @if($exception ?? null)
              <tr><td class="fw-bold text-nowrap pe-3">Message</td><td>{{ $exception->getMessage() ?: 'No matching route found' }}</td></tr>
            @endif
            <tr><td class="fw-bold text-nowrap pe-3">Slug lookup</td><td>
              @php
                $path = trim(request()->path(), '/');
                $slugMatch = \Illuminate\Support\Facades\DB::table('slug')->where('slug', $path)->first();
              @endphp
              @if($slugMatch)
                Slug <code>{{ $path }}</code> exists (object_id={{ $slugMatch->object_id }}) but no route handled it.
                @php
                  $obj = \Illuminate\Support\Facades\DB::table('object')->where('id', $slugMatch->object_id)->first();
                @endphp
                @if($obj)
                  <br>Object class: <code>{{ $obj->class_name }}</code>
                @endif
              @else
                No slug <code>{{ $path }}</code> found in the database.
              @endif
            </td></tr>
          </table>
        </div>
      @endif

      <p class="mb-0">
        <a href="javascript:history.go(-1)">
          {{ __('Back to previous page.') }}
        </a><br>
        <a href="{{ url('/') }}">{{ __('Go to homepage.') }}</a>
      </p>
    </div>
  </div>
</div>
@endsection
