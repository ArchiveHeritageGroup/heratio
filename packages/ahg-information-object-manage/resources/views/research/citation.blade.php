@extends('theme::layout_2col')
@section('title', 'Cite this Record — ' . ($io->title ?? ''))

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'bibliographies'])
@endsection

@section('content')
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? '' }}</a></li>
      <li class="breadcrumb-item active">Citation Generator</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="fas fa-quote-right text-primary me-2"></i>Citation Generator</h1>
    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
  </div>

  @php
    $creatorStr = $creators->pluck('name')->implode('; ');
    $repoName = $repository->name ?? '';
    $dateStr = $dates->date_display ?? '';
    $identifier = $io->identifier ?? '';
    $url = url($io->slug);
    $today = now()->format('j F Y');
    $year = $dateStr ? preg_replace('/[^0-9]/', '', substr($dateStr, 0, 4)) : '';

    $styleColors = [
        'chicago' => 'primary',
        'mla' => 'success',
        'turabian' => 'info',
        'apa' => 'warning',
        'harvard' => 'danger',
        'unisa' => 'dark',
    ];

    $citations = [
        'chicago' => [
            'name' => 'CHICAGO Style',
            'text' => ($creatorStr ? $creatorStr . '. ' : '') . '"' . $io->title . '."' . ($dateStr ? ' ' . $dateStr . '.' : '') . ($repoName ? ' ' . $repoName . '.' : '') . ' Accessed ' . $today . '. ' . $url . '.',
        ],
        'mla' => [
            'name' => 'MLA Style',
            'text' => ($creatorStr ? $creatorStr . '. ' : '') . '"' . $io->title . '." <em>' . ($repoName ?: 'Digital Archive') . '</em>,' . ($dateStr ? ' ' . $dateStr . ',' : '') . ' ' . $url . '. Accessed ' . $today . '.',
        ],
        'turabian' => [
            'name' => 'TURABIAN Style',
            'text' => ($creatorStr ? $creatorStr . '. ' : '') . '"' . $io->title . '."' . ($repoName ? ' ' . $repoName . '.' : '') . ($dateStr ? ' ' . $dateStr . '.' : '') . ' Accessed ' . $today . '. ' . $url . '.',
        ],
        'apa' => [
            'name' => 'APA Style',
            'text' => ($creatorStr ? $creatorStr . ' ' : '') . '(' . ($year ?: 'n.d.') . '). <em>' . $io->title . '</em>.' . ($repoName ? ' ' . $repoName . '.' : '') . ' ' . $url,
        ],
        'harvard' => [
            'name' => 'HARVARD Style',
            'text' => ($creatorStr ? $creatorStr . ' ' : '') . ($year ? $year . '. ' : '') . '<em>' . $io->title . '</em>.' . ($repoName ? ' ' . $repoName . '.' : '') . ' Available at: ' . $url . ' [Accessed ' . $today . '].',
        ],
        'unisa' => [
            'name' => 'UNISA HARVARD Style',
            'text' => ($creatorStr ? $creatorStr . '. ' : '') . ($year ? $year . '. ' : '') . '<em>' . $io->title . '</em>.' . ($repoName ? ' ' . $repoName . '.' : '') . ' [Online]. Available from: ' . $url . ' [Accessed: ' . now()->format('Y-m-d') . '].',
        ],
    ];
  @endphp

  <div class="row">
    @foreach($citations as $style => $data)
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header bg-{{ $styleColors[$style] ?? 'secondary' }} text-white">
            <h5 class="mb-0">{{ $data['name'] }}</h5>
          </div>
          <div class="card-body">
            <p class="citation-text" id="cite-{{ $style }}">{!! $data['text'] !!}</p>
          </div>
          <div class="card-footer">
            <button class="btn btn-sm btn-outline-primary copy-cite-btn" data-target="cite-{{ $style }}">
              <i class="fas fa-copy me-1"></i> {{ __('Copy') }}
            </button>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card mt-4 mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Citation Information</h5></div>
    <div class="card-body">
      <table class="table table-borderless mb-0">
        <tr><th width="150">{{ __('Title:') }}</th><td>{{ $io->title }}</td></tr>
        @if($identifier)
          <tr><th>{{ __('Identifier:') }}</th><td>{{ $identifier }}</td></tr>
        @endif
        @if($creatorStr)
          <tr><th>{{ __('Creator:') }}</th><td>{{ $creatorStr }}</td></tr>
        @endif
        @if($repoName)
          <tr><th>{{ __('Repository:') }}</th><td>{{ $repoName }}</td></tr>
        @endif
        @if($dateStr)
          <tr><th>{{ __('Date:') }}</th><td>{{ $dateStr }}</td></tr>
        @endif
        <tr><th>{{ __('URL:') }}</th><td><a href="{{ $url }}" target="_blank">{{ $url }}</a></td></tr>
        <tr><th>{{ __('Accessed:') }}</th><td>{{ $today }}</td></tr>
      </table>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header bg-light"><h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Citation Style Guide</h5></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6><span class="badge bg-danger">{{ __('Harvard') }}</span></h6>
          <p class="small text-muted">Standard Harvard referencing style used internationally.</p>
        </div>
        <div class="col-md-6">
          <h6><span class="badge bg-dark">{{ __('UNISA Harvard') }}</span></h6>
          <p class="small text-muted">University of South Africa's specific Harvard referencing format, commonly used in South African academic institutions.</p>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.querySelectorAll('.copy-cite-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var el = document.getElementById(this.dataset.target);
      var text = el.innerText || el.textContent;
      navigator.clipboard.writeText(text).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(function() {
          btn.innerHTML = orig;
          btn.classList.remove('btn-success');
          btn.classList.add('btn-outline-primary');
        }, 2000);
      });
    });
  });
  </script>
@endsection
