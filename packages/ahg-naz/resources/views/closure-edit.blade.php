{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Edit Closure Period')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.closures') }}">Closures</a></li>
                    <li class="breadcrumb-item active">Edit Closure</li>
                </ol>
            </nav>
            <h1><i class="fas fa-lock me-2"></i>Edit Closure Period</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Closure Details</h5></div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Record</dt>
                        <dd class="col-sm-8">{{ $ioTitle ?? ('Record #' . ($closure->information_object_id ?? '')) }}</dd>
                    </dl>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Closure Type</label>
                            <select name="closure_type" class="form-select">
                                <option value="standard" @if(($closure->closure_type ?? '') === 'standard') selected @endif>Standard</option>
                                <option value="extended" @if(($closure->closure_type ?? '') === 'extended') selected @endif>Extended</option>
                                <option value="indefinite" @if(($closure->closure_type ?? '') === 'indefinite') selected @endif>Indefinite</option>
                                <option value="ministerial" @if(($closure->closure_type ?? '') === 'ministerial') selected @endif>Ministerial</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" @if(($closure->status ?? '') === 'active') selected @endif>Active</option>
                                <option value="expired" @if(($closure->status ?? '') === 'expired') selected @endif>Expired</option>
                                <option value="extended" @if(($closure->status ?? '') === 'extended') selected @endif>Extended</option>
                                <option value="released" @if(($closure->status ?? '') === 'released') selected @endif>Released</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ $closure->end_date ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Review Date</label>
                            <input type="date" name="review_date" class="form-control" value="{{ $closure->review_date ?? '' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Closure Reason</label>
                            <textarea name="closure_reason" class="form-control" rows="3">{{ $closure->closure_reason ?? '' }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Release Notes</label>
                            <textarea name="release_notes" class="form-control" rows="2">{{ $closure->release_notes ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Changes</button>
                    <a href="{{ route('ahgnaz.closures') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
