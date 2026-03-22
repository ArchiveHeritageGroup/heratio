@if($sf_context->getConfiguration()->isPluginEnabled('arCasPlugin') || $sf_context->getConfiguration()->isPluginEnabled('arOidcPlugin'))
    @php include 'loginSuccess.mod_ext_auth.php'; @endphp
@php } else { @endphp
    @php include 'loginSuccess.mod_standard.php'; @endphp
@endforeach
