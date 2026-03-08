<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Surprise Moi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .logo {
            margin-bottom: 24px;
        }

        .logo img {
            height: 56px;
            width: auto;
            display: inline-block;
            margin-bottom: 8px;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        h1 {
            color: #1a202c;
            font-size: 22px;
            margin-bottom: 12px;
        }

        p {
            color: #4a5568;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: #FDC541;
            color: #000000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: box-shadow 0.12s ease;
            margin-top: 8px;
        }

        .btn:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
        }

        .store-links {
            margin-top: 20px;
            font-size: 13px;
            color: #718096;
        }

        .store-links a {
            color: #6C1A81;
            text-decoration: none;
            font-weight: 600;
        }

        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #a0aec0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <img src="/images/logo-purple.svg" alt="Surprise Moi" />
        </div>

        @if ($success)
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="#FDC541" opacity="0.18" />
                    <path d="M7 12L10 15L17 8" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>

            <a href="{{ $deepLink }}" class="btn">Open the App</a>

            <div class="store-links">
                <p>Don't have the app yet?</p>
                <a href="{{ config('deep_links.android.store_url') }}">Android</a>
                &nbsp;&bull;&nbsp;
                <a href="{{ config('deep_links.ios.store_url') }}">iOS</a>
            </div>
        @else
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="#f56565" opacity="0.18" />
                    <path d="M15 9L9 15M9 9L15 15" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
        @endif

        <div class="footer">
            <p>&copy; {{ date('Y') }} Surprise Moi. All rights reserved.</p>
        </div>
    </div>

    @if ($success)
        <script>
            // Auto-redirect to the app after a short delay
            setTimeout(function() {
                window.location.href = "{{ $deepLink }}";
            }, 1500);
        </script>
    @endif
</body>

</html>
