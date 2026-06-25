<!-- resources/views/Mail/ForgotPasswordOtp.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password OTP</title>
    <style>
        /* General styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #007bff;
            color: #ffffff;
            text-align: center;
            padding: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px;
        }
        .content h2 {
            color: #333333;
            font-size: 20px;
            margin-top: 0;
        }
        .otp {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
            text-align: center;
        }
        .instructions {
            font-size: 16px;
            color: #555555;
            line-height: 1.5;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #f4f4f4;
            font-size: 14px;
            color: #777777;
        }
        /* Responsive styling */
        @media only screen and (max-width: 600px) {
            .container {
                width: 90%;
            }
            .header h1, .content h2 {
                font-size: 20px;
            }
            .otp {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $heading }}</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <h2>Hello,</h2>
            <p class="instructions">
                We received a request to reset your password. Use the OTP below to proceed with resetting your password. This OTP will expire in 10 minutes.
            </p>
            <div class="otp">{{ $user_otp }}</div>
            <p class="instructions">
                If you did not request a password reset, please ignore this email or contact support if you have any questions.
            </p>
        </div>
        
        <!-- Footer -->
        {{-- <div class="footer">
            <p>&copy; {{ date('Y') }} Binarygeckos. All rights reserved.</p>
        </div> --}}
    </div>
</body>
</html>
