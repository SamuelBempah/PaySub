<?php
require_once '../includes/auth.php';
require_once '../includes/db.php'; // Include database connection

if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

// Fetch total users
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Fetch total deposits (approved transactions)
$stmt = $pdo->query("SELECT SUM(amount) as total_deposits FROM transactions WHERE status = 'approved'");
$total_deposits = $stmt->fetch(PDO::FETCH_ASSOC)['total_deposits'] ?: 0.00;

// Fetch total active subscriptions
$stmt = $pdo->query("SELECT COUNT(*) as total_subscriptions FROM user_subscriptions WHERE status = 'active'");
$total_subscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total_subscriptions'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PaySub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #fafafa;
            color: #000;
            min-height: 100vh;
            display: flex;
        }

        .barter-app {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 200px;
            background: #f1f3f5;
            padding: 20px;
            transition: transform 0.3s ease;
            position: fixed;
            top: 0;
            bottom: 0;
            z-index: 1000;
        }

        .sidebar .logo {
            font-size: 1.3em;
            font-weight: 700;
            color: #9b1d2a;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: #444;
            text-decoration: none;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
            font-size: 0.95em;
            font-weight: 500;
        }

        .sidebar a i {
            margin-right: 8px;
            font-size: 1em;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #9b1d2a;
            color: #fff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 200px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #9b1d2a;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .hamburger {
            font-size: 1.5em;
            color: #fff;
            cursor: pointer;
            display: none;
        }

        .user-info {
            font-size: 0.9em;
            color: #fff;
            font-weight: 500;
        }

        /* Card */
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .card h2 {
            font-size: 1.5em;
            color: #000;
            margin-bottom: 15px;
            font-weight: 700;
        }

        /* Stats Container */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
            flex: 1;
            min-width: 150px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 1em;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .stat-card p {
            font-size: 1.4em;
            color: #9b1d2a;
            font-weight: 600;
        }

        /* Quick Links */
        .quick-links h3 {
            font-size: 1.2em;
            color: #000;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .quick-links ul {
            list-style: none;
            padding: 0;
        }

        .quick-links ul li {
            margin: 8px 0;
        }

        .quick-links ul li a {
            display: flex;
            align-items: center;
            color: #800080;
            text-decoration: none;
            font-size: 0.95em;
            padding: 8px;
            border-radius: 4px;
            transition: text-decoration 0.2s;
            font-weight: 500;
        }

        .quick-links ul li a i {
            margin-right: 8px;
            font-size: 1em;
        }

        .quick-links ul li a:hover {
            text-decoration: underline;
        }

        /* Mobile Styles */
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

            .hamburger {
                display: block;
            }

            .stats-container {
                flex-direction: column;
            }

            .stat-card {
                min-width: 100%;
            }

            .quick-links ul li a {
                font-size: 1em;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="barter-app">
        <nav class="sidebar">
            <div class="logo">PaySub Admin</div>
            <a href="index" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_deposits"><i class="fas fa-money-bill-wave"></i> Manage Deposits</a>
            <a href="manage_subs"><i class="fas fa-subscript"></i> Manage Subscriptions</a>
            <a href="manage_plans"><i class="fas fa-list"></i> Manage Plans</a>
            <a href="support_admin"><i class="fas fa-headset"></i> Support Chat</a>
            <a href="update_status"><i class="fas fa-server"></i> Update Status</a>
            <a href="../logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="main-content">
            <header class="header">
                <span class="hamburger" onclick="toggleSidebar()">☰</span>
                <div class="user-info"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            </header>
            <div class="card">
                <h2>Admin Dashboard</h2>
                <div class="stats-container">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <p><?php echo $total_users; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Deposits</h3>
                        <p>GHS <?php echo number_format($total_deposits, 2); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Subscriptions</h3>
                        <p><?php echo $total_subscriptions; ?></p>
                    </div>
                </div>
                <div class="quick-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="manage_deposits"><i class="fas fa-money-bill-wave"></i> Manage Deposits</a></li>
                        <li><a href="manage_subs"><i class="fas fa-subscript"></i> Manage Subscriptions</a></li>
                        <li><a href="manage_plans"><i class="fas fa-list"></i> Manage Plans</a></li>
                        <li><a href="support_admin"><i class="fas fa-headset"></i> Support Chat</a></li>
                        <li><a href="update_status"><i class="fas fa-server"></i> Update Status</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>