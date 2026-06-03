{{-- Chatbot UI --}}
@extends('layouts/admin')

@section('title', __('chatbot.title') ?? 'Chatbot — Heratio')

@section('content')
<div class="container-fluid py-4">

    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-1">{{ __('chatbot.title') ?? 'Archival Research Assistant' }}</h2>
            <p class="text-muted small mb-0">
                Ask questions about the catalogue in natural language.
                Responses are grounded in the archival descriptions and cite their sources.
            </p>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-secondary me-1" id="model-badge">model: –</span>
            <span class="badge bg-info" id="grounding-badge">grounding: –</span>
            <button class="btn btn-outline-secondary btn-sm ms-2" id="reset-btn" title="{{ __('Clear conversation') }}">
                <i class="fas fa-trash-alt"></i> Clear
            </button>
        </div>
    </div>

    {{-- Stats bar --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted text-uppercase">Messages (30d)</div>
                    <div class="fw-bold">{{ $stats['messages_30d'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted text-uppercase">Sessions (30d)</div>
                    <div class="fw-bold">{{ $stats['sessions_30d'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted text-uppercase">Total messages</div>
                    <div class="fw-bold">{{ $stats['total_messages'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted text-uppercase">Low grounding (30d)</div>
                    <div class="fw-bold {{ ($stats['low_grounding_30d'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">
                        {{ $stats['low_grounding_30d'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chat area --}}
    <div class="card shadow-sm">
        <div class="card-body p-0" style="min-height: 480px;">

            {{-- Messages --}}
            <div id="chat-messages" class="overflow-auto p-4" style="height: 420px;">

                {{-- System greeting --}}
                <div class="d-flex flex-row justify-content-start mb-3">
                    <div class="bg-white border rounded-3 shadow-sm p-3" style="max-width: 75%;">
                        <div class="small text-muted mb-1">
                            <i class="fas fa-robot me-1"></i> Heratio Assistant
                        </div>
                        <div class="message-text">
                            Welcome to the Heratio Archival Research Assistant.
                            Start by typing a question about the catalogue below.
                        </div>
                    </div>
                </div>

                @foreach ($history as $msg)
                    @if ($msg['role'] === 'user')
                        <div class="d-flex flex-row justify-content-end mb-3">
                            <div class="bg-primary text-white rounded-3 shadow-sm p-3"
                                 style="max-width: 75%;">
                                <div class="small opacity-75 mb-1">You</div>
                                <div>{{ $msg['content'] }}</div>
                            </div>
                        </div>
                    @else
                        <div class="d-flex flex-row justify-content-start mb-3">
                            <div class="bg-white border rounded-3 shadow-sm p-3"
                                 style="max-width: 75%;">
                                <div class="small text-muted mb-1">
                                    <i class="fas fa-robot me-1"></i> Assistant
                                    @if ($msg['grounding_score'] !== null)
                                        <span class="ms-2 badge {{ $msg['grounding_score'] >= 0.5 ? 'bg-success' : 'bg-warning' }} small">
                                            G: {{ number_format($msg['grounding_score'], 2) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="message-text mb-2">{{ $msg['content'] }}</div>

                                @if (!empty($msg['sources']))
                                    <div class="mt-small border-top pt-2">
                                        <div class="small text-muted mb-1">Sources</div>
                                        @foreach ($msg['sources'] as $src)
                                            <div class="small">
                                                {{ $src['ref'] ?? '' }}
                                                <strong>{{ $src['title'] }}</strong>
                                                @if ($src['identifier'])
                                                    <span class="text-muted">({{ $src['identifier'] }})</span>
                                                @endif
                                                @if ($src['url'])
                                                    <a href="{{ $src['url'] }}" target="_blank" class="ms-1 small">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- Typing placeholder (hidden until active) --}}
                <div id="typing-indicator" class="d-flex flex-row justify-content-start mb-3" style="display: none;">
                    <div class="bg-white border rounded-3 shadow-sm p-3" style="max-width: 75%;">
                        <div class="small text-muted mb-1">
                            <i class="fas fa-robot me-1"></i> Assistant
                        </div>
                        <span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i> thinking…</span>
                    </div>
                </div>
            </div>

            {{-- Input area --}}
            <div class="border-top p-3 bg-light">
                <form id="chat-form" class="d-flex gap-2">
                    @csrf
                    <input type="hidden" name="_session" value="{{ $sessionId }}">
                    <textarea
                        id="chat-input"
                        name="message"
                        class="form-control"
                        rows="2"
                        placeholder="{{ __('Ask about the archive… e.g. \'What records exist about the 1976 student uprisings?\'') }}"
                        maxlength="4000"
                        required
                        autofocus
                    ></textarea>
                    <button type="submit" class="btn btn-primary align-self-end" id="send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                <div class="small text-muted mt-1" id="status-line"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var sessionId = {{ json_encode($sessionId) }};
    var chatMessages = document.getElementById('chat-messages');
    var chatForm = document.getElementById('chat-form');
    var chatInput = document.getElementById('chat-input');
    var sendBtn = document.getElementById('send-btn');
    var typingIndicator = document.getElementById('typing-indicator');
    var statusLine = document.getElementById('status-line');
    var groundingBadge = document.getElementById('grounding-badge');
    var modelBadge = document.getElementById('model-badge');

    function scrollBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addUserMessage(text) {
        var div = document.createElement('div');
        div.className = 'd-flex flex-row justify-content-end mb-3';
        div.innerHTML = '<div class="bg-primary text-white rounded-3 shadow-sm p-3" style="max-width: 75%;">' +
            '<div class="small opacity-75 mb-1">You</div>' +
            '<div>' + escapeHtml(text) + '</div>' +
            '</div>';
        chatMessages.insertBefore(div, typingIndicator);
        scrollBottom();
    }

    function addAssistantMessage(data) {
        var sourcesHtml = '';
        if (data.sources && data.sources.length) {
            sourcesHtml = '<div class="mt-2 border-top pt-2">' +
                '<div class="small text-muted mb-1">Sources</div>';
            data.sources.forEach(function (src) {
                sourcesHtml += '<div class="small">' +
                    (src.ref || '') + ' <strong>' + escapeHtml(src.title || '') + '</strong>';
                if (src.identifier) sourcesHtml += ' <span class="text-muted">(' + escapeHtml(src.identifier) + ')</span>';
                if (src.url) sourcesHtml += ' <a href="' + escapeHtml(src.url) + '" target="_blank" class="ms-1 small"><i class="fas fa-external-link-alt"></i></a>';
                sourcesHtml += '</div>';
            });
            sourcesHtml += '</div>';
        }

        var gsBadge = '';
        if (data.grounding_score !== null) {
            var gsClass = data.grounding_score >= 0.5 ? 'bg-success' : 'bg-warning';
            gsBadge = '<span class="ms-2 badge ' + gsClass + ' small">G: ' + data.grounding_score.toFixed(2) + '</span>';
        }

        var div = document.createElement('div');
        div.className = 'd-flex flex-row justify-content-start mb-3';
        div.innerHTML = '<div class="bg-white border rounded-3 shadow-sm p-3" style="max-width: 75%;">' +
            '<div class="small text-muted mb-1"><i class="fas fa-robot me-1"></i> Assistant ' + gsBadge + '</div>' +
            '<div class="message-text mb-2">' + escapeHtml(data.reply || '') + '</div>' +
            sourcesHtml +
            '</div>';
        chatMessages.insertBefore(div, typingIndicator);
        scrollBottom();

        // Update badges
        if (data.model) modelBadge.textContent = 'model: ' + data.model;
        if (data.grounding_score !== null) {
            var gClass = data.grounding_score >= 0.5 ? 'text-success' : 'text-warning';
            groundingBadge.textContent = 'grounding: ' + data.grounding_score.toFixed(2);
            groundingBadge.className = 'badge ' + gClass;
        }
        if (data.flags && data.flags.includes('low_grounding')) {
            groundingBadge.textContent += ' !';
        }
    }

    function showError(message) {
        var div = document.createElement('div');
        div.className = 'd-flex flex-row justify-content-start mb-3';
        div.innerHTML = '<div class="bg-danger text-white rounded-3 shadow-sm p-3" style="max-width: 75%;">' +
            '<div class="small opacity-75 mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Error</div>' +
            '<div>' + escapeHtml(message) + '</div>' +
            '</div>';
        chatMessages.insertBefore(div, typingIndicator);
        scrollBottom();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"');
    }

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = chatInput.value.trim();
        if (!message) return;

        chatInput.value = '';
        sendBtn.disabled = true;
        typingIndicator.style.display = 'flex';
        statusLine.textContent = 'Sending…';
        scrollBottom();
        addUserMessage(message);

        var formData = new FormData();
        formData.append('message', message);
        formData.append('_session', sessionId);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route('chatbot.message') }}', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            typingIndicator.style.display = 'none';
            sendBtn.disabled = false;

            if (data.success) {
                addAssistantMessage(data);
                statusLine.textContent = '';
            } else {
                showError(data.error || 'Chatbot unavailable');
                statusLine.textContent = '';
            }
        })
        .catch(function (err) {
            typingIndicator.style.display = 'none';
            sendBtn.disabled = false;
            showError('Network error: ' + err.message);
            statusLine.textContent = '';
        });
    });

    // Reset button
    document.getElementById('reset-btn').addEventListener('click', function () {
        if (!confirm('Clear this conversation?')) return;
        var formData = new FormData();
        formData.append('_session', sessionId);
        formData.append('_token', '{{ csrf_token() }}');
        fetch('{{ route('chatbot.reset') }}', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            chatMessages.querySelectorAll('.d-flex').forEach(function (el) { el.remove(); });
            chatMessages.appendChild(typingIndicator);
            groundingBadge.textContent = 'grounding: –';
            groundingBadge.className = 'badge bg-info';
            modelBadge.textContent = 'model: –';
        });
    });

    // Scroll to bottom on load
    scrollBottom();
})();
</script>
@endpush
