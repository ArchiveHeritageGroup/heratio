{{-- H07: install, upgrade, demonstration and security documentation for the running
     release. Links come from SolutionsController::documentation(), pinned to the tag. --}}
@extends('theme::layouts.1col')

@section('title', __('Documentation').' - Heratio')

@section('title-block')
  <h1>{{ __('Documentation') }}</h1>
  @if($version !== '')
    <p class="lead mb-1">{{ __('For Heratio :version, the release this site runs.', ['version' => $version]) }}</p>
    <p class="small text-muted mb-3">{{ __('Every guide below opens at the :tag tag on GitHub, so what you read matches what you install.', ['tag' => 'v'.$version]) }}
      <a href="{{ $releaseUrl }}" rel="noopener">{{ __('Release notes for :version', ['version' => $version]) }}</a></p>
  @endif
@endsection

@section('content')
  <div class="row g-4">
    <div class="col-lg-8">
      @foreach($sections as [$heading, $links])
        <section class="mb-4">
          <h2 class="h4">{{ __($heading) }}</h2>
          <ul class="list-unstyled">
            @foreach($links as [$label, $url, $summary])
              <li class="mb-2"><a href="{{ $url }}" rel="noopener">{{ __($label) }}</a><br><span class="small text-muted">{{ __($summary) }}</span></li>
            @endforeach
          </ul>
        </section>
      @endforeach

      <section class="mb-4">
        <h2 class="h4">{{ __('See it working') }}</h2>
        <ul class="list-unstyled">
          @if($tour)
            <li class="mb-2"><a href="{{ route('solutions.tour') }}">{{ __('Guided tour of the live demo') }}</a><br><span class="small text-muted">{{ __('Step by step through browsing, a collection tree, 3D, authority records, editing and a virtual exhibition.') }}</span></li>
          @endif
          <li class="mb-2"><a href="{{ route('solutions.capabilities') }}">{{ __('Capabilities') }}</a><br><span class="small text-muted">{{ __('Everything Heratio can do, marked released, preview or planned, with a link that shows each one.') }}</span></li>
        </ul>
      </section>
    </div>

    <aside class="col-lg-4">
      <div class="card">
        <div class="card-body small">
          <h2 class="h6">{{ __('How the quickstart is checked') }}</h2>
          <p class="mb-0">{{ __('Every release tag triggers a clean install on a new GitHub runner with MySQL 8 and Elasticsearch 8. The run fails if the schema is incomplete or the installed site does not serve its home, sign-in and browse pages.') }}</p>
        </div>
      </div>
    </aside>
  </div>
@endsection
