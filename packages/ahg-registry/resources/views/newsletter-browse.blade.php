@extends('theme::layouts.1col')

@section('title', 'Newsletters')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Newsletters') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1"><i class="fas fa-newspaper me-2"></i>{{ __('Newsletters') }}</h1>
    <p class="text-muted mb-0">{{ __(':count items', ['count' => number_format($result['total'] ?? 0)]) }}</p>
  </div>
  @auth
  <div class="col-auto">
    <a href="{{ route('registry.newsletterBrowse') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</a>
  </div>
  @endauth
</div>

<div class="mb-4">
  <form method="get">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ request('q') }}" placeholder="{{ __('Search...') }}">
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if(!empty($result['items']) && count($result['items']))
<div class="table-responsive">
  <table class="table table-striped table-bordered">
    <thead>
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Updated') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($result['items'] as $item)
      <tr>
        <td>{{ e($item->name ?? '') }}</td>
        <td>{{ $item->updated_at ?? '' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="alert alert-info">{{ __('No results found.') }}</div>
@endif

@endsection
