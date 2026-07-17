{{-- #1390 #4a - Curator moderation queue for offline-synced metadata suggestions. --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'metadataSuggestions'])
@endsection

@section('title')
  <h1><i class="fas fa-comment-dots text-primary me-2"></i>{{ __('Metadata Suggestions') }}</h1>
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <h1 class="h2 mb-2"><i class="fas fa-comment-dots text-primary me-2"></i>{{ __('Metadata Suggestions') }}</h1>
  <p class="text-muted">{{ __('Corrections and additions contributed offline (portable / research packages) and synced back for review. Approving an auto-mappable field applies it to the record.') }}</p>

  @php $cs = $filter ?? 'open'; @endphp
  <ul class="nav nav-pills mb-3">
    @foreach(['open' => 'warning text-dark', 'approved' => 'success', 'rejected' => 'secondary'] as $st => $badge)
      <li class="nav-item">
        <a class="nav-link {{ $cs === $st ? 'active' : '' }}" href="{{ route('research.admin.metadataSuggestions', ['filter' => $st]) }}">
          {{ ucfirst($st) }}
          <span class="badge bg-{{ $cs === $st ? 'white text-primary' : $badge }} ms-1">{{ (int) ($counts[$st] ?? 0) }}</span>
        </a>
      </li>
    @endforeach
  </ul>

  @if($suggestions->isEmpty())
    <div class="alert alert-info">{{ __('No :status suggestions.', ['status' => $cs]) }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>{{ __('Record') }}</th>
            <th>{{ __('Field') }}</th>
            <th>{{ __('Suggested value') }}</th>
            <th>{{ __('Contributor') }}</th>
            <th>{{ __('When') }}</th>
            @if($cs === 'open')<th class="text-end">{{ __('Action') }}</th>@else<th>{{ __('Status') }}</th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach($suggestions as $s)
            <tr>
              <td>
                @if($s->record_slug)
                  <a href="{{ url('/informationobject/' . $s->record_slug) }}" target="_blank" rel="noopener">{{ $s->record_title ?: ('#' . $s->object_id) }}</a>
                @else
                  {{ $s->record_title ?: ('#' . $s->object_id) }}
                @endif
              </td>
              <td><span class="badge bg-light text-dark border">{{ $s->field }}</span></td>
              <td style="max-width:28rem"><div class="text-break">{{ \Illuminate\Support\Str::limit($s->suggestion, 400) }}</div></td>
              <td>{{ trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')) ?: ('Researcher #' . $s->researcher_id) }}</td>
              <td class="text-nowrap"><small class="text-muted">{{ $s->created_at ? \Illuminate\Support\Carbon::parse($s->created_at)->format('Y-m-d H:i') : '' }}</small></td>
              @if($cs === 'open')
                <td class="text-end text-nowrap">
                  <form method="post" action="{{ route('research.admin.metadataSuggestions.approve', $s->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success" title="{{ __('Approve (and apply if the field maps)') }}"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                  </form>
                  <form method="post" action="{{ route('research.admin.metadataSuggestions.reject', $s->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Reject this suggestion?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Reject') }}"><i class="fas fa-times"></i></button>
                  </form>
                </td>
              @else
                <td><span class="badge bg-{{ $s->status === 'approved' ? 'success' : 'secondary' }}">{{ ucfirst($s->status) }}</span></td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
