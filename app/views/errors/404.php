<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
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
            color: #e5e7eb;
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
        
        .illustration {
            margin-bottom: 2rem;
        }
        
        .illustration svg {
            width: 200px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1>Page Not Found</h1>
        <p>Sorry, the page you are looking for could not be found. It might have been removed, renamed, or did not exist in the first place.</p>
        <a href="/btsapp/dashboard" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>