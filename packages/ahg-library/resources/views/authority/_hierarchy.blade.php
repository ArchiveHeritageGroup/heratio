{{-- heratio#1475 item 3: record type, broader/narrower navigation, vocabulary sync --}}
<div class="card shadow-sm mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>{{ __('Hierarchy') }}</h5>
    </div>
    <div class="card-body">
        <p class="mb-3">
            <span class="text-muted">{{ __('Record type') }}:</span>
            @php $typeCode = $recordType; @endphp
            <span class="badge bg-secondary">{{ $recordTypes[$typeCode] ?? $typeCode }}</span>
        </p>

        <div class="row g-3 mb-3">
            @foreach([['label' => __('Broader terms'), 'terms' => $broader], ['label' => __('Narrower terms'), 'terms' => $narrower]] as $group)
                <div class="col-md-6">
                    <h6>{{ $group['label'] }}</h6>
                    @forelse($group['terms'] as $term)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-1">
                            <span>
                                <a href="{{ route('library.authority-view', $term->id) }}">{{ $term->heading }}</a>
                                @if($term->origin === 'sync')
                                    <small class="text-muted">({{ __('external') }})</small>
                                @endif
                            </span>
                            @auth
                                <form method="POST" action="{{ route('library.authority-relation-remove', $term->relation_id) }}">
                                    @csrf
                                    <input type="hidden" name="authority_id" value="{{ $authority->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove relation to :term', ['term' => $term->heading]) }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            @endauth
                        </div>
                    @empty
                        <p class="text-muted small mb-0">{{ __('None recorded.') }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('library.authority-relation-add', $authority->id) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-3">
                <label for="relation-direction" class="form-label small">{{ __('Add') }}</label>
                <select name="direction" id="relation-direction" class="form-select form-select-sm">
                    <option value="broader">{{ __('Broader term') }}</option>
                    <option value="narrower">{{ __('Narrower term') }}</option>
                </select>
            </div>
            <div class="col-md-7">
                <label for="relation-term" class="form-label small">{{ __('Exact heading or record ID') }}</label>
                <input type="text" name="term" id="relation-term" class="form-control form-control-sm" required maxlength="500">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button>
            </div>
        </form>

        <h6>{{ __('External vocabulary') }}</h6>
        @if($typing && $typing->external_id)
            <p class="small mb-1">
                {{ strtoupper($typing->scheme) }} <code>{{ $typing->external_id }}</code>
                @if($typing->last_synced_at)
                    - {{ __('last synced :date', ['date' => $typing->last_synced_at]) }}
                @endif
            </p>
            @if($typing->last_sync_error)
                <p class="small text-danger mb-1">{{ $typing->last_sync_error }}</p>
            @endif
            @if($overrides)
                <p class="small mb-1">{{ __('Kept as local overrides') }}: {{ implode(', ', $overrides) }}</p>
                <form method="POST" action="{{ route('library.authority-overrides-clear', $authority->id) }}" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Clear overrides') }}</button>
                </form>
            @endif
        @endif
        <form method="POST" action="{{ route('library.authority-sync', $authority->id) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label for="external-id" class="form-label small">{{ __('LCSH identifier') }}</label>
                <input type="text" name="external_id" id="external-id" class="form-control form-control-sm"
                       value="{{ $typing->external_id ?? '' }}" placeholder="sh85034652" maxlength="20">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">{{ __('Sync from vocabulary') }}</button>
            </div>
        </form>
    </div>
</div>
