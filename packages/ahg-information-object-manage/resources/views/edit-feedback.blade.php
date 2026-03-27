@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')
  <h1>{{ __('Feedback') }}</h1>
  <span class="text-muted">{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</span>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form action="{{ route('informationobject.editFeedback', $resource->slug) }}" method="post" id="feedbackForm">
    @csrf

  <!-- Identification -->
  <div class="card mb-3">
    <div class="card-header bg-success text-white">
      <i class="fas fa-info-circle me-2"></i>{{ __('Identification area') }}
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="name" class="form-label">{{ __('Name of Collection/Item') }}</label>
          <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $feedbackData['name'] ?? '') }}" readonly>
        </div>
        <div class="col-md-6 mb-3">
          <label for="identifier" class="form-label">{{ __('Identifier') }}</label>
          <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $feedbackData['identifier'] ?? '') }}" readonly>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="unique_identifier" class="form-label">{{ __('Unique Identifier') }}</label>
          <input type="text" class="form-control" id="unique_identifier" name="unique_identifier" value="{{ old('unique_identifier', $feedbackData['unique_identifier'] ?? '') }}" readonly>
        </div>
      </div>
    </div>
  </div>

  <!-- Feedback -->
  <div class="card mb-3">
    <div class="card-header bg-success text-white">
      <i class="fas fa-comment-alt me-2"></i>{{ __('Feedback area') }}
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label for="feed_type" class="form-label">{{ __('Feedback Type') }}</label>
        <select class="form-select" id="feed_type" name="feed_type">
          @foreach($feedbackTypes ?? [] as $value => $label)
            <option value="{{ $value }}" {{ old('feed_type', $feedbackData['feed_type'] ?? '') == $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="remarks" class="form-label">{{ __('Remarks/Feedback/Comments') }}</label>
        <textarea class="form-control" id="remarks" name="remarks" rows="5">{{ old('remarks', $feedbackData['remarks'] ?? '') }}</textarea>
      </div>
    </div>
  </div>

  <!-- Contact Information -->
  <div class="card mb-3">
    <div class="card-header bg-success text-white">
      <i class="fas fa-user me-2"></i>{{ __('Contact Information') }}
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="feed_name" class="form-label">{{ __('Name') }}</label>
          <input type="text" class="form-control" id="feed_name" name="feed_name" value="{{ old('feed_name', $feedbackData['feed_name'] ?? '') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="feed_surname" class="form-label">{{ __('Surname') }}</label>
          <input type="text" class="form-control" id="feed_surname" name="feed_surname" value="{{ old('feed_surname', $feedbackData['feed_surname'] ?? '') }}">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="feed_phone" class="form-label">{{ __('Phone Number') }}</label>
          <input type="text" class="form-control" id="feed_phone" name="feed_phone" value="{{ old('feed_phone', $feedbackData['feed_phone'] ?? '') }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="feed_email" class="form-label">{{ __('e-Mail Address') }}</label>
          <input type="email" class="form-control" id="feed_email" name="feed_email" value="{{ old('feed_email', $feedbackData['feed_email'] ?? '') }}">
        </div>
      </div>

      <div class="mb-3">
        <label for="feed_relationship" class="form-label">{{ __('Relationship to item') }}</label>
        <input type="text" class="form-control" id="feed_relationship" name="feed_relationship" value="{{ old('feed_relationship', $feedbackData['feed_relationship'] ?? '') }}">
      </div>
    </div>
  </div>

  <!-- Actions -->
  <section class="actions">
    <ul class="list-unstyled d-flex flex-wrap gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Submit Feedback') }}"></li>
    </ul>
  </section>

  </form>

@endsection
