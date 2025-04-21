<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

if (!file_exists('includes/auth.php')) {
    file_put_contents('error.log', "auth.php missing\n", FILE_APPEND);
    die('Internal server error');
}
require_once 'includes/auth.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) || isset($_SESSION['logged_in']) || isset($_SESSION['email'])) {
    header('Location: dashboard');
    exit;
}

global $pdo;

$message = '';
$message_type = 'error';
$show_otp_form = false;
$show_signup_form = false;
$show_success_message = false;

if (isset($_GET['action']) && $_GET['action'] === 'signup') {
    $show_signup_form = true;
}

function generateOTP() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['otp_code'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $terms_accepted = isset($_POST['terms']) && $_POST['terms'] === 'on';

    if (empty($full_name) || empty($phone_number) || empty($email) || empty($password)) {
        $message = "All fields are required.";
        $message_type = 'error';
        $show_signup_form = true;
    } elseif (!$terms_accepted) {
        $message = "You must accept the terms and conditions.";
        $message_type = 'error';
        $show_signup_form = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
        $message_type = 'error';
        $show_signup_form = true;
    } elseif (!preg_match('/^0\d{9}$/', $phone_number)) {
        $message = "Phone number must be a 10-digit number starting with 0 (e.g., 0559118581).";
        $message_type = 'error';
        $show_signup_form = true;
    } else {
        $_SESSION['signup_data'] = [
            'full_name' => $full_name,
            'phone_number' => $phone_number,
            'email' => $email,
            'password' => $password
        ];

        $otp_code = generateOTP();
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        try {
            $stmt = $pdo->prepare("INSERT INTO otps (phone_number, otp_code, expires_at) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
            $stmt->execute([$phone_number, $otp_code, $expires_at, $otp_code, $expires_at]);
        } catch (Exception $e) {
            $message = "Failed to generate OTP. Please try again.";
            $message_type = 'error';
            unset($_SESSION['signup_data']);
            $show_signup_form = true;
            file_put_contents('otp_error.log', $e->getMessage() . "\n", FILE_APPEND);
            goto display_page;
        }

        $otp_phone_number = '233' . substr($phone_number, 1);
        $api_key = 'OmNQR2VuT1RqR29qZDk1aDE=';
        $from = 'PaySub';
        $sms_message = "Your PaySub OTP is $otp_code. It expires in 5 minutes.";
        $sms_message_encoded = urlencode($sms_message);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://sms.arkesel.com/sms/api?action=send-sms&api_key=$api_key&to=$otp_phone_number&from=$from&sms=$sms_message_encoded",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $message = "Failed to send OTP due to network error. Please try again.";
            $message_type = 'error';
            unset($_SESSION['signup_data']);
            $show_signup_form = true;
            file_put_contents('sms_debug.log', "cURL Error: $curl_error\n", FILE_APPEND);
        } else {
            file_put_contents('sms_debug.log', "HTTP Code: $http_code\nResponse: $response\n\n", FILE_APPEND);
            $response_data = json_decode($response, true);

            if ($http_code === 200 && (isset($response_data['message']) && $response_data['message'] === 'Successfully Sent')) {
                $message = "OTP sent to your phone number. Please enter the 6-digit code.";
                $message_type = 'success';
                $show_otp_form = true;
            } else {
                $message = "Failed to send OTP. Error: " . ($response_data['message'] ?? 'Unknown error');
                $message_type = 'error';
                unset($_SESSION['signup_data']);
                $show_signup_form = true;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_code'])) {
    $otp_code = trim($_POST['otp_code'] ?? '');
    if (empty($otp_code) || !isset($_SESSION['signup_data'])) {
        $message = "Invalid or expired OTP session.";
        $message_type = 'error';
        $show_otp_form = true;
    } else {
        $signup_data = $_SESSION['signup_data'];
        $phone_number = $signup_data['phone_number'];

        try {
            $stmt = $pdo->prepare("SELECT * FROM otps WHERE phone_number = ?");
            $stmt->execute([$phone_number]);
            $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $message = "Failed to verify OTP. Please try again.";
            $message_type = 'error';
            $show_otp_form = true;
            file_put_contents('otp_error.log', $e->getMessage() . "\n", FILE_APPEND);
            goto display_page;
        }

        if (!$otp_record) {
            $message = "No OTP found for this phone number. Please request a new OTP.";
            $message_type = 'error';
            $show_otp_form = true;
        } elseif ($otp_record['otp_code'] !== $otp_code) {
            $message = "Invalid OTP. Please try again.";
            $message_type = 'error';
            $show_otp_form = true;
        } elseif (strtotime($otp_record['expires_at']) < time()) {
            $message = "OTP has expired. Please request a new OTP.";
            $message_type = 'error';
            $show_otp_form = true;
        } else {
            $full_name = $signup_data['full_name'];
            $phone_number = $signup_data['phone_number'];
            $email = $signup_data['email'];
            $password = $signup_data['password'];

            try {
                $message = signup($email, $password, $full_name, $phone_number);
            } catch (Exception $e) {
                $message = "Signup failed: Internal error.";
                $message_type = 'error';
                $show_otp_form = true;
                file_put_contents('signup_error.log', $e->getMessage() . "\n", FILE_APPEND);
                goto display_page;
            }

            if (strpos($message, 'successful') !== false) {
                $message_type = 'success';
                $message = "Registration Successful! Welcome to PaySub!";
                $new_otp_code = generateOTP();
                $new_expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                try {
                    $stmt = $pdo->prepare("INSERT INTO otps (phone_number, otp_code, expires_at) 
                                           VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE otp_code = ?, expires_at = ?");
                    $stmt->execute([$phone_number, $new_otp_code, $new_expires_at, $new_otp_code, $new_expires_at]);
                } catch (Exception $e) {
                    file_put_contents('otp_debug.log', "Failed to generate new OTP after signup: " . $e->getMessage() . "\n", FILE_APPEND);
                }

                unset($_SESSION['signup_data']);
                $show_success_message = true;
            } else {
                $message = "Signup failed: $message";
                $message_type = 'error';
                $show_otp_form = true;
            }
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
    <title>Welcome to PaySub</title>
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
            display: flex;
            flex-direction: column;
        }
        .sidebar .logo {
            font-size: 24px;
            font-weight: 700;
            color: #a61c3c;
            margin-bottom: 60px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
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
        .card chalkboard {
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
        input[type="text"],
        input[type="email"],
        input[type="password"] {
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
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-wrapper input {
            width: auto;
            margin-right: 10px;
        }
        .checkbox-wrapper .checkmark {
            display: none;
        }
        .checkbox-wrapper label {
            font-size: 14px;
            color: #333;
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
        .welcome-container {
            max-width: 800px;
            margin: 40px auto;
            text-align: center;
        }
        .welcome-container h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        .welcome-container p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #666;
        }
        .success-container {
            text-align: center;
            padding: 50px;
        }
        .success-container h2 {
            font-size: 2rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .success-container p {
            font-size: 1.2rem;
            color: #333;
        }
        .bot-text-container {
            font-size: 1.2rem;
            color: #333;
            font-family: 'Poppins', sans-serif;
        }
        .bot-text {
            display: inline-block;
            overflow: hidden;
            white-space: nowrap;
            border-right: 2px solid #a61c3c;
            animation: blink-cursor 0.75s step-end infinite;
        }
        @keyframes blink-cursor {
            50% {
                border-color: transparent;
            }
        }
        .parent {
            width: 290px;
            height: 350px;
            perspective: 1000px;
            margin: 20px auto;
        }
        .card.welcome {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(135deg, rgb(0, 255, 214) 0%, rgb(8, 226, 96) 100%);
            transition: all 0.5s ease-in-out;
            transform-style: preserve-3d;
            box-shadow: rgba(5, 71, 17, 0) 40px 50px 25px -40px, rgba(5, 71, 17, 0.2) 0px 25px 25px -5px;
        }
        .glass {
            transform-style: preserve-3d;
            position: absolute;
            inset: 8px;
            border-radius: 55px;
            border-top-right-radius: 100%;
            background: linear-gradient(0deg, rgba(255, 255, 255, 0.349) 0%, rgba(255, 255, 255, 0.815) 100%);
            transform: translate3d(0px, 0px, 25px);
            border-left: 1px solid white;
            border-bottom: 1px solid white;
            transition: all 0.5s ease-in-out;
        }
        .heading {
            padding: 20px;
            text-align: center;
            margin-top: 100px;
            transform: translate3d(0, 0, 26px);
        }
        .buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            margin-top: 20px;
            transform: translate3d(0, 0, 26px);
        }
        .buttons a {
            padding: 8px 16px;
            font-size: 12px;
            text-decoration: none;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            transform: translate3d(0, 0, 0);
            transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
            cursor: pointer;
        }
        .buttons .signup-btn {
            background-color: #a61c3c;
        }
        .buttons .signup-btn:hover {
            background-color: #84162f;
            transform: translate3d(0, 0, 10px);
        }
        .buttons .login-btn {
            background-color: #28a745;
        }
        .buttons .login-btn:hover {
            background-color: #218838;
            transform: translate3d(0, 0, 10px);
        }
        .parent:hover .card.welcome {
            transform: rotate3d(1, 1, 0, 30deg);
            box-shadow: rgba(5, 71, 17, 0.3) 30px 50px 25px -40px, rgba(5, 71, 17, 0.1) 0px 25px 30px 0px;
        }
        .parent:hover .card.welcome .buttons a {
            transform: translate3d(0, 0, 50px);
        }
        .logo {
            position: absolute;
            right: 0;
            top: 0;
            transform-style: preserve-3d;
        }
        .logo .circle {
            display: block;
            position: absolute;
            aspect-ratio: 1;
            border-radius: 50%;
            top: 0;
            right: 0;
            box-shadow: rgba(100, 100, 111, 0.2) -10px 10px 20px 0px;
            -webkit-backdrop-filter: blur(5px);
            backdrop-filter: blur(5px);
            background: rgba(0, 249, 203, 0.2);
            transition: all 0.5s ease-in-out;
        }
        .logo .circle1 {
            width: 170px;
            transform: translate3d(0, 0, 20px);
            top: 8px;
            right: 8px;
        }
        .logo .circle2 {
            width: 140px;
            transform: translate3d(0, 0, 40px);
            top: 10px;
            right: 10px;
            -webkit-backdrop-filter: blur(1px);
            backdrop-filter: blur(1px);
            transition-delay: 0.4s;
        }
        .logo .circle3 {
            width: 110px;
            transform: translate3d(0, 0, 60px);
            top: 17px;
            right: 17px;
            transition-delay: 0.8s;
        }
        .logo .circle4 {
            width: 80px;
            transform: translate3d(0, 0, 80px);
            top: 23px;
            right: 23px;
            transition-delay: 1.2s;
        }
        .logo .circle5 {
            width: 50px;
            transform: translate3d(0, 0, 100px);
            top: 30px;
            right: 30px;
            display: grid;
            place-content: center;
            transition-delay: 1.6s;
        }
        .parent:hover .card.welcome .logo .circle2 {
            transform: translate3d(0, 0, 60px);
        }
        .parent:hover .card.welcome .logo .circle3 {
            transform: translate3d(0, 0, 80px);
        }
        .parent:hover .card.welcome .logo .circle4 {
            transform: translate3d(0, 0, 100px);
        }
        .parent:hover .card.welcome .logo .circle5 {
            transform: translate3d(0, 0, 120px);
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left:0;
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
    <script src="js/confetti.browser.min.js"></script>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="index" class="active">Home</a>
            <a href="autopayla">About AutoPayla 1</a>
            <a href="server_status">Server Status</a>
            <a href="login">Login</a>
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
            <?php if ($show_success_message): ?>
                <div class="success-container">
                    <h2>Registration Successful!</h2>
                    <p>Welcome to PaySub! Redirecting to login...</p>
                </div>
                <script>
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                    setTimeout(() => {
                        window.location.href = 'login';
                    }, 3000);
                </script>
            <?php elseif ($show_otp_form): ?>
                <div class="card">
                    <h2>Verify Signup OTP</h2>
                    <form method="POST" onsubmit="showLoading()">
                        <input type="text" name="otp_code" placeholder="Enter 6-digit OTP" required>
                        <button type="submit">Verify OTP</button>
                    </form>
                </div>
            <?php elseif ($show_signup_form): ?>
                <div class="card">
                    <h2>Sign Up</h2>
                    <form method="POST" onsubmit="showLoading()">
                        <input type="text" name="full_name" placeholder="Full Name" value="<?php echo isset($_SESSION['signup_data']['full_name']) ? htmlspecialchars($_SESSION['signup_data']['full_name']) : ''; ?>" required>
                        <input type="text" name="phone_number" placeholder="Phone Number" value="<?php echo isset($_SESSION['signup_data']['phone_number']) ? htmlspecialchars($_SESSION['signup_data']['phone_number']) : ''; ?>" required>
                        <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_SESSION['signup_data']['email']) ? htmlspecialchars($_SESSION['signup_data']['email']) : ''; ?>" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="terms" required>
                            <span class="checkmark"></span>
                            I agree to the Terms and Conditions
                        </label>
                        <button type="submit">Sign Up</button>
                    </form>
                    <p class="signup-link">Have an account? <a href="login">Log in</a></p>
                </div>
            <?php else: ?>
                <div class="welcome-container">
                    <h1>Welcome to PaySub</h1>
                    <p>
                        PaySub makes managing your subscriptions easy and secure. Just pay with mobile money, provide your credentials, and purchase a subscription, and our trained bot <strong>AutoPayla 1</strong> will do the heavy lifting for you.
                        Sign up today to access premium plans, deposit funds, and enjoy seamless support with our live chat feature.
                    </p>
                    <div class="parent">
                        <div class="card welcome">
                            <div class="logo">
                                <span class="circle circle1"></span>
                                <span class="circle circle2"></span>
                                <span class="circle circle3"></span>
                                <span class="circle circle4"></span>
                                <span class="circle circle5"></span>
                            </div>
                            <div class="glass"></div>
                            <div class="heading">
                                <div class="bot-text-container">
                                    <span class="bot-text"></span>
                                </div>
                            </div>
                            <div class="buttons">
                                <a class="signup-btn" onclick="delayRedirectSignup()">Sign Up</a>
                                <a class="login-btn" onclick="delayRedirectLogin()">Log In</a>
                            </div>
                        </div>
                    </div>
                    <p style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                        Made with ❤️ By Samuel Bempah
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }
        function delayRedirectSignup() {
            showLoading();
            setTimeout(() => {
                window.location.href = 'index?action=signup';
            }, 3000);
        }
        function delayRedirectLogin() {
            showLoading();
            setTimeout(() => {
                window.location.href = 'login';
            }, 3000);
        }
        document.addEventListener('DOMContentLoaded', () => {
            const botText = document.querySelector('.bot-text');
            const text = 'Powered By AutoPayla 1';
            let index = 0;

            function type() {
                if (index < text.length) {
                    botText.textContent += text.charAt(index);
                    index++;
                    setTimeout(type, 100);
                } else {
                    botText.style.borderRight = '2px solid #a61c3c';
                }
            }
            type();
        });
    </script>
</body>
</html>