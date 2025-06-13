<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, two_factor_secret FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Check 2FA
            if ($user['two_factor_secret']) {
                $_SESSION['temp_user_id'] = $user['id'];
                header("Location: verify_2fa.php");
                exit();
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid credentials!";
        }
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Clone - Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #0070BA, #00A1D6);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background: #003087;
            padding: 20px;
            text-align: center;
        }
        header h1 {
            font-size: 2.5em;
            color: #fff;
        }
        nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            font-weight: bold;
        }
        nav a:hover {
            color: #FFD700;
        }
        .hero {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 50px 20px;
        }
        .hero-content {
            max-width: 600px;
        }
        .hero h2 {
            font-size: 2.2em;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: 1.2em;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #FFD700;
            color: #003087;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #FFC107;
        }
        .login-form {
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            margin: 20px auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .login-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .login-form button {
            width: 100%;
            padding: 12px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .login-form button:hover {
            background: #005EA6;
        }
        .error {
            color: #D32F2F;
            margin-bottom: 10px;
        }
        footer {
            background: #003087;
            padding: 20px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .hero h2 {
                font-size: 1.8em;
            }
            .hero p {
                font-size: 1em;
            }
            .login-form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>PayPal Clone</h1>
    </header>
    <nav>
        <a href="index.php">Home</a>
        <a href="#" onclick="redirectTo('signup.php')">Sign Up</a>
        <a href="#" onclick="redirectTo('reset_password.php')">Reset Password</a>
    </nav>
    <div class="hero">
        <div class="hero-content">
            <h2>Send and Receive Money Instantly</h2>
            <p>Securely manage your transactions with our fast and reliable payment platform.</p>
            <a href="#" class="btn" onclick="redirectTo('signup.php')">Get Started</a>
        </div>
    </div>
    <div class="login-form">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <p><a href="#" onclick="redirectTo('reset_password.php')">Forgot Password?</a></p>
    </div>
    <footer>
        <p>&copy; 2025 PayPal Clone. All rights reserved.</p>
    </footer>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
