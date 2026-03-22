{{-- Partial: Semantic search results --}}
<div class="semantic-search-results">
  @forelse($results ?? [] as $result)
  <div class="card mb-2"><div class="card-body py-2">
    <div class="d-flex justify-content-between"><a href="{{ $result->url ?? '#' }}">{{ $result->title ?? '' }}</a><span class="badge bg-info">{{ number_format(($result->score ?? 0) * 100) }}%</span></div>
    <small class="text-muted">{{ Str::limit($result->snippet ?? '', 120) }}</small>
  </div></div>
  @empty<p class="text-muted">No semantic search results.</p>@endforelse
</div>
