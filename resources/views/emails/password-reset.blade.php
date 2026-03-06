<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #0d6efd; color: #fff; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset Request</h2>

        <p>Hello {{ $username }},</p>

        <p>You have requested to reset your password. Click the button below to set a new password:</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
        </p>

        <p>If the button above does not work, copy and paste the following URL into your browser:</p>
        <p style="word-break: break-all;">{{ $resetUrl }}</p>

        <p>This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>

        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
