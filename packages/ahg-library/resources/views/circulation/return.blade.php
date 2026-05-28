@extends('theme::layouts.1col')
@section('title', 'Return Item')
@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-undo me-2"></i>{{ __('Return Item') }}</h1>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.circulation.do-return') }}">
        @csrf

        {{-- Copy details --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('Item Details') }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('Barcode') }}</dt>
                    <dd class="col-sm-9"><code>{{ $checkout->barcode ?? '' }}</code></dd>
                    <dt class="col-sm-3">{{ __('Title') }}</dt>
                    <dd class="col-sm-9">{{ $checkout->title ?? __('(untitled)') }}</dd>
                    <dt class="col-sm-3">{{ __('Call Number') }}</dt>
                    <dd class="col-sm-9">{{ $checkout->call_number ?? '—' }}</dd>
                    <dt class="col-sm-3">{{ __('Checked Out') }}</dt>
                    <dd class="col-sm-9">{{ $checkout->checkout_date ?? '' }}</dd>
                    <dt class="col-sm-3">{{ __('Due Date') }}</dt>
                    <dd class="col-sm-9">
                        {{ $checkout->due_date ?? '' }}
                        @if(strToTime($checkout->due_date ?? '') < time())
                            <span class="badge bg-danger ms-1">{{ __('OVERDUE') }}</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Condition --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Return Condition') }}</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="condition" class="form-label">{{ __('Condition') }}</label>
                    <select name="condition" id="condition" class="form-select">
                        <option value="good">{{ __('Good — item returned in normal condition') }}</option>
                        <option value="damaged">{{ __('Damaged — item has minor to moderate damage') }}</option>
                        <option value="lost">{{ __('Lost — patron has reported the item lost') }}</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label for="notes" class="form-label">{{ __('Return Notes') }}</label>
                    <textarea name="notes" id="notes" rows="3" class="form-control"
                              placeholder="{{ __('Optional notes about the returned item…') }}"></textarea>
                </div>
            </div>
        </div>

        <input type="hidden" name="checkout_id" value="{{ $checkout->id ?? 0 }}">

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check me-1"></i>{{ __('Confirm Return') }}
            </button>
            <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
