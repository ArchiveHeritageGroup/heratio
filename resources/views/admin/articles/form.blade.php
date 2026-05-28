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
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <label for="body" class="form-label mb-0">{{ __('Body') }} <small class="text-muted">({{ __('Markdown') }})</small></label>
                            <span>
                                <input type="file" id="inlineImage" accept="image/*" class="d-none">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="insertImageBtn"><i class="fas fa-image me-1"></i>{{ __('Insert image') }}</button>
                            </span>
                        </div>
                        <textarea name="body" id="body" rows="18" class="form-control font-monospace" style="font-size:.9rem;">{{ $val('body') }}</textarea>
                        <div class="form-text">{{ __('Markdown supported. Use the Insert image button to upload and embed an image.') }}</div>
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
</div>

@push('js')
<script>
(function () {
    const btn = document.getElementById('insertImageBtn');
    const file = document.getElementById('inlineImage');
    const body = document.getElementById('body');
    if (!btn || !file || !body) return;

    btn.addEventListener('click', () => file.click());
    file.addEventListener('change', async () => {
        if (!file.files.length) return;
        const fd = new FormData();
        fd.append('image', file.files[0]);
        fd.append('_token', '{{ csrf_token() }}');
        btn.disabled = true;
        try {
            const res = await fetch('{{ route('admin.articles.upload-image') }}', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('upload failed');
            const data = await res.json();
            const snippet = '\n![](' + data.url + ')\n';
            const pos = body.selectionStart ?? body.value.length;
            body.value = body.value.slice(0, pos) + snippet + body.value.slice(pos);
        } catch (e) {
            alert('{{ __('Image upload failed.') }}');
        } finally {
            btn.disabled = false;
            file.value = '';
        }
    });
})();
</script>
@endpush
@endsection
