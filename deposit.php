<?php
require_once 'includes/auth.php';
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$message = '';
$phone_number = '0559118581 - VERONICA BARNES';
$admin_phone = '233559118581'; // Admin phone number in international format (0559118581)

// Fetch user's phone number
global $pdo;
$user_stmt = $pdo->prepare("SELECT phone_number FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();
$user_phone = !empty($user['phone_number']) ? '233' . substr($user['phone_number'], 1) : null; // Convert to international format (e.g., 233544919953)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        // Insert the transaction
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, phone_number) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $amount, $phone_number]);

        // Prepare SMS messages
        $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
        $from = 'PaySub';
        $sms_success = true;
        $sms_error = '';

        // Send SMS to the user (if phone number exists)
        if ($user_phone) {
            $user_message = "Your deposit of GHS $amount has been submitted to PaySub. Awaiting approval.";
            $user_message_encoded = urlencode($user_message);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$user_phone&from=$from&sms=$user_message_encoded",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code !== 200 || !$response) {
                $sms_success = false;
                $sms_error .= "Failed to send SMS to user. ";
            }
        } else {
            $sms_success = false;
            $sms_error .= "User phone number not found. ";
        }

        // Send SMS to the admin
        $admin_identifier = $user_phone ?? "user ID {$_SESSION['user_id']}";
        $admin_message = "New deposit submitted on PaySub: GHS $amount from $admin_identifier. Please approve.";
        $admin_message_encoded = urlencode($admin_message);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$admin_phone&from=$from&sms=$admin_message_encoded",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code !== 200 || !$response) {
            $sms_success = false;
            $sms_error .= "Failed to send SMS to admin. ";
        }

        // Set the message based on SMS success
        if ($sms_success) {
            $message = "Deposit submitted! Please be sure you've sent GHS $amount to $phone_number. Awaiting approval.";
        } else {
            $message = "Deposit submitted! Please be sure you've sent GHS $amount to $phone_number. Awaiting approval. However, $sms_error";
        }
    } else {
        $message = "Invalid amount.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Funds | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard">Dashboard</a>
            <a href="deposit" class="active">Deposit Funds</a>
            <a href="support">Support</a>
            <?php if (is_admin()): ?><a href="admin/index">Admin</a><?php endif; ?>
            <a href="logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <?php if ($message): ?>
                <div class="toast <?php echo strpos($message, 'submitted') !== false ? 'success' : 'error'; ?>">
                    <span class="icon"><?php echo strpos($message, 'submitted') !== false ? '✔' : '✖'; ?></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
            <div class="card">
                <h2>Deposit Funds</h2>
                <p>Send money to: <strong><?php echo $phone_number; ?></strong></p>
                <p>Enter the amount you want to deposit, send the money to the number above and click <strong>I've Sent The Money</strong></p>
                <form method="POST" onsubmit="showLoading()">
                    <input type="number" name="amount" placeholder="Amount (GHS)" step="0.01" required>
                    <button type="submit">I’ve Sent the Money</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }
    </script>
</body>
</html>