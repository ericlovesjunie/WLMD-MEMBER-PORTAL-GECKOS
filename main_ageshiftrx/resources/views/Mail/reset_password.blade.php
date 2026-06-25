<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: auto;
        }
        h2 {
            color: #444;
        }
        .temp-password {
            font-size: 18px;
            font-weight: bold;
            color: #ff6b6b;
            margin: 15px 0;
        }
        .reset-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
            background-color: #ff6b6b;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .reset-link:hover {
            background-color: #ff4b4b;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Password Reset Request</h2>
    <p>We have received a request to reset your password.</p>
    
    {{-- <p class="temp-password">Temporary Password: {{ $temp_password }}</p> --}}

    <p>Click the link below to reset your password:</p>
    <a href="{{ $url }}" class="reset-link">Reset Password</a>

    <p>If you did not request a password reset, please ignore this email or contact support.</p>

    <p>Best regards,</p>
</div>

</body>
</html>
