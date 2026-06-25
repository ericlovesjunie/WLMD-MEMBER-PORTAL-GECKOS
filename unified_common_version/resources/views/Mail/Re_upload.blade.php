<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ID Verification Request</title>
    @php
    $theme = DB::table('theme_colores')->latest()->first();
    $is_admin = session('IsAdmin');

    @endphp
    <style>
        /* General reset for the entire page */
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #f4f4f4;
            font-family: 'Arial', sans-serif;
        }

        /* Container for the entire email */
        .email-container {
            padding: 40px 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Main email content box */
        .email-content {
            max-width: 600px;
            width: 100%;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: fadeIn 1s ease-in-out;
        }

        /* Header text */
        h2 {
            color: #333333;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Paragraphs */
        p {
            color: #666666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* Call-to-action button styling */
        .cta-button {
            display: inline-block;
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
            color: white;
            padding: 14px 30px;
            border-radius: 5px;
            font-size: 16px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        /* Button hover effect */
        .cta-button:hover {
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
        }

        /* Footer text styling */
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #888888;
        }

        /* Logo styling */
        .logo {
            width: 120px;
            margin-bottom: 20px;
        }

        /* Media query for responsiveness */
        @media (max-width: 600px) {
            .email-content {
                padding: 20px;
            }

            h2 {
                font-size: 22px;
            }

            p {
                font-size: 14px;
            }

            .cta-button {
                padding: 12px 24px;
                font-size: 14px;
            }
        }

        /* Fade-in animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-content">
            <!-- Logo Section -->
            {{-- <img src="https://lightgoldenrodyellow-okapi-586794.hostingersite.com/mensrs/public/assets/logo/bg_logo.png" alt="Company Logo" class="logo">
            <img src="{{ URL::asset('public/assets/logo/' . $theme->theme_favicon) }}" alt="Company Logo" class="logo">
    <link rel="icon" href="{{ URL::asset('public/assets/theme_favicon/' . $theme->theme_favicon) }}" type="image/png"> --}}
            @if($theme)
            <img src="{{ URL::asset('public/assets/theme_logo/' . $theme->theme_logo) }}" alt="Company Logo" class="logo">
            @else
                
            @endif
            <!-- Email Header -->
            <h2>ID Verification Needed</h2>

            <!-- Main Message -->
            <p>Hello,</p>
            <p>We require a clear photo of your valid ID for verification purposes. Please click the button below to upload the necessary document:</p>
            
            <!-- Call to Action Button -->
            <a href="https://member.gettrim.com/" class="cta-button" style="color: #000000">Upload Your ID</a>

            <!-- Contact Details -->
            {{-- <p>If you have any questions, feel free to reach out to us at <strong>{{ $sender_email }}</strong>.</p> --}}

            <!-- Footer Section -->
            <div class="footer">
                <p>Thank you,<br>The gettrim Team</p>
            </div>
        </div>
    </div>
</body>
</html>
