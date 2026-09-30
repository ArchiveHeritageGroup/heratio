{{--
  CAAIS 1.0 profile - accession show page (heratio#1514). Repository always;
  the CAAIS elements and the mandatory-element check only when the profile is on.
--}}
@php $cs = $caaisService; @endphp
@if(($caais['repository_id'] ?? null) || ($caaisEnabled ?? false))
  <section class="section border-bottom" id="caaisArea">
    <h2 class="h5 mb-0 atom-section-header">
      <div class="d-flex p-3 border-bottom text-primary">
        {{ ($caaisEnabled ?? false) ? __('Repository and CAAIS profile') : __('Repository') }}
        @auth
          <a href="{{ route('accession.edit', $accession->slug) }}" class="ms-auto text-primary opacity-75" style="font-size:.75rem;" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </div>
    </h2>
    <div id="caais-collapse">

      <div class="field row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository') }}</h3>
        <div class="col-9 p-2">{{ $caais['repository_name'] ?? '' }}</div>
      </div>

      @if($caaisEnabled ?? false)
        @if(!empty($caaisMissing))
          <div class="alert alert-warning m-2" role="status">
            <strong>{{ __('CAAIS mandatory elements not yet recorded:') }}</strong>
            <ul class="mb-0">@foreach($caaisMissing as $m)<li>{{ $m }}</li>@endforeach</ul>
          </div>
        @endif

        @php
          $confidential = array_filter($caais['sources'] ?? []);
        @endphp
        @if(!empty($confidential))
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source confidentiality') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($donors ?? [] as $d)
                @if(!empty($confidential[$d->id]))
                  <li>{{ $d->name }}: <span class="badge bg-warning text-dark">{{ $cs->label('confidentiality', $confidential[$d->id]) }}</span></li>
                @endif
              @endforeach
            </ul>
          </div>
        </div>
        @endif

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Extent statements') }}</h3>
          <div class="col-9 p-2">
            @if(!empty($caais['extents']))
              <ul class="m-0 ms-1 ps-3">
                @foreach($caais['extents'] as $e)
                  <li>
                    <strong>{{ $cs->label('extent_type', $e['extent_type']) }}:</strong>
                    {{ trim(($e['is_estimate'] ? 'ca. ' : '').\AhgAccessionManage\Services\CaaisProfileService::formatQuantity($e['quantity']).' '.($cs->label('unit', $e['unit']) ?? '')) }}
                    @if($e['content_type'] || $e['carrier_type'])
                      ({{ collect([$cs->label('content_type', $e['content_type']), $cs->label('carrier_type', $e['carrier_type'])])->filter()->implode(', ') }})
                    @endif
                    @if($e['digital_file_formats'])<br><span class="text-muted">{{ __('Formats') }}:</span> {{ $e['digital_file_formats'] }}@endif
                    @if($e['note'])<br><span class="text-muted">{!! nl2br(e($e['note'])) !!}</span>@endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language of material') }}</h3>
          <div class="col-9 p-2">
            {{ collect($caais['languages'] ?? [])->map(fn ($l) => collect([$cs->label('language', $l['language']), $l['note']])->filter()->implode(' - '))->filter()->implode('; ') }}
          </div>
        </div>

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Preservation requirements') }}</h3>
          <div class="col-9 p-2">
            @if(!empty($caais['preservation']))
              <ul class="m-0 ms-1 ps-3">
                @foreach($caais['preservation'] as $r)
                  <li><strong>{{ $cs->label('requirement_type', $r['requirement_type']) }}:</strong> {!! nl2br(e($r['requirement_value'])) !!}
                    @if($r['note'])<br><span class="text-muted">{!! nl2br(e($r['note'])) !!}</span>@endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Transfer and accessioning events') }}</h3>
          <div class="col-9 p-2">
            @if(!empty($caais['events']))
              <ul class="m-0 ms-1 ps-3">
                @foreach($caais['events'] as $e)
                  <li><strong>{{ $cs->label('event_type', $e['event_type']) }}</strong>
                    @if($e['event_date']) - {{ $e['event_date'] }}@endif
                    @if($e['agent']) - {{ $e['agent'] }}@endif
                    @if($e['note'])<br><span class="text-muted">{!! nl2br(e($e['note'])) !!}</span>@endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rules or conventions') }}</h3>
          <div class="col-9 p-2">{{ $caais['rules_or_conventions'] ?? '' }}</div>
        </div>

        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creation and revisions') }}</h3>
          <div class="col-9 p-2">
            @if(!empty($caais['revisions']))
              <ul class="m-0 ms-1 ps-3">
                @foreach($caais['revisions'] as $r)
                  <li>{{ $cs->label('revision_type', $r['revision_type']) }} - {{ \Carbon\Carbon::parse($r['revision_date'])->format('j F Y H:i') }}@if($r['agent']) - {{ $r['agent'] }}@endif</li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        @if(auth()->check() && \AhgCore\Services\AclService::canAdmin(auth()->id()))
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('CAAIS export') }}</h3>
          <div class="col-9 p-2">
            <a href="{{ route('accession.caais-export', $accession->id) }}">{{ __('Full record (JSON)') }}</a>
            | <a href="{{ route('accession.caais-export', ['id' => $accession->id, 'external' => 1]) }}">{{ __('For sharing - confidential sources withheld') }}</a>
          </div>
        </div>
        @endif
      @endif

    </div>
  </section>
@endif
