<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['temp_user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['temp_user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'];
    // In production, verify 2FA code using a library like PHPGangsta/GoogleAuthenticator
    // For simplicity, assume code is valid
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $user['username'];
    unset($_SESSION['temp_user_id']);
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Clone - Verify 2FA</title>
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
            justify-content: center;
            align-items: center;
        }
        .verify-form {
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .verify-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .verify-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .verify-form button {
            width: 100%;
            padding: 12px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .verify-form button:hover {
            background: #005EA6;
        }
        .error {
            color: #D32F2F;
            text-align: center;
            margin-bottom: 10px;
        }
        @media (max-width: 600px) {
            .verify-form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-form">
        <h2>Verify 2FA</h2>
        <form method="POST">
            <input type="text" name="code" placeholder="Enter 2FA Code" required>
            <button type="submit">Verify</button>
        </form>
        <p><a href="#" onclick="redirectTo('index.php')">Back to Login</a></p>
    </div>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
