<?php
require_once 'includes/auth.php';
session_start();

// Check if user is authenticated
if (!is_logged_in()) {
    file_put_contents('navigation_log.txt', "Unauthorized access attempt to dashboard at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    header("Location: login");
    exit;
}

global $pdo;
$user = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$user->execute([$_SESSION['user_id']]);
$balance = $user->fetch()['balance'];

$subs = $pdo->prepare("SELECT us.*, sp.name, sp.image FROM user_subscriptions us JOIN subscription_plans sp ON us.plan_id = sp.id WHERE us.user_id = ?");
$subs->execute([$_SESSION['user_id']]);
$subscriptions = $subs->fetchAll(PDO::FETCH_ASSOC);

$transactions = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$transactions->execute([$_SESSION['user_id']]);
$transaction_history = $transactions->fetchAll(PDO::FETCH_ASSOC);

// Fetch available plans
$plans = $pdo->query("SELECT * FROM subscription_plans")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
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
        .status.approved {
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
        .status.rejected { 
            background-color: rgb(255, 0, 25); 
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* MacBook Loading Animation */
        .macbook {
            width: 150px;
            height: 96px;
            position: absolute;
            left: 50%;
            top: 50%;
            margin: -85px 0 0 -78px;
            perspective: 500px;
            z-index: 1000;
        }
        .shadow {
            position: absolute;
            width: 60px;
            height: 0px;
            left: 40px;
            top: 160px;
            transform: rotateX(80deg) rotateY(0deg) rotateZ(0deg);
            box-shadow: 0 0 60px 40px rgba(0,0,0,0.3);
            animation: shadow infinite 7s ease;
        }
        .inner {
            z-index: 20;
            position: absolute;
            width: 150px;
            height: 96px;
            left: 0;
            top: 0;
            transform-style: preserve-3d;
            transform: rotateX(-20deg) rotateY(0deg) rotateZ(0deg);
            animation: rotate infinite 7s ease;
        }
        .screen {
            width: 150px;
            height: 96px;
            position: absolute;
            left: 0;
            bottom: 0;
            border-radius: 7px;
            background: #ddd;
            transform-style: preserve-3d;
            transform-origin: 50% 93px;
            transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg);
            animation: lid-screen infinite 7s ease;
            background-image: linear-gradient(45deg, rgba(0,0,0,0.34) 0%,rgba(0,0,0,0) 100%);
            background-position: left bottom;
            background-size: 300px 300px;
            box-shadow: inset 0 3px 7px rgba(255,255,255,0.5);
        }
        .screen .face-one {
            width: 150px;
            height: 96px;
            position: absolute;
            left: 0;
            bottom: 0;
            border-radius: 7px;
            background: #d3d3d3;
            transform: translateZ(2px);
            background-image: linear-gradient(45deg,rgba(0,0,0,0.24) 0%,rgba(0,0,0,0) 100%);
        }
        .screen .face-one .camera {
            width: 3px;
            height: 3px;
            border-radius: 100%;
            background: #000;
            position: absolute;
            left: 50%;
            top: 4px;
            margin-left: -1.5px;
        }
        .screen .face-one .display {
            width: 130px;
            height: 74px;
            margin: 10px;
            background-color: #000;
            background-size: 100% 100%;
            border-radius: 1px;
            position: relative;
            box-shadow: inset 0 0 2px rgba(0,0,0,1);
        }
        .screen .face-one .display .shade {
            position: absolute;
            left: 0;
            top: 0;
            width: 130px;
            height: 74px;
            background: linear-gradient(-135deg, rgba(255,255,255,0) 0%,rgba(255,255,255,0.1) 47%,rgba(255,255,255,0) 48%);
            animation: screen-shade infinite 7s ease;
            background-size: 300px 200px;
            background-position: 0px 0px;
        }
        .screen .face-one span {
            position: absolute;
            top: 85px;
            left: 57px;
            font-size: 6px;
            color: #666;
        }
        .macbody {
            width: 150px;
            height: 96px;
            position: absolute;
            left: 0;
            bottom: 0;
            border-radius: 7px;
            background: #cbcbcb;
            transform-style: preserve-3d;
            transform-origin: 50% bottom;
            transform: rotateX(-90deg);
            animation: lid-macbody infinite 7s ease;
            background-image: linear-gradient(45deg, rgba(0,0,0,0.24) 0%,rgba(0,0,0,0) 100%);
        }
        .macbody .face-one {
            width: 150px;
            height: 96px;
            position: absolute;
            left: 0;
            bottom: 0;
            border-radius: 7px;
            transform-style: preserve-3d;
            background: #dfdfdf;
            animation: lid-keyboard-area infinite 7s ease;
            transform: translateZ(-2px);
            background-image: linear-gradient(30deg, rgba(0,0,0,0.24) 0%,rgba(0,0,0,0) 100%);
        }
        .macbody .touchpad {
            width: 40px;
            height: 31px;
            position: absolute;
            left: 50%;
            top: 50%;
            border-radius: 4px;
            margin: -44px 0 0 -18px;
            background: #cdcdcd;
            background-image: linear-gradient(30deg, rgba(0,0,0,0.24) 0%,rgba(0,0,0,0) 100%);
            box-shadow: inset 0 0 3px #888;
        }
        .macbody .keyboard {
            width: 130px;
            height: 45px;
            position: absolute;
            left: 7px;
            top: 41px;
            border-radius: 4px;
            transform-style: preserve-3d;
            background: #cdcdcd;
            background-image: linear-gradient(30deg, rgba(0,0,0,0.24) 0%,rgba(0,0,0,0) 100%);
            box-shadow: inset 0 0 3px #777;
            padding: 0 0 0 2px;
        }
        .keyboard .key {
            width: 6px;
            height: 6px;
            background: #444;
            float: left;
            margin: 1px;
            transform: translateZ(-2px);
            border-radius: 2px;
            box-shadow: 0 -2px 0 #222;
            animation: keys infinite 7s ease;
        }
        .key.space {
            width: 45px;
        }
        .key.f {
            height: 3px;
        }
        .macbody .pad {
            width: 5px;
            height: 5px;
            background: #333;
            border-radius: 100%;
            position: absolute;
        }
        .pad.one {
            left: 20px;
            top: 20px;
        }
        .pad.two {
            right: 20px;
            top: 20px;
        }
        .pad.three {
            right: 20px;
            bottom: 20px;
        }
        .pad.four {
            left: 20px;
            bottom: 20px;
        }
        @keyframes rotate {
            0% { transform: rotateX(-20deg) rotateY(0deg) rotateZ(0deg); }
            5% { transform: rotateX(-20deg) rotateY(-20deg) rotateZ(0deg); }
            20% { transform: rotateX(30deg) rotateY(200deg) rotateZ(0deg); }
            25% { transform: rotateX(-60deg) rotateY(150deg) rotateZ(0deg); }
            60% { transform: rotateX(-20deg) rotateY(130deg) rotateZ(0deg); }
            65% { transform: rotateX(-20deg) rotateY(120deg) rotateZ(0deg); }
            80% { transform: rotateX(-20deg) rotateY(375deg) rotateZ(0deg); }
            85% { transform: rotateX(-20deg) rotateY(357deg) rotateZ(0deg); }
            87% { transform: rotateX(-20deg) rotateY(360deg) rotateZ(0deg); }
            100% { transform: rotateX(-20deg) rotateY(360deg) rotateZ(0deg); }
        }
        @keyframes lid-screen {
            0% { transform: rotateX(0deg); background-position: left bottom; }
            5% { transform: rotateX(50deg); background-position: left bottom; }
            20% { transform: rotateX(-90deg); background-position: -150px top; }
            25% { transform: rotateX(15deg); background-position: left bottom; }
            30% { transform: rotateX(-5deg); background-position: right top; }
            38% { transform: rotateX(5deg); background-position: right top; }
            48% { transform: rotateX(0deg); background-position: right top; }
            90% { transform: rotateX(0deg); background-position: right top; }
            100% { transform: rotateX(0deg); background-position: right center; }
        }
        @keyframes lid-macbody {
            0%, 50%, 100% { transform: rotateX(-90deg); }
        }
        @keyframes lid-keyboard-area {
            0%, 100% { background-color: #dfdfdf; }
            50% { background-color: #bbb; }
        }
        @keyframes screen-shade {
            0% { background-position: -20px 0px; }
            5% { background-position: -40px 0px; }
            20% { background-position: 200px 0; }
            50% { background-position: -200px 0; }
            80% { background-position: 0px 0px; }
            85% { background-position: -30px 0; }
            90% { background-position: -20px 0; }
            100% { background-position: -20px 0px; }
        }
        @keyframes keys {
            0%, 80%, 85%, 87%, 100% { box-shadow: 0 -2px 0 #222; }
            5% { box-shadow: 1 -1px 0 #222; }
            20%, 25%, 60% { box-shadow: -1px 1px 0 #222; }
        }
        @keyframes shadow {
            0% { transform: rotateX(80deg) rotateY(0deg) rotateZ(0deg); box-shadow: 0 0 60px 40px rgba(0,0,0,0.3); }
            5% { transform: rotateX(80deg) rotateY(10deg) rotateZ(0deg); box-shadow: 0 0 60px 40px rgba(0,0,0,0.3); }
            20% { transform: rotateX(30deg) rotateY(-20deg) rotateZ(-20deg); box-shadow: 0 0 50px 30px rgba(0,0,0,0.3); }
            25% { transform: rotateX(80deg) rotateY(-20deg) rotateZ(50deg); box-shadow: 0 0 35px 15px rgba(0,0,0,0.1); }
            60% { transform: rotateX(80deg) rotateY(0deg) rotateZ(-50deg) translateX(30px); box-shadow: 0 0 60px 40px rgba(0,0,0,0.3); }
            100% { box-shadow: 0 0 60px 40px rgba(0,0,0,0.3); }
        }
        /* Loading and Fade-In Animations */
        .loader {
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
        }
        .loader.hidden {
            display: none;
        }
        .section {
            opacity: 0;
            transition: opacity 1s ease-in;
        }
        .section.visible {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="loader" id="loader">
        <div class="macbook">
            <div class="inner">
                <div class="screen">
                    <div class="face-one">
                        <div class="camera"></div>
                        <div class="display">
                            <div class="shade"></div>
                        </div>
                        <span>MacBook Air</span>
                    </div>
                </div>
                <div class="macbody">
                    <div class="face-one">
                        <div class="touchpad"></div>
                        <div class="keyboard">
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key space"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div><div class="key"></div>
                            <div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div>
                            <div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div>
                            <div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div><div class="key f"></div>
                            <div class="key f"></div>
                        </div>
                    </div>
                    <div class="pad one"></div>
                    <div class="pad two"></div>
                    <div class="pad three"></div>
                    <div class="pad four"></div>
                </div>
            </div>
            <div class="shadow"></div>
        </div>
    </div>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub</div>
            <a href="dashboard" class="active">Dashboard</a>
            <a href="deposit">Deposit Funds</a>
            <a href="support">Support</a>
            <?php if (is_admin()): ?><a href="admin/index">Admin</a><?php endif; ?>
            <a href="autopayla">About AutoPayla 1</a>
            <a href="server_status">Server Status</a>
            <a href="logout">Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            </header>
            <div class="section" id="wallet-section">
                <div class="card wallet-card">
                    <h2>PaySub Wallet Balance</h2>
                    <div class="balance">GHS <?php echo number_format($balance, 2); ?></div>
                    <a href="deposit" class="btn">Deposit</a>
                </div>
            </div>
            <div class="section" id="plans-section">
                <div class="card">
                    <h2>Available Plans</h2>
                    <?php if (empty($plans)): ?>
                        <p>No plans available.</p>
                    <?php else: ?>
                        <div class="ecommerce-container">
                            <?php foreach ($plans as $plan): ?>
                                <div class="ecommerce-card">
                                    <?php if ($plan['image']): ?>
                                        <img src="<?php echo htmlspecialchars($plan['image']); ?>" alt="<?php echo htmlspecialchars($plan['name']); ?>" class="ecommerce-image">
                                    <?php else: ?>
                                        <div class="ecommerce-placeholder">No Image</div>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                                    <p>Choose a plan to subscribe.</p>
                                    <a href="subscribe?plan_id=<?php echo $plan['id']; ?>" class="btn">Subscribe</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="section" id="subscriptions-section">
                <div class="card">
                    <h2>Your Subscriptions</h2>
                    <?php if (empty($subscriptions)): ?>
                        <p>No subscriptions yet.</p>
                    <?php else: ?>
                        <div class="ecommerce-container">
                            <?php foreach ($subscriptions as $sub): ?>
                                <div class="ecommerce-card">
                                    <?php if ($sub['image']): ?>
                                        <img src="<?php echo htmlspecialchars($sub['image']); ?>" alt="<?php echo htmlspecialchars($sub['name']); ?>" class="ecommerce-image">
                                    <?php else: ?>
                                        <div class="ecommerce-placeholder"><?php echo htmlspecialchars($sub['name']); ?></div>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars($sub['chosen_plan']); ?></h3>
                                    <span class="status <?php echo strtolower($sub['status']); ?>">
                                        <?php echo htmlspecialchars($sub['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="section" id="transactions-section">
                <div class="card">
                    <h2>Transaction History</h2>
                    <?php if (empty($transaction_history)): ?>
                        <p>No transactions yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($transaction_history as $txn): ?>
                                <li>
                                    GHS <?php echo number_format($txn['amount'], 2); ?>
                                    <span class="status <?php echo strtolower($txn['status']); ?>">
                                        <?php echo htmlspecialchars($txn['status']); ?>
                                    </span>
                                    (<?php echo htmlspecialchars($txn['created_at']); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const loader = document.getElementById('loader');
            const sections = [
                document.getElementById('wallet-section'),
                document.getElementById('plans-section'),
                document.getElementById('subscriptions-section'),
                document.getElementById('transactions-section')
            ];

            // Show loader for 5 seconds
            setTimeout(() => {
                loader.classList.add('hidden');
                // Fade in sections sequentially
                sections.forEach((section, index) => {
                    setTimeout(() => {
                        section.classList.add('visible');
                    }, index * 1000); // 1-second delay between each section
                });
            }, 5000);

            // Handle back navigation to stay on dashboard
            sessionStorage.setItem('dashboard_active', 'true');
            history.pushState({ page: 'dashboard' }, null, '');

            window.onpopstate = function(event) {
                if (sessionStorage.getItem('dashboard_active') === 'true') {
                    // Log back navigation
                    fetch('log_navigation.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ event: 'Back navigation on dashboard', time: new Date().toISOString() })
                    });
                    // Redirect to dashboard
                    window.location.href = 'dashboard';
                }
            };
        });
    </script>
</body>
</html>