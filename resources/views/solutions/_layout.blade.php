{{-- Shared frame for the H03 solution pages. A page sets: title (via $meta),
     section('intro'), and the controller supplies $capabilities
     plus optional $demoLinks = [[label, url], ...]. Every capability listed must
     be in a tagged release: they come from App\Support\CapabilityMatrix, RELEASED rows only (H04). --}}
@extends('theme::layouts.1col')

@section('title', __($meta[0]).' - Heratio')

@section('title-block')
  <h1>{{ __($meta[0]) }}</h1>
  <p class="lead mb-1">{{ __($meta[1]) }}</p>
  @php
    $heratioVersion = json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '';
  @endphp
  <p class="small text-muted mb-3">
    {{ __('Open-source GLAM and archival management software') }} &middot;
    {{ __('Everything on this page is in the current release') }}@if($heratioVersion !== '') ({{ $heratioVersion }})@endif.
  </p>
@endsection

@section('content')
  <div class="row g-4">
    <div class="col-lg-8">
      <section class="mb-4">
        @yield('intro')
      </section>

      <section class="mb-4">
        <h2 class="h4">{{ __('What it does') }}</h2>
        <table class="table table-sm align-top">
          <thead><tr><th scope="col">{{ __('Capability') }}</th><th scope="col">{{ __('What it gives you') }}</th></tr></thead>
          <tbody>
            @foreach($capabilities as [$name, $what, $standard, $note])
              <tr>
                <th scope="row" class="fw-semibold">{{ __($name) }}@if($standard)<br><span class="small text-muted fw-normal">{{ $standard }}</span>@endif</th>
                <td>{{ __($what) }}@if($note)<br><span class="small text-muted">{{ __($note) }}</span>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p class="small"><a href="{{ route('solutions.capabilities') }}">{{ __('See every capability, including those in preview or planned') }}</a></p>
      </section>

      @hasSection('more')
        <section class="mb-4">@yield('more')</section>
      @endif
    </div>

    <aside class="col-lg-4">
      @if(!empty($demoLinks))
        <div class="card mb-3">
          <div class="card-body">
            <h2 class="h6">{{ __('See it in the live demo') }}</h2>
            <ul class="list-unstyled mb-0 small">
              @foreach($demoLinks as [$label, $url])
                <li class="mb-1"><a href="{{ $url }}">{{ __($label) }}</a></li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif
      <div class="card mb-3">
        <div class="card-body small">
          <h2 class="h6">{{ __('Get started') }}</h2>
          <p class="mb-2">{{ __('Heratio is free under the AGPL-3.0 licence. Install it yourself, or ask The Archive and Heritage Group to host it for you.') }}</p>
          <a class="btn btn-sm btn-primary mb-1" href="https://github.com/ArchiveHeritageGroup/heratio#installation" rel="noopener">{{ __('Install') }}</a>
          <a class="btn btn-sm btn-outline-secondary mb-1" href="https://github.com/ArchiveHeritageGroup/heratio" rel="noopener">{{ __('Source on GitHub') }}</a>
        </div>
      </div>
      <nav class="card" aria-label="{{ __('Other solutions') }}">
        <div class="card-body small">
          <h2 class="h6">{{ __('Other solutions') }}</h2>
          <ul class="list-unstyled mb-0">
            @foreach($pages as $slug => [$t, $s])
              @if($slug !== $page)
                <li class="mb-1"><a href="{{ route('solutions.show', $slug) }}">{{ __($t) }}</a></li>
              @endif
            @endforeach
          </ul>
        </div>
      </nav>
    </aside>
  </div>
@endsection
