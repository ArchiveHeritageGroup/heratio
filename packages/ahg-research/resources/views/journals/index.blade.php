{{-- #1105 Journal builder — index --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'journals'])
@endsection

@section('title', 'Journals')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-newspaper text-primary me-2"></i>{{ __('Journals') }}</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('research.journal-builder.create', ['kind' => 'publication']) }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('New Journal') }}</a>
      <a href="{{ route('research.journal-builder.create', ['kind' => 'manuscript']) }}" class="btn atom-btn-white"><i class="fas fa-file-pen me-1"></i>{{ __('New Manuscript') }}</a>
    </div>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @foreach ([['label' => __('Journal publications'), 'rows' => $publications, 'hint' => __('Institutional journals you publish: issues, articles, table of contents.')], ['label' => __('Manuscripts'), 'rows' => $manuscripts, 'hint' => __('Single articles drafted toward an external target journal.')]] as $group)
    <div class="card mb-4">
      <div class="card-header"><strong>{{ $group['label'] }}</strong> <small class="text-muted ms-2">{{ $group['hint'] }}</small></div>
      <div class="card-body p-0">
        @if (count($group['rows']))
        <table class="table mb-0 align-middle">
          <thead><tr><th>{{ __('Title') }}</th><th>{{ __('ISSN') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
          <tbody>
            @foreach ($group['rows'] as $j)
            <tr>
              <td><a href="{{ route('research.journal-builder.show', $j['id']) }}">{{ $j['title'] }}</a>@if($j['subtitle'])<br><small class="text-muted">{{ $j['subtitle'] }}</small>@endif</td>
              <td>{{ $j['issn'] ?: ($j['eissn'] ?: '—') }}</td>
              <td><span class="badge bg-{{ $j['status'] === 'published' ? 'success' : ($j['status'] === 'archived' ? 'secondary' : 'warning text-dark') }}">{{ ucfirst($j['status']) }}</span></td>
              <td class="text-end">
                <a href="{{ route('research.journal-builder.show', $j['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Open') }}</a>
                <a href="{{ route('research.journal-builder.edit', $j['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
                <form action="{{ route('research.journal-builder.destroy', $j['id']) }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Delete this journal and all its issues/articles?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else<p class="text-muted m-3">{{ __('Nothing here yet.') }}</p>@endif
      </div>
    </div>
  @endforeach
</div>
@endsection
