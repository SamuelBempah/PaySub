<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Do NOT start session here; auth.php will handle it

// Log script start
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - update_status.php started\n", FILE_APPEND);

// Check if auth.php exists
if (!file_exists('../includes/auth.php')) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - auth.php not found\n", FILE_APPEND);
    die("Error: Authentication file not found.");
}

require_once '../includes/auth.php';

// Check if user is logged in and an admin
if (!is_logged_in()) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Not logged in, redirecting to login.php\n", FILE_APPEND);
    header('Location: ../login.php');
    exit;
}
if (!is_admin()) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Not admin, redirecting to login.php\n", FILE_APPEND);
    header('Location: ../login.php');
    exit;
}

// Log session data
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check if db.php exists
if (!file_exists('../includes/db.php')) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - db.php not found\n", FILE_APPEND);
    die("Error: Database configuration file not found.");
}

require_once '../includes/db.php';

// Verify PDO
if (!isset($pdo)) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - PDO not initialized\n", FILE_APPEND);
    die("Error: Database connection not initialized.");
}

$success = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Processing POST\n", FILE_APPEND);
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'server_status'");
        if ($tableCheck->rowCount() == 0) {
            $error = "Error: server_status table does not exist.";
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - server_status table missing\n", FILE_APPEND);
        } else {
            if (!isset($_POST['status']) || !is_array($_POST['status'])) {
                $error = "Error: Invalid form data.";
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Invalid POST data\n", FILE_APPEND);
            } else {
                // Fetch all users' phone numbers
                $userStmt = $pdo->query("SELECT phone_number FROM users WHERE phone_number IS NOT NULL");
                $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Fetched " . count($users) . " users for SMS\n", FILE_APPEND);

                $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
                $from = 'PaySub';
                $sms_errors = [];
                $updated_components = [];

                foreach ($_POST['status'] as $id => $data) {
                    $status = in_array($data['status'], ['Online', 'Offline', 'Degraded']) ? $data['status'] : 'Online';
                    $details = trim($data['details']) ?: 'No details provided.';

                    // Fetch current component status and name
                    $compStmt = $pdo->prepare("SELECT name, status FROM server_status WHERE id = ?");
                    $compStmt->execute([$id]);
                    $component = $compStmt->fetch(PDO::FETCH_ASSOC);
                    $component_name = $component['name'] ?? 'Unknown Component';
                    $current_status = $component['status'] ?? '';

                    // Only process if status has changed
                    if ($status !== $current_status) {
                        // Update status in database
                        $stmt = $pdo->prepare("UPDATE server_status SET status = ?, details = ? WHERE id = ?");
                        $stmt->execute([$status, $details, $id]);
                        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Updated ID $id to $status\n", FILE_APPEND);
                        $updated_components[] = $component_name;

                        // Prepare SMS message based on status
                        switch ($status) {
                            case 'Online':
                                $sms_message = "PaySub Update: $component_name is now Online. All services are fully operational.";
                                break;
                            case 'Offline':
                                $sms_message = "PaySub Alert: $component_name is Offline. We're working to restore services ASAP.";
                                break;
                            case 'Degraded':
                                $sms_message = "PaySub Notice: $component_name is experiencing Degraded performance. We're addressing the issue.";
                                break;
                            default:
                                $sms_message = "PaySub Update: $component_name status changed. Check our status page for details.";
                        }

                        // Send SMS to each user
                        foreach ($users as $user) {
                            $user_phone = !empty($user['phone_number']) ? '233' . substr($user['phone_number'], 1) : null;
                            if ($user_phone) {
                                $sms_message_encoded = urlencode($sms_message);
                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$user_phone&from=$from&sms=$sms_message_encoded",
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
                                    $sms_errors[] = "Failed to send SMS to $user_phone for $component_name.";
                                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - SMS failed for $user_phone: HTTP $http_code\n", FILE_APPEND);
                                } else {
                                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - SMS sent to $user_phone for $component_name\n", FILE_APPEND);
                                }
                            }
                        }
                    } else {
                        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - No status change for ID $id ($component_name)\n", FILE_APPEND);
                    }
                }

                if (!empty($updated_components)) {
                    $success = "Status updated successfully for: " . implode(', ', $updated_components) . "!";
                    if (!empty($sms_errors)) {
                        $success .= " However, some SMS notifications failed.";
                        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - SMS errors: " . implode(', ', $sms_errors) . "\n", FILE_APPEND);
                    } else {
                        $success .= " SMS notifications sent to all users.";
                    }
                } else {
                    $success = "No status changes detected.";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Update error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch current statuses
$components = [];
try {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Fetching statuses\n", FILE_APPEND);
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'server_status'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM server_status");
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Error: server_status table does not exist.";
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - server_status table missing during fetch\n", FILE_APPEND);
    }
} catch (Exception $e) {
    $error = "Error fetching statuses: " . $e->getMessage();
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Fetch error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Server Status | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }
        .barter-app {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #0f0f1c;
            padding: 20px;
            position: fixed;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 123, 255, 0.2);
        }
        .sidebar:hover {
            box-shadow: 0 0 30px rgba(0, 123, 255, 0.4);
        }
        .sidebar .logo {
            font-size: 1.8rem;
            color: #007bff;
            margin-bottom: 30px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        .sidebar a {
            display: block;
            color: #e0e0e0;
            padding: 10px;
            text-decoration: none;
            margin: 10px 0;
            border-radius: 4px;
            transition: background 0.3s, transform 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #007bff;
            color: #fff;
            transform: scale(1.05);
        }
        .main-content {
            margin-left: 250px;
            padding: 40px;
            width: calc(100% - 250px);
            animation: fadeIn 1s ease-in;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f0f1c;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.3);
        }
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            animation: slideIn 0.5s ease-in;
        }
        .form-group {
            background: #0f0f1c;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .form-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(0, 123, 255, 0.4);
        }
        select, textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: #1a1a2e;
            color: #e0e0e0;
            border: 1px solid #007bff;
            border-radius: 4px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        select:focus, textarea:focus {
            border-color: #00c4ff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
            outline: none;
        }
        button {
            background: #007bff;
            color: #fff;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            display: block;
            margin: 20px auto;
        }
        button:hover {
            background: #0056b3;
            transform: scale(1.05);
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            animation: fadeIn 0.5s;
        }
        .success {
            background: #28a745;
        }
        .error {
            background: #dc3545;
        }
        h1 {
            font-size: 2.8rem;
            color: #007bff;
            margin-bottom: 1rem;
            text-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
            text-align: center;
            animation: glow 2s infinite alternate;
        }
        h3 {
            color: #e0e0e0;
            margin-bottom: 15px;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes glow {
            0% { text-shadow: 0 0 10px rgba(0, 123, 255, 0.5); }
            100% { text-shadow: 0 0 20px rgba(0, 123, 255, 0.8); }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .hamburger {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="../dashboard">Dashboard</a>
            <a href="../status">Server Status</a>
            <a href="update_status" class="active">Update Status</a>
            <a href="../logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Admin'; ?></div>
            </header>
            <div class="form-container">
                <h1>Update Server Status</h1>
                <?php if (isset($success)): ?>
                    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($components)): ?>
                    <form method="POST">
                        <?php foreach ($components as $component): ?>
                            <div class="form-group">
                                <h3><?php echo htmlspecialchars($component['name']); ?></h3>
                                <select name="status[<?php echo $component['id']; ?>][status]">
                                    <option value="Online" <?php echo $component['status'] === 'Online' ? 'selected' : ''; ?>>Online</option>
                                    <option value="Offline" <?php echo $component['status'] === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                                    <option value="Degraded" <?php echo $component['status'] === 'Degraded' ? 'selected' : ''; ?>>Degraded</option>
                                </select>
                                <textarea name="status[<?php echo $component['id']; ?>][details]" rows="4"><?php echo htmlspecialchars($component['details']); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit">Update Status</button>
                    </form>
                <?php else: ?>
                    <div class="message error">No components available. Please check database connection and table.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        document.querySelectorAll('.form-group').forEach((group, index) => {
            group.style.animationDelay = `${index * 0.1}s`;
        });
    </script>
</body>
</html>