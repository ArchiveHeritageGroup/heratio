@php /**
 * Contact information view for authority records
 */

use AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository;

$contactRepo = new ContactInformationRepository();
$contacts = $contactRepo->getByActorId($resource->id);

if ($contacts->isEmpty()) {
    return;
} @endphp

@php foreach ($contacts as $contact): @endphp
  @if($contact->contact_person)
    @php echo render_show(__('Contact person'), esc_specialchars($contact->contact_person)); @endphp
  @endif

  @php $addressParts = array_filter([
      $contact->street_address,
      $contact->city,
      $contact->region,
      $contact->postal_code,
      $contact->country_code
  ]);
  if (!empty($addressParts)): @endphp
    @php echo render_show(__('Address'), esc_specialchars(implode(', ', $addressParts))); @endphp
  @endif

  @if($contact->telephone)
    @php echo render_show(__('Telephone'), '<a href="tel:' . esc_specialchars($contact->telephone) . '">' . esc_specialchars($contact->telephone) . '</a>'); @endphp
  @endif

  @if($contact->fax)
    @php echo render_show(__('Fax'), esc_specialchars($contact->fax)); @endphp
  @endif

  @if($contact->email)
    @php echo render_show(__('Email'), '<a href="mailto:' . esc_specialchars($contact->email) . '">' . esc_specialchars($contact->email) . '</a>'); @endphp
  @endif

  @if($contact->website)
    @php echo render_show(__('Website'), '<a href="' . esc_specialchars($contact->website) . '" target="_blank" rel="noopener">' . esc_specialchars($contact->website) . ' <i class="fas fa-external-link-alt fa-xs"></i></a>'); @endphp
  @endif

  @if($contact->note)
    @php echo render_show(__('Note'), nl2br(esc_specialchars($contact->note))); @endphp
  @endif

  @if($contact->primary_contact)
    @php echo render_show(__('Primary contact'), '<span class="badge bg-success">' . __('Yes') . '</span>'); @endphp
  @endif

  @if($contacts->count() > 1 && $contact !== $contacts->last())
    <hr class="my-3">
  @endif
@php endforeach; @endphp
