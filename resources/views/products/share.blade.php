<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product['name'] }} | Surprise Moi</title>

    <meta property="og:title" content="{{ $openGraph['title'] }}">
    <meta property="og:description" content="{{ $openGraph['description'] }}">
    <meta property="og:image" content="{{ $openGraph['image'] }}">
    <meta property="og:url" content="{{ $openGraph['url'] }}">
    <meta property="og:type" content="product">

    <script>
        (function() {
            var ua = navigator.userAgent || navigator.vendor || window.opera;
            var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
            var iosUrl = @json($downloadLinks['ios']);
            var androidUrl = @json($downloadLinks['android']);

            if (isIOS && iosUrl) {
                window.location.replace(iosUrl);
            } else if (androidUrl) {
                window.location.replace(androidUrl);
            }
        })();
    </script>

    <style>
        :root {
            color-scheme: light;
            --bg: #f5f8ff;
            --card: #ffffff;
            --text: #11203b;
            --muted: #4a5a7a;
            --accent: #0b65f4;
            --border: #d8e2f2;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top right, #e5edff 0%, var(--bg) 55%);
            color: var(--text);
        }
        .container {
            max-width: 760px;
            margin: 40px auto;
            padding: 24px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(17, 32, 59, 0.08);
        }
        .hero {
            width: 100%;
            aspect-ratio: 16 / 10;
            object-fit: cover;
            background: #eef3ff;
        }
        .content {
            padding: 24px;
        }
        .price {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 8px 0 16px;
        }
        .description {
            line-height: 1.6;
            color: var(--muted);
            margin: 0 0 24px;
        }
        .cta-title {
            margin: 0 0 12px;
            font-weight: 600;
        }
        .cta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 12px 18px;
            text-decoration: none;
            font-weight: 600;
            min-width: 180px;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-secondary {
            border-color: var(--border);
            color: var(--text);
            background: #fff;
        }
    </style>
</head>
<body>
<main class="container">
    <article class="card">
        @if($product['image'])
            <img
                class="hero"
                src="{{ $product['image'] }}"
                alt="{{ $product['name'] }}"
            >
        @endif

        <section class="content">
            <h1>{{ $product['name'] }}</h1>
            @if($product['price'] !== null)
                <p class="price">{{ $product['currency'] }} {{ number_format((float) $product['price'], 2) }}</p>
            @endif
            <p class="description">{{ $product['description'] }}</p>

            <p class="cta-title">Download the App</p>
            <div class="cta">
                <a class="btn btn-primary" href="{{ $downloadLinks['android'] }}" target="_blank" rel="noopener noreferrer">
                    Download on Play Store
                </a>
                <a class="btn btn-secondary" href="{{ $downloadLinks['ios'] }}" target="_blank" rel="noopener noreferrer">
                    Download on App Store
                </a>
            </div>
        </section>
    </article>
</main>
</body>
</html>
