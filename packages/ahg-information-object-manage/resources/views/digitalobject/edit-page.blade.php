{{--
  Edit Digital Object — Heratio
  Migrated from AtoM ahgThemeB5Plugin digitalobject/editSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems

  This file is part of Heratio.
  Heratio is free software under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')

@section('title', 'Edit ' . mb_strtolower(config('app.ui_label_digitalobject', 'digital object')) . ' — ' . ($ioTitle ?: 'Untitled'))

@section('content')
<div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x {{ $mediaIcon }} me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
        <h1 class="mb-0">Edit {{ mb_strtolower(config('app.ui_label_digitalobject', 'digital object')) }}</h1>
        @if($ioTitle)
            <span class="small text-muted">{{ e($ioTitle) }}</span>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('io.digitalobject.update', $do->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-lg-8">

            {{-- Preview --}}
            @php
                $previewUrl = $refUrl ?: $thumbUrl;
                $isImage = $do->media_type_id == 136;
                $isVideo = $do->media_type_id == 138;
                $isAudio = $do->media_type_id == 135;
            @endphp
            @if($previewUrl || $isVideo || $isAudio)
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-eye me-2"></i>{{ __('Preview') }}</h5>
                </div>
                <div class="card-body text-center bg-light" style="min-height:200px;">
                    @if($isImage && $previewUrl)
                        <img src="{{ $previewUrl }}" class="img-fluid rounded shadow" style="max-height:400px;" alt="">
                    @elseif($isVideo && $masterUrl)
                        <video controls class="w-100" style="max-height:400px;">
                            <source src="{{ $masterUrl }}" type="{{ $do->mime_type }}">
                        </video>
                    @elseif($isAudio && $masterUrl)
                        <audio controls class="w-100 mt-4">
                            <source src="{{ $masterUrl }}" type="{{ $do->mime_type }}">
                        </audio>
                    @elseif($previewUrl)
                        <img src="{{ $previewUrl }}" class="img-fluid rounded" style="max-height:200px;" alt="">
                    @else
                        <div class="py-5 text-muted">
                            <i class="fas {{ $mediaIcon }} fa-4x mb-3"></i>
                            <p>No preview available</p>
                        </div>
                    @endif
                </div>
                @if($masterUrl)
                <div class="card-footer text-center">
                    <a href="{{ $masterUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>{{ __('View Original') }}
                    </a>
                    <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>{{ __('Download') }}
                    </a>
                </div>
                @endif
            </div>
            @endif

            {{-- Master --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-file me-2"></i>{{ __('Master') }}</h5></div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-0">{{ __('Filename') }}</label>
                            <p class="fw-bold mb-0">{{ e($do->name) }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-0">{{ __('Filesize') }}</label>
                            <p class="mb-0">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($do->byte_size) }}</p>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small mb-0">{{ __('MIME') }}</label>
                            <p class="mb-0"><code>{{ e($do->mime_type) }}</code></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="media_type_id" class="form-label">{{ __('Media type') }}</label>
                        <select class="form-select" id="media_type_id" name="media_type_id">
                            @foreach($mediaTypes as $mtId => $mtName)
                                <option value="{{ $mtId }}" @selected($do->media_type_id == $mtId)>{{ $mtName }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($hasChildren)
                    <div class="mb-3">
                        <label for="display_as_compound" class="form-label">{{ __('View children as compound?') }}</label>
                        <select class="form-select" id="display_as_compound" name="display_as_compound">
                            <option value="1" @selected($do->display_as_compound ?? false)>Yes</option>
                            <option value="0" @selected(!($do->display_as_compound ?? false))>No</option>
                        </select>
                    </div>
                    @endif

                    <hr>
                    <label class="form-label">{{ __('Replace master file') }}</label>
                    <input type="file" class="form-control" name="replace_file">
                    <div class="form-text">Select a new file to replace the existing master.</div>
                </div>
            </div>

            {{-- Reference representation --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-image me-2"></i>{{ __('Reference representation') }}</h5></div>
                <div class="card-body">
                    @if($referenceImage)
                        <div class="row align-items-center">
                            @if($refUrl)
                            <div class="col-md-3 text-center mb-3 mb-md-0">
                                <img src="{{ $refUrl }}" class="img-thumbnail" style="max-height:120px;" alt="">
                            </div>
                            @endif
                            <div class="col">
                                <p class="mb-1"><strong>{{ __('Filename:') }}</strong> {{ e($referenceImage->name) }}</p>
                                <p class="mb-2"><strong>{{ __('Filesize:') }}</strong> {{ \AhgCore\Services\DigitalObjectService::formatFileSize($referenceImage->byte_size) }}</p>
                                <a href="{{ $refUrl }}" target="_blank" class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-external-link-alt me-1"></i>{{ __('View') }}</a>
                                {{-- PSIS-parity per-representation delete. Button is
                                     wired via HTML5 form="..." to a <form> outside the
                                     outer edit form (nested forms are invalid HTML).
                                     The form is rendered at the bottom of this view. --}}
                                <button type="submit" form="rep-delete-ref-{{ $referenceImage->id }}" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('{{ __('Delete this reference representation? The master file will remain intact.') }}');">
                                    <i class="fas fa-times me-1"></i>{{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">{{ __('Upload reference image') }}</label>
                            <input type="file" class="form-control" name="repFile_reference" accept="image/*">
                        </div>
                    @endif
                </div>
            </div>

            {{-- Thumbnail representation --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-th-large me-2"></i>{{ __('Thumbnail representation') }}</h5></div>
                <div class="card-body">
                    @if($thumbnailImage)
                        <div class="row align-items-center">
                            @if($thumbUrl)
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <img src="{{ $thumbUrl }}" class="img-thumbnail" style="max-height:80px;" alt="">
                            </div>
                            @endif
                            <div class="col">
                                <p class="mb-1"><strong>{{ __('Filename:') }}</strong> {{ e($thumbnailImage->name) }}</p>
                                <p class="mb-2"><strong>{{ __('Filesize:') }}</strong> {{ \AhgCore\Services\DigitalObjectService::formatFileSize($thumbnailImage->byte_size) }}</p>
                                <a href="{{ $thumbUrl }}" target="_blank" class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-external-link-alt me-1"></i>{{ __('View') }}</a>
                                <button type="submit" form="rep-delete-thumb-{{ $thumbnailImage->id }}" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('{{ __('Delete this thumbnail representation? The master file will remain intact.') }}');">
                                    <i class="fas fa-times me-1"></i>{{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">{{ __('Upload thumbnail image') }}</label>
                            <input type="file" class="form-control" name="repFile_thumbnail" accept="image/*">
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Info') }}</h6></div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted">ID</td><td><strong>{{ $do->id }}</strong></td></tr>
                        <tr><td class="text-muted">Size</td><td>{{ \AhgCore\Services\DigitalObjectService::formatFileSize($do->byte_size) }}</td></tr>
                        @if($do->checksum)
                        <tr><td class="text-muted">Checksum</td><td><code class="small">{{ substr($do->checksum, 0, 12) }}...</code></td></tr>
                        @endif
                        @if($do->mime_type)
                        <tr><td class="text-muted">MIME</td><td><code class="small">{{ $do->mime_type }}</code></td></tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Derivatives') }}</h6></div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">Reference <span class="badge bg-{{ $referenceImage ? 'success' : 'secondary' }}"><i class="fas fa-{{ $referenceImage ? 'check' : 'minus' }}"></i></span></li>
                    <li class="list-group-item d-flex justify-content-between">Thumbnail <span class="badge bg-{{ $thumbnailImage ? 'success' : 'secondary' }}"><i class="fas fa-{{ $thumbnailImage ? 'check' : 'minus' }}"></i></span></li>
                </ul>
            </div>

            @if($ioSlug)
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ url('/' . $ioSlug) }}" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>{{ __('Back to record') }}
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
        <li><a href="{{ route('io.digitalobject.delete', $do->id) }}" class="btn atom-btn-outline-danger" onclick="return confirm('Delete this digital object?')">Delete</a></li>
        @if($ioSlug)
            <li><a href="{{ url('/' . $ioSlug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
        @endif
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
    </ul>

</form>

{{-- Sibling forms for the per-representation Delete buttons. Live OUTSIDE the
     outer edit form because nested <form> elements are invalid HTML; the
     buttons inside the cards above target these via the HTML5 form="..." attr. --}}
@if($referenceImage ?? null)
  <form id="rep-delete-ref-{{ $referenceImage->id }}" method="POST"
        action="{{ route('io.digitalobject.representation.delete', $referenceImage->id) }}" style="display:none;">
    @csrf
    @method('DELETE')
  </form>
@endif
@if($thumbnailImage ?? null)
  <form id="rep-delete-thumb-{{ $thumbnailImage->id }}" method="POST"
        action="{{ route('io.digitalobject.representation.delete', $thumbnailImage->id) }}" style="display:none;">
    @csrf
    @method('DELETE')
  </form>
@endif

@endsection
