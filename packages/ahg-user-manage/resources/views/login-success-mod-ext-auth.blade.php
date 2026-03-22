@php decorate_with('layout_1col'); @endphp
@php use_helper('Javascript'); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

    @if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin'))
      @php echo $form->renderFormTag(route('cas.login')); @endphp
    @php } elseif ($sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin')) { @endphp
      @php echo $form->renderFormTag(route('oidc.login')); @endphp
    @endforeach

    @php echo $form->renderHiddenFields(); @endphp

    <ul class="actions mb-3 nav gap-2">
      <button type="submit" class="btn atom-btn-outline-success">
      @if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin'))
        {{ __('Log in with CAS') }}
      @php } elseif ($sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin')) { @endphp
        {{ __('Log in with SSO') }}
      @endforeach
      </button>
    </ul>

  </form>

@php end_slot(); @endphp
