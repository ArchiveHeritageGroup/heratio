<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking cancelled</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #222;">
    <h2 style="color: #dc2626;">Booking cancelled</h2>

    <p>Hello {{ trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? '')) ?: 'Researcher' }},</p>

    <p>Your reading-room booking has been cancelled.</p>

    <table style="width: 100%; border-collapse: collapse; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <tr><td style="padding: 6px 10px; width: 35%; color: #666;">Booking reference</td><td style="padding: 6px 10px;">#{{ $booking->id }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Reading room</td><td style="padding: 6px 10px;">{{ $booking->room_name ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Date</td><td style="padding: 6px 10px;">{{ $booking->booking_date }}</td></tr>
        <tr><td style="padding: 6px 10px; color: #666;">Time</td><td style="padding: 6px 10px;">{{ $booking->start_time }} - {{ $booking->end_time }}</td></tr>
        @if(!empty($reason))
            <tr><td style="padding: 6px 10px; color: #666; vertical-align: top;">Reason</td><td style="padding: 6px 10px;">{{ $reason }}</td></tr>
        @endif
    </table>

    <p>You are welcome to make a new booking at any time.</p>

    <p style="color: #888; font-size: 12px; margin-top: 30px;">Sent by {{ config('app.name', 'Heratio') }}.</p>
</body>
</html>
