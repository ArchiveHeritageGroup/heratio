@extends('theme::layouts.1col')
@section('title', 'Cite this Record — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-quote-left',
    'featureTitle' => 'Cite this Record',
    'featureDescription' => 'Generate citation in various formats',
  ])

  @php
    $creatorStr = $creators->pluck('name')->implode('; ');
    $repoName = $repository->name ?? '';
    $dateStr = $dates->date_display ?? '';
    $url = url($io->slug);
    $today = now()->format('j F Y');
  @endphp

  <div class="card mb-3">
    <div class="card-header fw-bold">Chicago / Turabian</div>
    <div class="card-body">
      <p id="cite-chicago">{{ $creatorStr ? $creatorStr . '. ' : '' }}"{{ $io->title }}."{{ $dateStr ? ' ' . $dateStr . '.' : '' }}{{ $repoName ? ' ' . $repoName . '.' : '' }} Accessed {{ $today }}. {{ $url }}.</p>
      <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cite-chicago').innerText)">
        <i class="fas fa-copy me-1"></i> Copy
      </button>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold">APA 7th edition</div>
    <div class="card-body">
      <p id="cite-apa">{{ $creatorStr ? $creatorStr . ' ' : '' }}({{ $dateStr ?: 'n.d.' }}). <em>{{ $io->title }}</em>.{{ $repoName ? ' ' . $repoName . '.' : '' }} {{ $url }}</p>
      <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cite-apa').innerText)">
        <i class="fas fa-copy me-1"></i> Copy
      </button>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header fw-bold">MLA 9th edition</div>
    <div class="card-body">
      <p id="cite-mla">{{ $creatorStr ? $creatorStr . '. ' : '' }}"{{ $io->title }}." <em>{{ $repoName ?: 'Digital Archive' }}</em>,{{ $dateStr ? ' ' . $dateStr . ',' : '' }} {{ $url }}. Accessed {{ $today }}.</p>
      <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cite-mla').innerText)">
        <i class="fas fa-copy me-1"></i> Copy
      </button>
    </div>
  </div>
@endsection
