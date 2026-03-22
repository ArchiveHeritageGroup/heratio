@extends('theme::layouts.1col')

@section('title', 'Digital Object: ' . $digitalObject->name)
@section('body-class', 'show digitalobject')

@section('content')
  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      @if($ioSlug)
        <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $ioSlug) }}">{{ $ioTitle ?: 'Information object' }}</a></li>
      @endif
      <li class="breadcrumb-item active" aria-current="page">Digital object</li>
    </ol>
  </nav>

  <h1 class="h3 mb-4">{{ $digitalObject->name }}</h1>

  <div class="row">
    <div class="col-md-8">
      <table class="table table-bordered">
        <tbody>
          <tr>
            <th style="width:35%">Filename</th>
            <td>{{ $digitalObject->name }}</td>
          </tr>
          <tr>
            <th>MIME type</th>
            <td>{{ $digitalObject->mime_type }}</td>
          </tr>
          <tr>
            <th>Media type</th>
            <td>{{ $mediaTypeName ?: '-' }}</td>
          </tr>
          <tr>
            <th>Usage</th>
            <td>{{ $usageName ?: '-' }}</td>
          </tr>
          <tr>
            <th>File size</th>
            <td>{{ $fileSize }}</td>
          </tr>
          @if($digitalObject->checksum)
            <tr>
              <th>Checksum ({{ $digitalObject->checksum_type }})</th>
              <td><code>{{ $digitalObject->checksum }}</code></td>
            </tr>
          @endif
          <tr>
            <th>Path</th>
            <td><code>{{ $url }}</code></td>
          </tr>
          @if($digitalObject->language)
            <tr>
              <th>Language</th>
              <td>{{ $digitalObject->language }}</td>
            </tr>
          @endif
        </tbody>
      </table>

      @if(!empty($metadata))
        <h5 class="mt-4">Extended metadata</h5>
        <table class="table table-bordered table-sm">
          <tbody>
            @foreach($metadata as $key => $value)
              @if($key !== 'digital_object_id' && $key !== 'id')
                <tr>
                  <th style="width:35%">{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                  <td>{{ $value }}</td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    <div class="col-md-4">
      @if($ioSlug)
        <a href="{{ route('informationobject.edit', $ioSlug) }}" class="btn atom-btn-white btn-sm mb-3">
          <i class="fas fa-arrow-left me-1"></i> Back to edit
        </a>
      @endif
    </div>
  </div>
@endsection
