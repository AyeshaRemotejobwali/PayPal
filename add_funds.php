<?php
session_start();
require_once 'db.php';

// Log errors to a file, but don't display them
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = htmlspecialchars(trim($_POST['payment_method']), ENT_QUOTES, 'UTF-8');

        if ($amount === false || $amount <= 0) {
            throw new Exception("Invalid amount! Please enter a positive number.");
        }
        if (empty($payment_method)) {
            throw new Exception("Please select a payment method.");
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("di", $amount, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            // Log transaction
            $sender_email = 'system';
            $recipient_email = $_SESSION['email'] ?? '';
            $stmt = $conn->prepare("INSERT INTO transactions (sender_id, recipient_id, sender_email, recipient_email, amount) VALUES (NULL, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("issd", $user_id, $sender_email, $recipient_email, $amount);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $conn->commit();
            $_SESSION['message'] = "Successfully added $$amount to your wallet via $payment_method.";
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Transaction failed: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log($error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Clone - Add Funds</title>
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
        .add-funds-form {
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .add-funds-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .add-funds-form input, .add-funds-form select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .add-funds-form button {
            width: 100%;
            padding: 12px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        .add-funds-form button:hover {
            background: #005EA6;
        }
        .error {
            color: #D32F2F;
            text-align: center;
            margin-bottom: 10px;
        }
        @media (max-width: 600px) {
            .add-funds-form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="add-funds-form">
        <h2>Add Funds</h2>
        <?php if (isset($error)) echo "<p class='error'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
        <form method="POST">
            <input type="number" name="amount" placeholder="Amount" step="0.01" required>
            <select name="payment_method" required>
                <option value="" disabled selected>Select Payment Method</option>
                <option value="credit_card">Credit Card</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>
            <button type="submit">Add Funds</button>
        </form>
        <p><a href="#" onclick="redirectTo('dashboard.php')">Back to Dashboard</a></p>
    </div>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
