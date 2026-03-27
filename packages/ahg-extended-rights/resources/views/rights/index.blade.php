@extends('theme::layouts.1col')

@section('title', 'Rights - ' . ($resource->title ?? $resource->slug))
@section('body-class', 'rights index')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug }}</h1>
    <span class="small">Rights</span>
  </div>
@endsection

@section('content')
  @auth
  <div class="mb-3">
    <a href="{{ route('ext-rights.add', $resource->slug) }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>Add Rights
    </a>
    <a href="{{ route('ext-rights.edit-embargo', $resource->slug) }}" class="btn btn-outline-warning">
      <i class="fas fa-clock me-1"></i>Add Embargo
    </a>
  </div>
  @endauth

  {{-- Access Status Summary --}}
  <div class="card mb-4 border-{{ ($accessCheck['accessible'] ?? true) ? 'success' : 'warning' }}">
    <div class="card-header bg-{{ ($accessCheck['accessible'] ?? true) ? 'success' : 'warning' }} text-{{ ($accessCheck['accessible'] ?? true) ? 'white' : 'dark' }}">
      <h5 class="mb-0">
        <i class="fas fa-{{ ($accessCheck['accessible'] ?? true) ? 'check-circle' : 'exclamation-triangle' }} me-2"></i>
        Access Status
      </h5>
    </div>
    <div class="card-body">
      @if($accessCheck['accessible'] ?? true)
        <p class="text-success mb-0"><strong>This item is accessible.</strong></p>
      @else
        <p class="text-warning mb-2"><strong>Access to this item may be restricted.</strong></p>
        @if(!empty($accessCheck['restrictions']))
          <ul class="mb-0">
            @foreach($accessCheck['restrictions'] as $restriction)
            <li>
              {{ ucfirst($restriction['type'] ?? '') }}
              @if(isset($restriction['reason'])) - {{ ucfirst(str_replace('_', ' ', $restriction['reason'])) }} @endif
              @if(isset($restriction['until'])) (until {{ $restriction['until'] }}) @endif
            </li>
            @endforeach
          </ul>
        @endif
      @endif

      @if($accessCheck['rights_statement'] ?? null)
      <div class="mt-3">
        <strong>Rights Statement:</strong>
        <a href="{{ $accessCheck['rights_statement']['uri'] }}" target="_blank" class="ms-2">
          {{ $accessCheck['rights_statement']['name'] }}
          <i class="fas fa-external-link-alt ms-1"></i>
        </a>
      </div>
      @endif

      @if($accessCheck['cc_license'] ?? null)
      <div class="mt-2">
        <strong>License:</strong>
        <a href="{{ $accessCheck['cc_license']['uri'] }}" target="_blank" class="ms-2">
          @if($accessCheck['cc_license']['badge_url'] ?? null)
          <img src="{{ $accessCheck['cc_license']['badge_url'] }}" alt="{{ $accessCheck['cc_license']['name'] }}" height="31">
          @else
          {{ $accessCheck['cc_license']['name'] }}
          @endif
        </a>
      </div>
      @endif
    </div>
  </div>

  {{-- Active Embargo --}}
  @if($embargo)
  <div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white">
      <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Embargo</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Type</dt>
        <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $embargo->embargo_type ?? '')) }}</dd>

        <dt class="col-sm-3">Reason</dt>
        <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $embargo->reason ?? '')) }}</dd>

        <dt class="col-sm-3">Start Date</dt>
        <dd class="col-sm-9">{{ $embargo->start_date ? \Carbon\Carbon::parse($embargo->start_date)->format('j F Y') : '-' }}</dd>

        <dt class="col-sm-3">End Date</dt>
        <dd class="col-sm-9">
          @if($embargo->end_date)
            {{ \Carbon\Carbon::parse($embargo->end_date)->format('j F Y') }}
            @php $daysLeft = (int) now()->diffInDays(\Carbon\Carbon::parse($embargo->end_date), false); @endphp
            @if($daysLeft > 0)
              <span class="badge bg-warning text-dark ms-2">{{ $daysLeft }} days remaining</span>
            @endif
          @else
            <span class="text-danger">Indefinite</span>
          @endif
        </dd>

        @if($embargo->reason_note ?? null)
        <dt class="col-sm-3">Note</dt>
        <dd class="col-sm-9">{!! nl2br(e($embargo->reason_note)) !!}</dd>
        @endif
      </dl>
    </div>
  </div>
  @endif

  {{-- TK Labels --}}
  @if(isset($tkLabels) && count($tkLabels) > 0)
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Traditional Knowledge Labels</h5>
    </div>
    <div class="card-body">
      <div class="row">
        @foreach($tkLabels as $label)
        <div class="col-md-6 mb-3">
          <div class="d-flex align-items-start">
            <span class="badge me-3" style="background-color: {{ $label->color ?? '#6c757d' }}; width: 60px; padding: 10px;">
              {{ $label->code ?? '' }}
            </span>
            <div>
              <strong>{{ $label->name ?? '' }}</strong>
              @if($label->verified ?? false)
                <i class="fas fa-check-circle text-success ms-1" title="Verified"></i>
              @endif
              <br>
              <small class="text-muted">{{ $label->description ?? '' }}</small>
              @if($label->community_name ?? null)
                <br><small>Community: {{ $label->community_name }}</small>
              @endif
            </div>
          </div>
        </div>
        @endforeach
      </div>
      <p class="mt-2 mb-0">
        <small>
          <i class="fas fa-info-circle me-1"></i>
          Learn more about Traditional Knowledge Labels at
          <a href="https://localcontexts.org" target="_blank">Local Contexts</a>
        </small>
      </p>
    </div>
  </div>
  @endif

  {{-- Rights Records --}}
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Rights Records</h5>
    </div>
    <div class="card-body">
      @if(isset($rights) && count($rights) > 0)
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Basis</th>
              <th>Rights Statement / License</th>
              <th>Status</th>
              <th>Dates</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($rights as $right)
            <tr>
              <td>
                @php
                  $basisColor = match($right->basis ?? '') {
                    'copyright' => 'primary', 'license' => 'success', 'statute' => 'warning', 'donor' => 'info', 'policy' => 'secondary', default => 'light'
                  };
                @endphp
                <span class="badge bg-{{ $basisColor }}">{{ ucfirst($right->basis ?? '') }}</span>
              </td>
              <td>
                @if($right->rights_statement_name ?? null)
                  <a href="{{ $right->rights_statement_uri }}" target="_blank">
                    {{ $right->rights_statement_name }}
                  </a>
                @elseif($right->cc_license_name ?? null)
                  <a href="{{ $right->cc_license_uri }}" target="_blank">
                    @if($right->cc_badge_url ?? null)
                    <img src="{{ $right->cc_badge_url }}" alt="{{ $right->cc_license_name }}" height="20">
                    @else
                    {{ $right->cc_license_name }}
                    @endif
                  </a>
                @else
                  -
                @endif
              </td>
              <td>
                @if($right->copyright_status ?? null)
                  {{ ucfirst(str_replace('_', ' ', $right->copyright_status)) }}
                @endif
                @if($right->copyright_holder ?? null)
                  <br><small>Holder: {{ $right->copyright_holder }}</small>
                @endif
              </td>
              <td>
                @if(($right->start_date ?? null) || ($right->end_date ?? null))
                  {{ $right->start_date ?: '...' }} - {{ $right->end_date ?: '...' }}
                @else
                  -
                @endif
              </td>
              <td>
                @auth
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('ext-rights.edit', [$resource->slug, $right->id]) }}" class="btn btn-outline-secondary" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  <form action="{{ route('ext-rights.delete', [$resource->slug, $right->id]) }}" method="post"
                        onsubmit="return confirm('Delete this rights record?');" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
                @endauth
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <p class="text-muted mb-0">No rights records have been added yet.</p>
      @endif
    </div>
  </div>

  {{-- Orphan Work --}}
  @if($orphanWork)
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fas fa-search me-2"></i>Orphan Work Due Diligence</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
          @php
            $owColor = match($orphanWork->status ?? '') {
              'in_progress' => 'warning', 'completed' => 'success', 'rights_holder_found' => 'info', 'abandoned' => 'secondary', default => 'light'
            };
          @endphp
          <span class="badge bg-{{ $owColor }}">{{ ucfirst(str_replace('_', ' ', $orphanWork->status ?? '')) }}</span>
        </dd>
        <dt class="col-sm-3">Work Type</dt>
        <dd class="col-sm-9">{{ ucfirst(str_replace('_', ' ', $orphanWork->work_type ?? '')) }}</dd>
        <dt class="col-sm-3">Search Started</dt>
        <dd class="col-sm-9">{{ $orphanWork->search_started_date ?? '-' }}</dd>
        @if($orphanWork->search_completed_date ?? null)
        <dt class="col-sm-3">Search Completed</dt>
        <dd class="col-sm-9">{{ $orphanWork->search_completed_date }}</dd>
        @endif
      </dl>
      <a href="{{ route('ext-rights-admin.orphan-work-edit', $orphanWork->id) }}" class="btn btn-sm btn-outline-info mt-2">
        View Search Details
      </a>
    </div>
  </div>
  @endif
@endsection
