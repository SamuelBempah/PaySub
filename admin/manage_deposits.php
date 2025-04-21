<?php
require_once '../includes/auth.php';
if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

global $pdo;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $txn_id = $_POST['txn_id'];
    $action = $_POST['action'];

    // Fetch transaction details and user's phone number
    $txn = $pdo->prepare("SELECT t.user_id, t.amount, u.phone_number 
                          FROM transactions t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.id = ?");
    $txn->execute([$txn_id]);
    $data = $txn->fetch();

    if ($data) {
        $user_phone = !empty($data['phone_number']) ? '233' . substr($data['phone_number'], 1) : null; // Convert to international format (e.g., 233544919953)

        // Update the transaction status
        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?");
        $stmt->execute([$action, $txn_id]);

        // If approved, update the user's balance
        if ($action === 'approved') {
            update_balance($data['user_id'], $data['amount']);
        }

        // Prepare SMS message
        $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
        $from = 'PaySub';
        $sms_success = true;
        $sms_error = '';

        // Send SMS to the user (if phone number exists)
        if ($user_phone) {
            $user_message = $action === 'approved' 
                ? "Your deposit of GHS " . number_format($data['amount'], 2) . " has been approved by PaySub."
                : "Your deposit of GHS " . number_format($data['amount'], 2) . " has been rejected by PaySub.";
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
                $sms_error = "Failed to send SMS to user.";
            }
        } else {
            $sms_success = false;
            $sms_error = "User phone number not found.";
        }

        // Set the message based on action and SMS success
        if ($action === 'approved') {
            $message = "Deposit approved!";
        } else {
            $message = "Deposit rejected.";
        }

        if ($sms_success) {
            $message .= " SMS notification sent.";
        } else {
            $message .= " However, $sms_error";
        }
    } else {
        $message = "Transaction not found.";
    }
}

$transactions = $pdo->query("SELECT t.*, u.email FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deposits | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub Admin</div>
            <a href="index">Dashboard</a>
            <a href="manage_deposits" class="active">Manage Deposits</a>
            <a href="manage_subs">Manage Subscriptions</a>
            <a href="manage_plans">Manage Plans</a>
            <a href="../logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <?php if ($message): ?>
                <div class="toast <?php echo strpos($message, 'approved') !== false ? 'success' : 'error'; ?>">
                    <span class="icon"><?php echo strpos($message, 'approved') !== false ? '✔' : '✖'; ?></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
            <div class="card">
                <h2>Manage Deposits</h2>
                <?php if (empty($transactions)): ?>
                    <p>No pending deposits.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($transactions as $txn): ?>
                            <li>
                                <?php echo $txn['email'] . " - GHS " . number_format($txn['amount'], 2) . " to " . $txn['phone_number']; ?>
                                <span class="status <?php echo strtolower($txn['status']); ?>">
                                    <?php echo $txn['status']; ?>
                                </span>
                                <form method="POST" class="inline" onsubmit="showLoading()">
                                    <input type="hidden" name="txn_id" value="<?php echo $txn['id']; ?>">
                                    <button type="submit" name="action" value="approved" class="bg-green-500">Approve</button>
                                    <button type="submit" name="action" value="rejected" class="bg-red-500">Reject</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
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