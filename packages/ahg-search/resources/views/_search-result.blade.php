@php $doc = $hit->getData(); @endphp

<article class="search-result row g-0 p-3 border-bottom">
  @if(!empty($doc['hasDigitalObject']))
    @php // Get thumbnail or generic icon path
        if (
            isset($doc['digitalObject']['thumbnailPath'])
            && \AtomExtensions\Services\AclService::check(
                QubitInformationObject::getById($hit->getId()),
                'readThumbnail'
            )
        ) {
            $imagePath = $doc['digitalObject']['thumbnailPath'];
        } else {
            $imagePath = '/' . QubitDigitalObject::getGenericIconPathByMediaTypeId(
                $doc['digitalObject']['mediaTypeId'] ?: null
            );
        } @endphp
    <div class="col-12 col-lg-3 pb-2 pb-lg-0 pe-lg-3">
      <a href="@php echo url_for(
          ['module' => 'informationobject', 'slug' => $doc['slug']]
      ); @endphp">
        @php echo image_tag($imagePath, [ 
            'alt' => $doc['digitalObject']['digitalObjectAltText'] ?: strip_markdown(
                get_search_i18n(
                    $doc,
                    'title',
                    ['allowEmpty' => false, 'culture' => $culture]
                )
            ),
            'class' => 'img-thumbnail',
        ]); @endphp
      </a>
    </div>
  @endforeach

  <div class="col-12@php echo empty($doc['hasDigitalObject']) ? '' : ' col-lg-9'; @endphp d-flex flex-column gap-1">
    <div class="d-flex align-items-center gap-2">
      @php echo link_to(
          render_title(get_search_i18n(
              $doc,
              'title',
              ['allowEmpty' => false, 'culture' => $culture]
          )),
          ['module' => 'informationobject', 'slug' => $doc['slug']],
          ['class' => 'h5 mb-0 text-truncate'],
      ); @endphp

      @php include_component('accessFilter', 'classificationBadge', ['objectId' => $hit->getId()]); @endphp
      @php echo get_component('clipboard', 'button', [
          'slug' => $doc['slug'],
          'type' => 'informationObject',
          'wide' => false,
      ]); @endphp
    </div>

    <div class="d-flex flex-column gap-2">
      <div class="d-flex flex-column">
        <div class="d-flex flex-wrap">
          @php $showDash = false; @endphp
          @if(
              '1' == sfConfig::get('app_inherit_code_informationobject', 1)
              && isset($doc['referenceCode']) && !empty($doc['referenceCode'])
          )
            <span class="text-primary">@php echo $doc['referenceCode']; @endphp</span>
            @php $showDash = true; @endphp
          @php } elseif (isset($doc['identifier']) && !empty($doc['identifier'])) { @endphp
            <span class="text-primary">@php echo $doc['identifier']; @endphp</span>
            @php $showDash = true; @endphp
          @endforeach

          @if(
              isset($doc['levelOfDescriptionId'])
              && !empty($doc['levelOfDescriptionId'])
          )
            @if($showDash)
              <span class="text-muted mx-2"> · </span>
            @endforeach
            <span class="text-muted">
              @php echo render_value_inline(
                  \AtomExtensions\Services\CacheService::getLabel($doc['levelOfDescriptionId'], 'QubitTerm')
              ); @endphp
            </span>
            @php $showDash = true; @endphp
          @endforeach

          @if(isset($doc['dates']))
            @php $date = render_search_result_date($doc['dates']); @endphp
            @if(!empty($date))
              @if($showDash)
                <span class="text-muted mx-2"> · </span>
              @endforeach
              <span class="text-muted">
                @php echo render_value_inline($date); @endphp
              </span>
              @php $showDash = true; @endphp
            @endforeach
          @endforeach

          @if(
              isset($doc['publicationStatusId'])
              && QubitTerm::PUBLICATION_STATUS_DRAFT_ID == $doc['publicationStatusId']
          )
            @if($showDash)
              <span class="text-muted mx-2"> · </span>
            @endforeach
            <span class="text-muted">
              @php echo render_value_inline(
                  \AtomExtensions\Services\CacheService::getLabel($doc['publicationStatusId'], 'QubitTerm')
              ); @endphp
            </span>
          @endforeach
        </div>

        @if(isset($doc['partOf']))
          <span class="text-muted">
            {{ __('Part of ') }}
            @php echo link_to(
                render_title(get_search_i18n(
                    $doc['partOf'],
                    'title',
                    ['allowEmpty' => false, 'culture' => $culture, 'cultureFallback' => true]
                )),
                ['slug' => $doc['partOf']['slug'], 'module' => 'informationobject']
            ); @endphp
          </span> 
        @endforeach
      </div>

      @if(null !== $scopeAndContent = get_search_i18n(
          $doc,
          'scopeAndContent',
          ['culture' => $culture]
      ))
        <span class="text-block d-none">
          @php echo render_value($scopeAndContent); @endphp
        </span>
      @endforeach

      @if(
          isset($doc['creators'])
          && null !== $creationDetails = get_search_creation_details($doc, $culture)
      )
        <span class="text-muted">
          @php echo render_value_inline($creationDetails); @endphp
        </span>
      @endforeach
    </div>
  </div>
</article>
