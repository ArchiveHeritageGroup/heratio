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
    | Preservation-knowledge grounding passages (issue #1243)
    |--------------------------------------------------------------------------
    | How many curated digital-preservation passages to retrieve from the
    | in-repo knowledge corpus (docs/reference/dp-*.md + preservation help
    | articles) and inject as a supplementary grounding block when the query
    | looks like a preservation question. Deterministic, file-based retrieval -
    | NO AI / embedding / gateway call is made. Set to 0 to disable the block.
    */

    'preservation_passages'  => (int) env('AHG_CHATBOT_PRESERVATION_PASSAGES', 3),

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
    | Reply in the input message language (heratio#1275)
    |--------------------------------------------------------------------------
    | When true, the chatbot detects the language a message was typed in (local,
    | no-network, no-LLM heuristic) and replies in that language via the sanctioned
    | MT route, regardless of the UI locale. Low-confidence or MT-unsupported input
    | falls back to the UI locale / English (never a qwen "reply in X" prompt).
    | Default off: the #1273 UI-locale behaviour stays unless explicitly enabled.
    */

    'reply_in_input_language' => (bool) env('AHG_CHATBOT_REPLY_IN_INPUT_LANGUAGE', false),

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

    /*
    |--------------------------------------------------------------------------
    | Floating widget injection
    |--------------------------------------------------------------------------
    | When 'enabled' is true, the InjectChatbotWidget middleware appends a
    | floating chat widget to every authenticated HTML response. Set to false
    | to require explicit layout-level @include('ahg-ai-chatbot::widget')
    | placement instead.
    */

    'widget' => [
        'enabled' => env('AHG_CHATBOT_WIDGET_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API channel (issue #1095)
    |--------------------------------------------------------------------------
    | Inbound webhook + outbound send via the Meta WhatsApp Cloud API. The
    | whole channel is gated by `enabled` (default false) so the public webhook
    | route 404s until an operator turns it on and supplies credentials.
    |
    | Secrets are read from env, NEVER hardcoded:
    |   - verify_token : the string echoed back during Meta's GET verification
    |   - access_token : Cloud API bearer token used for outbound sends
    |   - phone_number_id : the WhatsApp phone-number-id for the send endpoint
    |   - app_secret   : (optional) used to validate X-Hub-Signature-256
    |   - api_base / api_version : Graph API endpoint (override per environment)
    */

    'whatsapp' => [
        'enabled'         => env('AHG_CHATBOT_WHATSAPP_ENABLED', false),
        'verify_token'    => env('AHG_CHATBOT_WHATSAPP_VERIFY_TOKEN'),
        'access_token'    => env('AHG_CHATBOT_WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('AHG_CHATBOT_WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret'      => env('AHG_CHATBOT_WHATSAPP_APP_SECRET'),
        'api_base'        => env('AHG_CHATBOT_WHATSAPP_API_BASE', 'https://graph.facebook.com'),
        'api_version'     => env('AHG_CHATBOT_WHATSAPP_API_VERSION', 'v21.0'),
    ],

];
