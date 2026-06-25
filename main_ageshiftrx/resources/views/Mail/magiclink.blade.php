<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Link</title>
    @php
    $theme = DB::table('theme_colores')->latest()->first();
    $is_admin = session('IsAdmin');

    @endphp
    <style>
        /* General styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
            color: #ffffff;
            text-align: center;
            padding: 20px;
            font-size: 24px;
        }
        .email-body {
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .email-body h2 {
            font-size: 20px;
            color: #444;
        }
        .email-footer {
            background-color: #f9f9f9;
            padding: 10px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .magic-link {
            display: inline-block;
            margin: 20px 0;
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
        }
        .magic-link:hover {
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
        }
        /* Mobile responsiveness */
        @media only screen and (max-width: 600px) {
            .email-header {
                font-size: 20px;
                padding: 15px;
            }
            .email-body {
                padding: 15px;
            }
            .magic-link {
                font-size: 14px;
                padding: 8px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Email Header -->
        <div class="email-header">
            Login with Your Magic Link
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <h2>Hello, {{ $first_name }}</h2>
            <p>
                You requested a secure login link. Click the button below to log in to your account:
            </p>
            <p style="text-align: center;">
                <a href="{{ $url }}" class="magic-link">Login Now</a>
            </p>
            {{-- <p>
                If you didn't request this, please ignore this email or contact support.
            </p> --}}
            {{-- <p>
                This link will expire in 15 minutes for your security.
            </p> --}}
        </div>

        <!-- Email Footer -->
        <div class="email-footer">
            {{-- © {{ now()->year }} Your Company. All rights reserved. --}}
        </div>
    </div>
</body>
</html>
