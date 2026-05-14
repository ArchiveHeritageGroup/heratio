<?php

/*
 * Heratio - Pure Laravel archival management platform
 * Copyright (c) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under the GNU AGPL v3 or later
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        $culture = (string) app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        $page = DB::table('static_page')
            ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
            ->join('slug', 'static_page.id', '=', 'slug.object_id')
            ->where('slug.slug', 'contact')
            ->whereIn('static_page_i18n.culture', array_unique([$culture, $fallback]))
            ->orderByRaw('FIELD(static_page_i18n.culture, ?, ?)', [$culture, $fallback])
            ->select('static_page.id', 'static_page_i18n.title', 'static_page_i18n.content')
            ->first();

        return view('contact', compact('page'));
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'email'        => 'required|email:rfc|max:200',
            'organisation' => 'nullable|string|max:200',
            'message'      => 'required|string|min:10|max:5000',
            // Honeypot: real users leave this empty; bots fill it.
            'website'      => 'nullable|max:0',
        ], [
            'website.max' => 'Spam protection triggered.',
        ]);

        $recipient = config('heratio.contact_email')
            ?: config('mail.from.address')
            ?: 'johan@theahg.co.za';

        $siteName = config('app.name', 'Heratio');

        $bodyToOwner = "New contact form submission from {$siteName}:\n\n"
            . "Name        : {$data['name']}\n"
            . "Email       : {$data['email']}\n"
            . "Organisation: " . ($data['organisation'] ?? '-') . "\n"
            . "Submitted   : " . now()->toDateTimeString() . " " . config('app.timezone', 'UTC') . "\n"
            . "Source IP   : " . $request->ip() . "\n"
            . "\n--- Message ---\n"
            . $data['message'] . "\n";

        try {
            Mail::raw($bodyToOwner, function ($m) use ($recipient, $data, $siteName) {
                $m->to($recipient)
                  ->replyTo($data['email'], $data['name'])
                  ->subject("[{$siteName}] Contact form: {$data['name']}");
            });
        } catch (\Throwable $e) {
            Log::error('Contact form: failed to send owner mail', ['err' => $e->getMessage()]);
            return back()
                ->withInput()
                ->with('error', __('Sorry - we could not send your message just now. Please try again or email us directly at :addr', ['addr' => $recipient]));
        }

        $bodyToSubmitter = "Hi {$data['name']},\n\n"
            . "Thanks for reaching out to {$siteName}. We have received your message and will get back to you as soon as we can.\n\n"
            . "For reference, here is what you sent:\n\n"
            . "--- Your message ---\n"
            . $data['message'] . "\n"
            . "--------------------\n\n"
            . "Best regards,\n"
            . "The Heratio team\n";

        try {
            Mail::raw($bodyToSubmitter, function ($m) use ($data, $siteName, $recipient) {
                $m->to($data['email'], $data['name'])
                  ->replyTo($recipient)
                  ->subject("We received your message - {$siteName}");
            });
        } catch (\Throwable $e) {
            Log::warning('Contact form: auto-reply failed (owner mail already sent)', ['err' => $e->getMessage()]);
        }

        return redirect()->route('contact.show')
            ->with('success', __('Thanks - your message is on its way. We will respond by email shortly.'));
    }
}
