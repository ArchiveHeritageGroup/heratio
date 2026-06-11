{{--
  heratio#1205 - "Race against loss" public awareness board. A dignified,
  read-only, anonymous view of the records most at risk of being lost, drawn from
  AhgCore\Services\CapturePriorityService::register() (top-N, public-safe fields
  only). International / jurisdiction-neutral copy. No operator detail, no writes.
--}}
@extends('theme::layouts.1col')
@section('title', __('Race against loss'))

@section('content')
@php
  $report = $report ?? [];
  $rows = $report['rows'] ?? [];
  $maxScore = (int) ($maxScore ?? 0);

  // Public-safe band helper: map a raw score onto a clear High/Medium/Low label.
  $bandFor = function (int $score) use ($maxScore) {
      $pct = $maxScore > 0 ? (int) min(100, round($score / $maxScore * 100)) : 0;
      if ($pct >= 66) { return ['label' => __('High'), 'class' => 'danger', 'pct' => $pct]; }
      if ($pct >= 33) { return ['label' => __('Medium'), 'class' => 'warning', 'pct' => $pct]; }
      return ['label' => __('Low'), 'class' => 'secondary', 'pct' => $pct];
  };

  // Reasons are operator-facing phrasing; present them plainly to the public.
  $reasonLabel = function (string $reason) {
      return $reason;
  };
@endphp

<div class="container py-4">

  <div class="text-center mb-4">
    <h1 class="display-6 mb-2"><i class="fas fa-hourglass-half me-2 text-danger"></i>{{ __('Race against loss') }}</h1>
    <p class="lead text-muted mx-auto" style="max-width:760px">
      {{ __('Heritage is fragile. Some records survive only on a single original carrier, or are showing the first signs of decay. This page highlights the items our collection has identified as most at risk - the ones a digitisation effort should reach first, before they are lost.') }}
    </p>
  </div>

  <div class="row justify-content-center mb-4">
    <div class="col-lg-9">
      <div class="alert alert-light border d-flex align-items-start gap-3" role="note">
        <i class="fas fa-circle-info fs-5 text-primary mt-1"></i>
        <div class="small mb-0">
          {{ __('Each item is flagged from transparent catalogue signals - for example, having no preservation copy yet, a recorded condition of poor or fragile, or a note of decay. The priority shown (High, Medium or Low) reflects how strongly those signals combine. It is an awareness aid, not a verdict, and it reflects only what has been catalogued so far.') }}
        </div>
      </div>
    </div>
  </div>

  @if(empty($rows))
    {{-- Empty-state: nothing scored, or the service could not run. Dignified, never a 500. --}}
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-5">
            <i class="fas fa-circle-check fa-2x text-success mb-3"></i>
            <h2 class="h5">{{ __('Nothing flagged at risk right now') }}</h2>
            <p class="text-muted mb-0 mx-auto" style="max-width:520px">
              {{ __('No records currently meet the at-risk signals, or the collection is still being catalogued. As descriptions and condition assessments are added, any item needing urgent capture will appear here.') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="row g-3">
      @foreach($rows as $r)
        @php
          $band = $bandFor((int) ($r['score'] ?? 0));
          $title = trim((string) ($r['title'] ?? '')) !== '' ? $r['title'] : __('Untitled record');
        @endphp
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <span class="badge bg-{{ $band['class'] }}">
                  {{ __('At risk') }}: {{ $band['label'] }}
                </span>
                <span class="badge bg-light text-dark border" title="{{ __('Priority score') }}">{{ (int) ($r['score'] ?? 0) }}</span>
              </div>

              <h2 class="h6 mb-2">
                @if(!empty($r['slug']))
                  <a href="{{ url('/'.$r['slug']) }}" class="text-decoration-none">{{ $title }}</a>
                @else
                  {{ $title }}
                @endif
              </h2>

              <div class="progress mb-3" style="height:5px" role="progressbar"
                   aria-valuenow="{{ $band['pct'] }}" aria-valuemin="0" aria-valuemax="100"
                   aria-label="{{ __('Risk level') }}">
                <div class="progress-bar bg-{{ $band['class'] }}" style="width: {{ $band['pct'] }}%"></div>
              </div>

              @if(!empty($r['reasons']))
                <div class="text-muted small mb-0 mt-auto">
                  <div class="text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.03em">{{ __('Why it is at risk') }}</div>
                  <ul class="list-unstyled mb-0">
                    @foreach($r['reasons'] as $reason)
                      <li class="mb-1"><i class="fas fa-angle-right text-{{ $band['class'] }} me-1"></i>{{ $reasonLabel($reason) }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <p class="text-center text-muted small mt-4 mb-0">
      {{ __('Showing the most at-risk items first. This list reflects the catalogue as recorded and is not exhaustive.') }}
    </p>
  @endif

</div>
@endsection
