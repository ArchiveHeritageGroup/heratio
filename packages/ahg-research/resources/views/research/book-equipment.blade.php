{{-- Book Equipment - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'equipment'])
@endsection

@section('title', 'Book Equipment')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.equipment') }}">Equipment</a></li>
        <li class="breadcrumb-item active">Book Equipment</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-tools text-primary me-2"></i>{{ __('Book Equipment') }}</h1>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">Equipment Booking</div>
            <div class="card-body">
                <form method="POST">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Equipment <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <select name="equipment_id" class="form-select" required>
                                <option value="">-- Select equipment --</option>
                                @foreach($equipment ?? [] as $eq)
                                    <option value="{{ $eq->id }}">{{ e($eq->name) }} ({{ e($eq->equipment_type ?? '') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <input type="date" name="date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Time <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <input type="time" name="start_time" class="form-control" value="09:00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <input type="time" name="end_time" class="form-control" value="17:00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <textarea name="purpose" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn atom-btn-white"><i class="fas fa-calendar-check me-1"></i>{{ __('Book Equipment') }}</button>
                    <a href="{{ route('research.equipment') }}" class="btn atom-btn-white">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Equipment Info') }}</h6></div>
            <div class="card-body text-muted small">
                Select an equipment item to see its availability and details.
            </div>
        </div>
    </div>
</div>
@endsection