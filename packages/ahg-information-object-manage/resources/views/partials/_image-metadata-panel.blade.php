{{--
  Image Embedded Metadata panel - EXIF / IPTC / XMP

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.

  Issue #746 - Image show-page EXIF / IPTC / XMP metadata panel.

  Renders three collapsible sections (EXIF, IPTC, XMP) sourced from
  digital_object_metadata, dam_iptc_metadata, and media_metadata
  respectively. The panel only renders when at least one of the three
  tables has a row for $do->id; if all three are empty the entire
  panel suppresses itself so empty image DOs do not get a noisy shell.

  Expected blade variable:
    $do - the master digital_object row (must have ->id at minimum).
--}}
@php
  $__doId = is_object($do) ? ($do->id ?? null) : ($do['id'] ?? null);

  if (!$__doId) {
      // Defensive: a caller passed something without an id. Bail silently
      // rather than throw - this panel is purely additive UI.
      return;
  }

  $__exif = \Illuminate\Support\Facades\DB::table('digital_object_metadata')
      ->where('digital_object_id', $__doId)
      ->first();

  $__iptc = \Illuminate\Support\Facades\DB::table('dam_iptc_metadata')
      ->where('object_id', $__doId)
      ->first();

  $__media = \Illuminate\Support\Facades\DB::table('media_metadata')
      ->where('digital_object_id', $__doId)
      ->first();

  // Hide the panel entirely if all three sidecar tables are empty.
  $__hasAny = $__exif || $__iptc || $__media;
  if (!$__hasAny) {
      return;
  }

  // Field-row helper. Returns a Bootstrap 5 tr with a bi-* icon picked
  // from the value type. Kept inline so the partial stays self-contained.
  $__row = function (string $label, $value, string $iconHint = 'text') {
      if ($value === null || $value === '' || $value === []) {
          return '';
      }
      $icons = [
          'text'   => 'bi-fonts',
          'date'   => 'bi-calendar3',
          'gps'    => 'bi-geo-alt',
          'number' => 'bi-123',
          'image'  => 'bi-image',
          'camera' => 'bi-camera',
          'person' => 'bi-person',
          'rights' => 'bi-c-circle',
          'tag'    => 'bi-tag',
      ];
      $icon = $icons[$iconHint] ?? $icons['text'];
      return '<tr>'
          . '<td class="text-muted" style="white-space:nowrap;width:1%">'
              . '<i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>'
              . e($label)
          . '</td>'
          . '<td>' . e((string) $value) . '</td>'
          . '</tr>';
  };

  // Pre-compute "section has content" booleans so each accordion header
  // can hide when the row is wholly null/empty (UNIQUE KEY on each table
  // means the row exists but every column could still be null).
  $__exifFields = $__exif ? collect((array) $__exif)
      ->except(['id', 'digital_object_id', 'created_at', 'updated_at',
                'extraction_date', 'extraction_method', 'extraction_errors',
                'raw_metadata', 'consolidated_metadata'])
      ->filter(fn ($v) => $v !== null && $v !== '')
      ->count() : 0;

  $__iptcFields = $__iptc ? collect((array) $__iptc)
      ->except(['id', 'object_id', 'created_at', 'updated_at',
                'contributors_json'])
      ->filter(fn ($v) => $v !== null && $v !== '')
      ->count() : 0;

  $__mediaFields = $__media ? collect((array) $__media)
      ->except(['id', 'digital_object_id', 'object_id', 'extracted_at',
                'raw_metadata', 'consolidated_metadata', 'waveform_path'])
      ->filter(fn ($v) => $v !== null && $v !== '')
      ->count() : 0;

  // GPS - prefer EXIF, fall back to IPTC. The map link is a plain href
  // (no embed) so this partial never makes an outbound request on render.
  $__gpsLat = $__exif->gps_latitude ?? $__iptc->gps_latitude ?? null;
  $__gpsLon = $__exif->gps_longitude ?? $__iptc->gps_longitude ?? null;
  $__hasGps = $__gpsLat !== null && $__gpsLon !== null;

  $__panelId = 'image-metadata-' . $__doId;
@endphp

<div class="card mb-3" id="{{ $__panelId }}">
  <div class="card-header d-flex justify-content-between align-items-center"
       role="button"
       data-bs-toggle="collapse"
       data-bs-target="#{{ $__panelId }}-body"
       aria-expanded="false"
       aria-controls="{{ $__panelId }}-body"
       style="background:var(--ahg-primary);color:#fff">
    <span><i class="bi bi-image me-2" aria-hidden="true"></i>{{ __('Embedded Image Metadata') }}</span>
    <span class="badge bg-light text-dark">
      @if($__exifFields > 0)<span class="me-1">EXIF</span>@endif
      @if($__iptcFields > 0)<span class="me-1">IPTC</span>@endif
      @if($__mediaFields > 0)<span>XMP</span>@endif
    </span>
  </div>
  <div class="collapse" id="{{ $__panelId }}-body">
    <div class="card-body">
      <div class="accordion" id="{{ $__panelId }}-accordion">

        {{-- EXIF section (digital_object_metadata) --}}
        @if($__exifFields > 0)
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $__panelId }}-exif-h">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#{{ $__panelId }}-exif"
                      aria-expanded="false"
                      aria-controls="{{ $__panelId }}-exif">
                <i class="bi bi-camera me-2" aria-hidden="true"></i>
                {{ __('EXIF') }}
                <span class="badge bg-secondary ms-2">{{ $__exifFields }}</span>
              </button>
            </h2>
            <div id="{{ $__panelId }}-exif" class="accordion-collapse collapse"
                 aria-labelledby="{{ $__panelId }}-exif-h"
                 data-bs-parent="#{{ $__panelId }}-accordion">
              <div class="accordion-body p-0">
                <table class="table table-sm table-borderless mb-0">
                  <tbody>
                    {!! $__row(__('Title'),         $__exif->title         ?? null, 'text')   !!}
                    {!! $__row(__('Creator'),       $__exif->creator       ?? null, 'person') !!}
                    {!! $__row(__('Author'),        $__exif->author        ?? null, 'person') !!}
                    {!! $__row(__('Description'),   $__exif->description   ?? null, 'text')   !!}
                    {!! $__row(__('Keywords'),      $__exif->keywords      ?? null, 'tag')    !!}
                    {!! $__row(__('Copyright'),     $__exif->copyright     ?? null, 'rights') !!}
                    {!! $__row(__('Date Created'),  $__exif->date_created  ?? null, 'date')   !!}
                    {!! $__row(__('Image Width'),
                        ($__exif->image_width  ?? null) ? $__exif->image_width  . ' px' : null, 'number') !!}
                    {!! $__row(__('Image Height'),
                        ($__exif->image_height ?? null) ? $__exif->image_height . ' px' : null, 'number') !!}
                    {!! $__row(__('Camera Make'),   $__exif->camera_make   ?? null, 'camera') !!}
                    {!! $__row(__('Camera Model'),  $__exif->camera_model  ?? null, 'camera') !!}
                    {!! $__row(__('Application'),   $__exif->application   ?? null, 'text')   !!}
                    @if(($__exif->gps_latitude ?? null) !== null && ($__exif->gps_longitude ?? null) !== null)
                      <tr>
                        <td class="text-muted" style="white-space:nowrap;width:1%">
                          <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('GPS') }}
                        </td>
                        <td>
                          {{ number_format((float) $__exif->gps_latitude, 6) }},
                          {{ number_format((float) $__exif->gps_longitude, 6) }}
                          @if(($__exif->gps_altitude ?? null) !== null)
                            <span class="text-muted ms-2">({{ number_format((float) $__exif->gps_altitude, 1) }} m)</span>
                          @endif
                        </td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endif

        {{-- IPTC section (dam_iptc_metadata) --}}
        @if($__iptcFields > 0)
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $__panelId }}-iptc-h">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#{{ $__panelId }}-iptc"
                      aria-expanded="false"
                      aria-controls="{{ $__panelId }}-iptc">
                <i class="bi bi-card-text me-2" aria-hidden="true"></i>
                {{ __('IPTC') }}
                <span class="badge bg-secondary ms-2">{{ $__iptcFields }}</span>
              </button>
            </h2>
            <div id="{{ $__panelId }}-iptc" class="accordion-collapse collapse"
                 aria-labelledby="{{ $__panelId }}-iptc-h"
                 data-bs-parent="#{{ $__panelId }}-accordion">
              <div class="accordion-body p-0">
                <table class="table table-sm table-borderless mb-0">
                  <tbody>
                    {!! $__row(__('Headline'),         $__iptc->headline         ?? null, 'text')   !!}
                    {!! $__row(__('Title'),            $__iptc->title            ?? null, 'text')   !!}
                    {!! $__row(__('Caption'),          $__iptc->caption          ?? null, 'text')   !!}
                    {!! $__row(__('Keywords'),         $__iptc->keywords         ?? null, 'tag')    !!}
                    {!! $__row(__('Creator'),          $__iptc->creator          ?? null, 'person') !!}
                    {!! $__row(__('Creator Job Title'),$__iptc->creator_job_title?? null, 'person') !!}
                    {!! $__row(__('Credit Line'),      $__iptc->credit_line      ?? null, 'rights') !!}
                    {!! $__row(__('Source'),           $__iptc->source           ?? null, 'text')   !!}
                    {!! $__row(__('Copyright Notice'), $__iptc->copyright_notice ?? null, 'rights') !!}
                    {!! $__row(__('Rights / Usage'),   $__iptc->rights_usage_terms ?? null, 'rights') !!}
                    {!! $__row(__('License Type'),     $__iptc->license_type     ?? null, 'rights') !!}
                    {!! $__row(__('Date Created'),     $__iptc->date_created     ?? null, 'date')   !!}
                    {!! $__row(__('City'),             $__iptc->city             ?? null, 'text')   !!}
                    {!! $__row(__('State / Province'), $__iptc->state_province   ?? null, 'text')   !!}
                    {!! $__row(__('Country'),          $__iptc->country          ?? null, 'text')   !!}
                    {!! $__row(__('Sublocation'),      $__iptc->sublocation      ?? null, 'text')   !!}
                    {!! $__row(__('Camera Make'),      $__iptc->camera_make      ?? null, 'camera') !!}
                    {!! $__row(__('Camera Model'),     $__iptc->camera_model     ?? null, 'camera') !!}
                    {!! $__row(__('Lens'),             $__iptc->lens             ?? null, 'camera') !!}
                    {!! $__row(__('Focal Length'),     $__iptc->focal_length     ?? null, 'camera') !!}
                    {!! $__row(__('Aperture'),         $__iptc->aperture         ?? null, 'camera') !!}
                    {!! $__row(__('Shutter Speed'),    $__iptc->shutter_speed    ?? null, 'camera') !!}
                    {!! $__row(__('ISO Speed'),        $__iptc->iso_speed        ?? null, 'number') !!}
                    {!! $__row(__('Color Space'),      $__iptc->color_space      ?? null, 'text')   !!}
                    {!! $__row(__('Bit Depth'),        $__iptc->bit_depth        ?? null, 'number') !!}
                    {!! $__row(__('Orientation'),      $__iptc->orientation      ?? null, 'text')   !!}
                    @if(($__iptc->gps_latitude ?? null) !== null && ($__iptc->gps_longitude ?? null) !== null)
                      <tr>
                        <td class="text-muted" style="white-space:nowrap;width:1%">
                          <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('GPS') }}
                        </td>
                        <td>
                          {{ number_format((float) $__iptc->gps_latitude, 6) }},
                          {{ number_format((float) $__iptc->gps_longitude, 6) }}
                          @if(($__iptc->gps_altitude ?? null) !== null)
                            <span class="text-muted ms-2">({{ number_format((float) $__iptc->gps_altitude, 1) }} m)</span>
                          @endif
                        </td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endif

        {{-- XMP section (media_metadata: title/artist/album/software/etc. plus
             gps_coordinates string and the consolidated_metadata JSON blob) --}}
        @if($__mediaFields > 0)
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $__panelId }}-xmp-h">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#{{ $__panelId }}-xmp"
                      aria-expanded="false"
                      aria-controls="{{ $__panelId }}-xmp">
                <i class="bi bi-file-earmark-code me-2" aria-hidden="true"></i>
                {{ __('XMP') }}
                <span class="badge bg-secondary ms-2">{{ $__mediaFields }}</span>
              </button>
            </h2>
            <div id="{{ $__panelId }}-xmp" class="accordion-collapse collapse"
                 aria-labelledby="{{ $__panelId }}-xmp-h"
                 data-bs-parent="#{{ $__panelId }}-accordion">
              <div class="accordion-body p-0">
                <table class="table table-sm table-borderless mb-0">
                  <tbody>
                    {!! $__row(__('Title'),     $__media->title     ?? null, 'text')   !!}
                    {!! $__row(__('Artist'),    $__media->artist    ?? null, 'person') !!}
                    {!! $__row(__('Album'),     $__media->album     ?? null, 'text')   !!}
                    {!! $__row(__('Genre'),     $__media->genre     ?? null, 'tag')    !!}
                    {!! $__row(__('Year'),      $__media->year      ?? null, 'date')   !!}
                    {!! $__row(__('Copyright'), $__media->copyright ?? null, 'rights') !!}
                    {!! $__row(__('Comment'),   $__media->comment   ?? null, 'text')   !!}
                    {!! $__row(__('Make'),      $__media->make      ?? null, 'camera') !!}
                    {!! $__row(__('Model'),     $__media->model     ?? null, 'camera') !!}
                    {!! $__row(__('Software'),  $__media->software  ?? null, 'text')   !!}
                    {!! $__row(__('Format'),    $__media->format    ?? null, 'text')   !!}
                    @if($__media->gps_coordinates ?? null)
                      <tr>
                        <td class="text-muted" style="white-space:nowrap;width:1%">
                          <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('GPS') }}
                        </td>
                        <td>{{ e($__media->gps_coordinates) }}</td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endif

        {{-- #1106 All metadata - the COMPLETE grouped ExifTool set from
             digital_object_metadata.raw_metadata (EXIF / IPTC / XMP / GPS /
             MakerNotes / ICC / …), grouped + client-side filterable. GPS-group
             values are redacted for non-administrators (PII gate). --}}
        @php
          $__raw = ($__exif->raw_metadata ?? null) ? json_decode($__exif->raw_metadata, true) : null;
          $__rawGroups = [];
          if (is_array($__raw)) {
              foreach ($__raw as $__k => $__v) {
                  if ($__k === 'SourceFile') { continue; }
                  $__pos = strpos((string) $__k, ':');
                  $__grp = $__pos !== false ? substr((string) $__k, 0, $__pos) : 'Other';
                  $__tag = $__pos !== false ? substr((string) $__k, $__pos + 1) : (string) $__k;
                  if (is_array($__v)) { $__v = json_encode($__v, JSON_UNESCAPED_SLASHES); }
                  $__rawGroups[$__grp][$__tag] = (string) $__v;
              }
              ksort($__rawGroups);
          }
          $__rawCount = array_sum(array_map('count', $__rawGroups));
          $__isAdmin = \Illuminate\Support\Facades\Auth::check()
              && class_exists('\AhgCore\Services\AclService')
              && \AhgCore\Services\AclService::canAdmin(\Illuminate\Support\Facades\Auth::id());
        @endphp
        @if($__rawCount > 0)
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $__panelId }}-all-h">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#{{ $__panelId }}-all"
                      aria-expanded="false"
                      aria-controls="{{ $__panelId }}-all">
                <i class="bi bi-list-columns-reverse me-2" aria-hidden="true"></i>
                {{ __('All metadata') }}
                <span class="badge bg-secondary ms-2">{{ $__rawCount }}</span>
              </button>
            </h2>
            <div id="{{ $__panelId }}-all" class="accordion-collapse collapse"
                 aria-labelledby="{{ $__panelId }}-all-h"
                 data-bs-parent="#{{ $__panelId }}-accordion">
              <div class="accordion-body">
                <input type="text" class="form-control form-control-sm mb-2"
                       placeholder="{{ __('Filter tags…') }}"
                       onkeyup="var t=this.value.toLowerCase();this.closest('.accordion-body').querySelectorAll('tr[data-row]').forEach(function(r){r.style.display=r.getAttribute('data-row').indexOf(t)>-1?'':'none';});">
                @foreach($__rawGroups as $__grp => $__tags)
                  <div class="fw-bold small text-uppercase text-muted mt-2">{{ $__grp }} <span class="text-muted">({{ count($__tags) }})</span></div>
                  <table class="table table-sm table-borderless mb-0">
                    <tbody>
                      @foreach($__tags as $__tag => $__val)
                        @php $__display = (! $__isAdmin && $__grp === 'GPS') ? '[redacted]' : $__val; @endphp
                        <tr data-row="{{ strtolower($__grp.' '.$__tag.' '.$__display) }}">
                          <td class="text-muted" style="white-space:nowrap;width:1%">{{ $__tag }}</td>
                          <td class="text-break">{{ \Illuminate\Support\Str::limit($__display, 400) }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @endforeach
                @unless($__isAdmin)
                  <p class="form-text mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ __('GPS coordinates are hidden for non-administrators.') }}</p>
                @endunless
              </div>
            </div>
          </div>
        @endif

        {{-- GPS map links - collapsed by default so we never auto-fetch
             from Google / OSM on every page render. User has to expand. --}}
        @if($__hasGps)
          <div class="accordion-item">
            <h2 class="accordion-header" id="{{ $__panelId }}-gps-h">
              <button class="accordion-button collapsed" type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#{{ $__panelId }}-gps"
                      aria-expanded="false"
                      aria-controls="{{ $__panelId }}-gps">
                <i class="bi bi-geo-alt me-2" aria-hidden="true"></i>
                {{ __('GPS Location') }}
              </button>
            </h2>
            <div id="{{ $__panelId }}-gps" class="accordion-collapse collapse"
                 aria-labelledby="{{ $__panelId }}-gps-h"
                 data-bs-parent="#{{ $__panelId }}-accordion">
              <div class="accordion-body">
                @php
                  $__lat = number_format((float) $__gpsLat, 6, '.', '');
                  $__lon = number_format((float) $__gpsLon, 6, '.', '');
                @endphp
                <p class="mb-2">
                  <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                  <strong>{{ $__lat }}, {{ $__lon }}</strong>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="https://www.openstreetmap.org/?mlat={{ $__lat }}&mlon={{ $__lon }}#map=15/{{ $__lat }}/{{ $__lon }}"
                     target="_blank" rel="noopener noreferrer"
                     class="btn btn-sm atom-btn-white">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                    {{ __('OpenStreetMap') }}
                  </a>
                  <a href="https://www.google.com/maps?q={{ $__lat }},{{ $__lon }}"
                     target="_blank" rel="noopener noreferrer"
                     class="btn btn-sm atom-btn-white">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                    {{ __('Google Maps') }}
                  </a>
                </div>
              </div>
            </div>
          </div>
        @endif

      </div>
    </div>
  </div>
</div>
