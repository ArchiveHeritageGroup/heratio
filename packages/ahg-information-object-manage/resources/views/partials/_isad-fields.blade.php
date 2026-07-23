{{--
  ISAD(G) field set - #1425 dynamic-standard form. The default field accordion,
  also returned by InformationObjectController::standardFields() as the fallback
  for isad / unknown / not-yet-wired standards, and shown when the operator
  switches back to ISAD. $io is nullable (null on create, populated on edit).
  Field names match store()/update() so the shared save handles it.
--}}
@php $io = $io ?? null; @endphp

<input type="hidden" name="_display_standard_code" value="isad">

<div class="accordion mb-3" id="isad-accordion">

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#isad-identity">{{ __('Identity area') }}</button></h2>
    <div id="isad-identity" class="accordion-collapse collapse show">
      <div class="accordion-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Identifier') }}</label>
          <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
        </div>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#isad-context">{{ __('Context area') }}</button></h2>
    <div id="isad-context" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([['archival_history','Administrative / biographical history'], ['acquisition','Immediate source of acquisition']] as [$f,$l])
          <div class="mb-3"><label class="form-label">{{ __($l) }}</label><textarea name="{{ $f }}" class="form-control" rows="3">{{ old($f, $io->$f ?? '') }}</textarea></div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#isad-content">{{ __('Content and structure area') }}</button></h2>
    <div id="isad-content" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([['scope_and_content','Scope and content'], ['appraisal','Appraisal, destruction and scheduling'], ['accruals','Accruals'], ['arrangement','System of arrangement']] as [$f,$l])
          <div class="mb-3"><label class="form-label">{{ __($l) }}</label><textarea name="{{ $f }}" class="form-control" rows="3">{{ old($f, $io->$f ?? '') }}</textarea></div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#isad-conditions">{{ __('Conditions of access and use area') }}</button></h2>
    <div id="isad-conditions" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([['access_conditions','Conditions governing access'], ['reproduction_conditions','Conditions governing reproduction'], ['physical_characteristics','Physical characteristics and technical requirements'], ['finding_aids','Finding aids']] as [$f,$l])
          <div class="mb-3"><label class="form-label">{{ __($l) }}</label><textarea name="{{ $f }}" class="form-control" rows="2">{{ old($f, $io->$f ?? '') }}</textarea></div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#isad-allied">{{ __('Allied materials area') }}</button></h2>
    <div id="isad-allied" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([['location_of_originals','Existence and location of originals'], ['location_of_copies','Existence and location of copies'], ['related_units_of_description','Related units of description']] as [$f,$l])
          <div class="mb-3"><label class="form-label">{{ __($l) }}</label><textarea name="{{ $f }}" class="form-control" rows="2">{{ old($f, $io->$f ?? '') }}</textarea></div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#isad-control">{{ __('Description control area') }}</button></h2>
    <div id="isad-control" class="accordion-collapse collapse">
      <div class="accordion-body">
        <div class="mb-3"><label class="form-label">{{ __('Description identifier') }}</label><input type="text" name="description_identifier" class="form-control" value="{{ old('description_identifier', $io->description_identifier ?? '') }}"></div>
        <div class="mb-3"><label class="form-label">{{ __('Rules or conventions') }}</label><textarea name="rules" class="form-control" rows="2">{{ old('rules', $io->rules ?? '') }}</textarea></div>
        <div class="mb-3"><label class="form-label">{{ __('Sources') }}</label><textarea name="sources" class="form-control" rows="2">{{ old('sources', $io->sources ?? '') }}</textarea></div>
      </div>
    </div>
  </div>

</div>
