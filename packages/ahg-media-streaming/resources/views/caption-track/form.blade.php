@extends('theme::layouts.3col')

@section('title', $mode === 'edit' ? "Edit Track — {$track->label}" : 'Add Caption Track')
@section('body-class', 'caption-track-form')

@section('content')

<div class="mb-4">
    <a href="{{ route('caption-tracks.index', $digitalObjectId) }}" class="text-decoration-none">
        <i class="fas fa-arrow-left me-1"></i>Back to Caption Tracks
    </a>
</div>

<h1 class="mb-4">
    <i class="fas fa-closed-captioning me-2"></i>
    {{ $mode === 'edit' ? 'Edit Track' : 'Add Caption / Subtitle Track' }}
</h1>

<div class="card">
    <div class="card-body">

        @if($mode === 'edit')
            <form method="POST" action="{{ route('caption-tracks.update', [$digitalObjectId, $track->id]) }}">
                @method('PUT')
        @else
            <form method="POST" action="{{ route('caption-tracks.store', $digitalObjectId) }}">
        @endif

            @csrf

            {{-- Track type --}}
            <div class="mb-3">
                <label class="form-label" for="track_type">{{ __('Track type') }}</label>
                <select name="track_type" id="track_type" class="form-select" required>
                    <option value="subtitle" {{ ($track->track_type ?? request()->query('type', 'subtitle')) === 'subtitle' ? 'selected' : '' }}>Subtitle</option>
                    <option value="caption" {{ ($track->track_type ?? '') === 'caption' ? 'selected' : '' }}>Caption</option>
                    <option value="description" {{ ($track->track_type ?? '') === 'description' ? 'selected' : '' }}>Audio Description</option>
                    <option value="chapters" {{ ($track->track_type ?? '') === 'chapters' ? 'selected' : '' }}>Chapters</option>
                </select>
            </div>

            {{-- Label --}}
            <div class="mb-3">
                <label class="form-label" for="label">Label <span class="text-danger">*</span></label>
                <input type="text" name="label" id="label"
                       class="form-control"
                       value="{{ old('label', $track->label ?? '') }}"
                       maxlength="120"
                       placeholder="{{ __('e.g. English, English (SDH), isiZulu, Spanish') }}"
                       required>
                <small class="text-muted">Short label shown in the video player's track selector.</small>
            </div>

            {{-- Language --}}
            <div class="mb-3">
                <label class="form-label" for="language_code">Language code <span class="text-danger">*</span></label>
                <select name="language_code" id="language_code" class="form-select" required>
                    @php
                        $selectedLang = old('language_code', $track->language_code ?? $prefillLanguage ?? 'en');
                        $isoLangs = [
                            'en' => 'English',
                            'af' => 'Afrikaans',
                            'zu' => 'isiZulu',
                            'xh' => 'isiXhosa',
                            'nso' => 'Sesotho',
                            'tn' => 'Setswana',
                            'ss' => 'siSwati',
                            'ts' => 'Xitsonga',
                            've' => 'Tshivenda',
                            'nr' => 'isiNdebele',
                            'sot' => 'Sesotho sa Leboa',
                            'ar' => 'Arabic',
                            'fr' => 'French',
                            'de' => 'German',
                            'pt' => 'Portuguese',
                            'es' => 'Spanish',
                            'it' => 'Italian',
                            'nl' => 'Dutch',
                            'pl' => 'Polish',
                            'ru' => 'Russian',
                            'ja' => 'Japanese',
                            'ko' => 'Korean',
                            'zh' => 'Chinese',
                            'hi' => 'Hindi',
                            'ha' => 'Hausa',
                            'sw' => 'Swahili',
                            'am' => 'Amharic',
                            'st' => 'Sesotho',
                        ];
                    @endphp
                    @foreach($isoLangs as $code => $name)
                        <option value="{{ $code }}" {{ $selectedLang === $code ? 'selected' : '' }}>{{ strtoupper($code) }} — {{ $name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- SDH flag --}}
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_sdh" id="is_sdh"
                           class="form-check-input"
                           value="1"
                           {{ ($track->is_sdh ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_sdh">
                        SDH — Subtitle for the Deaf and Hard of Hearing
                    </label>
                </div>
                <small class="text-muted">SDH tracks include speaker identification and sound/event descriptions. Enable for accessibility compliance.</small>
            </div>

            {{-- Default flag --}}
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_default" id="is_default"
                           class="form-check-input"
                           value="1"
                           {{ ($track->is_default ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">
                        Default track — auto-selected when the video loads
                    </label>
                </div>
            </div>

            <hr>

            {{-- Source URL --}}
            <div class="mb-3">
                <label class="form-label" for="source_url">{{ __('Remote VTT or SRT URL') }}</label>
                <input type="url" name="source_url" id="source_url"
                       class="form-control"
                       value="{{ old('source_url', $track->source_url ?? '') }}"
                       maxlength="500"
                       placeholder="{{ __('https://example.com/subtitles/en.vtt') }}">
                <small class="text-muted">Link to an external WebVTT (.vtt) or SubRip (.srt) file. Contents are cached locally and served as inline VTT. Leave blank to paste VTT content below.</small>
            </div>

            <div class="text-center my-3">
                <span class="text-muted">— or paste VTT content below —</span>
            </div>

            {{-- VTT content --}}
            <div class="mb-3">
                <label class="form-label" for="vtt_content">{{ __('Inline VTT content') }}</label>
                <textarea name="vtt_content" id="vtt_content"
                          class="form-control font-monospace"
                          rows="8"
                          placeholder="{{ __('WEBVTT

00:00:01.000 --> 00:00:04.000
Welcome to our archival collection.') }}">{{ old('vtt_content', $track->vtt_content ?? '') }}</textarea>
                <small class="text-muted">
                    WebVTT format. Leave blank if using a remote URL above. Timestamps use HH:MM:SS.mmm format.<br>
                    <a href="https://www.w3.org/TR/webvtt/" target="_blank" rel="noopener">WebVTT specification on W3C <i class="fas fa-external-link-alt"></i></a>
                </small>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn atom-btn-white">
                    <i class="fas fa-save me-1"></i>{{ $mode === 'edit' ? 'Update Track' : 'Add Track' }}
                </button>
                <a href="{{ route('caption-tracks.index', $digitalObjectId) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@php
    // Auto-generate label from language code if creating and label is empty
@endphp

<script nonce="{{ csp_nonce() }}">
// Auto-fill label from language if left blank on create
document.querySelector('{{ $mode === 'edit' ? '#label' : 'form' }}').addEventListener('submit', function(e) {
    var labelInput = document.getElementById('label');
    var langSelect = document.getElementById('language_code');
    if (labelInput && !labelInput.value && langSelect) {
        var langOpts = langSelect.options;
        var selectedOpt = langOpts[langSelect.selectedIndex];
        labelInput.value = selectedOpt ? selectedOpt.text.split(' — ')[1] : 'Untitled';
    }
});
</script>

@endsection
