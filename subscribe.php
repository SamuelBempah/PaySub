<?php
require_once 'includes/auth.php';
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

global $pdo;
$message = '';

if (!isset($_GET['plan_id'])) {
    header("Location: dashboard.php");
    exit;
}

$plan_id = $_GET['plan_id'];
$plan = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
$plan->execute([$plan_id]);
$plan = $plan->fetch();

if (!$plan) {
    header("Location: dashboard.php");
    exit;
}

// Parse the plans column into an array of plan names and costs
$plan_options = [];
$plans_string = $plan['plans'];
if ($plans_string) {
    $plans_array = explode(',', $plans_string);
    foreach ($plans_array as $plan_item) {
        list($plan_name, $plan_cost) = explode(':', $plan_item);
        $plan_options[$plan_name] = floatval($plan_cost);
    }
} else {
    $plan_options = ['Default' => 0.00]; // Fallback if no plans are defined
}

// Fetch user's balance and phone number
$user = $pdo->prepare("SELECT balance, phone_number FROM users WHERE id = ?");
$user->execute([$_SESSION['user_id']]);
$user_data = $user->fetch();
$user_balance = $user_data['balance'];
$user_phone = !empty($user_data['phone_number']) ? '233' . substr($user_data['phone_number'], 1) : null; // Convert to international format (e.g., 233544919953)

$admin_phone = '233559118581'; // Admin phone number in international format (0559118581)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = $_POST['username_or_email'];
    $password = $_POST['password'];
    $chosen_plan = $_POST['chosen_plan'];

    // Validate inputs
    if (empty($username_or_email) || empty($password)) {
        $message = "Username/Email and Password are required.";
    } elseif (!isset($plan_options[$chosen_plan])) {
        $message = "Invalid plan selected.";
    } else {
        $plan_cost = $plan_options[$chosen_plan];

        // Encode credentials as JSON
        $credentials_array = ['username_or_email' => $username_or_email, 'password' => $password];
        $credentials = json_encode($credentials_array);

        // Verify JSON encoding
        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = "Error encoding credentials: " . json_last_error_msg();
        } else {
            // Check if balance is sufficient
            if ($user_balance >= $plan_cost) {
                // Deduct the cost from the user's balance
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$plan_cost, $_SESSION['user_id']]);

                // Record the subscription
                $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, chosen_plan, credentials, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$_SESSION['user_id'], $plan_id, $chosen_plan, $credentials]);

                // Prepare SMS messages
                $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
                $from = 'PaySub';
                $sms_success = true;
                $sms_error = '';

                // Send SMS to the user (if phone number exists)
                if ($user_phone) {
                    $user_message = "Your subscription to {$plan['name']} ({$chosen_plan}) for GHS " . number_format($plan_cost, 2) . " has been submitted to PaySub. Awaiting approval.";
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
                $admin_message = "New subscription submitted on PaySub: {$plan['name']} ({$chosen_plan}) for GHS " . number_format($plan_cost, 2) . " from $admin_identifier. Please approve.";
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
                    $message = "Subscription submitted! GHS " . number_format($plan_cost, 2) . " has been deducted. Awaiting approval. SMS notifications sent.";
                } else {
                    $message = "Subscription submitted! GHS " . number_format($plan_cost, 2) . " has been deducted. Awaiting approval. However, $sms_error";
                }
            } else {
                $message = "Insufficient balance! You need GHS " . number_format($plan_cost, 2) . " but you have GHS " . number_format($user_balance, 2) . ".";
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
    <title>Subscribe to <?php echo $plan['name']; ?> | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard">Dashboard</a>
            <a href="deposit">Deposit Funds</a>
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
                <h2>Subscribe to <?php echo $plan['name']; ?></h2>
                <p>Your Balance: GHS <?php echo number_format($user_balance, 2); ?></p>
                <p>Please select a plan and provide your login credentials so our bot can access your account and initiate the subscription. Don’t worry — your credentials are securely handled and kept safe.</p>
                <p><strong> NOTE: Some subscriptions incur charges due to overseas costs, charges will be added to the total amount for the plan you chose</strong></p>
                <p> If two-step verification is enabled on your account, it must be disabled. Otherwise, the bot will be unable to access your account and the process will be marked as failed.</p>
                <form method="POST" onsubmit="showLoading()">
                    <label for="chosen_plan">Select Plan:</label>
                    <select name="chosen_plan" id="chosen_plan" required onchange="updateCost()">
                        <?php foreach ($plan_options as $plan_name => $plan_cost): ?>
                            <option value="<?php echo htmlspecialchars($plan_name); ?>" data-cost="<?php echo $plan_cost; ?>">
                                <?php echo htmlspecialchars($plan_name) . " (GHS " . number_format($plan_cost, 2) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p>Cost: <span id="selected_cost">GHS <?php echo number_format(reset($plan_options), 2); ?></span></p>
                    <input type="text" name="username_or_email" placeholder="Username or Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Submit Credentials</button>
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

        function updateCost() {
            const select = document.getElementById('chosen_plan');
            const cost = select.options[select.selectedIndex].getAttribute('data-cost');
            document.getElementById('selected_cost').textContent = 'GHS ' + parseFloat(cost).toFixed(2);
        }
    </script>
</body>
</html>