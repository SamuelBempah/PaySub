<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once '../includes/auth.php';

    // Optional: Restrict to logged-in users (uncomment to enable)
    if (!is_logged_in()) {
        error_log("Not logged in, redirecting to login.php");
        header("Location: ../login");
        exit;
    }

    // Comment out to remove admin restriction (previously requested)
    /*
    if (!is_admin()) {
        header("Location: ../login.php");
        exit;
    }
    */

    global $pdo;
    if (!$pdo) {
        error_log("PDO not initialized");
        die("Database error");
    }

    // Admin phone number (for consistency)
    $admin_phone = '233559118581';

    // Fetch all users with active messages
    $users = $pdo->query("SELECT DISTINCT u.id, u.email, u.phone_number 
                          FROM users u 
                          JOIN chat_messages cm ON u.id = cm.user_id")->fetchAll(PDO::FETCH_ASSOC);

    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $message_history = [];
    if ($user_id) {
        $messages = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
        $messages->execute([$user_id]);
        $message_history = $messages->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle admin response
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $user_id) {
        $message = trim(htmlspecialchars($_POST['message']));
        if (!empty($message)) {
            // Insert message (sender set to 'admin' or 'support' based on role)
            $sender = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1 ? 'admin' : 'support';
            $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, sender) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $message, $sender]);

            // Fetch user's phone number
            $user = $pdo->prepare("SELECT phone_number FROM users WHERE id = ?");
            $user->execute([$user_id]);
            $user_data = $user->fetch();
            $user_phone = !empty($user_data['phone_number']) ? '233' . substr($user_data['phone_number'], 1) : null;

            // Send SMS to user
            if ($user_phone) {
                $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
                $from = 'PaySub';
                $user_message = "PaySub Support has responded to your query, please reply in the live chat: \"$message\".";
                $user_message_encoded = urlencode($user_message);
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$user_phone&from=$from&sms=$user_message_encoded",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                ));
                $response = curl_exec($curl);
                if (curl_errno($curl)) {
                    error_log("cURL Error: " . curl_error($curl));
                }
                curl_close($curl);
            }
            header("Location: support_admin?user_id=$user_id");
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error in support_admin: " . $e->getMessage());
    die("An error occurred");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Admin | PaySub Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .messages-container {
            max-width: 600px;
            margin: 20px auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f9f9f9;
        }
        .messages-box {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
            background: #fff;
        }
        .message {
            margin: 10px 0;
            padding: 8px 12px;
            border-radius: 4px;
            max-width: 80%;
        }
        .message.user {
            background: #007bff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .message.admin, .message.support {
            background: #28a745;
            color: white;
            margin-right: auto;
        }
        .messages-input {
            display: flex;
            gap: 10px;
        }
        .messages-input input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .messages-input button {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .messages-input button:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub Admin</div>
            <a href="index">Dashboard</a>
            <a href="manage_deposits">Manage Deposits</a>
            <a href="manage_subs">Manage Subscriptions</a>
            <a href="support_admin" class="active">Support Admin</a>
            <a href="manage_plans">Manage Plans</a>
            <a href="../logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info">
                    <?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Guest'; ?>
                </div>
            </header>
            <div class="card">
                <h2>Support Admin</h2>
                <div>
                    <h3>Active Conversations</h3>
                    <ul>
                        <?php foreach ($users as $u): ?>
                            <li>
                                <a href="support_admin?user_id=<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($user_id): ?>
                    <div class="messages-container">
                        <div class="messages-box" id="messages-box">
                            <?php foreach ($message_history as $msg): ?>
                                <div class="message <?php echo $msg['sender']; ?>">
                                    <strong><?php echo $msg['sender'] === 'user' ? 'User' : ($msg['sender'] === 'admin' ? 'Admin' : 'Support'); ?>:</strong>
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                    <small>(<?php echo $msg['created_at']; ?>)</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" class="messages-input">
                            <input type="text" name="message" placeholder="Type your response..." required>
                            <button type="submit">Send</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        window.onload = function() {
            const messagesBox = document.getElementById('messages-box');
            if (messagesBox) {
                messagesBox.scrollTop = messagesBox.scrollHeight;
            }
        };
    </script>
</body>
</html>