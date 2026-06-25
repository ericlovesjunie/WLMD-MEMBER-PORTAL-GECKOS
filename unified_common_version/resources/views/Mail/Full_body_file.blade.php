<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>File Upload Request</title>
    @php
    $theme = DB::table('theme_colores')->latest()->first();
    $is_admin = session('IsAdmin');

    @endphp
    <style>
        /* Basic reset for body and HTML */
        body, html {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: 'Arial', sans-serif;
        }

        /* Container for the entire email */
        .email-container {
            width: 100%;
            background-color: #f4f4f4;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Main content box */
        .email-content {
            max-width: 600px;
            width: 100%;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeIn 1s ease-in-out;
        }

        /* Header styling */
        h2 {
            font-size: 24px;
            color: #333333;
            margin-bottom: 20px;
            font-weight: bold;
        }

        /* Body text styling */
        p {
            font-size: 16px;
            color: #555555;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* Call-to-action button */
        .cta-button {
            display: inline-block;
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        /* Hover effect for the button */
        .cta-button:hover {
            background-color: {{ $theme->theme_colore ?? '#ffd800' }};
            transform: translateY(-2px);
        }

        /* Footer section */
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #888888;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        /* Logo styling */
        .logo {
            width: 100px;
            margin-bottom: 20px;
        }

        /* Responsive design for mobile devices */
        @media (max-width: 600px) {
            .email-content {
                padding: 20px;
            }

            h2 {
                font-size: 20px;
            }

            p {
                font-size: 14px;
            }

            .cta-button {
                font-size: 14px;
                padding: 10px 20px;
            }
        }

        /* Fade-in animation for content */
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
            <!-- Company Logo -->
            @if($theme)
            <img src="{{ URL::asset('public/assets/theme_logo/' . $theme->theme_logo) }}" alt="Company Logo" class="logo">
            @else
                
            @endif

            <h2>FULL Body Clothed Photo of Yourself</h2>
            <p>Hello,</p>
            <p>To ensure the accuracy of the information provided, we’ll need you to upload a clear, FULL body clothed photo of yourself.</p>
            <a href="https://member.gettrim.com/" class="cta-button" style="color: #000000">Upload Your Photo</a>

            {{-- <p>If you have any questions, feel free to reach out to us at <strong>{{ $sender_email }}</strong>.</p> --}}

            <div class="footer">
                <p>Thank you,<br>The gettrim Team</p>
            </div>
        </div>
    </div>
</body>
</html>
