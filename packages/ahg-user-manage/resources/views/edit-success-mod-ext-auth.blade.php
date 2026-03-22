@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <h1>{{ __('User %1%', ['%1%' => render_title($resource)]) }}</h1>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @if(isset($sf_request->getAttribute('sf_route')->resource))
    @php echo $form->renderFormTag(url_for([$resource, 'module' => 'user', 'action' => 'edit']), ['id' => 'editForm']); @endphp
  @php } else { @endphp
    @php echo $form->renderFormTag(route('user.add'), ['id' => 'editForm']); @endphp
  @endforeach

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      @if($sf_user->user != $resource)
        <div class="accordion-item">
          <h2 class="accordion-header" id="basic-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
              {{ __('Basic info') }}
            </button>
          </h2>
          <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
            <div class="accordion-body">
              @php echo render_field($form->active->label(__('Active'))); @endphp
            </div>
          </div>
        </div>
      @endforeach
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            {{ __('Access control') }}
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            @php echo render_field(
                $form->groups->label(__('User groups')),
                null,
                ['class' => 'form-autocomplete']
            ); @endphp

            @php echo render_field(
                $form->translate->label(__('Allowed languages for translation')),
                null,
                ['class' => 'form-autocomplete']
            ); @endphp

            @if($restEnabled)
              @php echo render_field($form->restApiKey->label(
                  __('REST API access key'.((isset($restApiKey)) ? ': <code class="ms-2">'.$restApiKey.'</code>' : ''))
              )); @endphp
            @endforeach

            @if($oaiEnabled)
              @php echo render_field($form->oaiApiKey->label(
                  __('OAI-PMH API access key'.((isset($oaiApiKey)) ? ': <code class="ms-2">'.$oaiApiKey.'</code>' : ''))
              )); @endphp
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if(isset($sf_request->getAttribute('sf_route')->resource))
        <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'user'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
      @php } else { @endphp
        <li>@php echo link_to(__('Cancel'), ['module' => 'user', 'action' => 'list'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      @endforeach
    </ul>

  </form>

@php end_slot(); @endphp
