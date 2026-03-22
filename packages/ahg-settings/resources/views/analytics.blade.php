@php decorate_with('layout_2col.php'); @endphp

@php slot('sidebar'); @endphp

  @php echo get_component('settings', 'menu'); @endphp

@php end_slot(); @endphp

@php slot('title'); @endphp

  <h1>{{ __('Web analytics') }}</h1>

@php end_slot(); @endphp

@php slot('content'); @endphp

  <div class="alert alert-info">
    {{ __('Please clear the cache and restart PHP-FPM after adding tracking ID.') }}
  </div>

  @if(!empty(sfConfig::get('app_google_analytics_api_key')) && '' == QubitSetting::getByName('google_analytics'))
    <div class="alert alert-info">
      {{ __('Google analytics is currently set in the app.yml.') }}
    </div>
  @endforeach

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(route('settings.analytics')); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="analytics-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#analytics-collapse" aria-expanded="true" aria-controls="analytics-collapse">
            {{ __('Web analytics') }}
          </button>
        </h2>
        <div id="analytics-collapse" class="accordion-collapse collapse show" aria-labelledby="analytics-heading">
          <div class="accordion-body">
            @php echo render_field($form->google_analytics); @endphp
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}">
    </section>

  </form>

@php end_slot(); @endphp
