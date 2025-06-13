<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);
    $amount = floatval($_POST['amount']);

    // Validate amount
    if ($amount <= 0) {
        $error = "Invalid amount!";
    } else {
        // Check sender's balance
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $sender = $stmt->get_result()->fetch_assoc();

        if ($sender['balance'] < $amount) {
            $error = "Insufficient balance!";
        } else {
            // Find recipient
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $recipient_email);
            $stmt->execute();
            $recipient = $stmt->get_result();

            if ($recipient->num_rows == 0) {
                $error = "Recipient not found!";
            } else {
                $recipient = $recipient->fetch_assoc();
                $recipient_id = $recipient['id'];

                // Update balances
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $user_id);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $recipient_id);
                    $stmt->execute();

                    // Log transaction
                    $sender_email = $_SESSION['email'] ?? '';
                    $stmt = $conn->prepare("INSERT INTO transactions (sender_id, recipient_id, sender_email, recipient_email, amount) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissd", $user_id, $recipient_id, $sender_email, $recipient_email, $amount);
                    $stmt->execute();

                    $conn->commit();
                    // Simulate email notification
                    $message = "Transaction successful! $$amount sent to $recipient_email.";
                    // In production, use mail() or an email service here
                    $_SESSION['message'] = $message;
                    header("Location: dashboard.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Transaction failed!";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Clone - Send Money</title>
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
        .send-money-form {
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .send-money-form h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .send-money-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .send-money-form button {
            width: 100%;
            padding: 12px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .send-money-form button:hover {
            background: #005EA6;
        }
        .error {
            color: #D32F2F;
            text-align: center;
            margin-bottom: 10px;
        }
        @media (max-width: 600px) {
            .send-money-form {
                margin: 10px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="send-money-form">
        <h2>Send Money</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="email" name="recipient_email" placeholder="Recipient Email" required>
            <input type="number" name="amount" placeholder="Amount" step="0.01" required>
            <button type="submit">Send</button>
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
