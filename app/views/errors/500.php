<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .error-container {
            text-align: center;
            max-width: 500px;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #fee2e2;
            line-height: 1;
            margin-bottom: 1rem;
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        p {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1>Server Error</h1>
        <p>Something went wrong on our end. Please try again later or contact support if the problem persists.</p>
        <a href="/btsapp/" class="btn">Go to Homepage</a>
    </div>
</body>
</html>