@extends('theme::layouts.1col')
@section('title', 'Source Assessment — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-clipboard-check',
    'featureTitle' => 'Source Assessment',
    'featureDescription' => 'Evaluate the reliability and authenticity of this archival source',
  ])

  <div class="card">
    <div class="card-body">
      <form>
        <div class="mb-3">
          <label class="form-label fw-bold">Authenticity</label>
          <select class="form-select" style="max-width:300px;">
            <option>— Select —</option>
            <option>Original</option>
            <option>Certified copy</option>
            <option>Copy</option>
            <option>Transcription</option>
            <option>Unknown</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Reliability</label>
          <select class="form-select" style="max-width:300px;">
            <option>— Select —</option>
            <option>Primary source</option>
            <option>Secondary source</option>
            <option>Tertiary source</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Notes</label>
          <textarea class="form-control" rows="4" placeholder="Assessment notes..."></textarea>
        </div>
        <button type="button" class="btn atom-btn-outline-success" onclick="alert('Source assessment save — migration in progress'); return false;">
          <i class="fas fa-save me-1"></i> Save assessment
        </button>
      </form>
    </div>
  </div>
@endsection
