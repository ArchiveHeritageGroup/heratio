@extends('theme::layouts.1col')
@section('title', $article ? __('Edit Article') : __('New Article'))
@section('content')
@php
    $editing = (bool) $article;
    $action  = $editing ? route('admin.articles.update', $article->id) : route('admin.articles.store');
    $val = fn(string $f, $d = null) => old($f, $editing ? ($article->{$f} ?? $d) : $d);
    $publishedLocal = $editing && $article->published_at
        ? \Carbon\Carbon::parse($article->published_at)->format('Y-m-d\TH:i') : '';
@endphp
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
        <h1 class="mb-0">{{ $editing ? __('Edit Article') : __('New Article') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @if($editing)@method('PUT')@endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('Title') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <input type="text" name="title" id="title" required class="form-control @error('title') is-invalid @enderror" value="{{ $val('title') }}">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="excerpt" class="form-label">{{ __('Excerpt') }}</label>
                            <textarea name="excerpt" id="excerpt" rows="2" class="form-control" maxlength="500" placeholder="{{ __('Short summary shown on cards and listings') }}">{{ $val('excerpt') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label for="body" class="form-label mb-0">{{ __('Body') }} <small class="text-muted">({{ __('Markdown or visual - toggle in the editor') }})</small></label>
                        </div>
                        {{-- Toast UI dual editor (Markdown + WYSIWYG tabs). The textarea stays as the
                             form field (hidden); the editor's Markdown is synced into it on submit. --}}
                        <textarea name="body" id="body" class="d-none">{{ $val('body') }}</textarea>
                        <div id="bodyEditor"></div>
                        <div class="form-text">{{ __('Switch between the Markdown and visual (WYSIWYG) tabs. Bold, italic, headings, lists, tables, links, quotes, code and image upload are on the toolbar.') }}</div>

                        <div class="mt-3">
                            <label for="attachments_label" class="form-label">{{ __('Downloads intro message') }}</label>
                            <input type="text" name="attachments_label" id="attachments_label" class="form-control" maxlength="255"
                                   value="{{ $val('attachments_label') }}"
                                   placeholder="{{ __('e.g. Download the cataloguing guide and templates below') }}">
                            <div class="form-text">{{ __('Optional. Shown just above the guides & templates download list on the published article.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><strong>{{ __('Publish') }}</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Status') }}</label>
                            <select name="status" id="status" class="form-select">
                                <option value="draft" @selected($val('status','draft')==='draft')>{{ __('Draft') }}</option>
                                <option value="published" @selected($val('status')==='published')>{{ __('Published') }}</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label for="published_at" class="form-label">{{ __('Publish date') }}</label>
                            <input type="datetime-local" name="published_at" id="published_at" class="form-control" value="{{ old('published_at', $publishedLocal) }}">
                            <div class="form-text">{{ __('Defaults to now when first published.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header"><strong>{{ __('Organise') }}</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="article_group" class="form-label">{{ __('Group') }}</label>
                            <input type="text" name="article_group" id="article_group" list="groupOptions" class="form-control" value="{{ $val('article_group') }}" placeholder="{{ __('e.g. Compliance, Product, News') }}">
                            <datalist id="groupOptions">
                                @foreach($groups as $g)<option value="{{ $g }}">@endforeach
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label for="author" class="form-label">{{ __('Author') }}</label>
                            <input type="text" name="author" id="author" class="form-control" value="{{ $val('author') }}">
                        </div>
                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <input type="text" name="slug" id="slug" class="form-control" value="{{ $val('slug') }}" placeholder="{{ __('auto from title if blank') }}">
                        </div>
                        @if($editing && $article->slug)
                        <div class="mb-0">
                            <label class="form-label">{{ __('Public URL') }}</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="article-permalink" readonly value="{{ route('articles.show', $article->slug) }}" onclick="this.select();">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('article-permalink').value); this.textContent='{{ __('Copied') }}'; setTimeout(()=>this.textContent='{{ __('Copy') }}',1500);">{{ __('Copy') }}</button>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header"><strong>{{ __('Cover image') }}</strong></div>
                    <div class="card-body">
                        @if($editing && $article->cover_image)
                            <img src="{{ $article->cover_image }}" alt="" class="img-fluid rounded mb-2">
                        @endif
                        <input type="file" name="cover" accept="image/*" class="form-control">
                        <div class="form-text">{{ __('JPG, PNG, GIF or WebP, up to 5 MB.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $editing ? __('Save Changes') : __('Create Article') }}</button>
            <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>

    {{-- Attachments (guides & templates): parent = this article, children = files.
         Separate form (cannot nest in the article form); only when editing. --}}
    @if($editing)
        <div class="card shadow-sm mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-paperclip me-1"></i>{{ __('Guides & Templates') }}</strong>
                <small class="text-muted">{{ __('Downloadable files published with this article') }}</small>
            </div>
            <div class="card-body">
                @if(!empty($attachments))
                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Type') }}</th><th>{{ __('Title') }}</th>
                                <th>{{ __('Description') }}</th><th>{{ __('File') }}</th>
                                <th class="text-end">{{ __('Size') }}</th><th></th>
                            </tr></thead>
                            <tbody>
                            @foreach($attachments as $att)
                                <tr>
                                    <td><span class="badge bg-{{ $att->kind === 'template' ? 'info' : 'success' }}">{{ __(ucfirst($att->kind)) }}</span></td>
                                    <td>{{ $att->title }}</td>
                                    <td class="text-muted small">{{ $att->description }}</td>
                                    <td><a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($att->file_path) }}" target="_blank" rel="noopener"><i class="fas fa-download me-1"></i>{{ $att->file_name }}</a></td>
                                    <td class="text-end text-muted small">{{ number_format($att->file_size / 1024, 0) }} KB</td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attEdit{{ $att->id }}" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></button>
                                        <form method="POST" action="{{ route('admin.articles.attachments.destroy', [$article->id, $att->id]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}');" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Per-attachment edit modals (Update side of CRUD). --}}
                    @foreach($attachments as $att)
                        <div class="modal fade" id="attEdit{{ $att->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('admin.articles.attachments.update', [$article->id, $att->id]) }}" enctype="multipart/form-data" class="modal-content">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ __('Edit attachment') }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('Type') }}</label>
                                            <select name="kind" class="form-select">
                                                @forelse(($attachmentKinds ?? collect()) as $k)
                                                    <option value="{{ $k->code }}" @selected($att->kind === $k->code)>{{ __($k->label) }}</option>
                                                @empty
                                                    <option value="guide" @selected($att->kind === 'guide')>{{ __('Guide') }}</option>
                                                    <option value="template" @selected($att->kind === 'template')>{{ __('Template') }}</option>
                                                @endforelse
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('Title') }}</label>
                                            <input type="text" name="title" class="form-control" value="{{ $att->title }}" maxlength="255">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('Description') }}</label>
                                            <input type="text" name="description" class="form-control" value="{{ $att->description }}" maxlength="500">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('Sort order') }}</label>
                                            <input type="number" name="sort_order" class="form-control" value="{{ $att->sort_order }}" min="0">
                                        </div>
                                        <div class="mb-1">
                                            <label class="form-label">{{ __('Replace file') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                            <input type="file" name="file" class="form-control">
                                            <div class="form-text">{{ __('Current file:') }} {{ $att->file_name }}. {{ __('Leave blank to keep it.') }}</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                        <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted">{{ __('No guides or templates attached yet.') }}</p>
                @endif

                <form method="POST" action="{{ route('admin.articles.attachments.store', $article->id) }}" enctype="multipart/form-data" class="row g-3 align-items-end border-top pt-3">
                    @csrf
                    <div class="col-md-2">
                        <label for="att_kind" class="form-label">{{ __('Type') }}</label>
                        <select name="kind" id="att_kind" class="form-select">
                            @forelse(($attachmentKinds ?? collect()) as $k)
                                <option value="{{ $k->code }}" @selected(($k->is_default ?? 0))>{{ __($k->label) }}</option>
                            @empty
                                <option value="guide">{{ __('Guide') }}</option>
                                <option value="template">{{ __('Template') }}</option>
                            @endforelse
                        </select>
                        <div class="form-text"><a href="{{ url('/admin/dropdowns') }}" target="_blank" rel="noopener">{{ __('Manage types') }}</a></div>
                    </div>
                    <div class="col-md-3">
                        <label for="att_title" class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" id="att_title" class="form-control" placeholder="{{ __('defaults to file name') }}" maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label for="att_description" class="form-label">{{ __('Description') }}</label>
                        <input type="text" name="description" id="att_description" class="form-control" placeholder="{{ __('Short description') }}" maxlength="500">
                    </div>
                    <div class="col-md-3">
                        <label for="att_file" class="form-label">{{ __('File') }}</label>
                        <input type="file" name="file" id="att_file" required class="form-control">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload"></i></button>
                    </div>
                    <div class="col-12">
                        <div class="form-text">{{ __('PDF, Word, Excel, PowerPoint, OpenDocument, CSV, TXT or ZIP.') }}</div>
                    </div>
                </form>
            </div>
        </div>
    @else
        <p class="text-muted mt-4"><i class="fas fa-info-circle me-1"></i>{{ __('Save the article first, then you can attach guides and templates.') }}</p>
    @endif

    {{-- Linked articles (bidirectional) — under Guides & Templates. heratio#1399 --}}
    @if($editing)
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong><i class="fas fa-link me-1"></i>{{ __('Linked articles') }}</strong> <span class="badge bg-secondary">{{ count($related) }}</span></div>
        <div class="card-body">
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-warning py-2">{{ session('error') }}</div>@endif

            <form action="{{ route('admin.articles.links.add', $article->id) }}" method="post" class="mb-3">
                @csrf
                <label class="form-label">{{ __('Add a linked article') }}</label>
                <div class="input-group">
                    <input type="text" name="target" class="form-control" list="post-options"
                           placeholder="{{ __('Search a title… or paste a /articles/… URL') }}" autocomplete="off" required>
                    <button class="btn btn-primary" type="submit">{{ __('Add & save') }}</button>
                </div>
                <input type="text" name="description" class="form-control mt-2" maxlength="500"
                       placeholder="{{ __('Description (optional) — shown under the linked article') }}">
                <datalist id="post-options">
                    @foreach($allPosts as $p)<option value="{{ $p['title'] }}"></option>@endforeach
                </datalist>
                <div class="form-text">{{ __('Bidirectional — appears on both articles and on the public article page. Repeat to add more.') }}</div>
            </form>

            @if(empty($related))
                <p class="text-muted mb-0">{{ __('No links yet. Add one above.') }}</p>
            @else
                <ul class="list-group" id="links-sortable">
                    @foreach($related as $rel)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2" data-id="{{ $rel['id'] }}" draggable="true">
                            <span class="d-flex align-items-start gap-2 flex-grow-1">
                                <i class="fas fa-grip-vertical text-muted mt-1 links-drag-handle" style="cursor:grab;" title="{{ __('Drag to reorder') }}"></i>
                                <span>
                                    <a href="{{ route('admin.articles.edit', $rel['id']) }}">{{ $rel['title'] }}</a>
                                    <span class="badge bg-{{ ($rel['status'] ?? '') === 'published' ? 'success' : 'secondary' }} ms-1">{{ $rel['status'] }}</span>
                                    @if(!empty($rel['description']))<span class="d-block text-muted small">{{ $rel['description'] }}</span>@endif
                                </span>
                            </span>
                            <span class="d-flex align-items-center gap-1 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-outline-secondary links-up" title="{{ __('Move up') }}"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary links-down" title="{{ __('Move down') }}"><i class="fas fa-arrow-down"></i></button>
                                <form action="{{ route('admin.articles.links.remove', [$article->id, $rel['id']]) }}" method="post" class="m-0">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- Standalone reorder form (kept OUT of the list so the per-row
                     remove forms are never nested). JS fills #links-order. --}}
                <form action="{{ route('admin.articles.links.reorder', $article->id) }}" method="post" class="mt-2" id="links-reorder-form">
                    @csrf @method('PUT')
                    <input type="hidden" name="order" id="links-order" value="">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">{{ __('Drag the handle or use the arrows, then Save order.') }}</small>
                        <button type="submit" class="btn btn-sm btn-primary" id="links-save-order" disabled><i class="fas fa-save me-1"></i>{{ __('Save order') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    @endif
</div>

@push('css')
<link rel="stylesheet" href="{{ asset('vendor/toastui/toastui-editor.min.css').'?v=322' }}">
@endpush
@push('js')
{{-- Toast UI is a UMD bundle: if an AMD loader (define.amd) is present on the page
     it would register as a module and never set window.toastui. Hide define across
     the script so it takes the browser-global branch. --}}
<script>window.__tuiD=window.define;window.__tuiE=window.exports;window.__tuiM=window.module;window.define=undefined;window.exports=undefined;window.module=undefined;</script>
<script src="{{ asset('vendor/toastui/toastui-editor-all.min.js').'?v=322' }}"></script>
<script>window.define=window.__tuiD;window.exports=window.__tuiE;window.module=window.__tuiM;</script>
<script>
(function () {
    var ta = document.getElementById('body');
    var holder = document.getElementById('bodyEditor');
    if (!ta || !holder) return;
    if (!window.toastui || !toastui.Editor) {
        holder.innerHTML = '<div class="alert alert-warning mb-0">Editor failed to load. Edit Markdown below.</div>';
        ta.classList.remove('d-none');
        ta.classList.add('form-control', 'font-monospace');
        ta.rows = 18;
        return;
    }

    var editor = new toastui.Editor({
        el: holder,
        height: '520px',
        initialEditType: 'markdown',   // opens in Markdown; one click to the WYSIWYG tab
        previewStyle: 'vertical',
        initialValue: ta.value,
        usageStatistics: false,
        hooks: {
            // Reuse the existing inline-image upload endpoint for drag/drop + toolbar image.
            addImageBlobHook: function (blob, callback) {
                var fd = new FormData();
                fd.append('image', blob);
                fd.append('_token', '{{ csrf_token() }}');
                fetch('{{ route('admin.articles.upload-image') }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { callback(d.url, ''); })
                    .catch(function () { alert('{{ __('Image upload failed.') }}'); });
            }
        }
    });

    // Sync the editor's Markdown back into the form field before the article saves.
    var form = ta.closest('form');
    if (form) { form.addEventListener('submit', function () { ta.value = editor.getMarkdown(); }); }
})();
</script>
{{-- Linked-articles reorder: HTML5 drag on desktop + up/down arrows for touch/mobile
     (HTML5 drag does not fire on touch). Both mutate the list, then a single
     "Save order" button posts the resulting id order. --}}
<script>
(function () {
    var ul = document.getElementById('links-sortable');
    if (!ul) return;
    var orderInput = document.getElementById('links-order');
    var saveBtn = document.getElementById('links-save-order');
    var dragEl = null;

    function sync() {
        if (!orderInput) return;
        orderInput.value = Array.prototype.map.call(
            ul.querySelectorAll('[data-id]'), function (li) { return li.getAttribute('data-id'); }
        ).join(',');
    }
    function dirty() { if (saveBtn) saveBtn.disabled = false; sync(); }
    sync();

    // Arrows — reliable on mobile/keyboard.
    ul.addEventListener('click', function (e) {
        var up = e.target.closest('.links-up');
        var down = e.target.closest('.links-down');
        if (!up && !down) return;
        var li = e.target.closest('[data-id]');
        if (!li) return;
        if (up && li.previousElementSibling) { ul.insertBefore(li, li.previousElementSibling); dirty(); }
        if (down && li.nextElementSibling) { ul.insertBefore(li.nextElementSibling, li); dirty(); }
    });

    // HTML5 drag — desktop.
    ul.addEventListener('dragstart', function (e) {
        var li = e.target.closest('[data-id]');
        if (!li) return;
        dragEl = li; li.classList.add('opacity-50');
        if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; }
    });
    ul.addEventListener('dragend', function () {
        if (dragEl) { dragEl.classList.remove('opacity-50'); dragEl = null; }
    });
    ul.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragEl) return;
        var li = e.target.closest('[data-id]');
        if (!li || li === dragEl) return;
        var rect = li.getBoundingClientRect();
        var after = (e.clientY - rect.top) > rect.height / 2;
        ul.insertBefore(dragEl, after ? li.nextElementSibling : li);
    });
    ul.addEventListener('drop', function (e) { e.preventDefault(); dirty(); });
})();
</script>
@endpush
@endsection
