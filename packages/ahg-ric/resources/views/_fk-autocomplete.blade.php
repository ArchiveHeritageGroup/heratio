{{-- Reusable autocomplete widget for a foreign-key field.

Required vars:
  $name       — form field name (the hidden input that holds the FK id)
  $types      — comma-separated entity types for the autocomplete endpoint (e.g. "io", "digital_object", "place,rule")
  $label      — label shown above the input
  $currentId  — currently-selected id (null if none)
  $currentLabel — human-readable label of the currently-selected entity (null if none)
  $hint       — optional help text below the input
--}}
@php
    $uid = 'fkac-' . uniqid();
@endphp
<div class="fk-autocomplete mb-3" data-uid="{{ $uid }}">
    <label class="form-label">{{ $label }}</label>
    <div class="position-relative">
        <input type="text" id="{{ $uid }}-search" class="form-control" autocomplete="off"
               value="{{ $currentLabel ?? '' }}"
               placeholder="Start typing to search…">
        <input type="hidden" id="{{ $uid }}-id" name="{{ $name }}" value="{{ $currentId ?? '' }}">
        <button type="button" class="btn btn-sm btn-link position-absolute" style="right: 0.3rem; top: 0.35rem; display: {{ $currentId ? '' : 'none' }};"
                id="{{ $uid }}-clear" title="Clear selection"><i class="fas fa-times"></i></button>
        <div class="list-group position-absolute w-100" id="{{ $uid }}-ac" style="z-index:1050; display:none; max-height:240px; overflow-y:auto;"></div>
    </div>
    @isset($hint)<div class="form-text">{!! $hint !!}</div>@endisset
</div>
<script>
(function () {
    const uid = '{{ $uid }}';
    const types = '{{ $types }}';
    const search = document.getElementById(uid + '-search');
    const idInput = document.getElementById(uid + '-id');
    const ac = document.getElementById(uid + '-ac');
    const clearBtn = document.getElementById(uid + '-clear');
    let debounce;

    function hideAc() { ac.style.display = 'none'; }
    function toggleClearBtn() { clearBtn.style.display = idInput.value ? '' : 'none'; }

    search.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        idInput.value = '';
        toggleClearBtn();
        if (q.length < 2) { hideAc(); return; }
        debounce = setTimeout(() => {
            fetch(`/api/ric/v1/autocomplete?q=${encodeURIComponent(q)}&types=${encodeURIComponent(types)}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(items => {
                    if (!items.length) { hideAc(); return; }
                    ac.innerHTML = items.map(i =>
                        `<a href="#" class="list-group-item list-group-item-action py-1 px-2" data-id="${i.id}" data-label="${i.label.replace(/"/g, '&quot;')}">
                            <span class="badge bg-secondary me-1">${i.type}</span> ${i.label}
                        </a>`
                    ).join('');
                    ac.style.display = '';
                    ac.querySelectorAll('a').forEach(a => {
                        a.addEventListener('click', function (e) {
                            e.preventDefault();
                            idInput.value = this.dataset.id;
                            search.value = this.dataset.label;
                            toggleClearBtn();
                            hideAc();
                        });
                    });
                });
        }, 300);
    });

    clearBtn.addEventListener('click', function () {
        idInput.value = '';
        search.value = '';
        toggleClearBtn();
    });

    document.addEventListener('click', function (e) {
        if (!ac.contains(e.target) && e.target !== search) hideAc();
    });
})();
</script>
