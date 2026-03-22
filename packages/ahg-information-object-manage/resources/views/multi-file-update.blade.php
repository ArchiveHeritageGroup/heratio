@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Update digital object titles') }}
    </h1>
    <span class="small" id="heading-label">
      @php echo render_title(new sfIsadPlugin($resource)); @endphp
    </span>
  </div>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $digitalObjectTitleForm->renderGlobalErrors(); @endphp
  @php echo $digitalObjectTitleForm->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'multiFileUpdate', 'items' => $sf_request->items]), ['method' => 'post', 'id' => 'bulk-title-update-form']); @endphp
    @php echo $digitalObjectTitleForm->renderHiddenFields(); @endphp

    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Object') }}</th>
            <th id="title-label">{{ __('Title') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($digitalObjectTitleForm->getInformationObjects() as $io)
            <tr>
              <td class="thumbnail-container">
                @foreach($io->digitalObjectsRelatedByobjectId as $do)
                  @if(
                      (null !== $thumbnail = $do->getRepresentationByUsage(QubitTerm::THUMBNAIL_ID))
                      && QubitAcl::check($io, 'readThumbnail')
                  )
                    @php echo image_tag($thumbnail->getFullPath(), ['alt' => __($do->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
                  @php } else { @endphp
                    @php echo image_tag(QubitDigitalObject::getGenericIconPathByMediaTypeId($do->mediaTypeId), ['alt' => __($do->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => sfConfig::get('app_ui_label_digitalobject')]), 'class' => 'img-thumbnail']); @endphp
                  @endforeach
                @endforeach
              </td>
              <td>
                @if($sf_user->getCulture() != $io->getSourceCulture() && !strlen($io->title))
                  <div class="default-translation">
                    @php echo render_value_inline($digitalObjectTitleForm[$io->id]->getValue(), $io); @endphp
                  </div>
                @endforeach

                @php echo render_field(
                    $digitalObjectTitleForm[$io->id],
                    null,
                    ['onlyInputs' => true, 'aria-labelledby' => 'title-label', 'class' => 'mb-3']
                ); @endphp

                @if(isset($io->digitalObjectsRelatedByobjectId[0]->name))
                  <div class="mb-3">
                    <h3 class="fs-6 mb-2">
                      {{ __('Filename') }}
                    </h3>
                    <span class="text-muted">
                      @php echo $io->digitalObjectsRelatedByobjectId[0]->name; @endphp
                    </span>
                  </div>
                @endforeach

                @if(isset($io->levelOfDescription))
                  <div class="mb-3">
                    <h3 class="fs-6 mb-2">
                      {{ __('Level of description') }}
                    </h3>
                    <span class="text-muted">
                      @php echo render_value_inline($io->levelOfDescription); @endphp
                    </span>
                  </div>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" id="rename-form-submit" type="submit" value="{{ __('Save') }}">
    </section>
  </form>

@php end_slot(); @endphp
