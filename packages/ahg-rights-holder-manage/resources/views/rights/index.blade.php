@extends('theme::layouts.1col')

@section('title', 'Rights')
@section('body-class', 'rights index')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug ?? 'Rights' }}</h1>
    <span class="small">Rights</span>
  </div>
@endsection

@section('content')
  @if(isset($rights) && count($rights) > 0)
    @foreach($rights as $right)
      <div class="card mb-3">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">{{ $right['basis_label'] ?? 'Rights record' }}</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <dl>
                @if($right['basis'] ?? null)<dt>Basis</dt><dd>{{ $right['basis'] }}</dd>@endif
                @if($right['start_date'] ?? null)<dt>Start date</dt><dd>{{ $right['start_date'] }}</dd>@endif
                @if($right['end_date'] ?? null)<dt>End date</dt><dd>{{ $right['end_date'] }}</dd>@endif
              </dl>
            </div>
            <div class="col-md-6">
              <dl>
                @if($right['rights_note'] ?? null)<dt>Rights note</dt><dd>{{ $right['rights_note'] }}</dd>@endif
                @if($right['copyright_status'] ?? null)<dt>Copyright status</dt><dd>{{ $right['copyright_status'] }}</dd>@endif
                @if($right['copyright_jurisdiction'] ?? null)<dt>Copyright jurisdiction</dt><dd>{{ $right['copyright_jurisdiction'] }}</dd>@endif
              </dl>
            </div>
          </div>

          {{-- Granted rights --}}
          @if(!empty($right['granted_rights']))
            <h6 class="text-muted mt-3">Granted rights</h6>
            <table class="table table-sm table-bordered">
              <thead>
                <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
                  <th>Act</th><th>Restriction</th><th>Start date</th><th>End date</th><th>Notes</th>
                </tr>
              </thead>
              <tbody>
                @foreach($right['granted_rights'] as $gr)
                  <tr>
                    <td>{{ $gr['act'] ?? '' }}</td>
                    <td>{{ $gr['restriction'] ?? '' }}</td>
                    <td>{{ $gr['start_date'] ?? '' }}</td>
                    <td>{{ $gr['end_date'] ?? '' }}</td>
                    <td>{{ $gr['notes'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    @endforeach
  @else
    <div class="alert alert-info">No rights records found for this object.</div>
  @endif
@endsection

@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <li><a href="{{ route('rights.add', $resource->slug ?? '') }}" class="btn atom-btn-outline-light">Add new rights</a></li>
    </ul>
  @endauth
@endsection
