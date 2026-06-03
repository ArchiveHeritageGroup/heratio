{{-- ahg-biblio-frbr/agent.blade.php — FRBR agent browser --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ __('FRBR Agents') }}</h1>
    <span class="badge bg-secondary">Agents</span>
  </div>
  <p class="text-muted small mb-4">
    Browse the agent authority used in FRBR records &mdash; creators, contributors,
    editors, illustrators, and other responsible parties.
  </p>

  <div class="card">
    <div class="card-header">
      <i class="bi bi-person me-1"></i>
      Agent Authority &mdash; {{ $agents->total() ?? $agents->count() }} total
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>{{ __('ID') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Role') }}</th>
            <th>{{ __('Added') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($agents as $agent)
            <tr>
              <td class="text-muted font-monospace" style="font-size:0.8rem;">{{ $agent->id }}</td>
              <td>{{ $agent->name ?? 'Unknown' }}</td>
              <td>
                <span class="badge bg-secondary">
                  {{ strtoupper($agent->type ?? 'per') }}
                </span>
              </td>
              <td class="text-muted small">
                {{ match($agent->type ?? 'aut') {
                    'aut' => 'Author',
                    'ctb' => 'Contributor',
                    'edt' => 'Editor',
                    'ill' => 'Illustrator',
                    'pht' => 'Photographer',
                    'trl' => 'Translator',
                    default => 'Author',
                } }}
              </td>
              <td class="text-muted small">
                @if($agent->created_at)
                  {{ \Carbon\Carbon::parse($agent->created_at)->format('j M Y') }}
                @else
                  &mdash;
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-3">
                No agents found. Agents are created when bibliographic works are added.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($agents, 'links'))
      <div class="card-footer">
        {{ $agents->links() }}
      </div>
    @endif
  </div>

  <div class="mt-3">
    <a href="{{ route('frbr.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
  </div>

</div>
@endsection
