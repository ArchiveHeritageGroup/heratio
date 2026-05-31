{{-- #1107 Target-journal directory — index --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'target-journals'])
@endsection

@section('title', 'Where to Publish')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-bullseye text-primary me-2"></i>{{ __('Where to Publish') }} <small class="text-muted">{{ __('(target-journal directory)') }}</small></h1>
    <div class="d-flex gap-2">
      <a href="{{ route('research.target-journal.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Add Journal') }}</a>
      <form action="{{ route('research.target-journal.seed-dhet') }}" method="post" onsubmit="return confirm('{{ __('Seed/refresh the DHET-accredited starter set (SA accreditation module)?') }}')">@csrf
        <button class="btn atom-btn-white"><i class="fas fa-seedling me-1"></i>{{ __('Seed DHET starter') }}</button>
      </form>
    </div>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-6"><input name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Search title, scope, publisher, indexing…') }}"></div>
    <div class="col-md-3"><input name="market" value="{{ $market }}" class="form-control" placeholder="{{ __('Market (e.g. ZA)') }}"></div>
    <div class="col-md-3"><button class="btn atom-btn-white w-100">{{ __('Filter') }}</button></div>
  </form>

  <div class="card"><div class="card-body p-0">
    @if (count($journals))
    <table class="table mb-0 align-middle">
      <thead><tr><th>{{ __('Journal') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Indexing') }}</th><th>{{ __('Style') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
      <tbody>
        @foreach ($journals as $j)
        <tr>
          <td><a href="{{ route('research.target-journal.show', $j['id']) }}">{{ $j['title'] }}</a>
            @if($j['open_access'])<span class="badge bg-success ms-1">OA</span>@endif
            @if($j['publisher'])<br><small class="text-muted">{{ $j['publisher'] }}</small>@endif</td>
          <td class="text-muted small">{{ \Illuminate\Support\Str::limit($j['subject_scope'] ?? '', 90) }}</td>
          <td class="small">{{ $j['accreditation'] }}</td>
          <td class="small">{{ $j['reference_style'] ?: '—' }}</td>
          <td class="text-end">
            <a href="{{ route('research.target-journal.edit', $j['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
            <form action="{{ route('research.target-journal.destroy', $j['id']) }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Remove from directory?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
      <p class="text-muted m-3">{{ __('The directory is empty. Use “Seed DHET starter” to load the South-African accreditation set, or add journals manually.') }}</p>
    @endif
  </div></div>
  <p class="form-text mt-2">{{ __('The directory core is jurisdiction-neutral; the DHET list is the South-African accreditation module. Other markets seed from DOAJ / Scopus / Web of Science.') }}</p>
</div>
@endsection
