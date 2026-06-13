{{--
  ai-decision.blade.php - <x-research::ai-decision/> wrapper for the AI accept/reject
  control (heratio#1252). Delegates to the canonical partial so there is one source
  of truth; views may also @include('research::research._ai-decision', [...]) directly.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0

      <x-research::ai-decision slice="writing" :id="$version->id" :decision="$version->ai_decision" />
--}}
@props([
    'slice'    => '',
    'id'       => 0,
    'decision' => null,
])
@include('research::research._ai-decision', ['slice' => $slice, 'id' => $id, 'decision' => $decision])
