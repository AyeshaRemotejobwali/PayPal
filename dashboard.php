<?php
session_start();
require_once 'db.php';

// Log errors to a file, do not display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = "User";
$balance = 0.00;
$transactions = [];
$error_message = "";

// Test database connection
try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Connection error: " . $e->getMessage());
    $error_message = "Unable to connect to the database. Please try again later.";
}

// Fetch user balance and username
if (!$error_message) {
    try {
        $stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for balance query: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for balance query: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $username = $row['username'];
            $balance = $row['balance'];
        } else {
            error_log("No user found for ID: $user_id");
            $error_message = "User not found.";
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Balance fetch error: " . $e->getMessage());
        $error_message = "Error fetching balance. Please try again.";
    }
}

// Fetch transaction history (limited to 5 for simplicity)
if (!$error_message) {
    try {
        $stmt = $conn->prepare("SELECT sender_id, recipient_id, sender_email, recipient_email, amount, created_at FROM transactions WHERE sender_id = ? OR recipient_id = ? ORDER BY created_at DESC LIMIT 5");
        if (!$stmt) {
            throw new Exception("Prepare failed for transactions query: " . $conn->error);
        }
        $stmt->bind_param("ii", $user_id, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for transactions query: " . $stmt->error);
        }
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Transactions fetch error: " . $e->getMessage());
        $error_message = "Error fetching transactions. Displaying limited data.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Clone - Dashboard</title>
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
        }
        header {
            background: #003087;
            padding: 20px;
            text-align: center;
        }
        header h1 {
            font-size: 2em;
        }
        nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            text-align: center;
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
        .dashboard {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            color: #003087;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .balance {
            text-align: center;
            margin-bottom: 20px;
        }
        .balance h2 {
            font-size: 2em;
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .action-btn {
            padding: 10px 20px;
            background: #003087;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .action-btn:hover {
            background: #005EA6;
        }
        .transactions {
            margin-top: 20px;
        }
        .transactions table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .transactions th, .transactions td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .transactions th {
            background: #003087;
            color: #fff;
        }
        .error {
            color: #D32F2F;
            text-align: center;
            margin-bottom: 20px;
        }
        .message {
            color: #4CAF50;
            text-align: center;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .dashboard {
                margin: 10px;
                padding: 15px;
            }
            .actions {
                flex-direction: column;
                gap: 10px;
            }
            .balance h2 {
                font-size: 1.5em;
            }
            .transactions table {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h1>
    </header>
    <nav>
        <a href="#" onclick="redirectTo('dashboard.php')">Dashboard</a>
        <a href="#" onclick="redirectTo('send_money.php')">Send Money</a>
        <a href="#" onclick="redirectTo('add_funds.php')">Add Funds</a>
        <a href="#" onclick="redirectTo('withdraw_funds.php')">Withdraw Funds</a>
        <a href="#" onclick="redirectTo('logout.php')">Logout</a>
    </nav>
    <div class="dashboard">
        <?php if (isset($_SESSION['message'])) { ?>
            <p class="message"><?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php unset($_SESSION['message']); ?>
        <?php } ?>
        <?php if ($error_message) { ?>
            <p class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>
        <div class="balance">
            <h2>Balance: $<?php echo number_format($balance, 2); ?></h2>
        </div>
        <div class="actions">
            <a href="#" class="action-btn" onclick="redirectTo('send_money.php')">Send Money</a>
            <a href="#" class="action-btn" onclick="redirectTo('add_funds.php')">Add Funds</a>
            <a href="#" class="action-btn" onclick="redirectTo('withdraw_funds.php')">Withdraw Funds</a>
        </div>
        <div class="transactions">
            <h3>Recent Transactions</h3>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Counterparty</th>
                </tr>
                <?php if ($transactions) { ?>
                    <?php foreach ($transactions as $row) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $row['sender_id'] == $user_id ? 'Sent' : 'Received'; ?></td>
                            <td>$<?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['sender_id'] == $user_id ? $row['recipient_email'] : $row['sender_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="4">No recent transactions.</td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
