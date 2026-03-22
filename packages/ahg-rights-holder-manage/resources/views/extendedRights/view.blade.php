@extends('theme::layouts.1col')

@section('title', 'Rights Information')
@section('body-class', 'extended-rights view')

@section('content')
@if($rightsData['has_rights'] ?? false)

<section id="extended-rights-area" class="card mb-3">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0">
      Rights Information
      @auth
        <a href="{{ route('extended-rights.edit', $resource->slug ?? '') }}" class="btn btn-sm atom-btn-outline-light float-end"><i class="fas fa-edit"></i> Edit</a>
      @endauth
    </h4>
  </div>
  <div class="card-body">
    {{-- Rights Badges --}}
    @if(!empty($rightsData['badges']))
      <div class="rights-badges mb-3">
        @foreach($rightsData['badges'] as $badge)
          <a href="{{ $badge['uri'] }}" target="_blank" class="rights-badge me-2 mb-2 d-inline-block" title="{{ $badge['label'] }}">
            @if($badge['type'] === 'creative_commons')
              {!! $badge['badge_html'] !!}
            @else
              <img src="{{ $badge['icon'] }}" alt="{{ $badge['label'] }}" class="rights-badge-icon">
            @endif
          </a>
        @endforeach
      </div>
    @endif

    @php $primary = $rightsData['primary'] ?? null; @endphp
    @if($primary)
      {{-- Rights Statement --}}
      @if($primary->rightsStatement ?? null)
        <div class="field mb-3">
          <h5>Rights Statement</h5>
          <div class="d-flex align-items-start">
            @if($primary->rightsStatement->icon_url ?? null)
              <img src="{{ $primary->rightsStatement->icon_url }}" alt="" class="me-3" style="width:88px;">
            @endif
            <div>
              <strong>{{ $primary->rightsStatement->name ?? '' }}</strong>
              <p class="text-muted mb-1">{{ $primary->rightsStatement->definition ?? '' }}</p>
              @if($primary->rightsStatement->uri ?? null)
                <a href="{{ $primary->rightsStatement->uri }}" target="_blank" class="small">Learn more <i class="fas fa-external-link-alt"></i></a>
              @endif
            </div>
          </div>
        </div>
      @endif

      {{-- Creative Commons --}}
      @if($primary->creativeCommonsLicense ?? null)
        <div class="field mb-3">
          <h5>License</h5>
          <div class="d-flex align-items-center">
            @if($primary->creativeCommonsLicense->badge_html ?? null)
              {!! $primary->creativeCommonsLicense->badge_html !!}
            @endif
            <div class="ms-3">
              <strong>{{ $primary->creativeCommonsLicense->name ?? '' }}</strong><br>
              @if($primary->creativeCommonsLicense->uri ?? null)
                <a href="{{ $primary->creativeCommonsLicense->uri }}" target="_blank" class="small">View license <i class="fas fa-external-link-alt"></i></a>
              @endif
            </div>
          </div>
        </div>
      @endif

      {{-- TK Labels --}}
      @if(($primary->tkLabels ?? null) && count($primary->tkLabels) > 0)
        <div class="field mb-3">
          <h5>Traditional Knowledge Labels</h5>
          <div class="tk-labels-grid">
            @foreach($primary->tkLabels as $label)
              <div class="tk-label-item d-flex align-items-start mb-2">
                @if($label->icon_url ?? null)
                  <img src="{{ $label->icon_url }}" alt="" class="me-2" style="width:48px;height:48px;">
                @endif
                <div>
                  <strong>{{ $label->name ?? '' }}</strong>
                  <p class="small text-muted mb-0">{{ $label->description ?? '' }}</p>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      {{-- Rights Holder --}}
      @if($primary->rights_holder ?? null)
        <div class="field mb-3">
          <h5>Rights Holder</h5>
          @if($primary->rights_holder_uri ?? null)
            <a href="{{ $primary->rights_holder_uri }}" target="_blank">{{ $primary->rights_holder }}</a>
          @else
            {{ $primary->rights_holder }}
          @endif
        </div>
      @endif

      {{-- Copyright Notice --}}
      @if($primary->copyright_notice ?? null)
        <div class="field mb-3"><h5>Copyright Notice</h5><p>{{ $primary->copyright_notice }}</p></div>
      @endif

      {{-- Usage Notes --}}
      @if($primary->rights_note ?? null)
        <div class="field mb-3"><h5>Usage Notes</h5><p>{!! nl2br(e($primary->rights_note)) !!}</p></div>
      @endif
    @endif
  </div>
</section>

@else

@auth
<section id="extended-rights-area" class="card mb-3">
  <div class="card-body text-center">
    <p class="text-muted mb-2">No extended rights information has been added.</p>
    <a href="{{ route('extended-rights.edit', $resource->slug ?? '') }}" class="btn atom-btn-white"><i class="fas fa-plus"></i> Add Rights Information</a>
  </div>
</section>
@endauth

@endif
@endsection
