{{--
  Union catalogue - admin: add / edit a federation member (#1203).

  Distinct from the locked F3 edit-peer.blade.php - this is the union-network
  member registry, not the SharePoint peer connector form.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', $member ? __('Edit member') : __('Add member'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-building me-2"></i>{{ $member ? __('Edit member') : __('Add member') }}
        </h4>
        <a href="{{ route('union.members.index') }}" class="atom-btn-white">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to members') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('union.members.save') }}">
                @csrf
                @if ($member)
                    <input type="hidden" name="id" value="{{ $member->id }}">
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">{{ __('Institution name') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" required
                           value="{{ old('name', $member->name ?? '') }}">
                </div>

                <div class="mb-3">
                    <label for="base_url" class="form-label">{{ __('Base URL') }}</label>
                    <input type="url" class="form-control" id="base_url" name="base_url"
                           placeholder="https://catalogue.example.org"
                           value="{{ old('base_url', $member->base_url ?? '') }}">
                    <div class="form-text">{{ __('Public catalogue base for this member.') }}</div>
                </div>

                <div class="mb-3">
                    <label for="contact" class="form-label">{{ __('Contact') }}</label>
                    <input type="text" class="form-control" id="contact" name="contact"
                           value="{{ old('contact', $member->contact ?? '') }}">
                </div>

                <div class="mb-3">
                    <label for="share_scope" class="form-label">{{ __('Sharing scope notes') }}</label>
                    <textarea class="form-control" id="share_scope" name="share_scope" rows="3"
                              placeholder="{{ __('What this member shares / agreement terms') }}">{{ old('share_scope', $member->share_scope ?? '') }}</textarea>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_self" name="is_self" value="1"
                           {{ old('is_self', $member->is_self ?? 0) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_self">
                        {{ __('This institution (the self-member that owns published records)') }}
                    </label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_enabled" name="is_enabled" value="1"
                           {{ old('is_enabled', $member->is_enabled ?? 0) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_enabled">
                        {{ __('Include this member in union searches') }}
                        <span class="text-muted small">({{ __('opt-in default OFF') }})</span>
                    </label>
                </div>

                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-save me-1"></i>{{ __('Save member') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
