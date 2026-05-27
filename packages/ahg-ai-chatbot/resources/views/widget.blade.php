{{-- AI Library Assistant - floating widget
     Include in any layout with @include('ahg-ai-chatbot::widget').
     Honours role gates: pass $chatbotShowWidget = false in the host view to suppress.
--}}
@php
    $chatbotEnabled = config('ahg-ai-chatbot.enabled', true) && config('ahg-ai-chatbot.widget.enabled', true);
    $showWidget    = $chatbotShowWidget ?? $chatbotEnabled;
@endphp
@if ($showWidget)
<div id="ahg-chatbot-widget" data-csrf="{{ csrf_token() }}"
     data-message-url="{{ route('chatbot.message') }}"
     data-history-url="{{ route('chatbot.history') }}"
     data-escalate-url="{{ route('chatbot.escalate') }}"
     data-reset-url="{{ route('chatbot.reset') }}"
     data-policy-url="{{ route('chatbot.policy') }}"
     data-open-label="{{ __('Open AI Library Assistant') }}"
     data-send-label="{{ __('Send') }}"
     data-escalate-label="{{ __('Talk to a librarian') }}"
     data-placeholder="{{ __('Ask the catalogue a question...') }}"
     data-greeting="{{ __('Hi! I am the library assistant. Ask me about anything in the catalogue and I will answer with citations.') }}"
     style="position: fixed; bottom: 24px; right: 24px; z-index: 1080;">

    <button type="button" id="ahg-chatbot-toggle"
            class="btn btn-primary rounded-circle shadow"
            style="width:56px;height:56px;font-size:24px;"
            aria-label="{{ __('Open AI Library Assistant') }}">
        <i class="bi bi-chat-dots"></i>
    </button>

    <div id="ahg-chatbot-panel" class="card shadow"
         style="display:none;position:absolute;bottom:72px;right:0;width:380px;max-width:90vw;height:520px;max-height:80vh;">
        <div class="card-header d-flex align-items-center justify-content-between bg-primary text-white">
            <strong>{{ __('AI Library Assistant') }}</strong>
            <div>
                <a href="{{ route('chatbot.policy') }}" target="_blank" class="text-white-50 me-2" title="{{ __('Privacy policy') }}">
                    <i class="bi bi-info-circle"></i>
                </a>
                <button type="button" id="ahg-chatbot-close" class="btn-close btn-close-white" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
        <div id="ahg-chatbot-log" class="card-body overflow-auto" style="background:#f8f9fa;font-size:0.92rem;"></div>
        <div class="card-footer p-2">
            <form id="ahg-chatbot-form" class="d-flex gap-2 mb-2">
                <input type="text" id="ahg-chatbot-input" class="form-control form-control-sm"
                       placeholder="{{ __('Ask the catalogue a question...') }}" maxlength="2000" autocomplete="off">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Send') }}</button>
            </form>
            <div class="d-flex justify-content-between small">
                <button type="button" id="ahg-chatbot-escalate" class="btn btn-link btn-sm p-0">{{ __('Talk to a librarian') }}</button>
                <button type="button" id="ahg-chatbot-reset" class="btn btn-link btn-sm p-0 text-muted">{{ __('Reset chat') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
#ahg-chatbot-log .cb-row{margin-bottom:.5rem}
#ahg-chatbot-log .cb-user{text-align:right}
#ahg-chatbot-log .cb-user .cb-bubble{background:#0d6efd;color:#fff}
#ahg-chatbot-log .cb-asst .cb-bubble{background:#fff;border:1px solid #dee2e6}
#ahg-chatbot-log .cb-bubble{display:inline-block;padding:.4rem .6rem;border-radius:.5rem;max-width:85%;white-space:pre-wrap;word-wrap:break-word}
#ahg-chatbot-log .cb-meta{font-size:.75rem;color:#6c757d;margin-top:.15rem}
#ahg-chatbot-log .cb-sources{font-size:.75rem;margin-top:.2rem}
#ahg-chatbot-log .cb-sources a{display:block;margin-top:.1rem}
</style>
<script>
(function(){
    var root = document.getElementById('ahg-chatbot-widget');
    if (!root) return;
    var csrf = root.dataset.csrf;
    var msgUrl = root.dataset.messageUrl;
    var historyUrl = root.dataset.historyUrl;
    var escalateUrl = root.dataset.escalateUrl;
    var resetUrl = root.dataset.resetUrl;
    var greeting = root.dataset.greeting;
    var log = document.getElementById('ahg-chatbot-log');
    var input = document.getElementById('ahg-chatbot-input');
    var panel = document.getElementById('ahg-chatbot-panel');
    var toggle = document.getElementById('ahg-chatbot-toggle');
    var closeBtn = document.getElementById('ahg-chatbot-close');
    var form = document.getElementById('ahg-chatbot-form');
    var escalateBtn = document.getElementById('ahg-chatbot-escalate');
    var resetBtn = document.getElementById('ahg-chatbot-reset');

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    }); }

    function addRow(role, text, meta, sources){
        var row = document.createElement('div');
        row.className = 'cb-row ' + (role==='user' ? 'cb-user' : 'cb-asst');
        var bubble = document.createElement('div');
        bubble.className = 'cb-bubble';
        bubble.textContent = text;
        row.appendChild(bubble);
        if (meta) {
            var m = document.createElement('div');
            m.className = 'cb-meta';
            m.textContent = meta;
            row.appendChild(m);
        }
        if (sources && sources.length) {
            var s = document.createElement('div');
            s.className = 'cb-sources';
            s.innerHTML = '<strong>Sources:</strong>';
            sources.forEach(function(src){
                var a = document.createElement('a');
                a.href = src.url || '#';
                a.target = '_blank';
                a.textContent = src.title || src.id || 'record';
                s.appendChild(a);
            });
            row.appendChild(s);
        }
        log.appendChild(row);
        log.scrollTop = log.scrollHeight;
    }

    function bootstrap(){
        log.innerHTML = '';
        addRow('assistant', greeting);
        fetch(historyUrl, {credentials:'same-origin'})
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(d){
                if (!d || !d.history) return;
                d.history.forEach(function(h){
                    addRow(h.role==='user'?'user':'assistant', h.content, null, h.sources||[]);
                });
            })
            .catch(function(){});
    }

    toggle.addEventListener('click', function(){
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        if (panel.style.display === 'block' && !log.dataset.bootstrapped) {
            bootstrap();
            log.dataset.bootstrapped = '1';
        }
    });
    closeBtn.addEventListener('click', function(){ panel.style.display='none'; });

    form.addEventListener('submit', function(e){
        e.preventDefault();
        var msg = input.value.trim();
        if (!msg) return;
        addRow('user', msg);
        input.value = '';
        addRow('assistant', '...');
        var placeholder = log.lastChild;
        fetch(msgUrl, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({message:msg})
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            log.removeChild(placeholder);
            if (d && d.success) {
                var meta = '';
                if (typeof d.grounding_score === 'number') {
                    meta = 'confidence ' + (d.grounding_score*100).toFixed(0) + '% - ' + (d.model||'');
                }
                addRow('assistant', d.reply, meta, d.sources||[]);
            } else {
                addRow('assistant', (d && d.error) || 'Sorry, I cannot respond right now.');
            }
        })
        .catch(function(){
            if (placeholder.parentNode) log.removeChild(placeholder);
            addRow('assistant', 'Network error.');
        });
    });

    escalateBtn.addEventListener('click', function(){
        var msg = prompt('What should we ask a librarian on your behalf?');
        if (!msg) return;
        fetch(escalateUrl, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({message:msg})
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d && d.success) {
                addRow('assistant', d.message);
            }
        })
        .catch(function(){
            addRow('assistant', 'Could not reach the escalation endpoint. Email reference@theahg.co.za instead.');
        });
    });

    resetBtn.addEventListener('click', function(){
        if (!confirm('Reset this conversation?')) return;
        fetch(resetUrl, {
            method:'POST',
            credentials:'same-origin',
            headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}
        }).then(function(){
            log.innerHTML='';
            addRow('assistant', greeting);
        });
    });
})();
</script>
@endif
