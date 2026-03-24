{{-- Saved Searches - ported from AtoM ahgSemanticSearchPlugin/modules/searchEnhancement/templates/savedSearchesSuccess.php --}}
@extends('theme::layouts.1col')

@section('title')
  <h1><i class="fa fa-bookmark me-2"></i>{{ __('My Saved Searches') }}</h1>
@endsection

@section('content')

@if(session('notice'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(empty($searches) || (is_countable($searches) && count($searches) === 0))
  <div class="alert alert-info">{{ __('No saved searches yet. Use the "Save This Search" button on search results.') }}</div>
@else

<div class="table-responsive">
  <table class="table table-striped">
    <thead>
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Notifications') }}</th>
        <th>{{ __('Uses') }}</th>
        <th>{{ __('Last Used') }}</th>
        <th>{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($searches as $search)
      <tr>
        <td><strong>{{ e($search->name) }}</strong></td>
        <td><code>{{ e($search->entity_type ?? '') }}</code></td>
        <td>
          @if($search->notify_on_new ?? false)
            <span class="badge bg-info">{{ ucfirst($search->notification_frequency ?? 'daily') }}</span>
          @else
            <span class="text-muted">-</span>
          @endif
        </td>
        <td>{{ (int) ($search->usage_count ?? 0) }}</td>
        <td>{{ $search->last_used_at ?? '-' }}</td>
        <td>
          @php
            $params = json_decode($search->search_params ?? '{}', true) ?: [];
            $runUrl = route('search.index') . '?' . http_build_query($params);
          @endphp
          <a href="{{ $runUrl }}" class="btn btn-primary btn-sm" title="{{ __('Run') }}">
            <i class="fa fa-play"></i> {{ __('Run') }}
          </a>
          <form action="{{ route('semantic-search.savedSearches') }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <input type="hidden" name="id" value="{{ $search->id }}">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('Delete this saved search?') }}')" title="{{ __('Delete') }}">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endif

<div class="mt-3">
  <a href="{{ route('search.index') }}" class="btn btn-secondary">
    <i class="fa fa-search me-1"></i>{{ __('Browse Records') }}
  </a>
</div>

@endsection
