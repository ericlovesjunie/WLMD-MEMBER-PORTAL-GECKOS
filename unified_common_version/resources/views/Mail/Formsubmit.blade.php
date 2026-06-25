<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Service</title>
    <style>
        /* Reset styles */
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        td {
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding: 0 !important;
            }
            .responsive-table {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <table class="container" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <h1>Welcome {{ $first_name }}!</h1>
                <p>Thank you for becoming a member!</p>
                <p>Below are your login credentials:</p>
                <p><strong>Login URL:</strong> <a href="{{ $url }}">{{ $url }}</a></p>
                <p><strong>Username:</strong> {{ $email }}</p>
                <p><strong>Password:</strong> {{ $password }}</p>
                <p>If you have any questions, feel free to reach out to our support team.</p>
                <p>Best regards,<br></p>
            </td>
        </tr>
    </table>
</body>
</html>
