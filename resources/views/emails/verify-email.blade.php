<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Surprise Moi!</title>
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
            background-color: #4f46e5;
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
            background-color: #4f46e5;
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
        <h1>Welcome to Surprise Moi!</h1>
    </div>

    <div class="content">
        <p>Hello {{ $userName }},</p>

        <p>Your Surprise Moi account has been created successfully. Let the surprises begin!</p>

        <p>Please click the button below to verify your email address and get started:</p>

        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
        </div>

        <p>Or you can copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #4f46e5;">{{ $verificationUrl }}</p>

        <p><strong>Important:</strong> This verification link will expire in 60 minutes for security reasons.</p>

        <p>If you did not create an account with us, please ignore this email.</p>
    </div>

    <div class="footer">
        <p>Best regards, The Surprise Moi Team</p>
        <p>&copy; {{ date('Y') }} Surprise Moi. All rights reserved.</p>
    </div>
</body>
</html>
