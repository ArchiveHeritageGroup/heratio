@extends('theme::layouts.1col')

@section('title', __('AI Library Assistant - Policy'))

@section('content')
<div class="container py-5" style="max-width: 800px;">
    <h1>{{ __('AI Library Assistant - Policy') }}</h1>
    <p class="text-muted">{{ __('Last updated: :date', ['date' => '27 May 2026']) }}</p>

    <h2 class="h4 mt-4">{{ __('What this chatbot does') }}</h2>
    <p>{{ __('The AI Library Assistant answers questions about our catalogue using artificial intelligence. It reads our catalogue records, our help articles, and our library policies, and writes a plain-language answer with citations to the records that supported it.') }}</p>

    <h2 class="h4 mt-4">{{ __('What we store') }}</h2>
    <ul>
        <li>{{ __('Every question and answer is saved to our database for audit and quality control.') }}</li>
        <li>{{ __('Each interaction is linked to your user account if you are signed in. Anonymous visitors are linked to a browser cookie that lasts 7 days.') }}</li>
        <li>{{ __('We record which model produced each answer and how confident the answer was.') }}</li>
        <li>{{ __('All of this is part of our EU AI Act Article 12 audit chain, mirroring the same receipts we keep for every AI inference in the platform.') }}</li>
    </ul>

    <h2 class="h4 mt-4">{{ __('What we do NOT do') }}</h2>
    <ul>
        <li>{{ __('We do not send your question to any third-party AI service. All inferences run on internal infrastructure owned by The Archive and Heritage Group (Pty) Ltd.') }}</li>
        <li>{{ __('We do not train external models on your questions.') }}</li>
        <li>{{ __('We do not share your interaction history with advertisers or analytics vendors.') }}</li>
    </ul>

    <h2 class="h4 mt-4">{{ __('Your rights') }}</h2>
    <ul>
        <li>{{ __('You can ask for a copy of every question and answer you have submitted by contacting our Data Protection Officer.') }}</li>
        <li>{{ __('You can ask us to delete your interaction history at any time.') }}</li>
        <li>{{ __('You can opt out of using the chatbot by simply not clicking the chat icon.') }}</li>
        <li>{{ __('Where applicable laws (POPIA in South Africa, GDPR in the EU, etc.) provide additional rights, those apply.') }}</li>
    </ul>

    <h2 class="h4 mt-4">{{ __('Confidence and accuracy') }}</h2>
    <p>{{ __('AI assistants can be wrong. Every response includes a confidence score and links back to the catalogue records that grounded the answer. If the confidence is low, the assistant will say so and recommend you switch to manual search.') }}</p>
    <p>{{ __('If you spot a wrong answer, click "Report" in the chat panel. A cataloguer will review the answer and the underlying records.') }}</p>

    <h2 class="h4 mt-4">{{ __('Speaking to a human') }}</h2>
    <p>{{ __('Every chat panel has a "Talk to a librarian" button. Clicking it sends your question to our reference desk and you will be contacted by a member of staff.') }}</p>

    <p class="mt-5"><a href="{{ route('chatbot.index') }}" class="btn btn-primary">{{ __('Back to the AI Library Assistant') }}</a></p>
</div>
@endsection
