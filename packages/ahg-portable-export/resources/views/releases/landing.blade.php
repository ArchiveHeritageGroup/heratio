@extends('theme::layouts.1col')
@section('title', $series->title.' - v'.$release->version_number)
@section('body-class', 'portable-release')
@section('content')
<div class="container py-4">
  <h1 class="h3">{{ $series->title }} <span class="text-muted">v{{ $release->version_number }}</span></h1>
  @if ($release->version_number < $newest)
    <div class="alert alert-info">{{ __('A newer version exists.') }} <a href="{{ route('portable-release.landing', ['key' => $series->series_key, 'version' => $newest]) }}">{{ __('Go to v:n', ['n' => $newest]) }}</a></div>
  @endif
  @if ($withheld)
    <div class="alert alert-warning">{{ __('This version is withheld pending review. Its citation remains valid, but the package and change list are not available.') }}</div>
  @endif
  @if ($series->description)<p>{{ $series->description }}</p>@endif

  <dl class="row">
    <dt class="col-sm-3">{{ __('Cite as') }}</dt><dd class="col-sm-9">{{ $series->creator_name }} ({{ \Illuminate\Support\Carbon::parse($release->released_at ?: $release->approved_at)->year }}). {{ $series->title }} (v{{ $release->version_number }}). {{ $series->publisher }}. https://doi.org/{{ $release->version_doi }}</dd>
    <dt class="col-sm-3">{{ __('Version DOI') }}</dt><dd class="col-sm-9"><a href="https://doi.org/{{ $release->version_doi }}">{{ $release->version_doi }}</a></dd>
    <dt class="col-sm-3">{{ __('Concept DOI (newest version)') }}</dt><dd class="col-sm-9"><a href="https://doi.org/{{ $series->concept_doi }}">{{ $series->concept_doi }}</a></dd>
    @if ($release->released_at)<dt class="col-sm-3">{{ __('Released') }}</dt><dd class="col-sm-9">{{ \Illuminate\Support\Carbon::parse($release->released_at)->toDateString() }}</dd>@endif
    <dt class="col-sm-3">{{ __('Records') }}</dt><dd class="col-sm-9">{{ number_format($release->record_count) }}</dd>
    @if ($series->license)<dt class="col-sm-3">{{ __('Licence') }}</dt><dd class="col-sm-9">{{ $series->license }}</dd>@endif
    <dt class="col-sm-3">SHA-256</dt><dd class="col-sm-9 text-break"><code>{{ $release->package_sha256 }}</code></dd>
  </dl>

  @unless ($withheld)
    <p>
      <a class="btn atom-btn-white" href="{{ route('portable-release.file', ['key' => $series->series_key, 'version' => $release->version_number, 'file' => 'package']) }}"><i class="fas fa-download me-1"></i>{{ __('Download package') }} ({{ number_format($release->package_bytes / 1048576, 1) }} MB)</a>
      <a class="btn atom-btn-white" href="{{ route('portable-release.file', ['key' => $series->series_key, 'version' => $release->version_number, 'file' => 'manifest']) }}">{{ __('Manifest') }}</a>
      @if ($changes['changelog_available'])<a class="btn atom-btn-white" href="{{ route('portable-release.file', ['key' => $series->series_key, 'version' => $release->version_number, 'file' => 'changelog']) }}">{{ __('Changelog') }}</a>@endif
    </p>

    <h2 class="h5 mt-4">{{ __('What changed in this version') }}</h2>
    @if ($release->release_notes)<p>{{ $release->release_notes }}</p>@endif
    @if (! empty($changes['first_release']))
      <p>{{ __('First release.') }}</p>
    @else
      <p>{{ __('Added: :a. Withdrawn: :w. Changed: :c.', ['a' => count($changes['added'] ?? []), 'w' => count($changes['removed'] ?? []) + $changes['removed_unlisted'], 'c' => count($changes['changed'] ?? [])]) }}</p>
      @if (! empty($changes['removed']))
        <h3 class="h6">{{ __('Withdrawn records') }}</h3>
        <ul>@foreach (array_slice($changes['removed'], 0, 50) as $r)<li>#{{ $r['id'] }}{{ $r['identifier'] ? ' ('.$r['identifier'].')' : '' }}</li>@endforeach</ul>
      @endif
      @if ($changes['removed_unlisted'] > 0)<p class="text-muted">{{ __('Withdrawn records not listed: :n.', ['n' => $changes['removed_unlisted']]) }}</p>@endif
      @if (! empty($changes['changed']))
        <h3 class="h6">{{ __('Changed records') }}</h3>
        <ul>@foreach (array_slice($changes['changed'], 0, 50) as $r)<li>#{{ $r['id'] }}{{ $r['identifier'] ? ' ('.$r['identifier'].')' : '' }}: {{ implode(', ', $r['fields']) }}</li>@endforeach</ul>
      @endif
      @if (count($changes['removed'] ?? []) > 50 || count($changes['changed'] ?? []) > 50)<p class="text-muted">{{ __('The changelog file has the complete list.') }}</p>@endif
    @endif
    @if (! empty($changes['corrections']))
      <h3 class="h6">{{ __('Corrections') }}</h3>
      <ul>@foreach ($changes['corrections'] as $c)
        <li><strong>{{ $correctionTypes[$c['type']] ?? $c['type'] }}</strong>{{ $c['record_ref'] ? ' - '.$c['record_ref'] : '' }}{{ $c['field'] ? ' - '.$c['field'] : '' }}@if (($c['old_value'] ?? '') !== '' || ($c['new_value'] ?? '') !== ''): {{ __('was') }} "{{ $c['old_value'] }}", {{ __('now') }} "{{ $c['new_value'] }}"@endif. {{ $c['reason'] }}</li>
      @endforeach</ul>
    @endif
  @endunless

  @if ($versions->count() > 1)
    <h2 class="h5 mt-4">{{ __('All versions') }}</h2>
    <ul>@foreach ($versions as $v)<li><a href="{{ route('portable-release.landing', ['key' => $series->series_key, 'version' => $v->version_number]) }}">v{{ $v->version_number }}</a> - {{ $v->version_doi }}</li>@endforeach</ul>
  @endif
</div>
@endsection
