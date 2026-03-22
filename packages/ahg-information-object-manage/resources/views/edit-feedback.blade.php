@php decorate_with('layout_2col.php'); @endphp

@php slot('sidebar'); @endphp
  @php include_component('informationobject', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp
  <h1>{{ __('Feedback') }}</h1>
  <span class="text-muted">@php echo render_title($resource); @endphp</span>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp
  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'editFeedback']), ['id' => 'feedbackForm']); @endphp
  @php echo $form->renderHiddenFields(); @endphp

  <!-- Identification -->
  <div class="card mb-3">
    <div class="card-header bg-success text-white">
      <i class="fas fa-info-circle me-2"></i>{{ __('Identification area') }}
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          @php echo $form->name
            ->label(__('Name of Collection/Item'))
            ->renderRow(['class' => 'form-control', 'readonly' => 'readonly']); @endphp
        </div>
        <div class="col-md-6 mb-3">
          @php echo $form->identifier
            ->label(__('Identifier'))
            ->renderRow(['class' => 'form-control', 'readonly' => 'readonly']); @endphp
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          @php echo $form->unique_identifier
            ->label(__('Unique Identifier'))
            ->renderRow(['class' => 'form-control', 'readonly' => 'readonly']); @endphp
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
        @php echo $form->feed_type
          ->label(__('Feedback Type'))
          ->renderRow(['class' => 'form-select']); @endphp
      </div>
      
      <div class="mb-3">
        @php echo $form->remarks
          ->label(__('Remarks/Feedback/Comments'))
          ->renderRow(['class' => 'form-control', 'rows' => 5]); @endphp
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
          @php echo $form->feed_name
            ->label(__('Name'))
            ->renderRow(['class' => 'form-control']); @endphp
        </div>
        <div class="col-md-6 mb-3">
          @php echo $form->feed_surname
            ->label(__('Surname'))
            ->renderRow(['class' => 'form-control']); @endphp
        </div>
      </div>
      
      <div class="row">
        <div class="col-md-6 mb-3">
          @php echo $form->feed_phone
            ->label(__('Phone Number'))
            ->renderRow(['class' => 'form-control']); @endphp
        </div>
        <div class="col-md-6 mb-3">
          @php echo $form->feed_email
            ->label(__('e-Mail Address'))
            ->renderRow(['class' => 'form-control', 'type' => 'email']); @endphp
        </div>
      </div>
      
      <div class="mb-3">
        @php echo $form->feed_relationship
          ->label(__('Relationship to item'))
          ->renderRow(['class' => 'form-control']); @endphp
      </div>
    </div>
  </div>

  <!-- Actions -->
  <section class="actions">
    <ul class="list-unstyled d-flex flex-wrap gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Submit Feedback') }}"></li>
    </ul>
  </section>

  </form>

@php end_slot(); @endphp
