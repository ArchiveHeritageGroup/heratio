{{--
  Import multiple digital objects — Heratio
  Migrated from AtoM informationobject/multiFileUploadSuccess.php

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems

  This file is part of Heratio.
  Heratio is free software under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')
@section('title', 'Import multiple digital objects — ' . ($io->title ?? ''))

@section('content')
<div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ __('Import multiple digital objects') }}</h1>
    <span class="small text-muted">{{ e($io->title ?? 'Untitled') }}</span>
</div>

<div class="multifileupload-form"
     data-multifileupload-max-file-size="{{ $maxFileSize }}"
     data-multifileupload-max-post-size="{{ $maxPostSize }}"
     data-multifileupload-upload-response-path="{{ $uploadResponsePath }}"
     data-multifileupload-slug="{{ $io->slug }}"
     data-multifileupload-thumb-width="150"
     data-multifileupload-i18n-max-file-size-message="Maximum file size: "
     data-multifileupload-i18n-max-post-size-message="Maximum total upload size: "
     data-multifileupload-i18n-max-size-note="%{maxFileSizeMessage}; %{maxPostSizeMessage}"
     data-multifileupload-i18n-retry="Retry"
     data-multifileupload-i18n-info-object-title="Title"
     data-multifileupload-i18n-save="Save"
     data-multifileupload-i18n-add-more-files="Add more files"
     data-multifileupload-i18n-add-more="Add more"
     data-multifileupload-i18n-adding-more-files="Adding more files"
     data-multifileupload-i18n-some-files-failed-error="Some files failed to upload. Press the 'Import' button to continue importing anyways, or press 'Retry' to re-attempt upload."
     data-multifileupload-i18n-retry-success="Files successfully uploaded! Press the 'Import' button to complete importing these files."
     data-multifileupload-i18n-file-selected="%{smart_count} file selected"
     data-multifileupload-i18n-files-selected="%{smart_count} files selected"
     data-multifileupload-i18n-uploading="Uploading"
     data-multifileupload-i18n-complete="Complete"
     data-multifileupload-i18n-upload-failed="Upload failed"
     data-multifileupload-i18n-remove-file="Remove file"
     data-multifileupload-i18n-drop-file="Drop files here, paste or %{browse}"
     data-multifileupload-i18n-file-uploaded-of-total="%{complete} of %{smart_count} file uploaded"
     data-multifileupload-i18n-files-uploaded-of-total="%{complete} of %{smart_count} files uploaded"
     data-multifileupload-i18n-data-uploaded-of-total="%{complete} of %{total}"
     data-multifileupload-i18n-time-left="%{time} left"
     data-multifileupload-i18n-cancel="Cancel"
     data-multifileupload-i18n-edit="Edit"
     data-multifileupload-i18n-back="Back"
     data-multifileupload-i18n-editing="Editing %{file}"
     data-multifileupload-i18n-uploading-file="Uploading %{smart_count} file"
     data-multifileupload-i18n-uploading-files="Uploading %{smart_count} files"
     data-multifileupload-i18n-importing="Importing digital objects - please wait..."
     data-multifileupload-i18n-failed-to-upload="Failed to upload %{file}"
     data-multifileupload-i18n-size-error="Skipping file %{fileName} because file size %{fileSize} is larger than file size limit of %{maxSize} MB"
     data-multifileupload-i18n-no-files-error="Please add a file to begin uploading."
     data-multifileupload-i18n-no-successful-files-error="Files not uploaded successfully. Please retry."
     data-multifileupload-i18n-post-size-error="Upload limit of %{maxPostSize} MB reached. Unable to add additional files."
     data-multifileupload-i18n-alert-close="Close">

    <form method="POST" action="{{ route('io.multiFileUpload', $io->slug) }}" id="multiFileUploadForm" enctype="multipart/form-data">
        @csrf

        <div class="accordion mb-3">
            <div class="accordion-item">
                <h2 class="accordion-header" id="upload-heading">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#upload-collapse" aria-expanded="true" aria-controls="upload-collapse">
                        {{ __('Import multiple digital objects') }}
                    </button>
                </h2>
                <div id="upload-collapse" class="accordion-collapse collapse show" aria-labelledby="upload-heading">
                    <div class="accordion-body">
                        <div class="alert alert-info" role="alert">
                            <p>Add your digital objects by dragging and dropping local files into the pane below, or by clicking the browse link to open your local file explorer.</p>
                            <p>The Title and Level of description values entered on this page will be applied to each child description created for the associated digital objects — <strong>%dd%</strong> represents an incrementing 2-value number, so by default descriptions created via this uploader will be named image 01, image 02, etc.</p>
                            <p>You will also be able to review and individually modify each description title on the next page after clicking "Upload."</p>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('Title') }}</label>
                            <input type="text" class="form-control" id="title" name="title" value="image %dd%">
                            <div class="form-text">The "<strong>%dd%</strong>" placeholder will be replaced with an incremental number (e.g. 'image <strong>01</strong>', 'image <strong>02</strong>')</div>
                        </div>

                        <div class="mb-3">
                            <label for="levelOfDescription" class="form-label">{{ __('Level of description') }}</label>
                            <select class="form-select" id="levelOfDescription" name="levelOfDescription">
                                <option value="">—</option>
                                @foreach($levels as $level)
                                    <option value="{{ $level->id }}">{{ e($level->name) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="alert alert-secondary py-2 mb-3">
                            <i class="fas fa-lightbulb me-1 text-warning"></i>
                            <strong>{{ __('Tip:') }}</strong> For bulk imports with metadata mapping, validation, and CSV support, use the
                            <a href="{{ route('ingest.index') }}"><i class="fas fa-file-import me-1"></i>{{ __('Data Ingest') }}</a> tool instead.
                        </div>

                        <h3 class="fs-6 mb-2">{{ __('Digital objects') }}</h3>

                        <div id="uploads"></div>

                        <div id="uiElements" class="d-inline">
                            <div id="uploaderContainer">
                                <div class="uppy-dashboard"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="actions mb-3 nav gap-2">
            <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
            <li><input class="btn atom-btn-outline-success" type="submit" value="Upload"></li>
        </ul>

    </form>
</div>
@endsection
