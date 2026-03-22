<div class="table-responsive mb-3">
  <table class="table table-bordered mb-0 compound_digiobj">
    <tbody>
      <tr>
        <td>
          @if(null !== $representation = $leftObject->getCompoundRepresentation())
            @if($resource->object instanceof QubitInformationObject)
              @php echo link_to_if(SecurityPrivileges::editCredentials($sf_user, 'informationObject') || QubitTerm::TEXT_ID == $resource->mediaType->id, image_tag($representation->getFullPath(), ['alt' => '', 'class' => 'img-thumbnail']), public_path($leftObject->getFullPath(), ['title' => __('View full size')])); @endphp
            @php } elseif ($resource->object instanceof QubitActor) { @endphp
              @php echo link_to_if(SecurityPrivileges::editCredentials($sf_user, 'actor') || QubitTerm::TEXT_ID == $resource->mediaType->id, image_tag($representation->getFullPath(), ['alt' => '', 'class' => 'img-thumbnail']), public_path($leftObject->getFullPath(), ['title' => __('View full size')])); @endphp
            @endforeach
          @endforeach
        </td><td>
          @if(null !== $rightObject && null !== $representation = $rightObject->getCompoundRepresentation())
            @if($resource->object instanceof QubitInformationObject)
              @php echo link_to_if(SecurityPrivileges::editCredentials($sf_user, 'informationObject') || QubitTerm::TEXT_ID == $resource->mediaType->id, image_tag($representation->getFullPath(), ['alt' => '', 'class' => 'img-thumbnail']), public_path($rightObject->getFullPath(), ['title' => __('View full size')])); @endphp
            @php } elseif ($resource->object instanceof QubitActor) { @endphp
              @php echo link_to_if(SecurityPrivileges::editCredentials($sf_user, 'actor') || QubitTerm::TEXT_ID == $resource->mediaType->id, image_tag($representation->getFullPath(), ['alt' => '', 'class' => 'img-thumbnail']), public_path($rightObject->getFullPath(), ['title' => __('View full size')])); @endphp
            @endforeach
          @endforeach
        </td>
      </tr>

      @if(($resource->object instanceof QubitInformationObject && SecurityPrivileges::editCredentials($sf_user, 'informationObject')) || ($resource->object instanceof QubitActor && SecurityPrivileges::editCredentials($sf_user, 'actor')))
        <tr>
          <td colspan="2" class="text-center">
            <a href="@php echo public_path($resource->getFullPath()); @endphp" class="btn btn-sm atom-btn-white">
              <i class="fas fa-download me-1" aria-hidden="true"></i>
              {{ __('Download %1%', ['%1%' => $resource]) }}
            </a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@php echo get_partial('default/pager', ['pager' => $pager]); @endphp
