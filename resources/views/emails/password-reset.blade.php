<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
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
            background-color: #dc2626;
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
            background-color: #dc2626;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
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
        <h1>Reset Your Password</h1>
    </div>
    
    <div class="content">
        <p>Hello {{ $userName }},</p>
        
        <p>We received a request to reset the password for your account. If you made this request, please click the button below to reset your password:</p>
        
        <div style="text-align: center;">
            <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        </div>
        
        <p>Or you can copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #dc2626;">{{ $resetUrl }}</p>
        
        <div class="warning">
            <p><strong>Security Notice:</strong></p>
            <ul>
                <li>This password reset link will expire in {{ $resetExpiryMinutes }} minutes for security reasons.</li>
                <li>If you did not request a password reset, please ignore this email.</li>
                <li>Never share this link with anyone.</li>
            </ul>
        </div>
        
        <p>If you continue to have problems, please contact our support team.</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} Your Application. All rights reserved.</p>
    </div>
</body>
</html>