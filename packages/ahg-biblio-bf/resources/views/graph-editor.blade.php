@extends('theme::layouts.1col')

@section('title', __('BIBFRAME Graph Editor'))

@section('content')
<div class="container py-4" style="max-width: 1100px;">
    <h1 class="h3">{{ __('BIBFRAME Graph Editor') }}</h1>
    <p class="text-muted">{{ __('Edit the Work / Instance / Item graph and its Contribution + Subject nodes inline. Saves round-trip through library_item, library_item_creator, and the term taxonomy.') }}</p>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-work">bf:Work + bf:Instance</a></li>
        <li class="nav-item"><a class="nav-link"        data-bs-toggle="tab" href="#tab-contributors">bf:Contribution</a></li>
        <li class="nav-item"><a class="nav-link"        data-bs-toggle="tab" href="#tab-subjects">bf:Topic (Subjects)</a></li>
        <li class="nav-item"><a class="nav-link"        data-bs-toggle="tab" href="#tab-rdf">RDF preview</a></li>
    </ul>

    <div class="tab-content">

        {{-- Work + Instance properties --}}
        <div id="tab-work" class="tab-pane fade show active">
            <form method="POST" action="{{ route('bibframe.editor.work', $item->id) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">bf:title / dcterms:title</label>
                        <input type="text" name="title" class="form-control" required value="{{ old('title', $item->title) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">bf:identifiedBy &raquo; bf:Isbn</label>
                        <input type="text" name="isbn" class="form-control" value="{{ old('isbn', $item->isbn) }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">bflc:Subtitle</label>
                        <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $item->subtitle) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">bf:identifiedBy &raquo; bf:Issn</label>
                        <input type="text" name="issn" class="form-control" value="{{ old('issn', $item->issn) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">bf:provisionActivity &raquo; bf:Publication &raquo; bf:agent</label>
                        <input type="text" name="publisher" class="form-control" value="{{ old('publisher', $item->publisher) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">bf:provisionActivity &raquo; bf:Publication &raquo; bf:place</label>
                        <input type="text" name="publication_place" class="form-control" value="{{ old('publication_place', $item->publication_place) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">bf:provisionActivity &raquo; bf:Publication &raquo; bf:date</label>
                        <input type="text" name="publication_date" class="form-control" value="{{ old('publication_date', $item->publication_date) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">bf:language</label>
                        <input type="text" name="language" class="form-control" value="{{ old('language', $item->language) }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">bf:editionStatement</label>
                        <input type="text" name="edition" class="form-control" value="{{ old('edition', $item->edition) }}">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Save Work + Instance') }}</button>
                    @if ($item->work_key)
                        <a href="{{ route('library.work-cluster.show', $item->work_key) }}" class="btn btn-outline-secondary">
                            {{ __('View FRBR cluster') }}
                            <span class="badge bg-light text-dark ms-1">{{ Str::limit($item->work_key, 8, '') }}</span>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Contributors --}}
        <div id="tab-contributors" class="tab-pane fade">
            <table class="table table-sm">
                <thead><tr><th>{{__('Name')}}</th><th>{{__('Role')}}</th><th>{{__('Authority URI')}}</th><th></th></tr></thead>
                <tbody>
                @forelse ($contributors as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td><small>{{ $c->role ?: '-' }}</small></td>
                        <td><small><code>{{ $c->authority_uri ?: '-' }}</code></small></td>
                        <td>
                            <form method="POST" action="{{ route('bibframe.editor.contributor.delete', [$item->id, $c->id]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center">{{ __('No contributors yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('bibframe.editor.contributor', $item->id) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-4"><input name="name" class="form-control" placeholder="{{ __('Contributor name') }}" required></div>
                <div class="col-md-3"><input name="role" class="form-control" placeholder="{{ __('author / editor / illustrator') }}"></div>
                <div class="col-md-3"><input name="authority_uri" class="form-control" placeholder="{{ __('https://id.loc.gov/...') }}"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">{{ __('Add') }}</button></div>
            </form>
        </div>

        {{-- Subjects --}}
        <div id="tab-subjects" class="tab-pane fade">
            <p class="text-muted small">{{ __('Subjects are sourced from the taxonomy. To add a subject, type the exact term name; new terms must first be created in /admin/taxonomy.') }}</p>
            <table class="table table-sm">
                <thead><tr><th>{{ __('Term') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($subjects as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>
                            <form method="POST" action="{{ route('bibframe.editor.subject.delete', [$item->id, $s->term_id]) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted text-center">{{ __('No subjects yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('bibframe.editor.subject', $item->id) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-9"><input name="subject_name" class="form-control" placeholder="{{ __('Exact term name from the taxonomy') }}" required></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">{{ __('Add subject') }}</button></div>
            </form>
        </div>

        {{-- RDF preview --}}
        <div id="tab-rdf" class="tab-pane fade">
            <p class="text-muted">{{ __('Use the export view at') }} <a href="{{ route('bibframe.export') }}">{{ route('bibframe.export') }}</a> {{ __('to download this record as RDF/XML, Turtle, or JSON-LD.') }}</p>
        </div>
    </div>
</div>
@endsection
