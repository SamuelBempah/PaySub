<?php
require_once '../includes/auth.php';
if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

global $pdo;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sub_id = $_POST['sub_id'];
    $action = $_POST['action'];

    // Fetch subscription details and user's phone number
    $sub = $pdo->prepare("SELECT us.user_id, us.chosen_plan, sp.name, u.phone_number 
                          FROM user_subscriptions us 
                          JOIN subscription_plans sp ON us.plan_id = sp.id 
                          JOIN users u ON us.user_id = u.id 
                          WHERE us.id = ?");
    $sub->execute([$sub_id]);
    $data = $sub->fetch();

    if ($data) {
        $user_phone = !empty($data['phone_number']) ? '233' . substr($data['phone_number'], 1) : null; // Convert to international format (e.g., 233544919953)

        // Update the subscription status
        $stmt = $pdo->prepare("UPDATE user_subscriptions SET status = ? WHERE id = ?");
        $stmt->execute([$action, $sub_id]);

        // Prepare SMS message based on the status
        $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
        $from = 'PaySub';
        $sms_success = true;
        $sms_error = '';

        // Define the SMS message for each status
        $user_message = '';
        switch ($action) {
            case 'pending':
                $user_message = "Your subscription to {$data['name']} ({$data['chosen_plan']}) has been submitted successfully and is pending approval by PaySub.";
                break;
            case 'processing':
                $user_message = "Your subscription to {$data['name']} ({$data['chosen_plan']}) has been accepted and is currently being processed by PaySub.";
                break;
            case 'active':
                $user_message = "Congratulations! Your subscription to {$data['name']} ({$data['chosen_plan']}) is now active! You can log in to that account.";
                break;
            case 'failed':
                $user_message = "We’re sorry, your subscription to {$data['name']} ({$data['chosen_plan']}) has failed. Please contact PaySub support.";
                break;
            case 'cancelled':
                $user_message = "Your subscription to {$data['name']} ({$data['chosen_plan']}) has been cancelled by PaySub.";
                break;
            case 'expired':
                $user_message = "Your subscription to {$data['name']} ({$data['chosen_plan']}) has expired. Renew now on PaySub to continue.";
                break;
            default:
                $user_message = "Your subscription to {$data['name']} ({$data['chosen_plan']}) has been updated to '$action' by PaySub.";
        }

        // Send SMS to the user (if phone number exists)
        if ($user_phone) {
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

        // Set the toast message based on action and SMS success
        $message = "Subscription set to $action!";
        if ($sms_success) {
            $message .= " SMS notification sent.";
        } else {
            $message .= " However, $sms_error";
        }
    } else {
        $message = "Subscription not found.";
    }
}

// Fetch all subscriptions
$subscriptions = $pdo->query("SELECT us.*, u.email, sp.name FROM user_subscriptions us JOIN users u ON us.user_id = u.id JOIN subscription_plans sp ON us.plan_id = sp.id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            opacity: 0.8;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status.pending { 
            opacity: 0.5; 
            background-color: #ffc107; 
        }
        .status.processing { 
            background-color: orange;
        }
        .status.processing::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid white;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        .status.active { 
            opacity: 1; 
            background-color: #28a745; 
        }
        .status.failed { 
            background-color: #dc3545; 
        }
        .status.cancelled { 
            background-color: #6c757d; 
        }
        .status.expired { 
            background-color: #343a40; 
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Button Styles */
        button.bg-yellow-500 { background: #ffc107; }
        button.bg-yellow-500:hover { background: #e0a800; }
        button.bg-orange-500 { background: orange; }
        button.bg-orange-500:hover { background: #e69500; }
        button.bg-green-500 { background: #28a745; }
        button.bg-green-500:hover { background: #218838; }
        button.bg-red-500 { background: #dc3545; }
        button.bg-red-500:hover { background: #c82333; }
        button.bg-gray-500 { background: #6c757d; }
        button.bg-gray-500:hover { background: #5a6268; }
        button.bg-dark-500 { background: #343a40; }
        button.bg-dark-500:hover { background: #23272b; }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub Admin</div>
            <a href="index">Dashboard</a>
            <a href="manage_deposits">Manage Deposits</a>
            <a href="manage_subs" class="active">Manage Subscriptions</a>
            <a href="manage_plans">Manage Plans</a>
            <a href="../logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo $_SESSION['email']; ?></div>
            </header>
            <?php if ($message): ?>
                <div class="toast <?php echo strpos($message, 'active') !== false ? 'success' : 'error'; ?>">
                    <span class="icon"><?php echo strpos($message, 'active') !== false ? '✔' : '✖'; ?></span>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
            <div class="card">
                <h2>Manage Subscriptions</h2>
                <?php if (empty($subscriptions)): ?>
                    <p>No subscriptions available.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($subscriptions as $sub): ?>
                            <li>
                                <?php 
                                echo $sub['email'] . " - " . $sub['name'] . " (" . $sub['chosen_plan'] . ")"; 
                                ?>
                                <span class="status <?php echo strtolower($sub['status']); ?>">
                                    <?php echo $sub['status']; ?>
                                </span>
                                <p>
                                    Credentials: 
                                    <?php 
                                    $credentials = json_decode($sub['credentials'], true);
                                    if (is_array($credentials) && isset($credentials['username_or_email']) && isset($credentials['password'])) {
                                        echo "Username/Email: " . htmlspecialchars($credentials['username_or_email']) . ", Password: " . htmlspecialchars($credentials['password']);
                                    } else {
                                        echo "No credentials provided.";
                                    }
                                    ?>
                                </p>
                                <form method="POST" class="inline" onsubmit="showLoading()">
                                    <input type="hidden" name="sub_id" value="<?php echo $sub['id']; ?>">
                                    <?php if ($sub['status'] !== 'pending'): ?>
                                        <button type="submit" name="action" value="pending" class="bg-yellow-500">Pending</button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'processing'): ?>
                                        <button type="submit" name="action" value="processing" class="bg-orange-500">Processing</button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'active'): ?>
                                        <button type="submit" name="action" value="active" class="bg-green-500">Active</button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'failed'): ?>
                                        <button type="submit" name="action" value="failed" class="bg-red-500">Failed</button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'cancelled'): ?>
                                        <button type="submit" name="action" value="cancelled" class="bg-gray-500">Cancelled</button>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'expired'): ?>
                                        <button type="submit" name="action" value="expired" class="bg-dark-500">Expired</button>
                                    <?php endif; ?>
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