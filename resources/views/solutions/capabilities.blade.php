{{-- H04: every capability with a Released, Preview or Planned badge and a proof link.
     Data: App\Support\CapabilityMatrix::rows(). --}}
@extends('theme::layouts.1col')

@section('title', __('Capabilities').' - Heratio')

@section('title-block')
  <h1>{{ __('Capabilities') }}</h1>
  @php
    $heratioVersion = json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '';
    $badge = [
      'released' => ['bg-success', __('Released')],
      'preview' => ['bg-warning text-dark', __('Preview')],
      'planned' => ['bg-secondary', __('Planned')],
    ];
    $counts = collect($rows)->countBy(fn ($r) => $r[4]);
  @endphp
  <p class="lead mb-1">{{ __('What Heratio can do, and how finished each part is.') }}</p>
  <p class="small mb-3">
    <span class="badge {{ $badge['released'][0] }}">{{ $badge['released'][1] }}</span> {{ __('in the current release') }}@if($heratioVersion !== '') ({{ $heratioVersion }})@endif, {{ $counts['released'] ?? 0 }} &middot;
    <span class="badge {{ $badge['preview'][0] }}">{{ $badge['preview'][1] }}</span> {{ __('in the code but partial, off by default or needing an outside service') }}, {{ $counts['preview'] ?? 0 }} &middot;
    <span class="badge {{ $badge['planned'][0] }}">{{ $badge['planned'][1] }}</span> {{ __('not working yet, tracked in an open issue') }}, {{ $counts['planned'] ?? 0 }}
  </p>
  <p class="small text-muted mb-3">{{ __('Where a capability has an automated test, the Proof column links to it at this release. A test shows the capability is checked on every change; it is not an independent certification.') }}</p>
@endsection

@section('content')
  @foreach($pages as $slug => [$title, $summary])
    @php $section = array_values(array_filter($rows, fn ($r) => in_array($slug, $r[0], true))); @endphp
    @if($section)
      <section class="mb-4" id="{{ $slug }}">
        <h2 class="h4"><a href="{{ route('solutions.show', $slug) }}" class="text-decoration-none">{{ __($title) }}</a></h2>
        <table class="table table-sm align-top">
          <thead>
            <tr>
              <th scope="col">{{ __('Capability') }}</th>
              <th scope="col">{{ __('Status') }}</th>
              <th scope="col">{{ __('What it gives you') }}</th>
              <th scope="col">{{ __('Proof') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($section as [$on, $name, $what, $standard, $status, $proof, $note])
              <tr>
                <th scope="row" class="fw-semibold">{{ __($name) }}@if($standard)<br><span class="small text-muted fw-normal">{{ $standard }}</span>@endif</th>
                <td><span class="badge {{ $badge[$status][0] }}">{{ $badge[$status][1] }}</span></td>
                <td>{{ __($what) }}@if($note)<br><span class="small text-muted">{{ __($note) }}</span>@endif</td>
                <td class="small">
                  <a href="{{ \App\Support\CapabilityMatrix::proofUrl($proof) }}">
                    @if($status === 'planned'){{ __('Issue') }}@elseif(str_contains($proof, '/help/article/') || str_contains($proof, 'github.com')){{ __('Documentation') }}@else{{ __('Live demo') }}@endif
                  </a>
                  @if($test = \App\Support\CapabilityMatrix::testUrl($name))
                    <br><a href="{{ $test }}" rel="noopener">{{ __('Automated test') }}</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>
    @endif
  @endforeach
@endsection
