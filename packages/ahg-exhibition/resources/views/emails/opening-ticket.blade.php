{{-- heratio#1192 slice 2b - live-opening ticket email (ticket code + join link). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your ticket</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #222;">
    <h2 style="color: #2563eb;">Your ticket is confirmed</h2>

    <p>Hello {{ $rsvp->name ?? 'there' }},</p>

    <p>You are booked in for the live virtual opening below. Keep your ticket code -
       return to the event page at start time and click <strong>Join the walkthrough</strong>.</p>

    <table style="width: 100%; border-collapse: collapse; background: #f8f9fa; margin: 15px 0; border-radius: 5px;">
        <tr><td style="padding: 6px 10px; width: 35%; color: #666;">Opening</td><td style="padding: 6px 10px;">{{ $event->title }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Gallery</td><td style="padding: 6px 10px;">{{ $space->name ?? '-' }}</td></tr>
        @if(!empty($event->host_name))
            <tr><td style="padding: 6px 10px; color: #666;">Host</td><td style="padding: 6px 10px;">{{ $event->host_name }}</td></tr>
        @endif
        <tr><td style="padding: 6px 10px; color: #666;">Starts</td><td style="padding: 6px 10px;">{{ \Illuminate\Support\Carbon::parse($event->starts_at)->format('l j F Y, H:i') }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Duration</td><td style="padding: 6px 10px;">{{ $event->duration_minutes }} min</td></tr>
        @if(!empty($rsvp->party_size) && (int) $rsvp->party_size > 1)
            <tr><td style="padding: 6px 10px; color: #666;">Party size</td><td style="padding: 6px 10px;">{{ $rsvp->party_size }}</td></tr>
        @endif
        @if(isset($rsvp->amount_paid) && $rsvp->amount_paid !== null)
            <tr><td style="padding: 6px 10px; color: #666;">Paid</td><td style="padding: 6px 10px;">{{ number_format((float) $rsvp->amount_paid, 2) }} {{ $event->currency ?? '' }}</td></tr>
        @endif
        <tr><td style="padding: 6px 10px; color: #666;">Ticket code</td><td style="padding: 6px 10px;"><code style="font-size: 15px;">{{ $rsvp->ticket_code }}</code></td></tr>
    </table>

    <p style="margin: 20px 0;">
        <a href="{{ $joinUrl }}" style="display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 5px;">Open the event page</a>
    </p>

    <p style="color: #888; font-size: 12px; margin-top: 30px;">Sent by {{ config('app.name', 'Heratio') }}.</p>
</body>
</html>
