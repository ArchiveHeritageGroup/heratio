@extends('theme::layouts.2col')
@section('title', 'AI Condition')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>AI Condition</h1>
@endsection

@section('content')
    <div class="accordion mb-3" id="aiConditionAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="ai-condition-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ai-condition-collapse" aria-expanded="false" aria-controls="ai-condition-collapse">
            AI Condition assessment settings
          </button>
        </h2>
        <div id="ai-condition-collapse" class="accordion-collapse collapse" aria-labelledby="ai-condition-heading">
          <div class="accordion-body">
            <p>AI condition assessment settings are managed in the AHG settings section.</p>
            <a href="{{ route('settings.ahg', 'ai_condition') }}" class="btn atom-btn-white">
              <i class="fas fa-robot me-1"></i>Go to AI Condition settings
            </a>
          </div>
        </div>
      </div>
    </div>
@endsection
