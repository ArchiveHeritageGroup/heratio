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
                        <div class="mb-0">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <input type="text" name="slug" id="slug" class="form-control" value="{{ $val('slug') }}" placeholder="{{ __('auto from title if blank') }}">
                        </div>
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
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.articles.attachments.destroy', [$article->id, $att->id]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}');" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
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
                        <div class="form-text">{{ __('PDF, Word, Excel, PowerPoint, OpenDocument, CSV, TXT or ZIP. Up to 20 MB.') }}</div>
                    </div>
                </form>
            </div>
        </div>
    @else
        <p class="text-muted mt-4"><i class="fas fa-info-circle me-1"></i>{{ __('Save the article first, then you can attach guides and templates.') }}</p>
    @endif
</div>

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@toast-ui/editor@3/dist/toastui-editor.min.css">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/@toast-ui/editor@3/dist/toastui-editor-all.min.js"></script>
<script>
(function () {
    if (!window.toastui || !toastui.Editor) return;
    var ta = document.getElementById('body');
    var holder = document.getElementById('bodyEditor');
    if (!ta || !holder) return;

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
@endpush
@endsection
