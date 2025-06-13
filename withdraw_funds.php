<?php
session_start();
require_once 'db.php';

// Log errors to a file, do not display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error_message = "";
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
        $withdrawal_method = trim($_POST['withdrawal_method']);

        if ($amount === false || $amount <= 0) {
            throw new Exception("Invalid amount. Please enter a positive number.");
        }
        if (empty($withdrawal_method)) {
            throw new Exception("Please select a withdrawal method.");
        }
        $withdrawal_method = htmlspecialchars($withdrawal_method, ENT_QUOTES, 'UTF-8');

        // Check balance
        $stmt = $conn->prepare("SELECT balance, email FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for balance check: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for balance check: " . $stmt->error);
        }
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) {
            throw new Exception("User not found.");
        }
        if ($user['balance'] < $amount) {
            throw new Exception("Insufficient balance.");
        }
        $sender_email = $user['email'];
        $stmt->close();

        // Start transaction
        $conn->begin_transaction();
        try {
            // Update balance
            $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for balance update: " . $conn->error);
            }
            $stmt->bind_param("di", $amount, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for balance update: " . $stmt->error);
            }
            $stmt->close();

            // Log transaction
            $recipient_email = 'system';
            $stmt = $conn->prepare("INSERT INTO transactions (sender_id, recipient_id, sender_email, recipient_email, amount) VALUES (?, NULL, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for transaction log: " . $conn->error);
            }
            $stmt->bind_param("issd", $user_id, $sender_email, $recipient_email, $amount);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for transaction log: " . $stmt->error);
            }
            $stmt->close();

            $conn->commit();
            $debug_info[] = "Withdrawal successful: $$amount via $withdrawal_method";
            $_SESSION['message'] = "Successfully withdrew $$amount via $withdrawal_method.";
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Transaction failed: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $debug_info[] = "Withdrawal error: " . $e->getMessage();
        error_log("Withdrawal error: " . $e->getMessage());
    }
}

// Log debug info
error_log("Withdraw debug: " . implode(" | ", $debug_info));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - PayPal Clone</title>
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
            justify-content: center;
            align-items: center;
        }
        .withdraw-funds-form {
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .withdraw-funds-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .withdraw-funds-form input, .withdraw-funds-form select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .withdraw-funds-form button {
            width: 100%;
            padding: 12px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .withdraw-funds-form button:hover {
            background: #005EA6;
        }
        .error {
            color: #D32F2F;
            text-align: center;
            margin-bottom: 20px;
        }
        .debug {
            color: #555;
            font-size: 0.8em;
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        @media (max-width: 600px) {
            .withdraw-funds-form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="withdraw-funds-form">
        <h2>Withdraw Funds</h2>
        <?php if ($error_message) { ?>
            <p class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>
        <form method="POST">
            <input type="number" name="amount" placeholder="Amount" step="0.01" min="0.01" required>
            <select name="withdrawal_method" required>
                <option value="" disabled selected>Select Withdrawal Method</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="paypal">PayPal</option>
            </select>
            <button type="submit">Withdraw Funds</button>
        </form>
        <p><a href="#" onclick="redirectTo('dashboard.php')">Back to Dashboard</a></p>
        <div class="debug">
            <p>Debug Info: <?php echo htmlspecialchars(implode(" | ", $debug_info), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
