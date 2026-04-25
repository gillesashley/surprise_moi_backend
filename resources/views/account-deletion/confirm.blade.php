<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Account Deletion - Surprise moi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #6C1A81;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            max-width: 450px;
            width: 100%;
            padding: 32px 24px;
            text-align: center;
            border: 2px solid #6C1A81;
        }
        .icon { width: 80px; height: 80px; margin: 0 auto 20px; }
        h1 { color: #1a202c; font-size: 22px; margin-bottom: 12px; }
        p { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 16px; }
        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
        .btn-danger {
            display: inline-block;
            padding: 14px 32px;
            background: #b91c1c;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-link {
            display: inline-block;
            padding: 10px;
            color: #6C1A81;
            font-size: 14px;
            text-decoration: none;
        }
        .footer { margin-top: 24px; font-size: 12px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="#f56565" opacity="0.18" />
                <path d="M12 8V12M12 16H12.01" stroke="#b91c1c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>

        <h1>Confirm Account Deletion</h1>
        <p>You are about to permanently delete the Surprise moi account associated with <strong>{{ $user->email }}</strong>.</p>
        <p>This action is <strong>irreversible</strong>. All your data — orders, vendor applications, messages, and payouts — will be permanently removed.</p>

        <form action="{{ $confirmFormUrl }}" method="POST" class="actions">
            @csrf
            <button type="submit" class="btn-danger">Yes, delete my account</button>
            <a href="{{ route('account-deletion.show') }}" class="btn-link">Cancel</a>
        </form>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Surprise moi. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
