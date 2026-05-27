<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chatbot Feature Flags
    |--------------------------------------------------------------------------
    */

    'enabled'                => env('AHG_CHATBOT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Maximum conversation-turn context
    |--------------------------------------------------------------------------
    | How many messages (user + assistant pairs) to include as context in
    | the RAG prompt. Trades off context-window pressure against richer
    | conversational history. Set to 0 to disable conversation memory.
    */

    'max_history'            => (int) env('AHG_CHATBOT_MAX_HISTORY', 20),

    /*
    |--------------------------------------------------------------------------
    | Maximum catalogue records injected as RAG context
    |--------------------------------------------------------------------------
    | Max information-object rows to retrieve from Qdrant/Elasticsearch
    | per turn and feed into the system prompt. Each record consumes
    | ~200–400 tokens of the LLM context window.
    */

    'max_context_records'    => (int) env('AHG_CHATBOT_MAX_CONTEXT', 5),

    /*
    |--------------------------------------------------------------------------
    | RAG grounding threshold
    |--------------------------------------------------------------------------
    | Minimum normalised grounding score (0–1) for a response to be accepted.
    | Responses below this threshold receive a low_grounding flag and are
    | surfaced in the admin review dashboard. Set to 0.0 to disable gating.
    */

    'grounding_threshold'   => (float) env('AHG_CHATBOT_GROUNDING', 0.5),

    /*
    |--------------------------------------------------------------------------
    | System prompt
    |--------------------------------------------------------------------------
    | Custom system prompt appended after the built-in archival-instruction
    | block. Leave null to use the built-in default.
    */

    'system_prompt'          => env('AHG_CHATBOT_SYSTEM_PROMPT', null),

    /*
    |--------------------------------------------------------------------------
    | Default model
    |--------------------------------------------------------------------------
    | Model name passed to LlmService when no user preference is set.
    | Null uses whatever the operator's default LLM config specifies.
    */

    'default_model'         => env('AHG_CHATBOT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Temperature
    |--------------------------------------------------------------------------
    | LLM sampling temperature for generative turns (0 = deterministic,
    | ~0.9 = very random). Archival description is factual; keep near 0.3.
    */

    'temperature'           => (float) env('AHG_CHATBOT_TEMPERATURE', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Qdrant collection name for RAG retrieval
    |--------------------------------------------------------------------------
    */

    'qdrant_collection'      => env('AHG_CHATBOT_QDRANT_COLLECTION', 'heratio-io'),

    /*
    |--------------------------------------------------------------------------
    | Guardrail mode
    |--------------------------------------------------------------------------
    | off | warn | mask | block  — see GuardrailService.
    | Default 'warn' is safe to deploy: flags but never blocks/mutates.
    */

    'guardrail_mode'         => env('AHG_CHATBOT_GUARDRAIL', 'warn'),

];
