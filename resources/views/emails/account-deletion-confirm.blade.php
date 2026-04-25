<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your account deletion</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #b91c1c;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background-color: #b91c1c;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Confirm Account Deletion</h1>
    </div>

    <div class="content">
        <p>Hello {{ $userName }},</p>

        <p>We received a request to delete your Surprise moi account. To confirm this and permanently remove your account and all associated data, click the button below.</p>

        <div style="text-align: center;">
            <a href="{{ $confirmationUrl }}" class="button" style="display: inline-block; background-color: #b91c1c; color: #ffffff !important; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0;">Confirm Deletion</a>
        </div>

        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #b91c1c;">{{ $confirmationUrl }}</p>

        <p><strong>Important:</strong> This confirmation link will expire in 60 minutes. Once confirmed, your account and all associated data will be permanently deleted and cannot be recovered.</p>

        <p>If you did not request this, you can safely ignore this email — no changes will be made to your account.</p>
    </div>

    <div class="footer">
        <p>Best regards, The Surprise moi Team</p>
        <p>&copy; {{ date('Y') }} Surprise moi. All rights reserved.</p>
    </div>
</body>
</html>
