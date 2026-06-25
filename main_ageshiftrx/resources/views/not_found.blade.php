<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Not Found</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f9;
            color: #333;
        }
        .not-found-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .not-found-icon {
            font-size: 80px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        .not-found-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .not-found-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background-color: #ff6b6b;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .back-btn:hover {
            background-color: #ff4b4b;
        }
    </style>
</head>
<body>

<div class="not-found-container">
    <div class="not-found-icon">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <div class="not-found-title">User Not Found</div>
    <p class="not-found-message">The user you are looking for does not exist or may have been removed.</p>
    {{-- <a href="{{ url()->previous() }}" class="back-btn">Go Back</a> --}}
</div>

</body>
</html>
