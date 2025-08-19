<?php
session_start();
$_SESSION['db_mode'] = 'previous';
require 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['db_mode'] = 'current'; 
        header("Location: /btsapp/dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | BTS DISC 2.O Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', 'Roboto', 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a1a3a, #8a1c1c, #0a1a3a);
            overflow: hidden;
            position: relative;
            color: #fff;
        }
        
        #three-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .container {
            display: flex;
            width: 90%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            border-radius: 25px;
            overflow: hidden;
            z-index: 10;
            position: relative;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transform: translateY(0);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        
        .container:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        }
        
        .left {
            flex: 1;
            min-height: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(45deg, #5e3825, #3d2317);
            position: relative;
            overflow: hidden;
            padding: 30px;
        }
        
        .left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
            animation: shine 8s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: rotate(30deg) translateX(-100%); }
            100% { transform: rotate(30deg) translateX(100%); }
        }
        
        .logo-container {
            text-align: center;
            z-index: 2;
            width: 100%;
            padding: 20px;
        }
        
        .logo-container img {
            max-width: 100%;
            height: auto;
            max-height: 280px;
            object-fit: contain;
            filter: drop-shadow(0 10px 25px rgba(0,0,0,0.4));
            transition: all 0.5s ease;
        }
        
        .logo-container img:hover {
            transform: scale(1.03);
            filter: drop-shadow(0 15px 30px rgba(0,0,0,0.5));
        }
        
        .brand-text {
            margin-top: 25px;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .right {
            flex: 1;
            padding: 50px 40px;
            position: relative;
        }
        
        h2 {
            margin-bottom: 30px;
            text-align: center;
            letter-spacing: 1px;
            color: #333;
            font-size: 2.4rem;
            position: relative;
            font-weight: 700;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 5px;
            background: linear-gradient(to right, #7e4c2e, #5a3521);
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #555;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        form input[type=text], form input[type=password] {
            width: 100%;
            padding: 16px 20px 16px 55px;
            margin: 8px 0;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            background-color: #f8f8f8;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
        }
        
        form input[type=text]:focus, form input[type=password]:focus {
            border-color: #7e4c2e;
            box-shadow: 0 0 0 4px rgba(126, 76, 46, 0.25);
            outline: none;
            background-color: #fff;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 48px;
            color: #7e4c2e;
            font-size: 1.3rem;
        }
        
        form input[type=submit] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #7e4c2e, #5a3521);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 20px;
            font-size: 1.15rem;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(126, 76, 46, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        form input[type=submit]:hover {
            background: linear-gradient(45deg, #5a3521, #7e4c2e);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(126, 76, 46, 0.6);
        }
        
        form input[type=submit]::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20px;
            height: 200%;
            background: rgba(255,255,255,0.3);
            transform: rotate(25deg);
            transition: all 0.6s ease;
        }
        
        form input[type=submit]:hover::after {
            left: 140%;
        }
        
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin: 20px 0;
            padding: 12px;
            background: rgba(231, 76, 60, 0.12);
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
            animation: shake 0.6s ease;
            font-weight: 500;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }
        
        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 8px;
            color: #7e4c2e;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            color: #5a3521;
            text-decoration: underline;
        }
        
        .powered-by {
            position: absolute;
            bottom: 20px;
            right: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #7e4c2e;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            opacity: 0.9;
            transition: all 0.3s ease;
        }
        
        .powered-by:hover {
            opacity: 1;
            transform: translateX(5px);
        }
        
        .powered-by span {
            color: #333;
            font-weight: 500;
        }
        
        .powered-by i {
            font-size: 1.1rem;
            color: #5a3521;
        }
        
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .left {
                min-height: 300px;
                padding: 30px 0;
            }
            
            .right {
                padding: 40px 35px;
            }
            
            h2 {
                font-size: 2.1rem;
            }
            
            .logo-container img {
                max-height: 200px;
            }
        }
        
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .container {
                width: 100%;
                border-radius: 20px;
            }
            
            .right {
                padding: 35px 25px;
            }
            
            form input[type=text], 
            form input[type=password] {
                padding: 14px 15px 14px 50px;
            }
            
            .input-icon {
                top: 45px;
                left: 18px;
            }
            
            .powered-by {
                position: static;
                margin-top: 25px;
                justify-content: center;
            }
        }
        
        .floating-text {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
            z-index: 11;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div id="three-container"></div>
    
    <div class="floating-text">Secure Login Portal | BTS DISC 2.O Application</div>
    
    <div class="container">
        <div class="left">
            <div class="logo-container">
                <img src="./public/logo.png" alt="Company Logo">
                <div class="brand-text">BTS DISC 2.O Application</div>
            </div>
        </div>
        <div class="right">
            <form method="POST">
                <h2>LOG IN</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-icon"><i class="fas fa-user"></i></div>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon"><i class="fas fa-lock"></i></div>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <input type="submit" value="Login">
                
                <div class="powered-by">
                    <span>Powered by</span>
                    <i class="fas fa-bolt"></i>
                    <strong><a href="https://www.SoluServ.in" target="_blank">SoluServ.in</a></strong>
                </div>
            </form>
        </div>
    </div>

</body>
</html>