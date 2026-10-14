{{-- H09: weekly source-to-enquiry dashboard for the demo site. --}}
@extends('theme::layouts.1col')

@section('title', __('Acquisition').' - Heratio')

@section('title-block')
  <h1>{{ __('Acquisition') }}</h1>
  <p class="lead mb-1">{{ __('Where demo-site visitors come from, and how far they get.') }}</p>
  <p class="small text-muted mb-3">
    {{ __('A visit counts once a page has been open in a real browser for five seconds, so crawlers are left out. No IP address, cookie or personal detail is stored.') }}
  </p>
@endsection

@section('content')
  @php
    $cols = ['visit' => __('Visits'), 'demo' => __('Live demo'), 'tour' => __('Tour started'), 'docs' => __('Documentation'), 'github' => __('GitHub'), 'enquiry' => __('Enquiries')];
    $pct = fn ($n, $d) => $d > 0 ? number_format(100 * $n / $d, 1).'%' : '-';
  @endphp

  <form class="mb-3 small" method="get">
    <label for="acq-weeks">{{ __('Weeks shown') }}</label>
    <select id="acq-weeks" name="weeks" onchange="this.form.submit()">
      @foreach([4, 12, 26, 52] as $w)<option value="{{ $w }}" @selected($w === $weeks)>{{ $w }}</option>@endforeach
    </select>
  </form>

  <section class="mb-4">
    <h2 class="h5">{{ __('By week') }}</h2>
    <table class="table table-sm">
      <thead><tr><th scope="col">{{ __('Week of') }}</th>@foreach($cols as $c)<th scope="col" class="text-end">{{ $c }}</th>@endforeach<th scope="col" class="text-end">{{ __('Visit to enquiry') }}</th></tr></thead>
      <tbody>
        @foreach($weekly as $week => $n)
          <tr>
            <th scope="row">{{ \Illuminate\Support\Carbon::parse($week)->format('j M Y') }}</th>
            @foreach(array_keys($cols) as $e)<td class="text-end">{{ $n[$e] }}</td>@endforeach
            <td class="text-end">{{ $pct($n['enquiry'], $n['visit']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>

  <section class="mb-4">
    <h2 class="h5">{{ __('By source, since :date', ['date' => $from->format('j M Y')]) }}</h2>
    @if($sources)
      <table class="table table-sm">
        <thead><tr><th scope="col">{{ __('Source') }}</th>@foreach($cols as $c)<th scope="col" class="text-end">{{ $c }}</th>@endforeach<th scope="col" class="text-end">{{ __('Visit to enquiry') }}</th></tr></thead>
        <tbody>
          @foreach($sources as $source => $n)
            <tr>
              <th scope="row">{{ $source }}</th>
              @foreach(array_keys($cols) as $e)<td class="text-end">{{ $n[$e] }}</td>@endforeach
              <td class="text-end">{{ $pct($n['enquiry'], $n['visit']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="small text-muted">{{ __('Sources: github, search, social, referral:<site>, campaign:<name> (from a utm_source tag on the link), direct (no referring site).') }}</p>
    @else
      <p class="text-muted">{{ __('Nothing recorded yet.') }}</p>
    @endif
  </section>

  <section class="mb-4">
    <h2 class="h5">{{ __('Enquiries by type, since :date', ['date' => $from->format('j M Y')]) }}</h2>
    @if($enquiryTypes)
      <table class="table table-sm w-auto">
        <tbody>
          @foreach($enquiryTypes as $code => $n)
            <tr><th scope="row">{{ $typeLabels[$code] ?? ($code ?: __('Not given')) }}</th><td class="text-end">{{ $n }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted">{{ __('No enquiries yet.') }}</p>
    @endif
  </section>
@endsection
