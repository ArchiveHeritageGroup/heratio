@extends('theme::layouts.1col')
@section('title', 'Publish Preservation')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-eye me-2"></i>Publish Preservation</h1>
      <a href="{{ route('reports.browse') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <form method="post" action="{{ route('reports.browse-publish') }}">
          @csrf
          <div class="table-responsive">
            <table class="table table-bordered table-sm table-striped mb-0">
              <thead>
                <tr style="background:var(--ahg-primary);color:#fff">
                  <th>Name</th>
                  <th>Identifier</th>
                  <th>Publish</th>
                  <th>Restriction</th>
                  <th>Refusal</th>
                  <th>Sensitivity</th>
                  <th>Classification</th>
                </tr>
              </thead>
              <tbody>
                @forelse($items ?? [] as $item)
                <tr>
                  <td>
                    <input type="hidden" name="ids[]" value="{{ $item->id }}">
                    <a href="{{ route('informationobject.show', $item->slug ?? $item->id) }}">{{ $item->title ?? $item->slug ?? $item->identifier ?? 'Untitled' }}</a>
                  </td>
                  <td>{{ $item->identifier ?? '-' }}</td>
                  <td>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="publish_{{ $loop->index }}" value="yes" {{ ($item->publish ?? '') === 'Yes' ? 'checked' : '' }}> Yes
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="publish_{{ $loop->index }}" value="no" {{ ($item->publish ?? '') === 'No' ? 'checked' : '' }}> No
                    </div>
                  </td>
                  <td>{{ ($item->restriction ?? 'Please Select') === 'Please Select' ? '-' : $item->restriction }}</td>
                  <td>{{ ($item->refusal ?? 'Please Select') === 'Please Select' ? '-' : $item->refusal }}</td>
                  <td>{{ ($item->sensitivity ?? 'Please Select') === 'Please Select' ? '-' : $item->sensitivity }}</td>
                  <td>{{ ($item->classification ?? 'Please Select') === 'Please Select' ? '-' : $item->classification }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No items found</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if(!empty($items) && count($items) > 0)
          <div class="card-footer">
            <a href="{{ route('informationobject.browse') }}" class="btn atom-btn-white">Return</a>
            <button type="submit" class="btn atom-btn-white ms-2">Continue</button>
          </div>
          @endif
        </form>
      </div>
    </div>
  </div>
</div>
@endsection