<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking received</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #222;">
    <h2 style="color: #2c3e50;">Booking received</h2>

    <p>Hello {{ trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? '')) ?: 'Researcher' }},</p>

    <p>We have received your reading-room booking request. It is currently <strong>pending confirmation</strong>; you will receive a follow-up email once a member of our team has reviewed it.</p>

    <table style="width: 100%; border-collapse: collapse; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <tr><td style="padding: 6px 10px; width: 35%; color: #666;">Booking reference</td><td style="padding: 6px 10px;">#{{ $booking->id }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Reading room</td><td style="padding: 6px 10px;">{{ $booking->room_name ?? '-' }}@if(!empty($booking->room_location)) ({{ $booking->room_location }})@endif</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Date</td><td style="padding: 6px 10px;">{{ $booking->booking_date }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Time</td><td style="padding: 6px 10px;">{{ $booking->start_time }} - {{ $booking->end_time }}</td></tr>
        @if(!empty($booking->purpose))
            <tr><td style="padding: 6px 10px; color: #666; vertical-align: top;">Purpose</td><td style="padding: 6px 10px;">{{ $booking->purpose }}</td></tr>
        @endif
    </table>

    <p>If you need to amend or cancel, please reply to this email or contact the institution directly.</p>

    <p style="color: #888; font-size: 12px; margin-top: 30px;">Sent by {{ config('app.name', 'Heratio') }}.</p>
</body>
</html>
