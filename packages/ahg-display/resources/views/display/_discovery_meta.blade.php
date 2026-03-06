{{--
  GLAM Browser – _discovery_meta.blade.php
  Per-result discovery metadata footer (relevance score, match reasons, explanation)
--}}
@php
  $discovery = $discovery ?? null;
@endphp

@if($discovery)
  <div class="d-flex flex-wrap align-items-center gap-2 small">
    {{-- Relevance score --}}
    @if(isset($discovery['score']))
      <span class="text-muted" title="Relevance score">
        <i class="fas fa-chart-bar me-1"></i>
        Score: <strong>{{ number_format($discovery['score'], 2) }}</strong>
      </span>
    @endif

    {{-- Match reason badges --}}
    @if(!empty($discovery['match_reasons']))
      @foreach($discovery['match_reasons'] as $reason)
        <span class="badge bg-light text-dark border" title="Match reason">
          <i class="fas fa-check-circle text-success me-1"></i> {{ e($reason) }}
        </span>
      @endforeach
    @endif

    {{-- Match fields --}}
    @if(!empty($discovery['matched_fields']))
      @foreach($discovery['matched_fields'] as $field)
        <span class="badge bg-light text-dark border" title="Matched field">
          <i class="fas fa-bullseye text-info me-1"></i> {{ e($field) }}
        </span>
      @endforeach
    @endif

    {{-- Explanation lines --}}
    @if(!empty($discovery['explanation']))
      <a class="text-muted ms-1 cursor-pointer" data-bs-toggle="collapse"
         href="#discovery-explain-{{ $discovery['id'] ?? uniqid() }}" role="button" aria-expanded="false"
         title="Show explanation">
        <i class="fas fa-info-circle"></i>
      </a>
    @endif
  </div>

  {{-- Collapsible explanation --}}
  @if(!empty($discovery['explanation']))
    <div class="collapse mt-1" id="discovery-explain-{{ $discovery['id'] ?? uniqid() }}">
      <div class="small text-muted bg-light rounded p-2">
        @if(is_array($discovery['explanation']))
          @foreach($discovery['explanation'] as $line)
            <div>{{ e($line) }}</div>
          @endforeach
        @else
          {{ e($discovery['explanation']) }}
        @endif
      </div>
    </div>
  @endif
@endif
