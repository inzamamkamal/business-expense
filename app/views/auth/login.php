<?php $title = 'Login - BTS DISC 2.0 Application'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            display: flex;
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 2;
            position: relative;
        }
        
        .login-brand {
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            z-index: 2;
            position: relative;
        }
        
        .login-tagline {
            font-size: 1rem;
            opacity: 0.9;
            text-align: center;
            z-index: 2;
            position: relative;
        }
        
        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .login-subtitle {
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-floating {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-floating input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        .form-floating .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
            z-index: 2;
        }
        
        .form-floating input:focus + .form-icon {
            color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            color: white;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
            font-size: 0.9rem;
        }
        
        .powered-by {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .powered-by a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .powered-by a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
                max-width: none;
            }
            
            .login-left {
                padding: 2rem;
                min-height: 200px;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .login-logo {
                width: 80px;
                height: 80px;
            }
            
            .login-brand {
                font-size: 1.5rem;
            }
            
            .login-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <img src="/assets/images/logo.png" alt="BTS DISC 2.0" class="login-logo">
            <h1 class="login-brand">BTS DISC 2.0</h1>
            <p class="login-tagline">Professional Business Management System</p>
        </div>
        
        <div class="login-right">
            <h2 class="login-title">Welcome Back</h2>
            <p class="login-subtitle">Please sign in to your account</p>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= Security::escape($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/">
                <div class="form-floating">
                    <input type="text" id="username" name="username" placeholder="Username" required>
                    <i class="fas fa-user form-icon"></i>
                </div>
                
                <div class="form-floating">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-lock form-icon"></i>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Sign In
                </button>
            </form>
            
            <div class="powered-by">
                Powered by <a href="https://www.SoluServ.in" target="_blank">SoluServ.in</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Add loading state to login button
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('.btn-login');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>