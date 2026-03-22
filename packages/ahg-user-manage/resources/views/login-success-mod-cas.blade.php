@php decorate_with('layout_1col'); @endphp
@php use_helper('Javascript'); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(route('cas.login')); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <ul class="actions mb-3 nav gap-2">
      <button type="submit" class="btn atom-btn-outline-success">{{ __('Log in with CAS') }}</button>
    </ul>

  </form>

@php end_slot(); @endphp
