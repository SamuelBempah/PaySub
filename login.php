<?php
require_once 'includes/auth.php';
session_start();

// Prevent browser caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is fully authenticated
if (isset($_SESSION['user_id']) && !isset($_SESSION['login_data'])) {
    header('Location: dashboard');
    exit;
}

// Check for back navigation during OTP verification
if (isset($_SESSION['login_data']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    file_put_contents('navigation_log.txt', "Back navigation detected at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    unset($_SESSION['login_data']);
    session_destroy(); // Clear all session data
    header('Location: index');
    exit;
}

global $pdo;

$message = '';
$message_type = 'error';
$show_otp_form = false;
$show_login_form = true;
$redirect_to_dashboard = false;

function generateOTP() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['otp_code'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $message = "Email and password are required.";
        $message_type = 'error';
        $show_login_form = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
        $message_type = 'error';
        $show_login_form = true;
    } else {
        $message = login($email, $password);
        if (strpos($message, 'successful') !== false) {
            $stmt = $pdo->prepare("SELECT id, phone_number FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['phone_number'])) {
                $_SESSION['login_data'] = [
                    'user_id' => $user['id'],
                    'phone_number' => $user['phone_number']
                ];

                $otp_code = generateOTP();
                $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                try {
                    $stmt = $pdo->prepare("INSERT INTO otps (phone_number, otp_code, expires_at) 
                                           VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
                    $stmt->execute([$user['phone_number'], $otp_code, $expires_at, $otp_code, $expires_at]);
                } catch (Exception $e) {
                    $message = "Failed to generate OTP. Please try again.";
                    $message_type = 'error';
                    unset($_SESSION['login_data']);
                    $show_login_form = true;
                    file_put_contents('otp_error.log', $e->getMessage() . "\n", FILE_APPEND);
                    goto display_page;
                }

                $otp_phone_number = '233' . substr($user['phone_number'], 1);
                $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
                $from = 'PaySub';
                $sms_message = "Your PaySub login OTP is $otp_code. It expires in 5 minutes.";
                $sms_message_encoded = urlencode($sms_message);

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$otp_phone_number&from=$from&sms=$sms_message_encoded",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                ));
                $response = curl_exec($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($curl);
                curl_close($curl);

                file_put_contents('sms_debug.log', "Login OTP - HTTP Code: $http_code\nResponse: $response\ncURL Error: $curl_error\n\n", FILE_APPEND);

                $response_data = json_decode($response, true);

                if ($http_code === 200 && (isset($response_data['message']) && $response_data['message'] === 'Successfully Sent')) {
                    $message = "Login successful! OTP sent to your phone number. Please enter the 6-digit code.";
                    $message_type = 'success';
                    $show_otp_form = true;
                    $show_login_form = false;
                } else {
                    $message = "Failed to send OTP. Please try again.";
                    $message_type = 'error';
                    unset($_SESSION['login_data']);
                    $show_login_form = true;
                }
            } else {
                $message = "Phone number not found for this account.";
                $message_type = 'error';
                $show_login_form = true;
            }
        } else {
            $message = "Login failed: $message";
            $message_type = 'error';
            $show_login_form = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_code'])) {
    $otp_code = trim($_POST['otp_code']);
    if (empty($otp_code) || !preg_match('/^\d{6}$/', $otp_code) || !isset($_SESSION['login_data'])) {
        $message = "Invalid or expired OTP session.";
        $message_type = 'error';
        $show_otp_form = true;
    } else {
        $phone_number = $_SESSION['login_data']['phone_number'];
        $user_id = $_SESSION['login_data']['user_id'];

        $stmt = $pdo->prepare("SELECT * FROM otps WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp_record) {
            $message = "No OTP found for this phone number. Resend OTP.";
            $message_type = 'error';
            $show_otp_form = true;
        } elseif ($otp_record['otp_code'] !== $otp_code) {
            $message = "Invalid OTP. Please try again.";
            $message_type = 'error';
            $show_otp_form = true;
        } elseif (strtotime($otp_record['expires_at']) < time()) {
            $message = "OTP has expired. Resend OTP.";
            $message_type = 'error';
            $show_otp_form = true;
        } else {
            $_SESSION['user_id'] = $user_id;
            unset($_SESSION['login_data']);

            $new_otp_code = generateOTP();
            $new_expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            try {
                $stmt = $pdo->prepare("INSERT INTO otps (phone_number, otp_code, expires_at) 
                                       VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
                $stmt->execute([$phone_number, $new_otp_code, $new_expires_at, $new_otp_code, $new_expires_at]);
            } catch (Exception $e) {
                file_put_contents('otp_debug.log', "Failed to generate new OTP after login: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            $message = "Successfully logged in! Redirecting to dashboard...";
            $message_type = 'success';
            $redirect_to_dashboard = true;
        }
    }
}

display_page:
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }
        .barter-app {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #fff;
            border-right: 1px solid #e0e0e0;
            padding: 20px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        .sidebar .logo {
            font-size: 24px;
            font-weight: 700;
            color: #a61c3c;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        .sidebar a {
            display: block;
            color: #666;
            padding: 10px;
            text-decoration: none;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background: #a61c3c;
            color: #fff;
            border-radius: 5px;
        }
        .hamburger {
            display: none;
            font-size: 24px;
            color: #fff;
            cursor: pointer;
        }
        .header {
            background: #a61c3c;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 99;
            height: 60px;
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 80px 20px 20px;
            overflow-y: auto;
        }
        .card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 40px auto;
            max-width: 400px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .card h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            background: #fff;
        }
        input:focus {
            border-color: #a61c3c;
            outline: none;
        }
        button {
            width: 100%;
            padding: 10px 20px;
            background: #a61c3c;
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background: #84162f;
        }
        .signup-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        .signup-link a {
            color: #a61c3c;
            text-decoration: none;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
            font-size: 14px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast.success {
            background: #e6f4ea;
            color: #2d862d;
        }
        .toast.error {
            background: #fceaea;
            color: #862d2d;
        }
        .toast .icon {
            font-size: 18px;
        }
        .loader {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }
        .loader.active {
            display: block;
        }
        .glitch {
            position: relative;
            font-size: 25px;
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
            letter-spacing: 5px;
            z-index: 1;
            animation: shift 1s ease-in-out infinite alternate;
        }
        .glitch:before,
        .glitch:after {
            display: block;
            content: attr(data-glitch);
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0.8;
        }
        .glitch:before {
            animation: glitch 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) both infinite;
            color: #8b00ff;
            z-index: -1;
        }
        .glitch:after {
            animation: glitch 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) reverse both infinite;
            color: #00e571;
            z-index: -2;
        }
        @keyframes glitch {
            0% {
                transform: translate(0);
            }
            20% {
                transform: translate(-3px, 3px);
            }
            40% {
                transform: translate(-3px, -3px);
            }
            60% {
                transform: translate(3px, 3px);
            }
            80% {
                transform: translate(3px, -3px);
            }
            to {
                transform: translate(0);
            }
        }
        @keyframes shift {
            0%, 40%, 44%, 58%, 61%, 65%, 69%, 73%, 100% {
                transform: skewX(0deg);
            }
            41% {
                transform: skewX(10deg);
            }
            42% {
                transform: skewX(-10deg);
            }
            59% {
                transform: skewX(40deg) skewY(10deg);
            }
            60% {
                transform: skewX(-40deg) skewY(-10deg);
            }
            63% {
                transform: skewX(10deg) skewY(-5deg);
            }
            70% {
                transform: skewX(-50deg) skewY(-20deg);
            }
            71% {
                transform: skewX(10deg) skewY(-10deg);
            }
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
            }
            .header {
                left: 0;
                height: 60px;
            }
            .hamburger {
                display: block;
            }
            .card {
                padding: 15px;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="index">Signup</a>
            <a href="login" class="active">Login</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info">Guest</div>
            </header>
            <?php if ($message): ?>
                <div class="toast <?php echo $message_type; ?>">
                    <span class="icon"><?php echo $message_type === 'success' ? '✔' : '✖'; ?></span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="loader" id="loading">
                <div data-glitch="Loading..." class="glitch">Loading...</div>
            </div>
            <?php if ($show_otp_form): ?>
                <div class="card">
                    <h2>Verify Login OTP</h2>
                    <form method="POST" onsubmit="submitForm(event, this)">
                        <input type="text" name="otp_code" placeholder="Enter 6-digit OTP" required>
                        <button type="submit">Verify OTP</button>
                    </form>
                </div>
            <?php elseif ($show_login_form): ?>
                <div class="card">
                    <h2>Login</h2>
                    <form method="POST" onsubmit="submitForm(event, this)">
                        <input type="email" name="email" placeholder="Email Address" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit">Login</button>
                    </form>
                    <p class="signup-link">No account? <a href="index">Sign Up</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        function submitForm(event, form) {
            event.preventDefault(); // Prevent immediate form submission
            document.getElementById('loading').classList.add('active'); // Show loader
            setTimeout(() => {
                form.submit(); // Submit form after delay
            }, 500); // 500ms delay to allow animation to render
        }

        <?php if ($redirect_to_dashboard): ?>
            setTimeout(function() {
                window.location.href = 'dashboard';
            }, 2000);
        <?php endif; ?>

        // Handle back navigation for OTP form
        <?php if ($show_otp_form): ?>
            sessionStorage.setItem('otp_form_active', 'true');
            // Push a new state to the history to detect back navigation
            history.pushState({ page: 'otp' }, null, '');
        <?php endif; ?>
        window.onpopstate = function(event) {
            if (sessionStorage.getItem('otp_form_active') === 'true') {
                // Clear session storage and redirect to index.php
                sessionStorage.removeItem('otp_form_active');
                window.location.href = 'index';
            }
        };
    </script>
</body>
</html>